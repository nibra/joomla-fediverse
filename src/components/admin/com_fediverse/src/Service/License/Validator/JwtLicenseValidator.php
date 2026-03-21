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
 * JwtLicenseValidator
 *
 * Validates RS256-signed JWT license keys using the embedded public key.
 * Works entirely offline — no external HTTP call required.
 *
 * Keys are minted with scripts/generate-license-key.php using the matching
 * private key (kept secret by the seller).
 *
 * JWT payload fields:
 *   - `dom`  — licensed domain (e.g. "example.com")
 *   - `tier` — "free" | "personal" | "developer" | "agency"
 *   - `exp`  — Unix expiry timestamp
 *   - `iat`  — Unix issued-at timestamp
 *
 * @since  __DEPLOY_VERSION__
 */
final class JwtLicenseValidator implements LicenseValidatorInterface
{
    /**
     * RSA-2048 public key used to verify license signatures.
     * The matching private key is kept by the seller and never distributed.
     */
    private const PUBLIC_KEY = <<<PEM
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwAQGI12O7YgURDpH2OvP
vXIKbhGB67sipZANMn5EycbAOmFzZ5n4JRQ2t4bU5gWXeoRp/NyDc2pSu30uFzC+
Shu4sa6Llwgl6U3phiWM4ozkYY/5QOIU5dVQi/33q49KDKNEuisKK32oa21ysE5U
/CjltHdRjXKym8f8rgFEFFD6Zs4U6lwWYcr25PuKG88ZHLGOwh7QaVhe5TvqAETG
O5qmbf8drvsY0yZDxwn+QArA88OCcdeVY2bkSUI30zojkzmu5HRRdauJGLZDIGql
NRJO+2KOUmuBEdmV8H7rEEfuznvfampTyZuNChbjTKJWxbtYlCwrpQQ8BjFKiKPU
9wIDAQAB
-----END PUBLIC KEY-----
PEM;

    /**
     * Validate a JWT license key.
     *
     * @param   string  $key  Raw JWT string.
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

        $parts = explode('.', $key);
        if (\count($parts) !== 3) {
            return LicenseValidationResult::free();
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $message   = $headerB64 . '.' . $payloadB64;
        $signature = $this->base64UrlDecode($signatureB64);
        $pubKey    = openssl_pkey_get_public(self::PUBLIC_KEY);

        if ($pubKey === false) {
            return LicenseValidationResult::free();
        }

        if (openssl_verify($message, $signature, $pubKey, OPENSSL_ALGO_SHA256) !== 1) {
            return LicenseValidationResult::free();
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!\is_array($payload)) {
            return LicenseValidationResult::free();
        }

        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($exp === 0) {
            return LicenseValidationResult::free();
        }

        $expiry  = \DateTimeImmutable::createFromFormat('U', (string) $exp) ?: null;
        $expired = $expiry !== null && $expiry < new \DateTimeImmutable();
        $domain  = strtolower(trim((string) ($payload['dom'] ?? ''))) ?: null;
        $tier    = LicenseTier::tryFrom(strtolower(trim((string) ($payload['tier'] ?? '')))) ?? LicenseTier::Free;

        return new LicenseValidationResult(
            tier:    $tier,
            valid:   true,
            expired: $expired,
            expiry:  $expiry,
            domain:  $domain,
        );
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = \strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'), true) ?: '';
    }
}
