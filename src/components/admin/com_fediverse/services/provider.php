<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactory;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NX\Component\Fediverse\Administrator\Adapter\ActivityPubAdapterInterface;
use NX\Component\Fediverse\Administrator\Adapter\RikudouAdapter;
use NX\Component\Fediverse\Administrator\Extension\FediverseComponent;
use NX\Component\Fediverse\Administrator\Http\JoomlaHttpClient;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaContentMapper;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaContentMapperInterface;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\MVC\FediverseMVCFactory;
use NX\Component\Fediverse\Administrator\Service\C2S\OAuthService;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Announce\NodeInfoService;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryService;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Federation\InboxProcessService;
use NX\Component\Fediverse\Administrator\Service\Media\MediaStorageService;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;
use NX\Component\Fediverse\Administrator\Service\Publishing\Provider\JoomlaArticleContentProvider;
use NX\Component\Fediverse\Administrator\Service\Publishing\Provider\JoomlaNewsfeedContentProvider;
use NX\Component\Fediverse\Administrator\Service\Publishing\PublishService;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturService;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Security\KeyRotationService;
use NX\Component\Fediverse\Administrator\Service\Security\KeyVaultService;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\License\Validator\JwtLicenseValidator;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;

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
     * Register.
     *
     * Execute the related behavior.
     *
     * @params Container $container Parameter container.
     *
     * @return  void None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $container->set(
            FediverseMVCFactory::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $db = $container->get(DatabaseDriver::class);

                return new FediverseMVCFactory($db);
            }
        );

        $globalContainer = Factory::getContainer();
        if ($container !== $globalContainer) {
            $container->set(
                MVCFactoryInterface::class,
                /**
                 * Define closure.
                 *
                 * Provide inline closure behavior.
                 *
                 * @params Container $container Parameter container.
                 *
                 * @return  mixed Result value.
                 *
                 * @since  __DEPLOY_VERSION__
                 */
                function(Container $container) {
                    return $container->get(FediverseMVCFactory::class);
                }
            );
        }
        $container->set(ActivityPubAdapterInterface::class, fn() => new RikudouAdapter());
        $container->set(RequestContext::class, fn() => RequestContext::fromGlobals());
        $container->set(LicenseService::class, fn() => new LicenseService(new JwtLicenseValidator()));
        $container->set(FediverseConfig::class, fn() => new FediverseConfig());
        $container->set(
            OAuthService::class,
            function(Container $container) {
                $mvcFactory = $container->get(FediverseMVCFactory::class);
                $config     = $container->get(FediverseConfig::class);

                return new OAuthService(
                    $mvcFactory->createModel('OAuth', 'Administrator', ['ignore_request' => true]),
                    $container->get(DatabaseDriver::class),
                    $config
                );
            }
        );
        $container->set(
            NodeInfoService::class,
            function(Container $container) {
                $config = $container->get(FediverseConfig::class);
                $baseUrlProvider = new JoomlaBaseUrlProvider($config);

                return new NodeInfoService(
                    $baseUrlProvider,
                    $container->get(DatabaseDriver::class)
                );
            }
        );
        $container->set(
            MediaStorageService::class,
            function(Container $container) {
                $config = $container->get(FediverseConfig::class);
                $baseUrlProvider = new JoomlaBaseUrlProvider($config);

                return new MediaStorageService($baseUrlProvider);
            }
        );
        $container->set(
            KeyRotationService::class,
            function(Container $container) {
                $config = $container->get(FediverseConfig::class);
                $actorMapper = new JoomlaUserActorMapper($config);

                return new KeyRotationService(
                    $config,
                    $actorMapper,
                    $container->get(FediverseMVCFactory::class),
                    $container->get(HttpSignaturServiceInterface::class),
                    $container->get(DatabaseDriver::class)
                );
            }
        );
        $container->set(
            ActorResolverServiceInterface::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params mixed $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function($container) {
                $mvcFactory      = $container->get(FediverseMVCFactory::class);
                $keyVaultService = new KeyVaultService();
                $config          = $container->get(FediverseConfig::class);
                $actorMapper     = new JoomlaUserActorMapper($config);
                $baseUrlProvider = new JoomlaBaseUrlProvider($config);

                return new ActorResolverService(
                    $mvcFactory,
                    $keyVaultService,
                    $actorMapper,
                    $baseUrlProvider
                );
            }
        );
        $container->set(
            HttpSignaturServiceInterface::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $mvcFactory = $container->get(FediverseMVCFactory::class);
                $keyVault   = new KeyVaultService();
                $client     = new JoomlaHttpClient();

                return new HttpSignaturService($mvcFactory, $keyVault, $client);
            }
        );
        $container->set(
            DeliveryServiceInterface::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $mvcFactory       = $container->get(FediverseMVCFactory::class);
                $signatureService = $container->get(HttpSignaturServiceInterface::class);
                $queueModel       = $mvcFactory->createModel(
                    'DeliveryQueue',
                    'Administrator',
                    ['ignore_request' => true]
                );
                $keysModel        = $mvcFactory->createModel('Keys', 'Administrator', ['ignore_request' => true]);

                return new DeliveryService(
                    $queueModel,
                    $keysModel,
                    $signatureService
                );
            }
        );
        $container->set(
            InboxProcessService::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $mvcFactory      = $container->get(FediverseMVCFactory::class);
                $inboxModel      = $mvcFactory->createModel('Inbox', 'Administrator', ['ignore_request' => true]);
                $actorsModel     = $mvcFactory->createModel('Actors', 'Administrator', ['ignore_request' => true]);
                $followersModel  = $mvcFactory->createModel('Followers', 'Administrator', ['ignore_request' => true]);
                $outboxModel     = $mvcFactory->createModel('Outbox', 'Administrator', ['ignore_request' => true]);
                $inboundModel    = $mvcFactory->createModel(
                    'InboundActivities',
                    'Administrator',
                    ['ignore_request' => true]
                );
                $deliveryService = $container->get(DeliveryServiceInterface::class);
                $adapter         = $container->get(ActivityPubAdapterInterface::class);
                $actorMapper     = new JoomlaUserActorMapper();
                $baseUrlProvider = new JoomlaBaseUrlProvider();

                $policiesModel   = $mvcFactory->createModel('Policies', 'Administrator', ['ignore_request' => true]);

                return new InboxProcessService(
                    $inboxModel,
                    $actorsModel,
                    $followersModel,
                    $outboxModel,
                    $inboundModel,
                    $deliveryService,
                    $adapter,
                    $actorMapper,
                    $baseUrlProvider,
                    $policiesModel
                );
            }
        );
        $container->set(
            JoomlaContentMapperInterface::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params mixed $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function($container) {
                $actorMapper = new JoomlaUserActorMapper();

                return new JoomlaContentMapper($actorMapper);
            }
        );
        $container->set(
            ContentProviderRegistry::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  ContentProviderRegistry  Registry instance.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $db       = $container->get(DatabaseDriver::class);
                $mapper   = $container->get(JoomlaContentMapperInterface::class);
                $provider = new JoomlaArticleContentProvider($mapper, $db);
                $actorMapper = new JoomlaUserActorMapper();
                $newsfeedProvider = new JoomlaNewsfeedContentProvider($actorMapper, $db);

                return new ContentProviderRegistry([$provider, $newsfeedProvider]);
            }
        );
        $container->set(
            PublishService::class,
            /**
             * Define closure.
             *
             * Provide inline closure behavior.
             *
             * @params Container $container Parameter container.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            function(Container $container) {
                $mvcFactory      = $container->get(FediverseMVCFactory::class);
                $outboxModel     = $mvcFactory->createModel('Outbox', 'Administrator', ['ignore_request' => true]);
                $followersModel  = $mvcFactory->createModel('Followers', 'Administrator', ['ignore_request' => true]);
                $contentProviders = $container->get(ContentProviderRegistry::class);
                $actorResolver   = $container->get(ActorResolverServiceInterface::class);
                $deliveryService = $container->get(DeliveryServiceInterface::class);
                $baseUrlProvider = new JoomlaBaseUrlProvider();

                return new PublishService(
                    $contentProviders,
                    $actorResolver,
                    $deliveryService,
                    $baseUrlProvider,
                    $followersModel,
                    $outboxModel,
                    $container->get(DatabaseDriver::class),
                );
            }
        );

        $app    = Factory::getApplication();
        $option = $app !== null ? $app->input->getCmd('option') : '';
        if ($option === 'com_fediverse') {
            $container->set(
                ComponentInterface::class,
                function(Container $container) {
                    $dispatcherFactory = new ComponentDispatcherFactory(
                        '\\NX\\Component\\Fediverse',
                        $container->get(FediverseMVCFactory::class)
                    );
                    $component         = new FediverseComponent($dispatcherFactory);

                    $component->setMVCFactory($container->get(FediverseMVCFactory::class));

                    return $component;
                }
            );
        }
    }
};
