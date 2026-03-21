<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * DisplayController Class
 *
 * Route base component requests to the management dashboard.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     *
     * @since  __DEPLOY_VERSION__
     */
    protected $default_view = 'dashboard';
}
