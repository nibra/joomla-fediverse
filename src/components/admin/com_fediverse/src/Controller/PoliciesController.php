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
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

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
     * Save a domain policy.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(): void
    {
        $app  = $this->app;
        $user = $this->getCurrentUser();

        Factory::getContainer()->get(LicenseService::class)->requirePro();

        Session::checkToken() or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        $domain = trim($app->input->post->getString('domain', ''));
        $policy = $app->input->post->getString('policy', 'block');
        $reason = $app->input->post->getString('reason', '');

        if ($domain === '') {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_SAVE_ERROR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        try {
            /** @var \NX\Component\Fediverse\Administrator\Model\PoliciesModel $model */
            $model = $this->getMVCFactory()->createModel('Policies', 'Administrator', ['ignore_request' => true]);
            $model->save($domain, $policy, $reason);
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_SAVE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=policies');
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
        $user = $this->getCurrentUser();

        Factory::getContainer()->get(LicenseService::class)->requirePro();

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=policies');

            return;
        }

        $domain = trim($app->input->getString('domain', ''));

        try {
            /** @var \NX\Component\Fediverse\Administrator\Model\PoliciesModel $model */
            $model = $this->getMVCFactory()->createModel('Policies', 'Administrator', ['ignore_request' => true]);
            $model->delete($domain);
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_POLICIES_DELETE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=policies');
    }
}

