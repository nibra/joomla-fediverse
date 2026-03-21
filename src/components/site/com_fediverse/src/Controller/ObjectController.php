<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;
use Throwable;

/**
 * ObjectController Class
 *
 * Handle ActivityPub object requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ObjectController extends BaseController
{
    /**
     * Handle an object GET request.
     *
     * Resolve the requested object and return an ActivityPub object payload.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function get(string $objectId): void
    {
        try {
            /** @var ContentProviderRegistry $registry */
            $registry = Factory::getContainer()->get(ContentProviderRegistry::class);
            $provider = $registry->getProviderForObjectId($objectId);
            if ($provider === null) {
                $this->sendJson(['error' => 'Object not found'], 404, 'application/json');

                return;
            }

            $itemId = $provider->parseObjectId($objectId);
            if ($itemId === null) {
                $this->sendJson(['error' => 'Object not found'], 404, 'application/json');

                return;
            }

            $item = $provider->fetchById($itemId);
            if ($item === null) {
                $this->sendJson(['error' => 'Object not found'], 404, 'application/json');

                return;
            }

            $userId = $provider->resolveUserId($item, '');
            if ($userId <= 0) {
                $this->sendJson(['error' => 'Object not available'], 404, 'application/json');

                return;
            }

            /** @var ActorResolverServiceInterface $resolver */
            $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);
            $actor = $resolver->ensureLocalActorForUserId($userId);

            $baseUrl = (new JoomlaBaseUrlProvider())->getBaseUrl();

            $object = $provider->toObject($actor, $item, $baseUrl);
            $payload = ['@context' => 'https://www.w3.org/ns/activitystreams'] + $object;

            $this->sendJson($payload, 200, $this->negotiateContentType());
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];
            if (defined('JDEBUG') && JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Negotiate the object response content type.
     *
     * Select the appropriate content type based on the Accept header.
     *
     * @return  string  Selected content type.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function negotiateContentType(): string
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/ld+json')) {
            return 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        }

        return 'application/activity+json';
    }

    /**
     * Send a JSON response.
     *
     * Encode a payload and emit a JSON response with headers.
     *
     * @params array<string,mixed> $payload Response payload.
     * @params int $status HTTP status code.
     * @params string $contentType Response content type.
     *
     * @return  void  None.
     * @throws  \JsonException  if JSON encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(array $payload, int $status, string $contentType): void
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType . '; charset=utf-8');
            header('Vary: Accept');
        }

        echo $json;

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }
}
