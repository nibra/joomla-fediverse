<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Adapter;

use Joomla\Http\HttpFactory;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * RikudouAdapter Class
 *
 * Provide the ActivityPub adapter.
 *
 * @since  __DEPLOY_VERSION__
 */
final class RikudouAdapter implements ActivityPubAdapterInterface
{
    /**
     * Parse an ActivityPub payload.
     *
     * Convert ActivityStreams JSON into an activity envelope.
     *
     * @params string $json ActivityStreams JSON payload.
     *
     * @return  ActivityEnvelope  Parsed activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function parseActivity(string $json): ActivityEnvelope
    {
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid ActivityPub payload');
        }

        return ActivityEnvelope::fromJson($payload);
    }

    /**
     * Serialize an activity envelope.
     *
     * Convert a domain activity envelope into ActivityStreams JSON.
     *
     * @params ActivityEnvelope $activity Activity envelope.
     *
     * @return  string  Serialized ActivityStreams JSON.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function serializeActivity(ActivityEnvelope $activity): string
    {
        return $activity->toJsonString();
    }

    /**
     * Fetch a remote actor.
     *
     * Retrieve and parse a remote actor resource.
     *
     * @params string $actorUrl Actor URL.
     *
     * @return  Actor  Remote actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchActor(string $actorUrl): Actor
    {
        $actorUrl = trim($actorUrl);
        if ($actorUrl === '') {
            throw new \RuntimeException('Actor URL is empty');
        }

        $fallbackHandle = $this->extractHandleFromUrl($actorUrl);

        try {
            $client   = (new HttpFactory())->getHttp();
            $headers  = [
                'Accept' => 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
            ];
            $response = $client->get($actorUrl, $headers);
            $status   = (int) ($response->code ?? 0);
            $body     = (string) ($response->body ?? '');

            if ($status < 200 || $status >= 300 || $body === '') {
                throw new \RuntimeException('Remote actor fetch failed');
            }

            $payload = json_decode($body, true);
            if (!is_array($payload)) {
                throw new \RuntimeException('Remote actor response invalid');
            }

            $id                = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : $actorUrl;
            $preferredUsername = isset($payload['preferredUsername']) && is_string($payload['preferredUsername'])
                ? $payload['preferredUsername']
                : $fallbackHandle;
            $handle            = $preferredUsername !== '' ? $preferredUsername : $fallbackHandle;
            $handle            = $this->normalizeRemoteHandle($handle, $id);

            $inbox       = isset($payload['inbox']) && is_string($payload['inbox']) ? $payload['inbox'] : '';
            $outbox      = isset($payload['outbox']) && is_string($payload['outbox']) ? $payload['outbox'] : '';
            $sharedInbox = null;
            if (isset($payload['endpoints']) && is_array($payload['endpoints'])) {
                $sharedInbox = isset($payload['endpoints']['sharedInbox']) && is_string(
                    $payload['endpoints']['sharedInbox']
                )
                    ? $payload['endpoints']['sharedInbox']
                    : null;
            }

            $publicKeyPem = null;
            if (isset($payload['publicKey']) && is_array($payload['publicKey'])) {
                $publicKeyPem = isset($payload['publicKey']['publicKeyPem']) && is_string(
                    $payload['publicKey']['publicKeyPem']
                )
                    ? $payload['publicKey']['publicKeyPem']
                    : null;
            }

            if ($inbox === '') {
                $inbox = rtrim($actorUrl, '/') . '/inbox';
            }
            if ($outbox === '') {
                $outbox = rtrim($actorUrl, '/') . '/outbox';
            }

            return Actor::newRemote(
                handle: $handle,
                preferredUsername: $preferredUsername,
                uri: $id,
                inboxUrl: $inbox,
                outboxUrl: $outbox,
                sharedInboxUrl: $sharedInbox,
                publicKeyPem: $publicKeyPem
            );
        } catch (\Throwable) {
            $handle = $this->normalizeRemoteHandle($fallbackHandle, $actorUrl);

            return Actor::newRemote(
                handle: $handle !== '' ? $handle : $fallbackHandle,
                preferredUsername: $fallbackHandle,
                uri: $actorUrl,
                inboxUrl: rtrim($actorUrl, '/') . '/inbox',
                outboxUrl: rtrim($actorUrl, '/') . '/outbox',
            );
        }
    }

    /**
     * Normalize a remote handle to include the domain.
     *
     * Append the actor URL host when the handle lacks a domain suffix.
     *
     * @params string $handle Base handle.
     * @params string $actorUrl Actor URL for domain extraction.
     *
     * @return  string  Normalized handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeRemoteHandle(string $handle, string $actorUrl): string
    {
        $handle = trim($handle);
        if ($handle === '') {
            return '';
        }

        $host = parse_url($actorUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && !str_contains($handle, '@')) {
            return $handle . '@' . $host;
        }

        return $handle;
    }

    /**
     * Extract a handle from an actor URL.
     *
     * Use the last path segment as a fallback handle.
     *
     * @params string $actorUrl Actor URL.
     *
     * @return  string  Extracted handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractHandleFromUrl(string $actorUrl): string
    {
        $path   = parse_url($actorUrl, PHP_URL_PATH);
        $handle = $path ? basename($path) : $actorUrl;

        return trim($handle) !== '' ? $handle : $actorUrl;
    }
}
