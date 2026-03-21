<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Domain\Actor;

/**
 * KeyPair Class
 *
 * Represent Key Pair domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeyPair
{
    public ?int    $id        = null;
    public ?string $createdAt = null;
    public ?string $rotatedAt = null;

    /**
     * Create a key pair instance.
     *
     * Capture the required key metadata and payloads.
     *
     * @params int $actorId Owning actor identifier.
     * @params string $keyIdUri Key identifier URI.
     * @params string $privateKeyEnc Encrypted private key payload.
     * @params string $publicKeyPem PEM-encoded public key.
     * @params string $status Key status value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly int $actorId,
        public readonly string $keyIdUri,
        public readonly string $privateKeyEnc,
        public readonly string $publicKeyPem,
        public readonly string $status, // active|rotated|revoked
    )
    {
    }

    /**
     * Create a key pair from a database row.
     *
     * Map row fields into a key pair instance and fill optional metadata.
     *
     * @params array<string,mixed>|object $row Source database row.
     *
     * @return  self  Key pair instance populated from the row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromDbRow(array | object $row): self
    {
        $get =
            /**
             * Safely acces an optional property
             *
             * @params string $k Parameter k.
             *
             * @return  mixed Result value.
             *
             * @since  __DEPLOY_VERSION__
             */
            static function(string $k) use ($row) {
                return $row->$k ?? null;
            };

        $pair = new self(
            actorId: (int) $get('actor_id'),
            keyIdUri: (string) $get('key_id_uri'),
            privateKeyEnc: (string) $get('private_key_enc'),
            publicKeyPem: (string) $get('public_key_pem'),
            status: (string) $get('status'),
        );

        if ($get('id') !== null) {
            $pair->setId((int) $get('id'));
        }

        return $pair
            ->setCreatedAt($get('created_at') !== null ? (string) $get('created_at') : null)
            ->setRotatedAt($get('rotated_at') !== null ? (string) $get('rotated_at') : null);
    }

    /**
     * Set the key pair id.
     *
     * Attach the database identifier to the key pair.
     *
     * @params int $id Key pair identifier.
     *
     * @return  self  Updated key pair instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the creation timestamp.
     *
     * Store the creation time for persistence metadata.
     *
     * @params ?string $createdAt Creation timestamp.
     *
     * @return  self  Updated key pair instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Set the rotation timestamp.
     *
     * Store the rotation time for persistence metadata.
     *
     * @params ?string $rotatedAt Rotation timestamp.
     *
     * @return  self  Updated key pair instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setRotatedAt(?string $rotatedAt): self
    {
        $this->rotatedAt = $rotatedAt;

        return $this;
    }
}
