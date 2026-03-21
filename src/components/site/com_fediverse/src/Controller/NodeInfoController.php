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
use NX\Component\Fediverse\Administrator\Service\Announce\NodeInfoService;

/**
 * NodeInfoController Class
 *
 * Handle Node Info requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class NodeInfoController extends BaseController
{
    /**
     * Handle the NodeInfo well-known request.
     *
     * Return the NodeInfo discovery payload.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function wellKnown(): void
    {
        /** @var NodeInfoService $svc */
        $svc = Factory::getContainer()->get(NodeInfoService::class);

        $result = $svc->buildWellKnown();

        $this->sendJson($result, 200);
    }

    /**
     * Handle the NodeInfo document request.
     *
     * Return the NodeInfo document.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function document(): void
    {
        /** @var NodeInfoService $svc */
        $svc = Factory::getContainer()->get(NodeInfoService::class);

        $result = $svc->buildDocument();

        $this->sendJson($result, 200);
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
}
