<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Http;

use Joomla\CMS\Version;
use Joomla\Http\HttpFactory;
use Throwable;

/**
 * JoomlaHttpClient Class
 *
 * Provide Joomla Http Client HTTP utilities.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaHttpClient implements HttpClientInterface
{
    /**
     * Send an HTTP request.
     *
     * Dispatch a request described by an array and return the client response.
     *
     * @params mixed $req Request descriptor.
     *
     * @return  mixed  Response payload or error structure.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function send($req)
    {
        if (!is_array($req)) {
            return ['status' => 0, 'body' => 'Invalid request'];
        }

        $url = $req['url'] ?? null;
        if (!is_string($url) || trim($url) === '') {
            return ['status' => 0, 'body' => 'Missing url'];
        }

        $method  = strtoupper(trim((string) ($req['method'] ?? 'GET')));
        $headers = is_array($req['headers'] ?? null) ? $req['headers'] : [];
        $body    = $req['body'] ?? null;
        $timeout = array_key_exists('timeout', $req) ? (int) $req['timeout'] : null;

        $allowed = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'PATCH'];
        if (!in_array($method, $allowed, true)) {
            return ['status' => 0, 'body' => 'Unsupported method'];
        }

        try {
            $client = $this->createHttpClient();

            return match ($method) {
                'GET'     => $client->get($url, $headers, $timeout),
                'POST'    => $client->post($url, $body, $headers, $timeout),
                'PUT'     => $client->put($url, $body, $headers, $timeout),
                'DELETE'  => $client->delete($url, $headers, $timeout, $body),
                'HEAD'    => $client->head($url, $headers, $timeout),
                'OPTIONS' => $client->options($url, $headers, $timeout),
                'TRACE'   => $client->trace($url, $headers, $timeout),
                'PATCH'   => $client->patch($url, $body, $headers, $timeout),
            };
        } catch (Throwable $e) {
            return ['status' => 0, 'body' => $e->getMessage()];
        }
    }

    /**
     * Create a Joomla HTTP client.
     *
     * Build a client configured with a Joomla user agent.
     *
     * @return  object  HTTP client instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function createHttpClient(): object
    {
        $options = [];

        if (class_exists(Version::class)) {
            $version              = new Version();
            $options['userAgent'] = $version->getUserAgent('Joomla', true, false);
        }

        return (new HttpFactory())->getHttp($options);
    }
}
