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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use NX\Component\Fediverse\Administrator\Service\Access\PermissionService;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * OAuthClientsController Class
 *
 * Handle O Auth Clients requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class OAuthClientsController extends BaseController
{
    /**
     * Display the OAuth clients view, gated behind a Pro license check.
     *
     * @param   bool   $cachable   Whether the view can be cached.
     * @param   array  $urlparams  URL parameters safe-list.
     *
     * @return  static
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($cachable = false, $urlparams = []): static
    {
        $user = $this->app->getIdentity();
        $permissions = Factory::getContainer()->get(PermissionService::class);
        if (!$permissions->canManageOAuthClients($user)) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );
            $this->redirect();
        }

        try {
            Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);
        } catch (\RuntimeException $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $e->getMessage(),
                'warning'
            );
            $this->redirect();
        }

        return parent::display($cachable, $urlparams);
    }
}
