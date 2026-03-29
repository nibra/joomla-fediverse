<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_dashboard
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Installer script for mod_fediverse_dashboard.
 *
 * Ensures all module instances have the header icon param needed by Atum chrome.
 */
final class mod_fediverse_dashboardInstallerScript extends InstallerScript
{
    /**
     * Run before install/update.
     *
     * @param   string            $type    Route type.
     * @param   InstallerAdapter  $parent  Installer adapter.
     *
     * @return  bool
     */
    public function preflight($type, $parent): bool
    {
        return parent::preflight($type, $parent);
    }

    /**
     * Run after install/update/discover_install.
     *
     * @param   string            $type    Route type.
     * @param   InstallerAdapter  $parent  Installer adapter.
     *
     * @return  bool
     */
    public function postflight($type, $parent): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        $this->ensureModuleInstance('Statistics', [
            'metrics' => [
                'actors-local',
                'actors-remote',
                'delivery-queued',
                'delivery-delivering',
                'delivery-delivered',
                'delivery-failed',
            ],
            'show_links' => 1,
            'header_icon' => 'icon-fediverse',
        ]);

        foreach ($this->getInstances(true) as $moduleId) {
            $moduleId = (int) $moduleId;

            if ($moduleId <= 0) {
                continue;
            }

            $current = (string) ($this->getParam('header_icon', $moduleId) ?? '');

            if (trim($current) !== '') {
                continue;
            }

            $this->setParams(['header_icon' => 'icon-fediverse'], 'edit', $moduleId);
        }

        return true;
    }

    /**
     * Ensure one configurable administrator module instance exists.
     *
     * @param   string               $defaultTitle   Default module title.
     * @param   array<string,mixed>  $defaultParams  Default module params.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function ensureModuleInstance(string $defaultTitle, array $defaultParams): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $moduleName = 'mod_fediverse_dashboard';
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = :module')
            ->where($db->quoteName('client_id') . ' = 1')
            ->bind(':module', $moduleName, ParameterType::STRING);
        $db->setQuery($query, 0, 1);
        $module = $db->loadAssoc();

        if (!is_array($module)) {
            $columns = [
                'title',
                'note',
                'content',
                'ordering',
                'position',
                'checked_out',
                'checked_out_time',
                'publish_up',
                'publish_down',
                'published',
                'module',
                'access',
                'showtitle',
                'params',
                'client_id',
                'language',
            ];
            $values = [
                $defaultTitle,
                '',
                '',
                0,
                'cpanel',
                0,
                $db->getNullDate(),
                $db->getNullDate(),
                $db->getNullDate(),
                0,
                'mod_fediverse_dashboard',
                1,
                1,
                json_encode($defaultParams, JSON_UNESCAPED_SLASHES),
                1,
                '*',
            ];

            $insert = $db->createQuery()
                ->insert($db->quoteName('#__modules'))
                ->columns(array_map([$db, 'quoteName'], $columns))
                ->values(implode(',', array_map([$db, 'quote'], $values)));
            $db->setQuery($insert);
            $db->execute();

            return;
        }

        $params = json_decode((string) ($module['params'] ?? '{}'), true);
        $params = is_array($params) ? $params : [];
        $params = array_replace($defaultParams, $params);
        $title = trim((string) ($module['title'] ?? ''));

        if ($title === '' || in_array($title, ['Fediverse Dashboard', 'MOD_FEDIVERSE_DASHBOARD'], true)) {
            $title = $defaultTitle;
        }

        $update = $db->createQuery()
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('title') . ' = :title')
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':title', $title, ParameterType::STRING);
        $encodedParams = json_encode($params, JSON_UNESCAPED_SLASHES) ?: '{}';
        $moduleId = (int) $module['id'];
        $update
            ->bind(':params', $encodedParams, ParameterType::STRING)
            ->bind(':id', $moduleId, ParameterType::INTEGER);
        $db->setQuery($update);
        $db->execute();
    }
}
