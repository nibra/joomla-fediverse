<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_task_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Federation\InboxProcessService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Plugin\Task\Fediverse\Extension\Fediverse;

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
     * Load component services and bind the task plugin instance.
     *
     * @params Container $container Dependency injection container.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $provider = require JPATH_FEDIVERSE . '/services/provider.php';
        $provider->register($container);
        $container->set(
            PluginInterface::class,
            /**
             * Build the task plugin instance.
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
                $deliveryService = $container->get(DeliveryServiceInterface::class);
                $inboxProcessorService = $container->get(InboxProcessService::class);
                $actorResolverService = $container->get(ActorResolverServiceInterface::class);
                $pluginConfig = (array) PluginHelper::getPlugin('task', 'fediverse');

                $plugin = new Fediverse(
                    $inboxProcessorService,
                    $deliveryService,
                    $actorResolverService,
                    $pluginConfig
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
