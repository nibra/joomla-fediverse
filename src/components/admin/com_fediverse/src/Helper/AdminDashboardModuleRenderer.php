<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

/**
 * Render administrator modules inside the Fediverse dashboard.
 *
 * @since  __DEPLOY_VERSION__
 */
final class AdminDashboardModuleRenderer
{
    /**
     * Render a synthetic administrator module instance.
     *
     * @param   string               $moduleName  Module extension name.
     * @param   string               $defaultTitle  Fallback module title.
     * @param   array<string,mixed>  $fallbackParams  Fallback module parameters.
     *
     * @return  string  Rendered module HTML.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function render(string $moduleName, string $defaultTitle, array $fallbackParams = []): string
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load($moduleName, JPATH_ADMINISTRATOR)
            || $language->load($moduleName, JPATH_ADMINISTRATOR . '/modules/' . $moduleName);

        $baseModule = ModuleHelper::getModule($moduleName);
        $configuredModule = self::loadConfiguredAdminModule($moduleName);

        if ($baseModule === null) {
            return '';
        }

        $module = clone $baseModule;

        if ($configuredModule !== null) {
            foreach (get_object_vars($configuredModule) as $property => $value) {
                $module->{$property} = $value;
            }
        }

        $module->title = trim((string) ($module->title ?? '')) !== '' ? (string) $module->title : $defaultTitle;
        $module->module = $moduleName;
        $module->showtitle = 1;
        $module->position = 'fediverse-dashboard';

        $currentParams = json_decode((string) ($module->params ?? '{}'), true);
        $currentParams = is_array($currentParams) ? $currentParams : [];
        $mergedParams  = array_replace($fallbackParams, $currentParams);
        $mergedParams['header_icon'] ??= 'icon-fediverse';
        $module->params = json_encode($mergedParams, JSON_UNESCAPED_SLASHES) ?: '{}';

        return ModuleHelper::renderModule($module, ['style' => 'well']);
    }

    /**
     * Load the first configured administrator module instance for an extension.
     *
     * @param   string  $moduleName  Module extension name.
     *
     * @return  ?object
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function loadConfiguredAdminModule(string $moduleName): ?object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = :module')
            ->where($db->quoteName('client_id') . ' = 1')
            ->order($db->quoteName('published') . ' DESC')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':module', $moduleName);

        $db->setQuery($query, 0, 1);
        $module = $db->loadObject();

        return is_object($module) ? $module : null;
    }
}
