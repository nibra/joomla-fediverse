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
use Joomla\CMS\MVC\Controller\BaseController;
use NX\Component\Fediverse\Administrator\Http\ResponseFactory;
use NX\Component\Fediverse\Administrator\Service\Announce\WebfingerService;

/**
 * WellKnownController Class
 *
 * Handle Well Known requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class WellKnownController extends BaseController
{
    /**
     * Handle WebFinger well-known requests.
     *
     * Resolve the requested resource and return a JSON response.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function webfinger(): void
    {
        /** @var WebfingerService $svc */
        $svc = Factory::getContainer()->get(WebfingerService::class);

        $resource = $this->input->getString('resource');
        $result   = $svc->resolve($resource);

        ResponseFactory::json($result, 200);
    }
}
