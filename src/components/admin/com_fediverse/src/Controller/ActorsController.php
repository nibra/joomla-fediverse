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
 * ActorsController Class
 *
 * Handle Actors requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorsController extends BaseController
{
    /**
     * Enable a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enable(): void
    {
        $app = $this->app;
        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $actorId = (int) $app->input->getInt('id', 0);

        $license = Factory::getContainer()->get(LicenseService::class);
        $model   = $this->getMVCFactory()->createModel('Actors', 'Administrator', ['ignore_request' => true]);
        $currentCount = count($model->getList('local'));
        if ($currentCount >= $license->getTier()->actorLimit()) {
            $app->enqueueMessage(
                Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_REACHED', $license->getTier()->actorLimit()),
                'warning'
            );
            $app->redirect('index.php?option=com_fediverse&view=actors');
            return;
        }

        try {
            /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
            $model->enable($actorId);
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_ENABLE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=actors');
    }

    /**
     * Redirect to the actor edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function edit(): void
    {
        $app     = $this->app;
        $actorId = (int) $app->input->getInt('id', 0);

        $app->redirect('index.php?option=com_fediverse&view=actor&id=' . $actorId);
    }

    /**
     * Save actor_type and object_type for a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(): void
    {
        $app = $this->app;
        Session::checkToken() or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $actorId    = (int) $app->input->post->getInt('id', 0);
        $actorType  = $app->input->post->getString('actor_type', 'Person');
        $objectType = $app->input->post->getString('object_type', 'Note');

        try {
            /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
            $model = $this->getMVCFactory()->createModel('Actors', 'Administrator', ['ignore_request' => true]);
            $model->updateTypes($actorId, $actorType, $objectType);
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_SAVE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_SAVE_ERROR'), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=actors');
    }

    /**
     * Disable a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disable(): void
    {
        $app = $this->app;
        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $actorId = (int) $app->input->getInt('id', 0);

        try {
            /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
            $model = $this->getMVCFactory()->createModel('Actors', 'Administrator', ['ignore_request' => true]);
            $model->disable($actorId);
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_DISABLE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=actors');
    }
}

