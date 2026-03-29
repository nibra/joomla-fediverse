<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_diagnostics
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
 * Installer script for mod_fediverse_diagnostics.
 *
 * Ensures one configurable administrator module instance exists.
 *
 * @since  __DEPLOY_VERSION__
 */
final class mod_fediverse_diagnosticsInstallerScript extends InstallerScript
{
    /**
     * Run before install/update.
     *
     * @param   string            $type    Route type.
     * @param   InstallerAdapter  $parent  Installer adapter.
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
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
     *
     * @since  __DEPLOY_VERSION__
     */
    public function postflight($type, $parent): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        $this->ensureModuleInstance();

        return true;
    }

    /**
     * Ensure one configurable administrator module instance exists.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function ensureModuleInstance(): void
    {
        $defaultTitle = 'Status';
        $defaultParams = [
            'tiles' => [
                'public_base_url',
                'plugins',
                'scheduler',
                'actors',
                'permissions',
            ],
            'header_icon' => 'icon-fediverse',
        ];

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $moduleName = 'mod_fediverse_diagnostics';
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
                'mod_fediverse_diagnostics',
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

        if ($title === '' || in_array($title, ['Fediverse Diagnostics', 'MOD_FEDIVERSE_DIAGNOSTICS'], true)) {
            $title = $defaultTitle;
        }

        $encodedParams = json_encode($params, JSON_UNESCAPED_SLASHES) ?: '{}';
        $moduleId = (int) ($module['id'] ?? 0);
        if ($moduleId <= 0) {
            return;
        }

        $update = $db->createQuery()
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('title') . ' = :title')
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':title', $title, ParameterType::STRING)
            ->bind(':params', $encodedParams, ParameterType::STRING)
            ->bind(':id', $moduleId, ParameterType::INTEGER);
        $db->setQuery($update);
        $db->execute();
    }
}
