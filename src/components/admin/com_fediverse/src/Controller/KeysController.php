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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\Security\KeyRotationService;


/**
 * KeysController Class
 *
 * Handle Keys requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeysController extends BaseController
{
    /**
     * Rotate a signing key for a specific user or handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotate(): void
    {
        try {
            Factory::getContainer()->get(LicenseService::class)->requirePro();
        } catch (\RuntimeException $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $e->getMessage(),
                'warning'
            );
            return;
        }

        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_fediverse', false));
            return;
        }

        $app = $this->app;
        $user = $app->getIdentity();
        $canManage = $user->authorise('core.manage', 'com_fediverse')
            || $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse');

        if (!$canManage) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );

            return;
        }

        $handle = trim($app->input->getString('handle', ''));
        $userId = (int) $app->input->getInt('user_id', 0);

        /** @var KeyRotationService $rotation */
        $rotation = Factory::getContainer()->get(KeyRotationService::class);

        if ($handle !== '') {
            $rotated = $rotation->rotateForHandle($handle, null, true);
            $message = $rotated
                ? Text::sprintf('COM_FEDIVERSE_KEY_ROTATION_SUCCESS_HANDLE', $handle)
                : Text::sprintf('COM_FEDIVERSE_KEY_ROTATION_SKIPPED_HANDLE', $handle);
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $message,
                $rotated ? 'message' : 'warning'
            );

            return;
        }

        if ($userId > 0) {
            $rotated = $rotation->rotateForUserId($userId, null, true);
            $message = $rotated
                ? Text::sprintf('COM_FEDIVERSE_KEY_ROTATION_SUCCESS_USER', $userId)
                : Text::sprintf('COM_FEDIVERSE_KEY_ROTATION_SKIPPED_USER', $userId);
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $message,
                $rotated ? 'message' : 'warning'
            );

            return;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_fediverse&view=dashboard', false),
            Text::_('COM_FEDIVERSE_KEY_ROTATION_MISSING_TARGET'),
            'warning'
        );
    }
}
