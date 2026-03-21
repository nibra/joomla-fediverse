<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_reactions
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Reactions\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use NX\Module\Fediverse\Reactions\Site\Helper\ReactionsHelper;

defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_fediverse_reactions.
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

        $data['reactions'] = ReactionsHelper::getReactionsData($data['params']);

        return $data;
    }
}
