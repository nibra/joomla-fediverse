<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Publishing;

use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * ContentProviderInterface Interface
 *
 * Define the shareable content provider contract.
 *
 * Third-party extensions should implement this interface to federate their content.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ContentProviderInterface
{
    /**
     * Get the provider key.
     *
     * Return a stable provider key used in object identifiers.
     *
     * @return  string  Provider key.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getKey(): string;

    /**
     * Check if the provider supports a content context.
     *
     * @params string $context Joomla content context string.
     *
     * @return  bool  True when the context is supported.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function supportsContext(string $context): bool;

    /**
     * Resolve the owning Joomla user id for a content item.
     *
     * @params object $item Joomla content item.
     * @params string $context Joomla content context string.
     *
     * @return  int  User id or 0 when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resolveUserId(object $item, string $context): int;

    /**
     * Parse an object identifier and return the local item id.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  ?int  Item id or null when not supported.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function parseObjectId(string $objectId): ?int;

    /**
     * Fetch a content item by id.
     *
     * @params int $id Content item id.
     *
     * @return  ?object  Content item or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchById(int $id): ?object;

    /**
     * Map a content item to a create or update activity.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $item Joomla content item.
     * @params bool $isNew Whether the item is new.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for create or update.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $item, bool $isNew, string $baseUrl): ActivityEnvelope;

    /**
     * Map a content item to a delete activity.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $item Joomla content item.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for delete.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $item, string $baseUrl): ActivityEnvelope;

    /**
     * Map a content item to an ActivityPub object payload.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $item Joomla content item.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  array<string,mixed>  ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $item, string $baseUrl): array;
}
