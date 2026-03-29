<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_dashboard
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Dashboard\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use NX\Module\Fediverse\Dashboard\Administrator\Helper\DashboardTileProvider;

defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_fediverse_dashboard.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Build module layout data.
     *
     * @return  array
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $showLinks = (bool) $data['params']->get('show_links', 1);
        $tiles = DashboardTileProvider::getTiles($data['params']);
        $buttons = [];

        foreach ($tiles as $tile) {
            $key = (string) ($tile['key'] ?? '');
            $id  = preg_replace('/[^a-z0-9_-]+/i', '_', $key);
            $value = (string) (int) ($tile['value'] ?? 0);

            $button = [
                'id'        => 'mod_fediverse_dashboard_' . ($id !== null ? $id : 'metric'),
                'metricKey' => $key,
                'name'      => (string) ($tile['label'] ?? ''),
                'value'     => $value !== '' ? $value : '0',
                'class'     => 'fediverse-dashboard-quickicon',
                'link'      => (string) ($tile['link'] ?? 'index.php?option=com_fediverse&view=dashboard'),
            ];

            if (!$showLinks) {
                $button['link'] = '#';
                $button['onclick'] = 'return false;';
                $button['class'] .= ' disabled';
            }

            $buttons[] = $button;
        }

        $data['buttons'] = $buttons;

        return $data;
    }
}
