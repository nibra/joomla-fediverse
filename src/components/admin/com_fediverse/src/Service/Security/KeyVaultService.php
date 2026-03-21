<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Security;

use Joomla\CMS\Factory;
use RuntimeException;

/**
 * KeyVaultService Class
 *
 * Provide Key Vault services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeyVaultService
{
    private const ENC_VERSION = 1;
    private const ENC_ALG     = 'aes-256-gcm';
    private const IV_BYTES    = 12;

    /**
     * Initialize the key vault service.
     *
     * @params string|null $appSecret Joomla application secret for key derivation.
     *                                When null (default), fetched lazily from Factory::getApplication().
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private ?string $appSecret = null)
    {
    }

    /**
     * Generate an RSA key pair.
     *
     * Create a new RSA key pair and return PEM payloads.
     *
     * @params int $bits Key size in bits.
     *
     * @return  array{public_key_pem:string,private_key_pem:string}  Generated key pair.
     * @throws  RuntimeException  if key generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function generateRsaKeyPair(int $bits = 2048): array
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => $bits,
        ]);

        if ($res === false) {
            throw new RuntimeException('openssl_pkey_new failed');
        }

        $privatePem = '';
        if (!openssl_pkey_export($res, $privatePem)) {
            throw new RuntimeException('openssl_pkey_export failed');
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['key'])) {
            throw new RuntimeException('openssl_pkey_get_details failed');
        }

        return [
            'public_key_pem'  => (string) $details['key'],
            'private_key_pem' => $privatePem,
        ];
    }

    /**
     * Encrypt a private key PEM.
     *
     * Encrypt the private key and return an encoded payload for storage.
     *
     * @params string $privateKeyPem Private key PEM payload.
     *
     * @return  string  Encrypted payload for storage.
     * @throws  RuntimeException  if encryption fails.
     * @throws  \Exception  if random bytes generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function encryptPrivateKey(string $privateKeyPem): string
    {
        $key = $this->deriveKey();
        $iv  = random_bytes(self::IV_BYTES);
        $tag = '';

        $ct = openssl_encrypt($privateKeyPem, self::ENC_ALG, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false || $tag === '') {
            throw new RuntimeException('openssl_encrypt failed');
        }

        $payload = [
            'v'   => self::ENC_VERSION,
            'alg' => self::ENC_ALG,
            'iv'  => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct'  => base64_encode($ct),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decrypt a private key payload.
     *
     * Decode and decrypt a stored private key payload.
     *
     * @params string $privateKeyEnc Encrypted payload.
     *
     * @return  string  Private key PEM.
     * @throws  RuntimeException  if decryption fails or payload is invalid.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function decryptPrivateKey(string $privateKeyEnc): string
    {
        $payload = json_decode($privateKeyEnc, true);
        if (!is_array(
                $payload
            ) || !isset($payload['v'], $payload['alg'], $payload['iv'], $payload['tag'], $payload['ct'])) {
            throw new RuntimeException('Invalid private_key_enc payload');
        }

        if ((int) $payload['v'] !== self::ENC_VERSION || (string) $payload['alg'] !== self::ENC_ALG) {
            throw new RuntimeException('Unsupported private_key_enc format');
        }

        $iv  = base64_decode((string) $payload['iv'], true);
        $tag = base64_decode((string) $payload['tag'], true);
        $ct  = base64_decode((string) $payload['ct'], true);

        if ($iv === false || $tag === false || $ct === false) {
            throw new RuntimeException('Invalid base64 in private_key_enc payload');
        }

        $key = $this->deriveKey();
        $pt  = openssl_decrypt($ct, self::ENC_ALG, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($pt === false) {
            throw new RuntimeException('openssl_decrypt failed');
        }

        return $pt;
    }

    /**
     * Derive an encryption key.
     *
     * Generate a fixed-length key from Joomla's secret.
     *
     * @return  string  Derived encryption key.
     * @throws  RuntimeException  if the Joomla secret is missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function deriveKey(): string
    {
        $secret = $this->appSecret ?? (string) Factory::getApplication()->get('secret', '');

        if ($secret === '') {
            throw new RuntimeException('Joomla secret is empty; cannot derive encryption key');
        }

        // HKDF-SHA256 -> 32 bytes
        return hash_hkdf('sha256', $secret, 32, 'nx-fediverse-keyvault', '');
    }
}
