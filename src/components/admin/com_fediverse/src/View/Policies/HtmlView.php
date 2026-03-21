<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Policies;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse domain policies view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of domain policy rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

    /**
     * Item currently being edited, or null.
     *
     * @var ?array<string, mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    public ?array $editItem = null;

    /**
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Whether the Pro license has expired.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $licenseExpired = false;

    /**
     * Display the policies view.
     *
     * @params string $tpl Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $license              = Factory::getContainer()->get(LicenseService::class);
        $this->isPro          = $license->isPro() && !$license->isExpired();
        $this->licenseExpired = $license->isExpired();

        /** @var \NX\Component\Fediverse\Administrator\Model\PoliciesModel $model */
        $model       = $this->getModel();
        $this->items = $model->getList();

        $domain = Factory::getApplication()->input->getString('domain', '');
        if ($domain !== '') {
            foreach ($this->items as $row) {
                if ($row['domain'] === strtolower(trim($domain))) {
                    $this->editItem = $row;
                    break;
                }
            }
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the policies toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_POLICIES_TITLE'), 'shield');
    }
}
