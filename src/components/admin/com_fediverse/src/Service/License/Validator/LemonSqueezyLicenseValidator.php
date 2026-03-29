<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\License\Validator;

use NX\Component\Fediverse\Administrator\Service\License\LicenseValidationResult;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;
use NX\Component\Fediverse\Administrator\Service\License\LicenseValidatorInterface;

/**
 * LemonSqueezyLicenseValidator
 *
 * Validates license keys issued by LemonSqueezy against their License Keys API.
 * Requires an outbound HTTPS call on each (uncached) validation.
 *
 * LemonSqueezy variant names are mapped to LicenseTier values via the
 * $variantMap constructor argument, e.g.:
 *
 *   ['personal' => LicenseTier::Personal, 'pro' => LicenseTier::Pro, ...]
 *
 * The match is case-insensitive and checks whether the variant name *contains*
 * the map key (so "Joomla Fedi Personal" matches "personal").
 *
 * @see https://docs.lemonsqueezy.com/api/license-keys#validate-a-license-key
 *
 * @since  __DEPLOY_VERSION__
 */
final class LemonSqueezyLicenseValidator implements LicenseValidatorInterface
{
    private const DEFAULT_API_BASE_URL = 'https://api.lemonsqueezy.com/v1/licenses';

    /** @var array<string, LicenseTier> */
    private readonly array $variantMap;
    private readonly string $apiBaseUrl;
    private readonly int $timeoutSecs;
    /** @var null|callable(string, array<string, scalar|null>): ?array<string, mixed> */
    private $requestHandler;

    /**
     * @param  array<string, LicenseTier>  $variantMap   Variant-name-fragment → tier.
     * @param  int                         $timeoutSecs  HTTP timeout for the API call.
     * @param  string                      $apiBaseUrl   LemonSqueezy License API base URL.
     * @param  null|callable(string, array<string, scalar|null>): ?array<string, mixed> $requestHandler
     *                                                  Optional transport override for tests.
     */
    public function __construct(
        array $variantMap = [],
        int $timeoutSecs = 5,
        string $apiBaseUrl = self::DEFAULT_API_BASE_URL,
        ?callable $requestHandler = null,
    ) {
        $this->variantMap = $variantMap !== [] ? $variantMap : self::defaultVariantMap();
        $this->timeoutSecs = $timeoutSecs;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->requestHandler = $requestHandler;
    }

    /**
     * Validate a LemonSqueezy license key.
     *
     * @param   string  $key  Raw license key string.
     *
     * @return  LicenseValidationResult  Validation result (Free on any failure).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function validate(string $key, ?string $instanceId = null, ?string $instanceName = null): LicenseValidationResult
    {
        $key = trim($key);
        if ($key === '') {
            return LicenseValidationResult::free();
        }

        if ($instanceId !== null && trim($instanceId) !== '') {
            $response = $this->request('/validate', [
                'license_key' => $key,
                'instance_id' => trim($instanceId),
            ]);
            $isValid = !empty($response['valid']);
        } else {
            $resolvedInstanceName = trim((string) $instanceName);
            if ($resolvedInstanceName === '') {
                $resolvedInstanceName = 'joomla-fediverse';
            }

            $response = $this->request('/activate', [
                'license_key'   => $key,
                'instance_name' => $resolvedInstanceName,
            ]);
            $isValid = !empty($response['activated']);
        }

        $response = $response ?? null;
        if ($response === null) {
            return LicenseValidationResult::free();
        }

        if (!$isValid) {
            return LicenseValidationResult::free();
        }

        $lk      = $response['license_key'] ?? [];
        $meta    = $response['meta']        ?? [];
        $status  = strtolower((string) ($lk['status'] ?? ''));
        $expired = \in_array($status, ['expired', 'disabled'], true);

        $expiry = null;
        if (!empty($lk['expires_at'])) {
            $expiry = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $lk['expires_at']) ?: null;
        }

        $variantName = strtolower((string) ($meta['variant_name'] ?? ''));
        $tier        = $this->resolveTier($variantName);
        $resolvedInstanceId = $this->resolveInstanceId($response, $instanceId);

        return new LicenseValidationResult(
            tier:       $tier,
            valid:      true,
            expired:    $expired,
            expiry:     $expiry,
            domain:     null,
            instanceId: $resolvedInstanceId,
        );
    }

    /**
     * Map a LemonSqueezy variant name to a LicenseTier.
     *
     * @param   string  $variantName  Lowercase variant name from the API.
     *
     * @return  LicenseTier  Resolved tier.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveTier(string $variantName): LicenseTier
    {
        foreach ($this->variantMap as $fragment => $tier) {
            if (str_contains($variantName, strtolower($fragment))) {
                return $tier;
            }
        }

        return $this->resolveTierAlias($variantName);
    }

    /**
     * Provide the default LemonSqueezy variant fragments for the supported paid tiers.
     *
     * @return  array<string, LicenseTier>  Variant-name-fragment → tier.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function defaultVariantMap(): array
    {
        return [
            'personal' => LicenseTier::Personal,
            'pro'      => LicenseTier::Pro,
            // Legacy tier names from older variants.
            'developer' => LicenseTier::Pro,
            'agency'    => LicenseTier::Pro,
        ];
    }

    /**
     * Resolve direct variant aliases when no fragment matched.
     *
     * @param   string  $variantName  Lowercase variant name.
     *
     * @return  LicenseTier  Resolved tier.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveTierAlias(string $variantName): LicenseTier
    {
        return match ($variantName) {
            'free'      => LicenseTier::Free,
            'personal'  => LicenseTier::Personal,
            'pro'       => LicenseTier::Pro,
            'developer' => LicenseTier::Pro,
            'agency'    => LicenseTier::Pro,
            default     => LicenseTier::Personal,
        };
    }

    /**
     * POST JSON to a URL and return decoded response, or null on failure.
     *
     * @param   string               $url      Target URL.
     * @param   array<string,mixed>  $payload  Request body.
     *
     * @return  array<string,mixed>|null  Decoded JSON response, or null on error.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function request(string $path, array $payload): ?array
    {
        if (is_callable($this->requestHandler)) {
            return ($this->requestHandler)($path, $payload);
        }

        $url = $this->apiBaseUrl . $path;
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content'       => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
                'timeout'       => $this->timeoutSecs,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }

        $data = json_decode($body, true);
        return \is_array($data) ? $data : null;
    }

    /**
     * Resolve the current LemonSqueezy instance id from the response.
     *
     * @param   array<string, mixed>  $response           API response payload.
     * @param   ?string               $existingInstanceId Stored instance id from local config.
     *
     * @return  ?string  Active instance id.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveInstanceId(array $response, ?string $existingInstanceId): ?string
    {
        $instance = $response['instance'] ?? null;

        if (is_array($instance)) {
            $id = trim((string) ($instance['id'] ?? $instance['identifier'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        $existingInstanceId = trim((string) $existingInstanceId);

        return $existingInstanceId !== '' ? $existingInstanceId : null;
    }
}
