<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Http\ResponseFactory;
use NX\Component\Fediverse\Administrator\Service\Security\KeyRotationService;

/**
 * KeysController Class
 *
 * Handle key rotation requests for logged-in users.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeysController extends BaseController
{
    /**
     * Rotate the signing key for the current user.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateSelf(): void
    {
        if (!Session::checkToken('post')) {
            ResponseFactory::json(['error' => Text::_('JINVALID_TOKEN')], 403);

            return;
        }

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if ($user === null || $user->guest) {
            ResponseFactory::json(['error' => Text::_('COM_FEDIVERSE_KEY_ROTATION_LOGIN_REQUIRED')], 403);

            return;
        }

        /** @var KeyRotationService $rotation */
        $rotation = Factory::getContainer()->get(KeyRotationService::class);
        $rotated = $rotation->rotateForUserId((int) $user->id, null, true);

        if (!$rotated) {
            ResponseFactory::json(['error' => Text::_('COM_FEDIVERSE_KEY_ROTATION_FAILED')], 400);

            return;
        }

        ResponseFactory::json(['status' => 'ok'], 200);
    }
}
