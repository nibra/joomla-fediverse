<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Webhook;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Model\WebhooksModel;
use NX\Component\Fediverse\Administrator\Service\Automation\WebhookEventCatalog;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse webhook edit view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * Webhook row being edited.
     *
     * @var array<string, mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $item = [];

    /**
     * Supported webhook event options.
     *
     * @var array<string, string>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $eventOptions = [];

    /**
     * Whether the current item is new.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isNew = true;

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
     * Display the webhook edit view.
     *
     * @param   string|null  $tpl  Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $webhookId = $app->input->getInt('id', 0);

        $license = Factory::getContainer()->get(LicenseService::class);
        $this->isPro = $license->isPro() && !$license->isExpired();
        $this->licenseExpired = $license->isExpired();
        $this->eventOptions = WebhookEventCatalog::labels();
        $this->item = [
            'id' => 0,
            'name' => '',
            'target_url' => '',
            'events' => [],
            'is_enabled' => true,
        ];

        if ($webhookId > 0) {
            $item = $this->getWebhooksModel()->findById($webhookId);

            if ($item === null) {
                $app->enqueueMessage(Text::_('COM_FEDIVERSE_WEBHOOKS_NOT_FOUND'), 'error');
                $app->redirect(Route::_('index.php?option=com_fediverse&view=webhooks', false));

                return;
            }

            $this->item = $item;
            $this->isNew = false;
        }

        $this->addToolbar();

        if ($this->getLayout() === 'default') {
            $this->setLayout('edit');
        }

        parent::display($tpl);
    }

    /**
     * Add the webhook edit toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(
            Text::_($this->isNew ? 'COM_FEDIVERSE_WEBHOOKS_NEW_TITLE' : 'COM_FEDIVERSE_WEBHOOKS_EDIT_TITLE'),
            'bolt'
        );

        if ($this->isPro) {
            ToolbarHelper::save('webhooks.save');
            ToolbarHelper::save2new('webhooks.save2new');
        }

        ToolbarHelper::cancel('webhooks.close', 'JTOOLBAR_CLOSE');
    }

    /**
     * Load the webhooks model.
     *
     * @return  WebhooksModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getWebhooksModel(): WebhooksModel
    {
        /** @var WebhooksModel|false $model */
        $model = $this->getModel('Webhooks');

        if (!$model instanceof WebhooksModel) {
            throw new \RuntimeException('Unable to load webhooks model.');
        }

        return $model;
    }
}
