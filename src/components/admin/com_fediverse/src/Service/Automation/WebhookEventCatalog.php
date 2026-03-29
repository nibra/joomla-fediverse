<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Automation;

/**
 * WebhookEventCatalog Class
 *
 * Define the supported Fediverse webhook event names and labels.
 *
 * @since  __DEPLOY_VERSION__
 */
final class WebhookEventCatalog
{
    /**
     * Return the supported webhook events in UI order.
     *
     * @return  array<string, string>  Event name to language key map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function labels(): array
    {
        return [
            'admin.actor_enabled' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_ACTOR_ENABLED',
            'admin.actor_disabled' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_ACTOR_DISABLED',
            'admin.actor_profile_updated' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_ACTOR_PROFILE_UPDATED',
            'admin.inbox_replies_moderated' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_INBOX_REPLIES_MODERATED',
            'admin.inbox_deleted' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_INBOX_DELETED',
            'admin.policy_saved' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_POLICY_SAVED',
            'admin.policy_deleted' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_POLICY_DELETED',
            'admin.key_rotated' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_KEY_ROTATED',
            'admin.config_exported' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_CONFIG_EXPORTED',
            'admin.config_imported' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_ADMIN_CONFIG_IMPORTED',
            'content.published' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_CONTENT_PUBLISHED',
            'content.updated' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_CONTENT_UPDATED',
            'content.deleted' => 'COM_FEDIVERSE_WEBHOOKS_EVENT_CONTENT_DELETED',
        ];
    }

    /**
     * Check whether the event name is supported.
     *
     * @params string $event Event name.
     *
     * @return  bool  True when the event can be subscribed to.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function isSupported(string $event): bool
    {
        return array_key_exists($event, self::labels());
    }

    /**
     * Normalise an arbitrary set of event names to supported UI order.
     *
     * @params array<int, string> $events Candidate event names.
     *
     * @return  array<int, string>  Supported unique event names.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function normalize(array $events): array
    {
        $selected = [];

        foreach ($events as $event) {
            $event = trim((string) $event);

            if ($event !== '') {
                $selected[$event] = true;
            }
        }

        $normalized = [];

        foreach (array_keys(self::labels()) as $event) {
            if (isset($selected[$event])) {
                $normalized[] = $event;
            }
        }

        return $normalized;
    }
}
