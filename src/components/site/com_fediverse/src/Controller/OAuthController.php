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
use NX\Component\Fediverse\Administrator\Exception\OAuthException;
use NX\Component\Fediverse\Administrator\Service\C2S\OAuthService;
use Throwable;

/**
 * OAuthController Class
 *
 * Handle O Auth requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class OAuthController extends BaseController
{
    /**
     * Handle the OAuth authorize endpoint.
     *
     * Return a placeholder response for authorization requests.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function authorise(): void
    {
        if (!$this->ensureMethod('GET')) {
            return;
        }

        $user = Factory::getApplication()->getIdentity();
        $userId = $user !== null ? (int) ($user->id ?? 0) : 0;

        try {
            /** @var OAuthService $svc */
            $svc = Factory::getContainer()->get(OAuthService::class);
            $params = $this->getRequestParams([
                'response_type',
                'client_id',
                'redirect_uri',
                'scope',
                'state',
            ]);

            $redirect = $svc->authorize($params, $userId);
            $this->sendRedirect($redirect);
        } catch (OAuthException $e) {
            $this->sendJson($this->buildErrorPayload($e), $e->getStatusCode());
        } catch (Throwable $e) {
            $payload = ['error' => 'server_error'];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }
            $this->sendJson($payload, 500);
        }
    }

    /**
     * Handle the OAuth token endpoint.
     *
     * Return a placeholder response for token requests.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function token(): void
    {
        if (!$this->ensureMethod('POST')) {
            return;
        }

        try {
            /** @var OAuthService $svc */
            $svc = Factory::getContainer()->get(OAuthService::class);
            $params = $this->getRequestParams([
                'grant_type',
                'code',
                'redirect_uri',
                'client_id',
                'client_secret',
                'refresh_token',
            ]);

            $result = $svc->token($params);
            $this->sendJson($result, 200);
        } catch (OAuthException $e) {
            $this->sendJson($this->buildErrorPayload($e), $e->getStatusCode());
        } catch (Throwable $e) {
            $payload = ['error' => 'server_error'];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }
            $this->sendJson($payload, 500);
        }
    }

    /**
     * Build an OAuth error response payload.
     *
     * @params OAuthException $e OAuth exception.
     *
     * @return  array<string,string>  Error payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildErrorPayload(OAuthException $e): array
    {
        $payload = ['error' => $e->getError()];
        if ($e->getMessage() !== '') {
            $payload['error_description'] = $e->getMessage();
        }

        return $payload;
    }

    /**
     * Read request parameters.
     *
     * @params string[] $keys Parameter names.
     *
     * @return  array<string,string>  Request params.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getRequestParams(array $keys): array
    {
        $params = [];
        foreach ($keys as $key) {
            $params[$key] = $this->input->getString($key, '');
        }

        return $params;
    }

    /**
     * Send a JSON response.
     *
     * Encode a payload and emit a JSON response with headers.
     *
     * @params array<string,mixed> $payload Response payload.
     * @params int $status HTTP status code.
     *
     * @return  void  None.
     * @throws  \JsonException  if JSON encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(array $payload, int $status): void
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo $json;

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }

    /**
     * Send a redirect response.
     *
     * @params string $location Redirect target.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendRedirect(string $location): void
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, 302);
        }

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }

    /**
     * Ensure the request method matches.
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

        if (!headers_sent()) {
            header('Allow: ' . $expected);
        }

        $this->sendJson(
            [
                'error' => 'invalid_request',
                'error_description' => 'Method Not Allowed',
            ],
            405
        );

        return false;
    }
}
