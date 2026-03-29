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
use NX\Component\Fediverse\Administrator\Event\PolicyDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\PolicySavedEvent;
use NX\Component\Fediverse\Administrator\Model\PoliciesModel;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\Access\PermissionService;
use NX\Component\Fediverse\Administrator\Service\Events\FediverseDomainEventDispatcherInterface;

/**
 * PoliciesController Class
 *
 * Handle Policies requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PoliciesController extends BaseController
{
    /**
     * Redirect to the policy edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function edit(): void
    {
        $app = $this->app;

        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        $domains = array_map(
            static fn(string $domain): string => strtolower(trim($domain)),
            (array) $app->input->post->get('cid', [], 'array')
        );
        $domains = array_values(array_filter($domains, static fn(string $domain): bool => $domain !== ''));
        $domain = $domains[0] ?? strtolower(trim($app->input->getString('domain', '')));

        if ($domain === '') {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        $app->redirect('index.php?option=com_fediverse&view=policy&domain=' . urlencode($domain));
    }

    /**
     * Redirect to a blank policy edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function add(): void
    {
        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

        $this->app->redirect('index.php?option=com_fediverse&view=policy');
    }

    /**
     * Save a domain policy.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(): void
    {
        $this->savePolicy('save');
    }

    /**
     * Save a domain policy and stay on the edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function apply(): void
    {
        $this->savePolicy('apply');
    }

    /**
     * Save a domain policy and continue with a fresh edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save2new(): void
    {
        $this->savePolicy('save2new');
    }

    /**
     * Close the policy edit view and return to the list.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function close(): void
    {
        $this->app->redirect('index.php?option=com_fediverse&view=policies');
    }

    /**
     * Cancel the policy edit view and return to the list.
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
     * Save a policy and redirect according to the toolbar task.
     *
     * @param   string  $returnTask  Toolbar task that triggered the save.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function savePolicy(string $returnTask): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);
        $permissions = Factory::getContainer()->get(PermissionService::class);

        Session::checkToken() or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$permissions->canManagePolicies($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        $domain = trim($app->input->post->getString('domain', ''));
        $policy = $app->input->post->getString('policy', 'block');
        $reason = $app->input->post->getString('reason', '');

        if ($domain === '') {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_SAVE_ERROR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policy');

            return;
        }

        try {
            $model = $this->getPoliciesModel();
            $model->save($domain, $policy, $reason);
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new PolicySavedEvent(
                    $domain,
                    $policy,
                    $reason,
                    (int) $user->id
                )
            );
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_SAVE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policy&domain=' . urlencode(strtolower($domain)));

            return;
        }

        $app->redirect($this->getSaveRedirect($returnTask, strtolower($domain), 0));
    }

    /**
     * Delete a domain policy.
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
        $permissions = Factory::getContainer()->get(PermissionService::class);

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$permissions->canManagePolicies($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        $domains = array_map(
            static fn(string $domain): string => strtolower(trim($domain)),
            (array) $app->input->post->get('cid', [], 'array')
        );
        $domains = array_values(array_filter($domains, static fn(string $domain): bool => $domain !== ''));

        if ($domains === []) {
            $domain = strtolower(trim($app->input->getString('domain', '')));
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        if ($domains === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=policies');
            return;
        }

        try {
            $model = $this->getPoliciesModel();
            foreach ($domains as $domain) {
                $model->delete($domain);
                Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                    new PolicyDeletedEvent(
                        $domain,
                        (int) $user->id
                    )
                );
            }
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_DELETE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=policies');
    }

    /**
     * Load the policies model.
     *
     * @return  PoliciesModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getPoliciesModel(): PoliciesModel
    {
        /** @var PoliciesModel|false $model */
        $model = $this->getModel('Policies', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof PoliciesModel) {
            throw new \RuntimeException('Unable to load policies model.');
        }

        return $model;
    }

    /**
     * Resolve the redirect target after a successful save.
     *
     * @param   string  $returnTask  Toolbar task that triggered the save.
     * @param   string  $domain      Saved policy domain.
     * @param   int     $id          Unused compatibility placeholder.
     *
     * @return  string  Redirect URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getSaveRedirect(string $returnTask, string $domain, int $id): string
    {
        unset($id);

        if ($returnTask === 'apply') {
            return 'index.php?option=com_fediverse&view=policy&domain=' . urlencode($domain);
        }

        if ($returnTask === 'save2new') {
            return 'index.php?option=com_fediverse&view=policy';
        }

        return 'index.php?option=com_fediverse&view=policies';
    }
}
