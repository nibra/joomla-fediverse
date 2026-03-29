<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Config;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/**
 * FediverseConfig Class
 *
 * Provide access to component configuration with validation and defaults.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseConfig
{
    private const DEFAULT_HANDLE_FORMAT = 'u{user_id}';
    private const DEFAULT_KEY_ROTATION_DAYS = 90;

    private ?Registry $params = null;

    /**
     * Get the configured public base URL.
     *
     * Return the validated base URL or null when not set.
     *
     * @return  ?string  Public base URL or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPublicBaseUrl(): ?string
    {
        $value = (string) $this->getParams()->get('public_base_url', '');
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = rtrim($value, '/');

        if (!preg_match('#^https?://#i', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Get the actor handle format string.
     *
     * Return a validated handle format with a fallback when invalid.
     *
     * @return  string  Actor handle format.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getActorHandleFormat(): string
    {
        $format = (string) $this->getParams()->get('actor_handle_format', self::DEFAULT_HANDLE_FORMAT);
        $format = trim($format) !== '' ? trim($format) : self::DEFAULT_HANDLE_FORMAT;

        if (!str_contains($format, '{user_id}') && !str_contains($format, '{username}')) {
            return self::DEFAULT_HANDLE_FORMAT;
        }

        return $format;
    }

    /**
     * Format an actor handle.
     *
     * Replace supported placeholders and sanitize the resulting handle.
     *
     * @params int $userId Joomla user identifier.
     * @params string $username Joomla username.
     *
     * @return  string  Formatted actor handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function formatActorHandle(int $userId, string $username): string
    {
        $format = $this->getActorHandleFormat();
        $username = trim($username);
        $handle = str_replace(
            ['{user_id}', '{username}'],
            [(string) $userId, $username],
            $format
        );

        $handle = strtolower($handle);
        $handle = preg_replace('/[^a-z0-9_.-]+/', '-', $handle) ?? '';
        $handle = trim($handle, '-');

        if ($handle === '') {
            return 'u' . $userId;
        }

        return $handle;
    }

    /**
     * Get the key rotation interval in days.
     *
     * Return a positive integer with a sensible fallback.
     *
     * @return  int  Key rotation interval in days.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getKeyRotationDays(): int
    {
        $raw = (int) $this->getParams()->get('key_rotation_days', self::DEFAULT_KEY_ROTATION_DAYS);

        return $raw > 0 ? $raw : self::DEFAULT_KEY_ROTATION_DAYS;
    }

    /**
     * Check whether the inbox worker is enabled.
     *
     * @return  bool  True when the inbox worker is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isInboxWorkerEnabled(): bool
    {
        return (int) $this->getParams()->get('enable_inbox_worker', 1) === 1;
    }

    /**
     * Check whether the delivery worker is enabled.
     *
     * @return  bool  True when the delivery worker is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isDeliveryWorkerEnabled(): bool
    {
        return (int) $this->getParams()->get('enable_delivery_worker', 1) === 1;
    }

    /**
     * Get the inbox retention period in days.
     *
     * @return  int  Inbox retention days (0 = keep forever).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getInboxRetentionDays(): int
    {
        return max(0, (int) $this->getParams()->get('inbox_retention_days', 90));
    }

    /**
     * Get the outbox retention period in days.
     *
     * @return  int  Outbox retention days (0 = keep forever).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getOutboxRetentionDays(): int
    {
        return max(0, (int) $this->getParams()->get('outbox_retention_days', 180));
    }

    /**
     * Get the delivery queue retention period in days.
     *
     * @return  int  Delivery queue retention days (0 = keep forever).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getDeliveryQueueRetentionDays(): int
    {
        return max(0, (int) $this->getParams()->get('delivery_queue_retention_days', 30));
    }

    /**
     * Get the follow policy.
     *
     * @return  string  'open' (auto-accept), 'approval' (manual), or 'closed' (reject all).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getFollowPolicy(): string
    {
        $value = (string) $this->getParams()->get('follow_policy', 'open');

        return in_array($value, ['open', 'approval', 'closed'], true) ? $value : 'open';
    }

    /**
     * Check whether strict action-level ACL enforcement is enabled.
     *
     * When enabled, only explicit Fediverse action permissions are accepted.
     *
     * @return  bool  True when strict ACL enforcement is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isStrictAclActionsEnabled(): bool
    {
        return (int) $this->getParams()->get('strict_acl_actions', 0) === 1;
    }

    /**
     * Get the component parameters.
     *
     * Lazy-load the params registry from Joomla.
     *
     * @return  Registry  Parameters registry.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getParams(): Registry
    {
        if ($this->params === null) {
            try {
                $this->params = ComponentHelper::getParams('com_fediverse');
            } catch (\Throwable) {
                $this->params = new Registry();
            }
        }

        return $this->params;
    }
}
