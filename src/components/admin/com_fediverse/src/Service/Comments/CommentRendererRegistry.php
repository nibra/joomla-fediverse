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
 * CommentRendererRegistry Class
 *
 * Store and resolve registered Fediverse comment renderers.
 *
 * @since  __DEPLOY_VERSION__
 */
final class CommentRendererRegistry
{
    /**
     * @var array<int,CommentRendererInterface>
     */
    private array $renderers = [];

    /**
     * Initialize the registry.
     *
     * @params array<int,CommentRendererInterface> $renderers Pre-registered renderers.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $renderers = [])
    {
        foreach ($renderers as $renderer) {
            $this->addRenderer($renderer);
        }
    }

    /**
     * Register a renderer.
     *
     * @params CommentRendererInterface $renderer Renderer instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function addRenderer(CommentRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    /**
     * Resolve the first matching renderer for the target.
     *
     * @params string $context Joomla content context.
     * @params ?int $itemId Joomla item id.
     * @params string $objectId Fediverse object id segment.
     *
     * @return  ?CommentRendererInterface  Matching renderer or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getRendererForTarget(string $context, ?int $itemId, string $objectId): ?CommentRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($context, $itemId, $objectId)) {
                return $renderer;
            }
        }

        return null;
    }
}
