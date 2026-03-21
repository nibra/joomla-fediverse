<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_system_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Announce\WebfingerService;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;
use NX\Plugin\System\Fediverse\Extension\Fediverse;

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
     * Load component services and bind the system plugin instance.
     *
     * @params Container $container Dependency injection container.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $componentProvider = require JPATH_FEDIVERSE . '/services/provider.php';

        $container->registerServiceProvider($componentProvider);

        $globalContainer = Factory::getContainer();
        if ($globalContainer !== $container) {
            $globalContainer->registerServiceProvider($componentProvider);
        }

        $container->set(
            PluginInterface::class,
            /**
             * Build the system plugin instance.
             *
             * Create the plugin with required service dependencies.
             *
             * @params Container $container Dependency injection container.
             *
             * @return  PluginInterface  Plugin instance.
             *
             * @since  __DEPLOY_VERSION__
             */
            static function(Container $container) {
                $mvcFactory       = $container->get(MVCFactoryInterface::class);
                $actorResolver    = $container->get(ActorResolverServiceInterface::class);
                $baseUrlProvider  = new JoomlaBaseUrlProvider();
                $webfingerService = new WebfingerService(
                    $mvcFactory,
                    $actorResolver,
                    $baseUrlProvider
                );
                $pluginConfig     = (array) PluginHelper::getPlugin('system', 'fediverse');

                $plugin = new Fediverse(
                    $webfingerService,
                    $mvcFactory,
                    $pluginConfig
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
