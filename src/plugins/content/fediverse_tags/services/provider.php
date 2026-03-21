<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_content_fediverse_tags
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Plugin\Content\FediverseTags\Extension\FediverseTags;

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
     * Load component services and bind the tags plugin instance.
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
             * Build the fediverse_tags plugin instance.
             *
             * Create the plugin with required service dependencies.
             *
             * @params Container $container Dependency injection container.
             *
             * @return  PluginInterface  Plugin instance.
             *
             * @since  __DEPLOY_VERSION__
             */
            static function (Container $container): PluginInterface {
                $actorResolver = $container->get(ActorResolverServiceInterface::class);
                $pluginConfig  = (array) PluginHelper::getPlugin('content', 'fediverse_tags');

                $plugin = new FediverseTags($actorResolver, $pluginConfig);
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
