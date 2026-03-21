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
use Joomla\Database\DatabaseDriver;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Exception\OAuthException;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\MediaModel;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\C2S\OAuthService;
use NX\Component\Fediverse\Administrator\Service\Media\MediaStorageService;
use RuntimeException;
use Throwable;

/**
 * MediaController Class
 *
 * Handle Media requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class MediaController extends BaseController
{
    /**
     * Handle a media GET request.
     *
     * Return a media ActivityPub object for a stored upload.
     *
     * @params string $id Media identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function get(string $id): void
    {
        $id = trim($id);
        if ($id === '' || !ctype_digit($id)) {
            $this->sendJson(['error' => 'Invalid media id'], 400);

            return;
        }

        try {
            $container = Factory::getContainer();
            $db        = $container->get(DatabaseDriver::class);
            $storage   = $container->get(MediaStorageService::class);

            $mediaModel = new MediaModel($db);
            $row        = $mediaModel->getById((int) $id);

            if ($row === null) {
                $this->sendJson(['error' => 'Media not found'], 404);

                return;
            }

            $relativePath = (string) ($row->relative_path ?? '');
            if ($relativePath === '' || !$storage->fileExists($relativePath)) {
                $this->sendJson(['error' => 'Media not found'], 404);

                return;
            }

            $actor = null;
            if (isset($row->local_actor_id)) {
                $actors = new ActorsModel($db);
                $actor  = $actors->getById((int) $row->local_actor_id);
            }

            $payload = $this->buildMediaDocument((int) $id, $row, $storage, $actor);
            $this->sendJson($payload, 200, 'application/activity+json');
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500);
        }
    }

    /**
     * Handle a media POST request.
     *
     * Accept a file upload and return a media ActivityPub object.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function post(): void
    {
        try {
            $container = Factory::getContainer();
            $db        = $container->get(DatabaseDriver::class);
            $ctx       = $container->get(RequestContext::class);
            $oauth     = $container->get(OAuthService::class);
            $resolver  = $container->get(ActorResolverServiceInterface::class);
            $storage   = $container->get(MediaStorageService::class);

            $token = $this->extractBearerToken($ctx->getHeader('Authorization'));
            $user  = $oauth->requireUserForToken($token, 'write');

            $actor = $resolver->ensureLocalActorForUserId((int) ($user->id ?? 0));
            if ($actor->id === null) {
                $this->sendJson(['error' => 'Actor not available'], 500);

                return;
            }

            if (!$actor->isEnabled) {
                $this->sendJson(['error' => 'Actor disabled'], 403);

                return;
            }

            $file = $this->resolveUpload();
            if ($file === null) {
                $this->sendJson(['error' => 'Missing upload'], 400);

                return;
            }

            $upload = $storage->storeUploadedFile($file);

            $mediaModel = new MediaModel($db);
            $mediaId = $mediaModel->insertMedia(
                (int) $actor->id,
                (int) ($user->id ?? 0),
                $upload['relative_path'],
                $upload['filename'],
                $upload['original_name'],
                $upload['mime_type'],
                (int) $upload['size']
            );

            $row = (object) [
                'relative_path' => $upload['relative_path'],
                'filename'      => $upload['filename'],
                'original_name' => $upload['original_name'],
                'mime_type'     => $upload['mime_type'],
                'size'          => $upload['size'],
                'local_actor_id' => $actor->id,
            ];

            $payload = $this->buildMediaDocument($mediaId, $row, $storage, $actor);
            $this->sendJson($payload, 201, 'application/activity+json');
        } catch (OAuthException $e) {
            $payload = ['error' => $e->getError()];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, $e->getStatusCode());
        } catch (RuntimeException $e) {
            $payload = ['error' => 'invalid_request'];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 400);
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];
            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500);
        }
    }

    /**
     * Build a media ActivityPub document.
     *
     * @params int $id Media identifier.
     * @params object $row Media row data.
     * @params MediaStorageService $storage Storage service.
     * @params ?Actor $actor Local actor (optional).
     *
     * @return  array<string,mixed>  Media document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildMediaDocument(int $id, object $row, MediaStorageService $storage, ?Actor $actor): array
    {
        $relativePath = (string) ($row->relative_path ?? '');
        $mimeType     = (string) ($row->mime_type ?? 'application/octet-stream');
        $type         = str_starts_with($mimeType, 'image/') ? 'Image' : 'Document';

        $payload = [
            '@context'  => 'https://www.w3.org/ns/activitystreams',
            'id'        => $storage->getPublicUrl('/ap/media/' . $id),
            'type'      => $type,
            'mediaType' => $mimeType,
            'url'       => $storage->getPublicUrl($relativePath),
        ];

        $name = trim((string) ($row->original_name ?? ''));
        if ($name !== '') {
            $payload['name'] = $name;
        }

        if ($actor !== null && $actor->uri !== null && $actor->uri !== '') {
            $payload['attributedTo'] = $actor->uri;
        }

        return $payload;
    }

    /**
     * Resolve the upload file data.
     *
     * @return  ?array  Uploaded file data or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveUpload(): ?array
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return null;
        }

        return $_FILES['file'];
    }

    /**
     * Extract a Bearer token from an Authorization header.
     *
     * @params ?string $header Authorization header value.
     *
     * @return  string  Access token or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractBearerToken(?string $header): string
    {
        $header = $header !== null ? trim($header) : '';
        if ($header === '') {
            return '';
        }

        if (preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return '';
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
    private function sendJson(array $payload, int $status, string $contentType = 'application/json'): void
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
