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
 *   ['personal' => LicenseTier::Personal, 'developer' => LicenseTier::Developer, ...]
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
    private const VALIDATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/validate';

    /**
     * @param  array<string, LicenseTier>  $variantMap   Variant-name-fragment → tier.
     * @param  string                      $instanceId   Stable identifier for this site
     *                                                   (e.g. SHA-1 of the site URL).
     * @param  int                         $timeoutSecs  HTTP timeout for the API call.
     */
    public function __construct(
        private readonly array  $variantMap   = [],
        private readonly string $instanceId   = '',
        private readonly int    $timeoutSecs  = 5,
    ) {}

    /**
     * Validate a LemonSqueezy license key.
     *
     * @param   string  $key  Raw license key string.
     *
     * @return  LicenseValidationResult  Validation result (Free on any failure).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function validate(string $key): LicenseValidationResult
    {
        $key = trim($key);
        if ($key === '') {
            return LicenseValidationResult::free();
        }

        $payload = ['license_key' => $key];
        if ($this->instanceId !== '') {
            $payload['instance_id'] = $this->instanceId;
        }

        $response = $this->post(self::VALIDATE_URL, $payload);
        if ($response === null) {
            return LicenseValidationResult::free();
        }

        // LemonSqueezy returns { "valid": bool, "license_key": {...}, "meta": {...} }
        if (empty($response['valid'])) {
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

        return new LicenseValidationResult(
            tier:    $tier,
            valid:   true,
            expired: $expired,
            expiry:  $expiry,
            domain:  null, // LemonSqueezy keys are not domain-scoped by default
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

        // Fallback: try direct enum match on the full variant name
        return LicenseTier::tryFrom($variantName) ?? LicenseTier::Personal;
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
    private function post(string $url, array $payload): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => json_encode($payload),
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
}
