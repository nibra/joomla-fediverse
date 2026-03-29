<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Dashboard;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Helper\AdminDashboardModuleRenderer;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

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
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Whether the configured Pro license has expired.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $licenseExpired = false;

    /**
     * Human-readable active plan label.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $planLabel = '';

    /**
     * Rendered statistics module HTML.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $statsModuleHtml = '';

    /**
     * Rendered diagnostics module HTML.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $diagnosticsModuleHtml = '';

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
        $license = Factory::getContainer()->get(LicenseService::class);
        $this->planLabel = $license->getTier()->label();
        $this->isPro = $license->isPro() && !$license->isExpired();
        $this->licenseExpired = $license->isExpired();

        $this->statsModuleHtml = AdminDashboardModuleRenderer::render(
            'mod_fediverse_dashboard',
            'Statistics',
            [
                'metrics' => [
                    'actors-local',
                    'actors-remote',
                    'delivery-queued',
                    'delivery-delivering',
                    'delivery-delivered',
                    'delivery-failed',
                ],
                'show_links' => 1,
            ]
        );
        $this->diagnosticsModuleHtml = AdminDashboardModuleRenderer::render(
            'mod_fediverse_diagnostics',
            'Status',
            [
                'tiles' => [
                    'public_base_url',
                    'plugins',
                    'scheduler',
                    'actors',
                    'permissions',
                ],
            ]
        );

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
        $user = $this->getCurrentUser();
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_FEDIVERSE_DASHBOARD_TITLE'), 'comment');

        if ($this->isPro) {
            ToolbarHelper::link(
                'index.php?option=com_fediverse&task=dashboard.exportConfigPackage&'
                . Session::getFormToken()
                . '=1',
                Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_EXPORT_ACTION'),
                'download'
            );
            ToolbarHelper::modal(
                'fediverse-transfer-import-modal',
                'icon-upload',
                'COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_ACTION'
            );
        }

        if (
            $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse')
        ) {
            ToolbarHelper::link(
                'index.php?option=com_config&view=component&component=com_fediverse',
                Text::_('JTOOLBAR_OPTIONS'),
                'options'
            );
        }
    }
}
