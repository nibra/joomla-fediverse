<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_diagnostics
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Diagnostics\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use NX\Component\Fediverse\Administrator\Model\DiagnosticsModel;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;

defined('_JEXEC') or die;

/**
 * Provide diagnostics quick tiles for the administrator module.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DiagnosticsTileProvider
{
    /**
     * Build diagnostics module state.
     *
     * @return  array<string,mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getModuleState(?Registry $params = null): array
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_fediverse', JPATH_ADMINISTRATOR)
            || $lang->load('com_fediverse', JPATH_ADMINISTRATOR . '/components/com_fediverse');

        Factory::getApplication()->bootComponent('com_fediverse');

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $model = new DiagnosticsModel($db);
        $checks = $model->getChecks();
        $enabledTiles = self::normalizeEnabledTiles($params);
        $tiles = [];

        foreach ($checks as $check) {
            $tileId = (string) ($check['id'] ?? '');

            if (!in_array($tileId, $enabledTiles, true)) {
                continue;
            }

            $tiles[] = self::mapCheckToTile($check);
        }

        if (in_array('permissions', $enabledTiles, true)) {
            $tiles[] = self::buildPermissionsTile();
        }

        return [
            'tiles' => $tiles,
            'publicBaseUrl' => $model->getPublicBaseUrl(),
            'schedulerTaskSettings' => $model->getRequiredSchedulerTaskSettings(),
        ];
    }

    /**
     * Map a diagnostics check to a tile definition.
     *
     * @param   array<string,string>  $check  Diagnostics check row.
     *
     * @return  array<string,mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function mapCheckToTile(array $check): array
    {
        $id = (string) ($check['id'] ?? '');
        $status = (string) ($check['status'] ?? 'warn');

        return [
            'id' => $id,
            'status' => $status,
            'message' => self::buildCompactMessage($id, $status),
            'icon' => match ($status) {
                'pass' => 'fas fa-check-circle',
                'fail' => 'fas fa-times-circle',
                default => 'fas fa-exclamation-triangle',
            },
            'dashboardAction' => self::buildDashboardAction($id),
            'homeLink' => self::buildHomeLink($id),
        ];
    }

    /**
     * Build the compact notification text for a diagnostics tile.
     *
     * @param   string  $id      Diagnostics check identifier.
     * @param   string  $status  Diagnostics check status.
     *
     * @return  string
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildCompactMessage(string $id, string $status): string
    {
        return match ($id) {
            'public_base_url' => $status === 'pass'
                ? 'Public base URL is configured'
                : 'Public base URL is not configured',
            'plugins' => $status === 'pass'
                ? 'Required Fediverse plugins are enabled'
                : 'Required Fediverse plugins need attention',
            'scheduler' => $status === 'pass'
                ? 'Fediverse scheduler tasks are configured'
                : 'Fediverse scheduler tasks need attention',
            'actors' => $status === 'pass'
                ? 'At least one local actor is enabled'
                : 'No enabled local actor is available',
            default => (string) ($id !== '' ? $id : 'Fediverse diagnostics status'),
        };
    }

    /**
     * Build the permissions status tile.
     *
     * @return  array<string,mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildPermissionsTile(): array
    {
        $strict = Factory::getContainer()->get(FediverseConfig::class)->isStrictAclActionsEnabled();

        return [
            'id' => 'permissions',
            'status' => $strict ? 'pass' : 'warn',
            'message' => $strict
                ? 'Strict Fediverse permissions are enabled'
                : 'Joomla permissions are active',
            'icon' => $strict ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle',
            'dashboardAction' => [
                'type' => 'link',
                'selector' => 'open-permissions',
                'href' => Route::_('index.php?option=com_config&view=component&component=com_fediverse'),
            ],
            'homeLink' => Route::_('index.php?option=com_config&view=component&component=com_fediverse'),
        ];
    }

    /**
     * Build the dashboard action definition for a diagnostics tile.
     *
     * @param   string  $id  Diagnostics check identifier.
     *
     * @return  array<string,string>
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildDashboardAction(string $id): array
    {
        return match ($id) {
            'public_base_url' => [
                'type' => 'modal',
                'label' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_CONFIGURE_PUBLIC_BASE_URL'),
                'selector' => 'configure-public-base-url',
                'target' => '#fediverse-public-base-url-modal',
            ],
            'plugins' => [
                'type' => 'link',
                'selector' => 'activate-plugins',
                'href' => Route::_(
                    'index.php?option=com_fediverse&task=diagnostics.activateCorePlugins&'
                    . Session::getFormToken()
                    . '=1'
                ),
            ],
            'scheduler' => [
                'type' => 'modal',
                'label' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_CONFIGURE_SCHEDULER'),
                'selector' => 'configure-scheduler-tasks',
                'target' => '#fediverse-scheduler-tasks-modal',
            ],
            default => [
                'type' => 'link',
                'label' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_OPEN_ACTORS'),
                'selector' => 'open-actors',
                'href' => Route::_('index.php?option=com_fediverse&view=actors'),
            ],
        };
    }

    /**
     * Build the home-dashboard link target for a diagnostics tile.
     *
     * @param   string  $id  Diagnostics check identifier.
     *
     * @return  string
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildHomeLink(string $id): string
    {
        return match ($id) {
            'plugins' => Route::_('index.php?option=com_plugins&view=plugins&filter[search]=fediverse'),
            'actors' => Route::_('index.php?option=com_fediverse&view=actors'),
            default => Route::_('index.php?option=com_fediverse&view=dashboard'),
        };
    }

    /**
     * Normalize the configured diagnostics tiles.
     *
     * @param   ?Registry  $params  Module parameters.
     *
     * @return  list<string>
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeEnabledTiles(?Registry $params): array
    {
        $defaultTiles = ['public_base_url', 'plugins', 'scheduler', 'actors', 'permissions'];
        $rawTiles = $params?->get('tiles', $defaultTiles);

        if (is_string($rawTiles)) {
            $rawTiles = preg_split('/\s*,\s*/', trim($rawTiles), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $rawTiles = is_array($rawTiles) ? $rawTiles : $defaultTiles;
        $allowedTiles = ['public_base_url', 'plugins', 'scheduler', 'actors', 'permissions'];
        $normalized = [];

        foreach ($rawTiles as $tile) {
            $tile = preg_replace('/[^a-z0-9_]+/i', '', (string) $tile) ?? '';

            if ($tile === '' || !in_array($tile, $allowedTiles, true) || in_array($tile, $normalized, true)) {
                continue;
            }

            $normalized[] = $tile;
        }

        return $normalized !== [] ? $normalized : $defaultTiles;
    }
}
