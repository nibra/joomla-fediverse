<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_system_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\System\Fediverse\Extension;

use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Service\Announce\NodeInfoService;
use NX\Component\Fediverse\Administrator\Service\Announce\WebfingerService;
use NX\Component\Fediverse\Administrator\Service\Comments\CommentRendererRegistry;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;
use NX\Component\Fediverse\Administrator\Service\Publishing\PublishService;
use NX\Component\Fediverse\Site\Controller\ActorController;
use NX\Component\Fediverse\Site\Controller\InboxController;
use NX\Component\Fediverse\Site\Controller\MediaController;
use NX\Component\Fediverse\Site\Controller\OAuthController;
use NX\Component\Fediverse\Site\Controller\NodeInfoController;
use NX\Component\Fediverse\Site\Controller\ObjectController;
use NX\Component\Fediverse\Site\Controller\OutboxController;

/**
 * Fediverse Class
 *
 * Provide the Fediverse system plugin.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Fediverse extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    private bool $contentProvidersRegistered = false;
    private bool $commentRenderersRegistered = false;

    /**
     * Initialize the system plugin.
     *
     * Store dependencies required to handle Fediverse endpoints.
     *
     * @params WebfingerService $webfingerService WebFinger resolver.
     * @params MVCFactoryInterface $mvcFactory MVC factory.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private readonly WebfingerService $webfingerService,
        private readonly MVCFactoryInterface $mvcFactory,
        $config = []
    )
    {
        parent::__construct($config);
    }

    /**
     * Get subscribed Joomla events.
     *
     * Return the event map for route handling.
     *
     * @return  array  Subscribed event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterRoute'      => 'onAfterRoute',
            'onContentAfterSave'   => 'onContentAfterSave',
            'onContentAfterDelete' => 'onContentAfterDelete',
            'onContentChangeState' => 'onContentChangeState',
        ];
    }

    /**
     * Handle the after-initialise event.
     *
     * Dispatch routing logic early in the request lifecycle.
     *
     * @params AfterInitialiseEvent $event Event instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onAfterInitialise(AfterInitialiseEvent $event): void
    {
        $app = $event->getApplication();

        $this->registerContentProviders();
        $this->registerCommentRenderers();
        $this->normalizeLiveSite($app);
        $this->handleRoutes($app);
    }

    /**
     * Handle the after-route event.
     *
     * Dispatch routing logic after Joomla routing has run.
     *
     * @params AfterRouteEvent $event Event instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onAfterRoute(AfterRouteEvent $event): void
    {
        $this->handleRoutes($event->getApplication());
    }

    /**
     * Handle Fediverse endpoint routing.
     *
     * Detect supported paths and dispatch to appropriate handlers.
     *
     * @params object $app Joomla application instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleRoutes(object $app): void
    {
        if (!$app->isClient('site')) {
            return;
        }

        $path = rtrim(Uri::getInstance()->getPath(), '/');

        if ($this->matchActorInbox($path, $handle)) {
            $this->ensureComponentPaths();
            $this->handleActorInbox($handle);
            $this->closeApp($app);

            return;
        }

        if ($this->matchActorOutbox($path, $handle)) {
            $this->ensureComponentPaths();
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            if ($method === 'GET') {
                $this->handleActorOutboxGet($handle);
            } elseif ($method === 'POST') {
                $this->handleActorOutboxPost($handle);
            } else {
                header('Allow: GET, POST');
                $this->sendJson(['error' => 'Method Not Allowed'], 'application/json', 405);
            }

            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/ap/inbox')) {
            $this->ensureComponentPaths();
            $this->handleActorInbox(null);
            $this->closeApp($app);

            return;
        }

        if ($this->matchActorKey($path, $handle, $keyPart)) {
            $this->ensureComponentPaths();
            $this->handleActorKey($handle, $keyPart);
            $this->closeApp($app);

            return;
        }

        if ($this->matchActorFeatured($path, $handle)) {
            $this->ensureComponentPaths();
            $this->handleActorFeatured($handle);
            $this->closeApp($app);

            return;
        }

        if ($this->matchActor($path, $handle)) {
            $this->ensureComponentPaths();
            $this->handleActor($handle);
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/ap/media')) {
            $this->ensureComponentPaths();
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            if ($method === 'POST') {
                $this->handleMediaPost();
            } else {
                header('Allow: POST');
                $this->sendJson(['error' => 'Method Not Allowed'], 'application/json', 405);
            }

            $this->closeApp($app);

            return;
        }

        if ($this->matchMedia($path, $mediaId)) {
            $this->ensureComponentPaths();
            if ($this->ensureMethod('GET')) {
                $this->handleMediaGet($mediaId);
            }
            $this->closeApp($app);

            return;
        }

        if ($this->matchObject($path, $objectId)) {
            $this->ensureComponentPaths();
            $this->handleObject($objectId);
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/.well-known/webfinger')) {
            $this->handleWebfinger();
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/.well-known/nodeinfo')) {
            $this->handleNodeinfoWellKnown();
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/ap/nodeinfo/2.0')) {
            $this->handleNodeinfoDocument();
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/ap/oauth/authorize')) {
            $this->ensureComponentPaths();
            $this->handleOauthAuthorize();
            $this->closeApp($app);

            return;
        }

        if ($this->endsWithPath($path, '/ap/oauth/token')) {
            $this->ensureComponentPaths();
            $this->handleOauthToken();
            $this->closeApp($app);

            return;
        }
    }

    /**
     * Normalize live_site for site/admin requests with host mismatches.
     *
     * Use the current request host to avoid cross-host redirects in admin.
     *
     * @params object $app Joomla application instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeLiveSite(object $app): void
    {
        if (!$app->isClient('administrator') && !$app->isClient('site')) {
            return;
        }

        try {
            $config = $app->getConfig();
        } catch (\Throwable) {
            return;
        }

        if (!is_object($config) || !method_exists($config, 'get') || !method_exists($config, 'set')) {
            return;
        }

        $liveSite = trim((string) $config->get('live_site'));
        if ($liveSite === '') {
            return;
        }

        $uri  = Uri::getInstance();
        $host = trim((string) $uri->getHost());
        if ($host === '') {
            return;
        }

        $liveHost = Uri::getInstance($liveSite)->getHost();
        if ($liveHost !== '' && strcasecmp($liveHost, $host) === 0) {
            return;
        }

        $scheme = trim((string) $uri->getScheme());
        $scheme = $scheme !== '' ? $scheme : 'http';

        $base = $scheme . '://' . $host;
        $port = (int) $uri->getPort();
        if ($port > 0 && !($scheme === 'http' && $port === 80) && !($scheme === 'https' && $port === 443)) {
            $base .= ':' . $port;
        }

        $config->set('live_site', $base);
    }

    /**
     * Handle a WebFinger request.
     *
     * Resolve the resource and emit a JRD response.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleWebfinger(): void
    {
        $app      = $this->getApplication();
        $resource = $app->input->getString('resource', '');

        $result = $this->webfingerService->resolve($resource);
        $status = isset($result['error']) ? 404 : 200;

        $this->sendJson($result, 'application/jrd+json', $status);
    }

    /**
     * Handle a NodeInfo well-known request.
     *
     * Emit the NodeInfo discovery document.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleNodeinfoWellKnown(): void
    {
        $svc = Factory::getContainer()->get(NodeInfoService::class);

        $data = $svc->buildWellKnown();

        $this->sendJson($data, 'application/json', 200);
    }

    /**
     * Handle a NodeInfo document request.
     *
     * Emit the NodeInfo 2.0 document.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleNodeinfoDocument(): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new NodeInfoController([], $this->mvcFactory, $app, $app->getInput());
        $controller->document();
    }

    /**
     * Handle an OAuth authorize request.
     *
     * Dispatch to the OAuth controller for authorization.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleOauthAuthorize(): void
    {
        $app        = $this->getApplication();
        $controller = new OAuthController([], $this->mvcFactory, $app, $app->getInput());
        $controller->authorise();
    }

    /**
     * Handle an OAuth token request.
     *
     * Dispatch to the OAuth controller for token exchange.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleOauthToken(): void
    {
        $app        = $this->getApplication();
        $controller = new OAuthController([], $this->mvcFactory, $app, $app->getInput());
        $controller->token();
    }

    /**
     * Handle an actor document request.
     *
     * Dispatch to the actor controller for the given handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActor(string $handle): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new ActorController([], $this->mvcFactory, $app, $app->getInput());
        $controller->get($handle);
    }

    /**
     * Handle an actor key request.
     *
     * Dispatch to the actor controller for the given handle and key id.
     *
     * @params string $handle Actor handle.
     * @params string $keyPart Key identifier segment.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActorKey(string $handle, string $keyPart): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new ActorController([], $this->mvcFactory, $app, $app->getInput());
        $controller->key($handle, $keyPart);
    }

    /**
     * Handle an actor featured collection request.
     *
     * Dispatch to the actor controller for the given handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActorFeatured(string $handle): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new ActorController([], $this->mvcFactory, $app, $app->getInput());
        $controller->featured($handle);
    }

    /**
     * Handle an inbox request.
     *
     * Dispatch to the inbox controller for a shared or actor inbox.
     *
     * @params ?string $handle Actor handle or null for shared inbox.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActorInbox(?string $handle): void
    {
        if (!$this->ensureMethod('POST')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new InboxController([], $this->mvcFactory, $app, $app->getInput());
        $controller->post($handle);
    }

    /**
     * Handle an outbox read request.
     *
     * Dispatch to the outbox controller for the given actor handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActorOutboxGet(string $handle): void
    {
        $app        = $this->getApplication();
        $controller = new OutboxController([], $this->mvcFactory, $app, $app->getInput());
        $controller->get($handle);
    }

    /**
     * Handle an outbox write request.
     *
     * Dispatch to the outbox controller for the given actor handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleActorOutboxPost(string $handle): void
    {
        $app        = $this->getApplication();
        $controller = new OutboxController([], $this->mvcFactory, $app, $app->getInput());
        $controller->post($handle);
    }

    /**
     * Handle an object request.
     *
     * Dispatch to the object controller for the given object id.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleObject(string $objectId): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $app        = $this->getApplication();
        $controller = new ObjectController([], $this->mvcFactory, $app, $app->getInput());
        $controller->get($objectId);
    }

    /**
     * Handle a media read request.
     *
     * Dispatch to the media controller for the given media id.
     *
     * @params string $mediaId Media identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleMediaGet(string $mediaId): void
    {
        $app        = $this->getApplication();
        $controller = new MediaController([], $this->mvcFactory, $app, $app->getInput());
        $controller->get($mediaId);
    }

    /**
     * Handle a media upload request.
     *
     * Dispatch to the media controller for uploads.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleMediaPost(): void
    {
        $app        = $this->getApplication();
        $controller = new MediaController([], $this->mvcFactory, $app, $app->getInput());
        $controller->post();
    }

    /**
     * Send a JSON response.
     *
     * Encode and emit a JSON response with a content type.
     *
     * @params mixed $data Response payload.
     * @params string $contentType Response content type.
     * @params int $statusCode HTTP status code.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(mixed $data, string $contentType, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: ' . $contentType);

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ensure the request method matches.
     *
     * Validate the HTTP method and emit an error when mismatched.
     *
     * @params string $expected Expected HTTP method.
     *
     * @return  bool  True when the method matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function ensureMethod(string $expected): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === $expected) {
            return true;
        }

        header('Allow: ' . $expected);
        $this->sendJson(['error' => 'Method Not Allowed'], 'application/json', 405);

        return false;
    }

    /**
     * Ensure component path constants are defined.
     *
     * Define component paths needed by the MVC factory.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function ensureComponentPaths(): void
    {
        if (!defined('JPATH_COMPONENT')) {
            define('JPATH_COMPONENT', JPATH_SITE . '/components/com_fediverse');
        }

        if (!defined('JPATH_COMPONENT_SITE')) {
            define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_fediverse');
        }

        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_fediverse');
        }
    }

    /**
     * Match an actor inbox route.
     *
     * Parse the actor handle from an inbox path.
     *
     * @params string $path Request path.
     * @params ?string $handle Output actor handle.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchActorInbox(string $path, ?string &$handle): bool
    {
        if (preg_match('#/ap/actors/([^/]+)/inbox$#', $path, $m)) {
            $handle = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Match an actor outbox route.
     *
     * Parse the actor handle from an outbox path.
     *
     * @params string $path Request path.
     * @params ?string $handle Output actor handle.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchActorOutbox(string $path, ?string &$handle): bool
    {
        if (preg_match('#/ap/actors/([^/]+)/outbox$#', $path, $m)) {
            $handle = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Match an actor key route.
     *
     * Parse the actor handle and key identifier from the key path.
     *
     * @params string $path Request path.
     * @params ?string $handle Output actor handle.
     * @params ?string $keyPart Output key identifier segment.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchActorKey(string $path, ?string &$handle, ?string &$keyPart): bool
    {
        if (preg_match('#/ap/actors/([^/]+)/(main-key[^/]*)$#', $path, $m)) {
            $handle = rawurldecode($m[1]);
            $keyPart = rawurldecode($m[2]);

            return true;
        }

        return false;
    }

    /**
     * Match an actor document route.
     *
     * Parse the actor handle from the actor path.
     *
     * @params string $path Request path.
     * @params ?string $handle Output actor handle.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchActor(string $path, ?string &$handle): bool
    {
        if (preg_match('#/ap/actors/([^/]+)$#', $path, $m)) {
            $handle = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Match an actor featured collection route.
     *
     * Parse the actor handle from the featured path.
     *
     * @params string $path Request path.
     * @params ?string $handle Output actor handle.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchActorFeatured(string $path, ?string &$handle): bool
    {
        if (preg_match('#/ap/actors/([^/]+)/featured$#', $path, $m)) {
            $handle = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Match an object route.
     *
     * Parse the object identifier from the object path.
     *
     * @params string $path Request path.
     * @params ?string $objectId Output object identifier segment.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchObject(string $path, ?string &$objectId): bool
    {
        if (preg_match('#/ap/objects/([^/]+)$#', $path, $m)) {
            $objectId = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Match a media route.
     *
     * Parse the media identifier from the media path.
     *
     * @params string $path Request path.
     * @params ?string $mediaId Output media identifier.
     *
     * @return  bool  True when the path matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchMedia(string $path, ?string &$mediaId): bool
    {
        if (preg_match('#/ap/media/([^/]+)$#', $path, $m)) {
            $mediaId = rawurldecode($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Check whether the path ends with a suffix.
     *
     * Compare the normalized request path against a suffix.
     *
     * @params string $path Request path.
     * @params string $suffix Suffix to match.
     *
     * @return  bool  True when the path ends with the suffix.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function endsWithPath(string $path, string $suffix): bool
    {
        // Support sites installed in a subdirectory (e.g. /joomla/.well-known/...)
        return str_ends_with(rtrim($path, '/'), $suffix);
    }

    /**
     * Close the application response.
     *
     * Terminate the request when running in a web context.
     *
     * @params object $app Joomla application instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function closeApp(object $app): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $app->close();
        }
    }

    /**
     * Register external content providers.
     *
     * Load fediverse provider plugins and dispatch the registration event.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function registerContentProviders(): void
    {
        if ($this->contentProvidersRegistered) {
            return;
        }

        $this->contentProvidersRegistered = true;

        try {
            $registry = Factory::getContainer()->get(ContentProviderRegistry::class);
        } catch (\Throwable) {
            return;
        }

        $app = $this->getApplication();
        if (!is_object($app) || !method_exists($app, 'triggerEvent')) {
            return;
        }

        PluginHelper::importPlugin('fediverse');
        $app->triggerEvent('onFediverseRegisterContentProviders', [$registry]);
    }

    /**
     * Register external Fediverse comment renderers.
     *
     * Load fediverse plugins and dispatch the comment renderer registration event.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function registerCommentRenderers(): void
    {
        if ($this->commentRenderersRegistered) {
            return;
        }

        $this->commentRenderersRegistered = true;

        try {
            $registry = Factory::getContainer()->get(CommentRendererRegistry::class);
        } catch (\Throwable) {
            return;
        }

        $app = $this->getApplication();
        if (!is_object($app) || !method_exists($app, 'triggerEvent')) {
            return;
        }

        PluginHelper::importPlugin('fediverse');
        $app->triggerEvent('onFediverseRegisterCommentRenderers', [$registry]);
    }

    /**
     * Handle content save events in the administrator client.
     *
     * Publish article create/update activities when content plugins are not loaded.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?object $article Joomla article object (legacy signature).
     * @params ?bool $isNew Whether the article is new (legacy signature).
     * @params array $data Additional event data (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterSave(mixed $eventOrContext, ?object $article = null, ?bool $isNew = null, array $data = []): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        if ($eventOrContext instanceof AfterSaveEvent) {
            $context = $eventOrContext->getContext();
            $article = $eventOrContext->getItem();
            $isNew   = $eventOrContext->getIsNew();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if ($article === null) {
            return;
        }

        if (is_array($article)) {
            $article = (object) $article;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        /** @var PublishService $svc */
        $svc = Factory::getContainer()->get(PublishService::class);
        $isNewFlag = (bool) ($isNew ?? false);
        $state = null;
        if (isset($article->state)) {
            $state = (int) $article->state;
        } elseif (isset($article->published)) {
            $state = (int) $article->published;
        }
        if ($state !== null && $state <= 0) {
            if ($isNewFlag) {
                return;
            }

            $svc->onContentDeleted($context, $article);

            return;
        }

        $svc->onContentSaved($context, $article, $isNewFlag);
    }

    /**
     * Handle content delete events in the administrator client.
     *
     * Publish article delete activities when content plugins are not loaded.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?object $article Joomla article object (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterDelete(mixed $eventOrContext, ?object $article = null): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        if ($eventOrContext instanceof AfterDeleteEvent) {
            $context = $eventOrContext->getContext();
            $article = $eventOrContext->getItem();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if ($article === null) {
            return;
        }

        if (is_array($article)) {
            $article = (object) $article;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        /** @var PublishService $svc */
        $svc = Factory::getContainer()->get(PublishService::class);
        $svc->onContentDeleted($context, $article);
    }

    /**
     * Handle content state changes in the administrator client.
     *
     * Publish delete activities when articles are unpublished or trashed.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?array $pks Article ids (legacy signature).
     * @params ?int $value New state value (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentChangeState(mixed $eventOrContext, ?array $pks = null, ?int $value = null): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getContext')) {
            $context = (string) $eventOrContext->getContext();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getPks')) {
            $pks = $eventOrContext->getPks();
        }

        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getValue')) {
            $value = $eventOrContext->getValue();
        }

        $ids = is_array($pks) ? array_values($pks) : [];
        if ($ids === [] || $value === null) {
            return;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        if ((int) $value > 0) {
            return;
        }

        /** @var PublishService $svc */
        $svc = Factory::getContainer()->get(PublishService::class);

        foreach ($ids as $id) {
            $articleId = (int) $id;
            if ($articleId <= 0) {
                continue;
            }

            $article = (object) [
                'id' => $articleId,
                'state' => (int) $value,
            ];

            $svc->onContentDeleted($context, $article);
        }
    }

    /**
     * Check whether a context string is supported by providers.
     *
     * @params string $context Event context.
     *
     * @return  bool  True when the context is supported.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function supportsContext(string $context): bool
    {
        try {
            /** @var ContentProviderRegistry $registry */
            $registry = Factory::getContainer()->get(ContentProviderRegistry::class);
        } catch (\Throwable) {
            return false;
        }

        return $registry->getProviderForContext($context) !== null;
    }
}
