<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Comments;

/**
 * CommentRendererInterface Interface
 *
 * Contract for rendering mapped Fediverse replies through a comments pipeline.
 *
 * @since  __DEPLOY_VERSION__
 */
interface CommentRendererInterface
{
    /**
     * Check whether this renderer supports the current content target.
     *
     * @params string $context Joomla content context.
     * @params ?int $itemId Joomla item id.
     * @params string $objectId Fediverse object id segment.
     *
     * @return  bool  True when the renderer supports this target.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function supports(string $context, ?int $itemId, string $objectId): bool;

    /**
     * Render comments markup for mapped replies.
     *
     * @params string $context Joomla content context.
     * @params ?int $itemId Joomla item id.
     * @params string $objectId Fediverse object id segment.
     * @params array<string,int> $counts Reaction counts.
     * @params array<int,array<string,string>> $replies Reply rows.
     *
     * @return  string  HTML markup.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function render(
        string $context,
        ?int $itemId,
        string $objectId,
        array $counts,
        array $replies
    ): string;
}
