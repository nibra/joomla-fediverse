<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Model\WebhooksModel;
use NX\Component\Fediverse\Administrator\Service\Access\PermissionService;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;

/**
 * WebhooksController Class
 *
 * Handle webhook list and form actions.
 *
 * @since  __DEPLOY_VERSION__
 */
final class WebhooksController extends BaseController
{
    /**
     * Redirect to the webhook edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function edit(): void
    {
        $app = $this->app;

        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        $webhookId = $ids[0] ?? (int) $app->input->getInt('id', 0);

        if ($webhookId <= 0) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=webhooks');

            return;
        }

        $app->redirect('index.php?option=com_fediverse&view=webhook&id=' . $webhookId);
    }

    /**
     * Redirect to a blank webhook edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function add(): void
    {
        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        $this->app->redirect('index.php?option=com_fediverse&view=webhook');
    }

    /**
     * Save one webhook endpoint.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(): void
    {
        $this->saveWebhook('save');
    }

    /**
     * Save one webhook endpoint and stay on the edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function apply(): void
    {
        $this->saveWebhook('apply');
    }

    /**
     * Save one webhook endpoint and continue with a fresh edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save2new(): void
    {
        $this->saveWebhook('save2new');
    }

    /**
     * Close the webhook edit view and return to the list.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function close(): void
    {
        $this->app->redirect('index.php?option=com_fediverse&view=webhooks');
    }

    /**
     * Cancel the webhook edit view and return to the list.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function cancel(): void
    {
        $this->close();
    }

    /**
     * Save one webhook endpoint and redirect according to the toolbar task.
     *
     * @param   string  $returnTask  Toolbar task that triggered the save.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function saveWebhook(string $returnTask): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        Session::checkToken() or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!Factory::getContainer()->get(PermissionService::class)->canManageWebhooks($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=webhooks');

            return;
        }

        $id        = (int) $app->input->post->getInt('id', 0);
        $name      = $app->input->post->getString('name', '');
        $targetUrl = $app->input->post->getString('target_url', '');
        $secret    = $app->input->post->getString('secret', '');
        $events    = (array) $app->input->post->get('events', [], 'array');
        $isEnabled = (int) $app->input->post->getInt('is_enabled', 0) === 1;

        try {
            $savedId = $this->getWebhooksModel()->save(
                $id > 0 ? $id : null,
                $name,
                $targetUrl,
                $events,
                $secret,
                $isEnabled
            );
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_WEBHOOKS_SAVE_SUCCESS'), 'message');
            $app->redirect($this->getSaveRedirect($returnTask, $savedId));
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $redirectId = $id > 0 ? '&id=' . $id : '';
            $app->redirect('index.php?option=com_fediverse&view=webhook' . $redirectId);
        }
    }

    /**
     * Delete selected webhook endpoints.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function delete(): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!Factory::getContainer()->get(PermissionService::class)->canManageWebhooks($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=webhooks');

            return;
        }

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=webhooks');

            return;
        }

        try {
            $deleted = $this->getWebhooksModel()->deleteByIds($ids);
            $app->enqueueMessage(Text::sprintf('COM_FEDIVERSE_WEBHOOKS_DELETE_SUCCESS', $deleted), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=webhooks');
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
        $model = $this->getModel('Webhooks', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof WebhooksModel) {
            throw new \RuntimeException('Unable to load webhooks model.');
        }

        return $model;
    }

    /**
     * Resolve the redirect target after a successful save.
     *
     * @param   string  $returnTask  Toolbar task that triggered the save.
     * @param   int     $savedId     Saved webhook id.
     *
     * @return  string  Redirect URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getSaveRedirect(string $returnTask, int $savedId): string
    {
        if ($returnTask === 'apply') {
            return 'index.php?option=com_fediverse&view=webhook&id=' . $savedId;
        }

        if ($returnTask === 'save2new') {
            return 'index.php?option=com_fediverse&view=webhook';
        }

        return 'index.php?option=com_fediverse&view=webhooks';
    }
}
