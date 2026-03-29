<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Administration;

use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * ConfigurationTransferService Class
 *
 * Export and import portable Fediverse configuration and operator-managed data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ConfigurationTransferService
{
    private const PACKAGE_SCHEMA_VERSION = 1;

    /**
     * @var array<string, list<string>>
     */
    private const TABLE_COLUMNS = [
        '#__fediverse_user_settings' => [
            'user_id',
            'federation_enabled',
            'bio',
            'website',
            'created_at',
            'updated_at',
        ],
        '#__fediverse_content_settings' => [
            'id',
            'context',
            'item_id',
            'federate',
            'created_at',
            'updated_at',
        ],
        '#__fediverse_domain_policies' => [
            'id',
            'domain',
            'policy',
            'reason',
            'created_at',
            'updated_at',
        ],
        '#__fediverse_oauth_clients' => [
            'id',
            'client_id',
            'client_secret',
            'redirect_uris',
            'scopes',
            'name',
            'website',
            'is_confidential',
            'created_at',
        ],
    ];

    /**
     * Initialize the transfer service.
     *
     * @params DatabaseInterface $db Database connection.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Export the portable Fediverse configuration package as JSON.
     *
     * @return  string  JSON package.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function exportPackage(): string
    {
        $payload = [
            'schema_version'   => self::PACKAGE_SCHEMA_VERSION,
            'exported_at'      => gmdate(\DateTimeInterface::ATOM),
            'component_params' => $this->loadComponentParams(),
            'scheduler_tasks'  => $this->loadSchedulerTasks(),
            'user_settings'    => $this->loadTableRows('#__fediverse_user_settings'),
            'content_settings' => $this->loadTableRows('#__fediverse_content_settings'),
            'domain_policies'  => $this->loadTableRows('#__fediverse_domain_policies'),
            'oauth_clients'    => $this->loadTableRows('#__fediverse_oauth_clients'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode the Fediverse configuration package.');
        }

        return $json;
    }

    /**
     * Import a portable Fediverse configuration package.
     *
     * @params string $json JSON package contents.
     *
     * @return  array<string, int>  Imported row counts by section.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function importPackage(string $json): array
    {
        $package = json_decode($json, true);

        if (!is_array($package) || (int) ($package['schema_version'] ?? 0) !== self::PACKAGE_SCHEMA_VERSION) {
            throw new RuntimeException('Invalid Fediverse configuration package.');
        }

        $summary = [
            'scheduler_tasks'  => 0,
            'user_settings'    => 0,
            'content_settings' => 0,
            'domain_policies'  => 0,
            'oauth_clients'    => 0,
        ];

        $this->db->transactionStart();

        try {
            $this->storeComponentParams((array) ($package['component_params'] ?? []));

            $summary['scheduler_tasks'] = $this->storeSchedulerTasks((array) ($package['scheduler_tasks'] ?? []));
            $summary['user_settings'] = $this->replaceTableRows(
                '#__fediverse_user_settings',
                (array) ($package['user_settings'] ?? [])
            );
            $summary['content_settings'] = $this->replaceTableRows(
                '#__fediverse_content_settings',
                (array) ($package['content_settings'] ?? [])
            );
            $summary['domain_policies'] = $this->replaceTableRows(
                '#__fediverse_domain_policies',
                (array) ($package['domain_policies'] ?? [])
            );
            $summary['oauth_clients'] = $this->replaceTableRows(
                '#__fediverse_oauth_clients',
                (array) ($package['oauth_clients'] ?? [])
            );

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw $e;
        }

        return $summary;
    }

    /**
     * Load component parameters, excluding environment-specific license data.
     *
     * @return  array<string, mixed>  Portable component params.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function loadComponentParams(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'))
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        $params = json_decode((string) ($row['params'] ?? '{}'), true);

        if (!is_array($params)) {
            $params = [];
        }

        unset($params['license_key'], $params['license_instance_id'], $params['license_key_hash']);

        return $params;
    }

    /**
     * Load scheduler task rows for the Fediverse task types.
     *
     * @return  array<int, array<string, mixed>>  Portable scheduler rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function loadSchedulerTasks(): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('type'),
                $this->db->quoteName('title'),
                $this->db->quoteName('state'),
                $this->db->quoteName('execution_rules'),
                $this->db->quoteName('params'),
            ])
            ->from($this->db->quoteName('#__scheduler_tasks'))
            ->where($this->db->quoteName('type') . ' LIKE ' . $this->db->quote('fediverse.%'))
            ->order($this->db->quoteName('type') . ' ASC');

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    /**
     * Load all rows from a portable Fediverse table.
     *
     * @params string $table Table name with Joomla prefix placeholder.
     *
     * @return  array<int, array<string, mixed>>  Exported rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function loadTableRows(string $table): array
    {
        $columns = self::TABLE_COLUMNS[$table] ?? null;

        if ($columns === null) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select(array_map([$this->db, 'quoteName'], $columns))
            ->from($this->db->quoteName($table));

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    /**
     * Persist the portable component params while preserving the local license key.
     *
     * @params array<string, mixed> $params Imported params.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function storeComponentParams(array $params): void
    {
        $current = $this->loadComponentParamsRow();
        $currentParams = json_decode((string) ($current['params'] ?? '{}'), true);

        if (!is_array($currentParams)) {
            $currentParams = [];
        }

        $licenseKey = $currentParams['license_key'] ?? null;
        $licenseInstanceId = $currentParams['license_instance_id'] ?? null;
        $licenseKeyHash = $currentParams['license_key_hash'] ?? null;
        unset($params['license_key'], $params['license_instance_id'], $params['license_key_hash']);

        if (is_string($licenseKey) && trim($licenseKey) !== '') {
            $params['license_key'] = $licenseKey;
        }

        if (is_string($licenseInstanceId) && trim($licenseInstanceId) !== '') {
            $params['license_instance_id'] = $licenseInstanceId;
        }

        if (is_string($licenseKeyHash) && trim($licenseKeyHash) !== '') {
            $params['license_key_hash'] = $licenseKeyHash;
        }

        $encoded = json_encode($params, JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode imported Fediverse component parameters.');
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__extensions'))
            ->set($this->db->quoteName('params') . ' = ' . $this->db->quote($encoded))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'));

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Update existing Fediverse scheduler tasks from the package.
     *
     * @params array<int, array<string, mixed>> $tasks Imported task rows.
     *
     * @return  int  Number of tasks updated.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function storeSchedulerTasks(array $tasks): int
    {
        $updated = 0;

        foreach ($tasks as $task) {
            $type = trim((string) ($task['type'] ?? ''));

            if ($type === '') {
                continue;
            }

            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__scheduler_tasks'))
                ->set($this->db->quoteName('title') . ' = ' . $this->db->quote((string) ($task['title'] ?? $type)))
                ->set($this->db->quoteName('state') . ' = ' . (int) ($task['state'] ?? 0))
                ->set(
                    $this->db->quoteName('execution_rules') . ' = '
                    . $this->db->quote((string) ($task['execution_rules'] ?? ''))
                )
                ->set($this->db->quoteName('params') . ' = ' . $this->db->quote((string) ($task['params'] ?? '')))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote($type));

            $this->db->setQuery($query);
            $this->db->execute();
            $updated++;
        }

        return $updated;
    }

    /**
     * Replace all rows in a portable Fediverse table.
     *
     * @params string                          $table Table name with Joomla prefix placeholder.
     * @params array<int, array<string, mixed>> $rows  Imported rows.
     *
     * @return  int  Number of inserted rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function replaceTableRows(string $table, array $rows): int
    {
        $columns = self::TABLE_COLUMNS[$table] ?? null;

        if ($columns === null) {
            return 0;
        }

        $deleteQuery = $this->db->createQuery()
            ->delete($this->db->quoteName($table));
        $this->db->setQuery($deleteQuery);
        $this->db->execute();

        $inserted = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $values = [];

            foreach ($columns as $column) {
                $values[] = $this->toSqlLiteral($row[$column] ?? null);
            }

            $query = $this->db->createQuery()
                ->insert($this->db->quoteName($table))
                ->columns(array_map([$this->db, 'quoteName'], $columns))
                ->values(implode(', ', $values));

            $this->db->setQuery($query);
            $this->db->execute();
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Fetch the raw extensions row for com_fediverse.
     *
     * @return  array<string, mixed>  Raw extensions row.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function loadComponentParamsRow(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'))
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        if ($row === null) {
            throw new RuntimeException('Unable to locate com_fediverse component parameters.');
        }

        return $row;
    }

    /**
     * Convert a PHP value to a SQL literal for portable row inserts.
     *
     * @params mixed $value Row value.
     *
     * @return  string  SQL literal.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function toSqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->db->quote((string) $value);
    }
}
