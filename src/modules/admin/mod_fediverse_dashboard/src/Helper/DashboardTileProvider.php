<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_dashboard
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Dashboard\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use NX\Component\Fediverse\Administrator\Helper\DashboardTileBuilder;
use NX\Component\Fediverse\Administrator\Model\DashboardModel;

defined('_JEXEC') or die;

/**
 * Provide dashboard metric tiles for the administrator module.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DashboardTileProvider
{
    /**
     * Get filtered dashboard tiles.
     *
     * @param   Registry  $params  Module parameters.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getTiles(Registry $params): array
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_fediverse', JPATH_ADMINISTRATOR)
            || $lang->load('com_fediverse', JPATH_ADMINISTRATOR . '/components/com_fediverse');

        Factory::getApplication()->bootComponent('com_fediverse');

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $model = new DashboardModel($db);
        $tiles = DashboardTileBuilder::fromSummary($model->getSummary());

        $allowedKeys = $params->get('metrics', []);

        if (is_string($allowedKeys) && $allowedKeys !== '') {
            $allowedKeys = array_map('trim', explode(',', $allowedKeys));
        }

        if (!is_array($allowedKeys) || $allowedKeys === []) {
            return $tiles;
        }

        $allowedLookup = array_fill_keys(array_map('strval', $allowedKeys), true);

        return array_values(
            array_filter(
                $tiles,
                static fn(array $tile): bool => isset($allowedLookup[(string) ($tile['key'] ?? '')])
            )
        );
    }
}
