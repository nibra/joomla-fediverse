<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_user_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Plugin\User\Fediverse\Extension\Fediverse;

if (!defined('JPATH_FEDIVERSE')) {
    define('JPATH_FEDIVERSE', JPATH_ROOT . '/administrator/components/com_fediverse');
}

return new 
/**
 * ServiceProviderInterface Class
 *
 * Provide ServiceProviderInterface behavior.
 *
 * @since  __DEPLOY_VERSION__
 */
class () implements ServiceProviderInterface {
    /**
     * Register plugin services.
     *
     * Load component services and bind the user plugin instance.
     *
     * @params Container $container Dependency injection container.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(
            require JPATH_FEDIVERSE . '/services/provider.php'
        );
        $container->set(
            PluginInterface::class,
            /**
             * Build the user plugin instance.
             *
             * Create the plugin with required configuration.
             *
             * @params Container $container Dependency injection container.
             *
             * @return  PluginInterface  Plugin instance.
             *
             * @since  __DEPLOY_VERSION__
             */
            static function(Container $container) {
                $pluginConfig = (array) PluginHelper::getPlugin('user', 'fediverse');

                $plugin = new Fediverse(
                    $pluginConfig
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
