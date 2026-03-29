<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Helper;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;

/**
 * Build dashboard tiles from summary data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DashboardTileBuilder
{
    /**
     * Build tile definitions from dashboard summary data.
     *
     * @param   array<string, array<string, int>>  $summary  Summary data.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromSummary(array $summary): array
    {
        $actors = $summary['actors'] ?? ['local' => 0, 'remote' => 0];
        $deliveries = $summary['deliveries'] ?? [
            'queued'     => 0,
            'delivering' => 0,
            'delivered'  => 0,
            'failed'     => 0,
        ];

        return [
            self::buildTile(
                'actors-local',
                Text::_('COM_FEDIVERSE_DASHBOARD_ACTORS_LOCAL_LABEL'),
                (int) ($actors['local'] ?? 0),
                'primary',
                'index.php?option=com_fediverse&view=actors'
            ),
            self::buildTile(
                'actors-remote',
                Text::_('COM_FEDIVERSE_DASHBOARD_ACTORS_REMOTE_LABEL'),
                (int) ($actors['remote'] ?? 0),
                'info',
                'index.php?option=com_fediverse&view=actors'
            ),
            self::buildTile(
                'delivery-queued',
                Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_QUEUED_LABEL'),
                (int) ($deliveries['queued'] ?? 0),
                'warning',
                'index.php?option=com_fediverse&view=deliveries'
            ),
            self::buildTile(
                'delivery-delivering',
                Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_DELIVERING_LABEL'),
                (int) ($deliveries['delivering'] ?? 0),
                'secondary',
                'index.php?option=com_fediverse&view=deliveries'
            ),
            self::buildTile(
                'delivery-delivered',
                Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_DELIVERED_LABEL'),
                (int) ($deliveries['delivered'] ?? 0),
                'success',
                'index.php?option=com_fediverse&view=deliveries'
            ),
            self::buildTile(
                'delivery-failed',
                Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_FAILED_LABEL'),
                (int) ($deliveries['failed'] ?? 0),
                'danger',
                'index.php?option=com_fediverse&view=deliveries'
            ),
        ];
    }

    /**
     * Build a single tile definition.
     *
     * @param   string  $key    Tile key.
     * @param   string  $label  Tile label.
     * @param   int     $value  Tile value.
     * @param   string  $tone   Bootstrap tone suffix.
     * @param   string  $link   Internal route.
     *
     * @return  array<string, mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildTile(string $key, string $label, int $value, string $tone, string $link): array
    {
        return [
            'key'   => $key,
            'label' => $label,
            'value' => $value,
            'tone'  => $tone,
            'link'  => Route::_($link),
        ];
    }
}
