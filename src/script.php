<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      pkg_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Table\Extension as ExtensionTable;
use Joomla\Database\DatabaseInterface;

/**
 * Installer script for the Fediverse package.
 *
 * Enables the shipped Fediverse extensions after package install/update so the
 * package is usable immediately from the Joomla administrator UI.
 *
 * @since  __DEPLOY_VERSION__
 */
final class pkg_fediverseInstallerScript extends InstallerScript
{
    /**
     * Extension rows that should be enabled after install/update.
     *
     * @var array<int,array{type:string, element:string, folder:?string, client_id:int}>
     *
     * @since  __DEPLOY_VERSION__
     */
    private const ENABLED_EXTENSIONS = [
        ['type' => 'component', 'element' => 'com_fediverse', 'folder' => null,        'client_id' => 1],
        ['type' => 'module',    'element' => 'mod_fediverse_dashboard',   'folder' => null,        'client_id' => 1],
        ['type' => 'module',    'element' => 'mod_fediverse_diagnostics', 'folder' => null,        'client_id' => 1],
        ['type' => 'module',    'element' => 'mod_fediverse_profile',     'folder' => null,        'client_id' => 0],
        ['type' => 'module',    'element' => 'mod_fediverse_reactions',   'folder' => null,        'client_id' => 0],
        ['type' => 'module',    'element' => 'mod_fediverse_timeline',    'folder' => null,        'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse',                 'folder' => 'content',   'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse_tags',            'folder' => 'content',   'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse',                 'folder' => 'system',    'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse',                 'folder' => 'task',      'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse',                 'folder' => 'user',      'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse_actionlog',       'folder' => 'fediverse', 'client_id' => 0],
        ['type' => 'plugin',    'element' => 'fediverse_webhooks',        'folder' => 'fediverse', 'client_id' => 0],
    ];

    /**
     * Run after install/update/discover_install.
     *
     * @param   string            $type    Installer route type.
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

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach (self::ENABLED_EXTENSIONS as $extension) {
            $this->enableExtension($db, $extension);
        }

        return true;
    }

    /**
     * Enable one installed extension row when it exists.
     *
     * @param   DatabaseInterface                         $db         Joomla database connection.
     * @param   array{type:string, element:string, folder:?string, client_id:int}  $extension  Extension identifier.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function enableExtension(DatabaseInterface $db, array $extension): void
    {
        $table = new ExtensionTable($db);
        $id    = $table->find([
            'type'      => $extension['type'],
            'element'   => $extension['element'],
            'client_id' => $extension['client_id'],
            'folder'    => $extension['folder'],
        ]);

        if ($id === null || $id === '' || !$table->load((int) $id)) {
            return;
        }

        $table->enabled = 1;
        $table->state   = 0;
        $table->store();
    }
}
