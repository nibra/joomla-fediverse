<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_diagnostics
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Diagnostics\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use NX\Module\Fediverse\Diagnostics\Administrator\Helper\DiagnosticsTileProvider;

defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_fediverse_diagnostics.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Build module layout data.
     *
     * @return  array<string,mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        $state = DiagnosticsTileProvider::getModuleState($data['params']);
        $input = Factory::getApplication()->getInput();

        $data['tiles'] = $state['tiles'];
        $data['publicBaseUrl'] = $state['publicBaseUrl'];
        $data['schedulerTaskSettings'] = $state['schedulerTaskSettings'];
        $data['isFediverseDashboard'] = $input->getCmd('option') === 'com_fediverse'
            && $input->getCmd('view') === 'dashboard';

        return $data;
    }
}
