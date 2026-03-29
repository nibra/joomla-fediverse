<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Security;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ModelInterface;
use NX\Component\Fediverse\Administrator\Http\HttpClientInterface;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;

/**
 * HttpSignaturService Class
 *
 * Provide Http Signatur services.
 *
 * @since  __DEPLOY_VERSION__
 */

final class HttpSignaturService implements HttpSignaturServiceInterface
{
    private const DELIVERY_TIMEOUT_SECONDS = 2;

    /** @var KeysModel */
    private ModelInterface $keysModel;

    /** @var ActorsModel */
    private ModelInterface $actorsModel;

    /**
     * Initialize the HTTP signature service.
     *
     * Build required models and store supporting services.
     *
     * @params MVCFactoryInterface $mvcFactory MVC factory.
     * @params KeyVaultService $keyVault Key vault service.
     * @params ?HttpClientInterface $httpClient HTTP client for outbound requests.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        MVCFactoryInterface $mvcFactory,
        private KeyVaultService $keyVault,
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->keysModel   = $mvcFactory->createModel('Keys', 'Administrator');
        $this->actorsModel = $mvcFactory->createModel('Actors', 'Administrator');
    }

    /**
     * Build a signed POST request.
     *
     * Prepare a signed ActivityPub delivery request for a remote inbox.
     *
     * @params string $targetInboxUrl Target inbox URL.
     * @params string $payload Serialized activity payload.
     * @params ?string $localActorKeyId Key identifier for signing.
     *
     * @return  mixed  Response data or null when signing fails.
     * @throws  \RuntimeException  if key decryption fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function signedPostRequest(string $targetInboxUrl, string $payload, ?string $localActorKeyId): mixed
    {
        $localActorKeyId = is_string($localActorKeyId) ? trim($localActorKeyId) : '';
        if ($localActorKeyId === '') {
            return null;
        }

        $requestedKeyId = $localActorKeyId;
        $localActorKeyId = $this->maybeUpgradeLegacyKeyId($localActorKeyId);

        $keyMeta = $this->keysModel->getByKeyIdUri($localActorKeyId);
        if (($keyMeta === null || !is_string($keyMeta->private_key_enc ?? null)) && $localActorKeyId !== $requestedKeyId) {
            $localActorKeyId = $requestedKeyId;
            $keyMeta = $this->keysModel->getByKeyIdUri($localActorKeyId);
        }

        if ($keyMeta === null || !is_string($keyMeta->private_key_enc ?? null)) {
            return null;
        }

        $privateKeyPem = $this->keyVault->decryptPrivateKey((string) $keyMeta->private_key_enc);

        $urlParts = parse_url($targetInboxUrl);
        if (!is_array($urlParts) || !isset($urlParts['host'])) {
            return null;
        }

        $path = $urlParts['path'] ?? '/';
        if (isset($urlParts['query']) && $urlParts['query'] !== '') {
            $path .= '?' . $urlParts['query'];
        }

        $host = (string) $urlParts['host'];
        if (isset($urlParts['port'])) {
            $port        = (int) $urlParts['port'];
            $scheme      = strtolower((string) ($urlParts['scheme'] ?? ''));
            $defaultPort = $scheme === 'https' ? 443 : 80;
            if ($port !== $defaultPort) {
                $host .= ':' . $port;
            }
        }

        $date   = gmdate('D, d M Y H:i:s \\G\\M\\T');
        $digest = self::computeDigestHeader($payload);
        $contentLength = (string) strlen($payload);

        $contentType = 'application/activity+json';
        $headers = [
            'Host'           => $host,
            'Date'           => $date,
            'Digest'         => $digest,
            'Content-Type'   => $contentType,
            'Content-Length' => $contentLength,
        ];

        $signedHeaders = ['(request-target)', 'host', 'date', 'digest'];
        $signingString = self::buildSigningString('post', $path, $headers, $signedHeaders);
        if ($signingString === null) {
            return null;
        }

        $signature = self::signString($signingString, $privateKeyPem);
        if ($signature === null) {
            return null;
        }

        $signatureKeyId = self::normalizeKeyIdUri($localActorKeyId);
        $headers['Signature'] = self::buildSignatureHeader($signatureKeyId, $signedHeaders, $signature);

        $request = [
            'method'  => 'POST',
            'url'     => $targetInboxUrl,
            'headers' => $headers,
            'body'    => $payload,
            'timeout' => self::DELIVERY_TIMEOUT_SECONDS,
        ];

        if ($this->httpClient === null) {
            return ['status' => 0, 'body' => '', 'request' => $request];
        }

        $response = $this->httpClient->send($request);

        return self::normalizeResponse($response, $request);
    }

    /**
     * Verify an inbound HTTP signature.
     *
     * Validate the signature headers and referenced actor public key.
     *
     * @params RequestContext $ctx Request context to verify.
     *
     * @return  bool  True when the signature is valid.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function verifyInbound(RequestContext $ctx): bool
    {
        $signatureHeader = $ctx->getHeader('Signature');
        if ($signatureHeader === null) {
            return false;
        }

        $params       = self::parseSignatureHeader($signatureHeader);
        $keyId        = $params['keyId'] ?? null;
        $signatureB64 = $params['signature'] ?? null;
        if (!is_string($keyId) || $keyId === '' || !is_string($signatureB64) || $signatureB64 === '') {
            return false;
        }

        $algorithm = strtolower((string) ($params['algorithm'] ?? 'rsa-sha256'));
        if (!in_array($algorithm, ['rsa-sha256', 'hs2019'], true)) {
            return false;
        }

        $headersParam  = (string) ($params['headers'] ?? 'date');
        $signedHeaders = preg_split('/\s+/', trim($headersParam)) ?: [];
        $signedHeaders = array_values(
            array_filter(array_map('strtolower', $signedHeaders), static fn($h) => $h !== '')
        );

        if ($signedHeaders === []) {
            return false;
        }

        if (in_array('digest', $signedHeaders, true)) {
            $digestHeader = $ctx->getHeader('Digest');
            if ($digestHeader === null) {
                return false;
            }

            $digestHeader = trim($digestHeader);
            $expectedHash = base64_encode(hash('sha256', $ctx->getRawBody(), true));
            [$algo, $value] = array_map('trim', explode('=', $digestHeader, 2) + [null, null]);
            if ($algo === null || $value === null || strtolower($algo) !== 'sha-256') {
                return false;
            }

            if (!hash_equals($expectedHash, $value)) {
                return false;
            }
        }

        $signingString = self::buildSigningString(
            strtolower($ctx->getMethod()),
            $ctx->getPath(),
            $ctx->getHeaders(),
            $signedHeaders,
        );
        if ($signingString === null) {
            return false;
        }

        $actorUri = self::actorUriFromKeyId($keyId);
        if ($actorUri === '') {
            return false;
        }

        $actor        = $this->actorsModel->getRemoteByUri($actorUri);
        $publicKeyPem = $actor !== null ? $actor->publicKeyPem : null;

        if ($publicKeyPem === null || trim($publicKeyPem) === '') {
            $publicKeyPem = $this->fetchPublicKeyPemFromRemote($keyId, $actorUri);
            if ($publicKeyPem === null) {
                return false;
            }
        }

        $signature = base64_decode($signatureB64, true);
        if ($signature === false) {
            return false;
        }

        $verify = openssl_verify($signingString, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $verify === 1;
    }

    /**
     * Fetch a public key PEM from a remote actor document.
     *
     * Attempt to retrieve the public key by fetching the key id URL and,
     * if different, the actor URI. Returns null when no valid PEM is found.
     * The fetched key is never persisted — it is used for one-time verification.
     *
     * @params string $keyId  Key identifier URL.
     * @params string $actorUri Actor URI derived from the key id.
     *
     * @return  ?string  PEM-encoded public key or null when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function fetchPublicKeyPemFromRemote(string $keyId, string $actorUri): ?string
    {
        if ($this->httpClient === null) {
            return null;
        }

        $pem = $this->fetchPublicKeyPemFromUrl($keyId);
        if ($pem !== null) {
            return $pem;
        }

        if ($actorUri !== $keyId) {
            return $this->fetchPublicKeyPemFromUrl($actorUri);
        }

        return null;
    }

    /**
     * Fetch and extract a public key PEM from a single URL.
     *
     * Make an HTTP GET request with an ActivityPub Accept header and parse
     * the response for a publicKey.publicKeyPem field.
     *
     * @params string $url URL to fetch.
     *
     * @return  ?string  PEM-encoded public key or null when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function fetchPublicKeyPemFromUrl(string $url): ?string
    {
        try {
            $response = $this->httpClient->send([
                'method'  => 'GET',
                'url'     => $url,
                'headers' => ['Accept' => 'application/activity+json'],
                'timeout' => self::DELIVERY_TIMEOUT_SECONDS,
            ]);
        } catch (\Throwable) {
            return null;
        }

        $normalized = self::normalizeResponse($response, []);
        if (!is_array($normalized) || ($normalized['status'] ?? 0) !== 200) {
            return null;
        }

        $body = $normalized['body'] ?? '';
        if (!is_string($body) || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        $publicKey = $data['publicKey'] ?? null;
        if (is_array($publicKey) && isset($publicKey['publicKeyPem']) && is_string($publicKey['publicKeyPem'])) {
            $pem = trim($publicKey['publicKeyPem']);

            return $pem !== '' ? $pem : null;
        }

        return null;
    }

    /**
     * Build a Digest header for a payload.
     *
     * Compute the SHA-256 digest header value for a payload.
     *
     * @params string $payload Payload to hash.
     *
     * @return  string  Digest header value.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function computeDigestHeader(string $payload): string
    {
        $hash = hash('sha256', $payload, true);

        return 'sha-256=' . base64_encode($hash);
    }

    /**
     * Upgrade legacy key ids to a unique key id URI.
     *
     * Preserve the stored key id for outbound signatures.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function maybeUpgradeLegacyKeyId(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (!preg_match('~^(.*)#main-key$~', $keyId, $matches)) {
            return $keyId;
        }

        $actorUri = trim((string) ($matches[1] ?? ''));
        if ($actorUri === '') {
            return $keyId;
        }

        $upgradedKeyId = $this->buildUniqueKeyIdUri($actorUri);

        try {
            $this->keysModel->updateActiveKeyIdUriByKeyId($keyId, $upgradedKeyId);
            return $upgradedKeyId;
        } catch (\Throwable) {
            return $keyId;
        }
    }

    /**
     * Build a unique key id URI for an actor.
     *
     * @params string $actorUri Actor URI.
     *
     * @return  string  Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildUniqueKeyIdUri(string $actorUri): string
    {
        $actorUri = trim($actorUri);
        $suffix   = 'fallback';

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable) {
            $suffix = substr(str_replace('.', '', uniqid('', true)), 0, 12);
        }

        return $actorUri . '#main-key-' . gmdate('YmdHis') . '-' . $suffix;
    }

    /**
     * Build the signing string.
     *
     * Compose the signature base string for HTTP signatures.
     *
     * @params string $method HTTP method.
     * @params string $path Request path.
     * @params array<string,string> $headers Request headers.
     * @params string[] $signedHeaders Header names to sign.
     *
     * @return  ?string  Signing string or null when headers are missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildSigningString(
        string $method,
        string $path,
        array $headers,
        array $signedHeaders,
    ): ?string {
        $method = strtolower($method);
        $path   = $path !== '' ? $path : '/';

        $lines = [];
        foreach ($signedHeaders as $headerName) {
            $name = strtolower($headerName);
            if ($name === '(request-target)') {
                $lines[] = '(request-target): ' . $method . ' ' . $path;
                continue;
            }

            $value = self::findHeaderValue($headers, $name);
            if ($value === null) {
                return null;
            }

            $lines[] = $name . ': ' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * Find a header value by name.
     *
     * Perform a case-insensitive lookup in the header map.
     *
     * @params array<string,string> $headers Request headers.
     * @params string $name Header name.
     *
     * @return  ?string  Header value or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function findHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return is_string($v) ? trim($v) : null;
            }
        }

        return null;
    }

    /**
     * Sign a string with a private key.
     *
     * Create a base64-encoded RSA signature for the signing string.
     *
     * @params string $signingString Signing string.
     * @params string $privateKeyPem Private key PEM.
     *
     * @return  ?string  Base64 signature or null on failure.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function signString(string $signingString, string $privateKeyPem): ?string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            return null;
        }

        $sig = '';
        $ok  = openssl_sign($signingString, $sig, $key, OPENSSL_ALGO_SHA256);
        if ($ok !== true) {
            return null;
        }

        return base64_encode($sig);
    }

    /**
     * Build a Signature header value.
     *
     * Format signature parameters for an HTTP Signature header.
     *
     * @params string $keyId Key identifier.
     * @params string[] $signedHeaders Header names that were signed.
     * @params string $signature Base64 signature.
     *
     * @return  string  Signature header value.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildSignatureHeader(string $keyId, array $signedHeaders, string $signature): string
    {
        $headers = implode(' ', $signedHeaders);

        return sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
            $headers,
            $signature
        );
    }

    /**
     * Parse a Signature header.
     *
     * Extract signature parameters from the header value.
     *
     * @params string $header Signature header value.
     *
     * @return  array<string,string>  Parsed signature parameters.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function parseSignatureHeader(string $header): array
    {
        $params = [];
        if (preg_match_all('/([a-zA-Z]+)=\"([^\"]*)\"/', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = $match[2];
            }
        }

        return $params;
    }

    /**
     * Extract the actor URI from a key id.
     *
     * Remove the fragment portion from the key identifier.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Actor URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function actorUriFromKeyId(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (str_contains($keyId, '#')) {
            $parts = explode('#', $keyId, 2);

            return trim($parts[0]);
        }

        if (preg_match('~^(.*)/main-key[^/]*$~', $keyId, $matches)) {
            return trim($matches[1]);
        }

        return $keyId;
    }

    /**
     * Normalize a key id URI for outbound use.
     *
     * Convert legacy fragment key ids to path-based key ids.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Normalized key id.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeKeyIdUri(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)#(main-key.*)$~', $keyId, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return $keyId;
    }

    /**
     * Normalize an HTTP client response.
     *
     * Convert a response into a consistent array shape.
     *
     * @params mixed $response HTTP client response.
     * @params array $request Request descriptor.
     *
     * @return  mixed  Normalized response structure.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeResponse(mixed $response, array $request): mixed
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
                    'status'  => $status,
                    'body'    => $body ?? '',
                    'request' => $request,
                ];
            }
        }

        return ['status' => 0, 'body' => '', 'request' => $request];
    }
}
