<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_profile
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Profile\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use NX\Module\Fediverse\Profile\Site\Helper\ProfileHelper;

defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_fediverse_profile.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Returns the layout data.
     *
     * @return  array  Layout data.
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $data['profile'] = ProfileHelper::getProfileData($data['params']);

        return $data;
    }
}
