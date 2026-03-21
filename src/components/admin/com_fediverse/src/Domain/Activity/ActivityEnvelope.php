<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Domain\Activity;

use RuntimeException;

/**
 * ActivityEnvelope Class
 *
 * Represent Activity Envelope domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActivityEnvelope
{
    /**
     * Initialize an activity envelope.
     *
     * Store the core identifiers and the raw JSON payload for later processing.
     *
     * @params string $idUri Activity identifier URI.
     * @params string $type Activity type.
     * @params string $actorUri Actor identifier URI.
     * @params array<string,mixed> $json Original activity payload.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly string $idUri,
        public readonly string $type,
        public readonly string $actorUri,
        public readonly array $json,
    ) {
    }

    /**
     * Create an envelope from a JSON payload.
     *
     * Validate the required fields and capture the raw activity body.
     *
     * @params array<string,mixed> $json Activity payload.
     *
     * @return  self  Envelope built from the payload.
     * @throws  RuntimeException  if required fields are missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromJson(array $json): self
    {
        $id    = (string) ($json['id'] ?? '');
        $type  = (string) ($json['type'] ?? '');
        $actor = (string) ($json['actor'] ?? '');
        if ($id === '' || $type === '' || $actor === '') {
            throw new RuntimeException('Invalid Activity: missing id/type/actor');
        }

        return new self($id, $type, $actor, $json);
    }

    /**
     * Serialize the activity payload as JSON.
     *
     * Convert the stored payload into its JSON representation.
     *
     * @return  string  JSON representation of the payload.
     * @throws  \JsonException  if encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toJsonString(): string
    {
        return json_encode($this->json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
