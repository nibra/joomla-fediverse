<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Dashboard;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Model\DashboardModel;

/**
 * HtmlView Class
 *
 * Render the Fediverse management dashboard.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * Summary data for the dashboard.
     *
     * @var array<string,array<string,int>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $summary = [];

    /**
     * Display the dashboard view.
     *
     * @params string $tpl Template name.
     *
     * @return  void None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        /** @var DashboardModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);
        $this->summary = $model->getSummary();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the dashboard toolbar.
     *
     * @return  void None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_FEDIVERSE_DASHBOARD_TITLE'), 'comment');

        if (
            $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse')
        ) {
            $toolbar->preferences('com_fediverse');
        }
    }
}
