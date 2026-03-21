<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Mapper;

use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * JoomlaContentMapperInterface Interface
 *
 * Define the Joomla Content Mapper contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface JoomlaContentMapperInterface
{
    /**
     * Map an article to a create or update activity.
     *
     * Build an ActivityPub activity for article creation or update.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params bool $isNew Whether the article is new.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for create or update.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $article, bool $isNew, string $baseUrl): ActivityEnvelope;

    /**
     * Map an article to a delete activity.
     *
     * Build an ActivityPub delete activity for the article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for delete.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $article, string $baseUrl): ActivityEnvelope;

    /**
     * Map an article to an ActivityPub object.
     *
     * Build an ActivityPub object representation for a Joomla article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  array<string,mixed>  ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $article, string $baseUrl): array;
}
