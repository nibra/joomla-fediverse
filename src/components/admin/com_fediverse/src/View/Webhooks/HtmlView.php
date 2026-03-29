<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Webhooks;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Service\Automation\WebhookEventCatalog;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse webhook management view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of webhook rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

    /**
     * Supported webhook event options.
     *
     * @var array<string, string>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $eventOptions = [];

    /**
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Whether the Pro license is expired.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $licenseExpired = false;

    /**
     * Display the webhooks view.
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
        $this->eventOptions   = WebhookEventCatalog::labels();

        /** @var \NX\Component\Fediverse\Administrator\Model\WebhooksModel $model */
        $model = $this->getModel();
        $this->items = $model->getList();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the webhooks toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_WEBHOOKS_TITLE'), 'bolt');

        if ($this->isPro) {
            ToolbarHelper::addNew('webhooks.add');
            ToolbarHelper::editList('webhooks.edit');
            ToolbarHelper::deleteList('', 'webhooks.delete');
        }
    }
}
