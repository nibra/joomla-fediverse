<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Event;

/**
 * ActorProfileUpdatedEvent Class
 *
 * Represent updating actor and object type settings.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorProfileUpdatedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseActorProfileUpdated';

    /**
     * Initialize the event.
     *
     * @params int $actorId Actor id.
     * @params string $handle Actor handle.
     * @params string $accountKind Semantic account kind.
     * @params string $actorType ActivityPub actor type.
     * @params string $objectType Default object type.
     * @params array<string, string> $profileData Canonical actor profile fields.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        int $actorId,
        string $handle,
        string $accountKind,
        string $actorType,
        string $objectType,
        array $profileData,
        int $userId
    )
    {
        parent::__construct(
            $userId,
            [
                'id' => $actorId,
                'actor_id' => $actorId,
                'handle' => $handle,
                'itemlink' => 'index.php?option=com_fediverse&view=actor&id=' . $actorId,
                'account_kind' => $accountKind,
                'actor_type' => $actorType,
                'object_type' => $objectType,
                'profile_name' => $profileData['name'] ?? '',
                'profile_summary' => $profileData['summary'] ?? '',
                'profile_url' => $profileData['url'] ?? '',
                'profile_icon_url' => $profileData['icon_url'] ?? '',
                'profile_header_url' => $profileData['image_url'] ?? '',
                'profile_metadata_count' => (string) (
                    (int) (($profileData['metadata_1_label'] ?? '') !== '' && ($profileData['metadata_1_value'] ?? '') !== '')
                    + (int) (($profileData['metadata_2_label'] ?? '') !== '' && ($profileData['metadata_2_value'] ?? '') !== '')
                    + (int) (($profileData['metadata_3_label'] ?? '') !== '' && ($profileData['metadata_3_value'] ?? '') !== '')
                ),
                'featured_content_id' => $profileData['featured_content_id'] ?? '',
                'user_id' => $userId,
            ]
        );
    }
}
