<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Automation;

use NX\Component\Fediverse\Administrator\Http\HttpClientInterface;
use NX\Component\Fediverse\Administrator\Model\WebhooksModel;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;

/**
 * WebhookAutomationService Class
 *
 * Deliver Fediverse automation events to subscribed webhook endpoints.
 *
 * @since  __DEPLOY_VERSION__
 */
final class WebhookAutomationService
{
    /**
     * Initialize the webhook automation service.
     *
     * @params WebhooksModel            $webhooksModel Webhook persistence model.
     * @params HttpClientInterface      $httpClient HTTP client for outbound POST requests.
     * @params BaseUrlProviderInterface $baseUrlProvider Canonical site URL provider.
     * @params LicenseService           $licenseService License gate service.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private WebhooksModel $webhooksModel,
        private HttpClientInterface $httpClient,
        private BaseUrlProviderInterface $baseUrlProvider,
        private LicenseService $licenseService,
    ) {
    }

    /**
     * Dispatch one automation event to every subscribed enabled webhook.
     *
     * @params string $event Event name.
     * @params array  $data Event payload data.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function dispatch(string $event, array $data): void
    {
        if (!$this->licenseService->isPro() || $this->licenseService->isExpired()) {
            return;
        }

        if (!WebhookEventCatalog::isSupported($event)) {
            return;
        }

        $webhooks = $this->webhooksModel->getEnabledForEvent($event);
        if ($webhooks === []) {
            return;
        }

        foreach ($webhooks as $webhook) {
            $payload = [
                'event' => $event,
                'occurred_at' => gmdate('c'),
                'source' => [
                    'component' => 'com_fediverse',
                    'site_url' => $this->baseUrlProvider->getBaseUrl(),
                ],
                'data' => $data,
            ];

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!is_string($body) || $body === '') {
                $this->webhooksModel->recordDeliveryResult((int) $webhook['id'], 0, 'Failed to encode webhook payload.');
                continue;
            }

            $request = [
                'url' => (string) $webhook['target_url'],
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Fediverse-Event' => $event,
                    'X-Fediverse-Webhook' => (string) $webhook['name'],
                    'X-Fediverse-Signature' => 'sha256=' . hash_hmac('sha256', $body, (string) $webhook['secret']),
                ],
                'body' => $body,
                'timeout' => 5,
            ];

            try {
                $response = self::normalizeResponse($this->httpClient->send($request), $request);
                $status   = (int) ($response['status'] ?? 0);
                $bodyText = trim((string) ($response['body'] ?? ''));

                if ($status >= 200 && $status < 300) {
                    $this->webhooksModel->recordDeliveryResult((int) $webhook['id'], $status, null);
                    continue;
                }

                $error = $bodyText !== '' ? $bodyText : ($status > 0 ? 'HTTP ' . $status : 'No response received.');
                $this->webhooksModel->recordDeliveryResult((int) $webhook['id'], $status, $error);
            } catch (\Throwable $e) {
                $this->webhooksModel->recordDeliveryResult((int) $webhook['id'], 0, $e->getMessage());
            }
        }
    }

    /**
     * Normalize an HTTP response into a consistent array shape.
     *
     * @params mixed $response HTTP response.
     * @params array $request Request descriptor.
     *
     * @return  array<string, mixed>  Normalized response.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeResponse(mixed $response, array $request): array
    {
        if (is_array($response) && isset($response['status'])) {
            return $response;
        }

        if (is_object($response)) {
            $status = null;
            $body   = null;

            if (isset($response->status)) {
                $status = (int) $response->status;
            } elseif (isset($response->code)) {
                $status = (int) $response->code;
            } elseif (method_exists($response, 'getStatusCode')) {
                $status = (int) $response->getStatusCode();
            }

            if (isset($response->body)) {
                $body = (string) $response->body;
            } elseif (method_exists($response, 'getBody')) {
                $body = (string) $response->getBody();
            }

            if ($status !== null) {
                return [
                    'status' => $status,
                    'body' => $body ?? '',
                    'request' => $request,
                ];
            }
        }

        return [
            'status' => 0,
            'body' => '',
            'request' => $request,
        ];
    }
}
