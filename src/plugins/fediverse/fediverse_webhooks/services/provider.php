<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_fediverse_fediverse_webhooks
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Component\Fediverse\Administrator\Service\Automation\WebhookAutomationService;
use NX\Plugin\Fediverse\FediverseWebhooks\Extension\FediverseWebhooks;

if (!defined('JPATH_FEDIVERSE')) {
    define('JPATH_FEDIVERSE', JPATH_ROOT . '/administrator/components/com_fediverse');
}

return new class () implements ServiceProviderInterface {
    /**
     * Register plugin services.
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
            static function(Container $container) {
                $pluginConfig = (array) PluginHelper::getPlugin('fediverse', 'fediverse_webhooks');

                $plugin = new FediverseWebhooks(
                    $container->get(WebhookAutomationService::class),
                    $pluginConfig
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
