<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Inboxitems;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HtmlView Class
 *
 * Render the Fediverse inbox items list view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of inbox item rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

    /**
     * Display the inbox items list view.
     *
     * @params string $tpl Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        /** @var \NX\Component\Fediverse\Administrator\Model\InboxModel $model */
        $model       = $this->getModel();
        $this->items = $model->getList('', 100);

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the inbox items toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_INBOX_TITLE'), 'inbox');
    }
}
