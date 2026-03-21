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
use NX\Component\Fediverse\Administrator\Adapter\ActivityPubAdapterInterface;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Model\InboxModel;
use NX\Component\Fediverse\Administrator\Service\Federation\InboxIngestService;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;
use Throwable;

/**
 * InboxController Class
 *
 * Handle Inbox requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class InboxController extends BaseController
{
    /**
     * Handle an inbox POST request.
     *
     * Ingest an inbound activity for a shared or actor-specific inbox.
     *
     * @params ?string $handle Actor handle or null for shared inbox.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function post(?string $handle = null): void
    {
        $localHandle = is_string($handle) ? trim($handle) : '';
        $localHandle = $localHandle !== '' ? $localHandle : null;

        try {
            $container  = Factory::getContainer();
            $db         = $container->get(DatabaseDriver::class);
            $inboxModel = new InboxModel($db);
            $adapter    = $container->get(ActivityPubAdapterInterface::class);
            $sig        = $container->get(HttpSignaturServiceInterface::class);
            $ctx        = $container->get(RequestContext::class);

            $svc = new InboxIngestService($adapter, $sig, $inboxModel);
            $id  = $svc->ingest($ctx, $localHandle);

            $this->sendJson(['status' => 'accepted', 'id' => $id], 202);
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
