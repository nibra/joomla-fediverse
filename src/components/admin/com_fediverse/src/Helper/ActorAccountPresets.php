<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Helper;

/**
 * ActorAccountPresets Class
 *
 * Centralize semantic actor account presets and ActivityPub actor type mapping.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorAccountPresets
{
    /**
     * Return the semantic account kind for an ActivityPub actor type.
     *
     * @param   ?string  $actorType  Raw ActivityPub actor type.
     *
     * @return  string  Semantic account kind.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function kindForActorType(?string $actorType): string
    {
        return match ($actorType) {
            'Organization' => 'organization',
            'Group' => 'channel',
            'Service' => 'service',
            default => 'person',
        };
    }

    /**
     * Return the ActivityPub actor type for a semantic account kind.
     *
     * @param   string  $kind  Semantic account kind.
     *
     * @return  string  ActivityPub actor type.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function actorTypeForKind(string $kind): string
    {
        return match ($kind) {
            'organization' => 'Organization',
            'channel' => 'Group',
            'service' => 'Service',
            default => 'Person',
        };
    }

    /**
     * Check if a semantic account kind requires Pro.
     *
     * @param   string  $kind  Semantic account kind.
     *
     * @return  bool  True when the kind is Pro-only.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function requiresPro(string $kind): bool
    {
        return in_array($kind, ['organization', 'channel'], true);
    }

    /**
     * Return the allowed ActivityPub actor types for local actors.
     *
     * @return  array<int, string>  Allowed actor types.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function allowedActorTypes(): array
    {
        return ['Person', 'Service', 'Organization', 'Group'];
    }

    /**
     * Return the translation key for an actor type label.
     *
     * @param   ?string  $actorType  Raw ActivityPub actor type.
     *
     * @return  string  Translation key.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function labelKeyForActorType(?string $actorType): string
    {
        return match ($actorType) {
            'Organization' => 'COM_FEDIVERSE_ACTORS_ACTOR_TYPE_ORGANIZATION',
            'Group' => 'COM_FEDIVERSE_ACTORS_ACTOR_TYPE_CHANNEL',
            'Service' => 'COM_FEDIVERSE_ACTORS_ACTOR_TYPE_SERVICE',
            default => 'COM_FEDIVERSE_ACTORS_ACTOR_TYPE_PERSON',
        };
    }
}
