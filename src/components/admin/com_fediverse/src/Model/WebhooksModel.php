<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NX\Component\Fediverse\Administrator\Service\Automation\WebhookEventCatalog;

/**
 * WebhooksModel Class
 *
 * Provide database access for outgoing webhook endpoints.
 *
 * @since  __DEPLOY_VERSION__
 */
final class WebhooksModel extends BaseModel
{
    /**
     * Initialize the webhooks model.
     *
     * @params DatabaseInterface $db Database connection.
     * @params array             $config Model configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Fetch all stored webhook rows ordered by name.
     *
     * @return  array<int, array<string, mixed>>  List of webhook rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(): array
    {
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_webhooks'))
            ->order($this->db->quoteName('name') . ' ASC');

        $this->db->setQuery($query);

        return array_map([$this, 'normalizeRow'], $this->db->loadAssocList() ?: []);
    }

    /**
     * Load one webhook row by id.
     *
     * @params int $id Webhook id.
     *
     * @return  ?array<string, mixed>  Normalized webhook row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_webhooks'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * Fetch enabled webhook rows subscribed to one event.
     *
     * @params string $event Event name.
     *
     * @return  array<int, array<string, mixed>>  Matching webhook rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getEnabledForEvent(string $event): array
    {
        if (!WebhookEventCatalog::isSupported($event)) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_webhooks'))
            ->where($this->db->quoteName('is_enabled') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($query);
        $rows = array_map([$this, 'normalizeRow'], $this->db->loadAssocList() ?: []);

        return array_values(
            array_filter(
                $rows,
                static fn(array $row): bool => in_array($event, $row['events'] ?? [], true)
            )
        );
    }

    /**
     * Create or update a webhook endpoint row.
     *
     * @params int|null          $id Webhook id to update, or null to insert.
     * @params string            $name Display name.
     * @params string            $targetUrl Target URL.
     * @params array<int,string> $events Subscribed event names.
     * @params string            $secret HMAC signing secret.
     * @params bool              $isEnabled Whether the endpoint is active.
     *
     * @return  int  Saved webhook id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(
        ?int $id,
        string $name,
        string $targetUrl,
        array $events,
        string $secret,
        bool $isEnabled
    ): int {
        $name = trim($name);

        if ($name === '') {
            throw new \RuntimeException('Provide a name for the webhook.');
        }

        $targetUrl = trim($targetUrl);
        $scheme = (string) parse_url($targetUrl, PHP_URL_SCHEME);
        if ($targetUrl === '' || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new \RuntimeException('Provide a valid webhook target URL.');
        }

        $events = WebhookEventCatalog::normalize($events);
        if ($events === []) {
            throw new \RuntimeException('Select at least one webhook event.');
        }

        $existing = $id !== null && $id > 0 ? $this->findById($id) : null;
        $secret   = trim($secret);

        if ($secret === '') {
            $secret = (string) ($existing['secret'] ?? '');
        }

        if ($secret === '') {
            throw new \RuntimeException('Provide a signing secret for the webhook.');
        }

        $eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES);
        $enabledValue = $isEnabled ? 1 : 0;

        if (!is_string($eventsJson) || $eventsJson === '') {
            throw new \RuntimeException('Select at least one webhook event.');
        }

        if ($existing !== null) {
            $existingId = (int) $existing['id'];
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_webhooks'))
                ->set($this->db->quoteName('name') . ' = :name')
                ->set($this->db->quoteName('target_url') . ' = :target_url')
                ->set($this->db->quoteName('secret') . ' = :secret')
                ->set($this->db->quoteName('events') . ' = :events')
                ->set($this->db->quoteName('is_enabled') . ' = :is_enabled')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':name', $name, ParameterType::STRING)
                ->bind(':target_url', $targetUrl, ParameterType::STRING)
                ->bind(':secret', $secret, ParameterType::STRING)
                ->bind(':events', $eventsJson, ParameterType::STRING)
                ->bind(':is_enabled', $enabledValue, ParameterType::INTEGER)
                ->bind(':id', $existingId, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $this->db->execute();

            return $existingId;
        }

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_webhooks'))
            ->columns([
                $this->db->quoteName('name'),
                $this->db->quoteName('target_url'),
                $this->db->quoteName('secret'),
                $this->db->quoteName('events'),
                $this->db->quoteName('is_enabled'),
            ])
            ->values(':name, :target_url, :secret, :events, :is_enabled')
            ->bind(':name', $name, ParameterType::STRING)
            ->bind(':target_url', $targetUrl, ParameterType::STRING)
            ->bind(':secret', $secret, ParameterType::STRING)
            ->bind(':events', $eventsJson, ParameterType::STRING)
            ->bind(':is_enabled', $enabledValue, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Delete webhook rows by id.
     *
     * @params array<int, int> $ids Webhook ids.
     *
     * @return  int  Number of deleted ids requested.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_webhooks'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(', ', $ids) . ')');

        $this->db->setQuery($query);
        $this->db->execute();

        return count($ids);
    }

    /**
     * Persist the latest delivery result for one webhook.
     *
     * @params int         $id Webhook id.
     * @params int         $status HTTP status code or 0.
     * @params string|null $error Error details.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function recordDeliveryResult(int $id, int $status, ?string $error = null): void
    {
        if ($id <= 0) {
            return;
        }

        $errorText = trim((string) $error);

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_webhooks'))
            ->set($this->db->quoteName('last_delivery_at') . ' = UTC_TIMESTAMP()')
            ->set($this->db->quoteName('last_delivery_status') . ' = :status')
            ->set($this->db->quoteName('last_error') . ' = :error')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':status', $status, ParameterType::INTEGER)
            ->bind(':error', $errorText, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Normalize a DB row into the model output shape.
     *
     * @params array<string, mixed> $row Raw database row.
     *
     * @return  array<string, mixed>  Normalized row.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeRow(array $row): array
    {
        $events = json_decode((string) ($row['events'] ?? '[]'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'target_url' => (string) ($row['target_url'] ?? ''),
            'secret' => (string) ($row['secret'] ?? ''),
            'events' => WebhookEventCatalog::normalize(is_array($events) ? $events : []),
            'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
            'last_delivery_at' => isset($row['last_delivery_at']) ? (string) $row['last_delivery_at'] : null,
            'last_delivery_status' => isset($row['last_delivery_status']) && $row['last_delivery_status'] !== null
                ? (int) $row['last_delivery_status']
                : null,
            'last_error' => (string) ($row['last_error'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
