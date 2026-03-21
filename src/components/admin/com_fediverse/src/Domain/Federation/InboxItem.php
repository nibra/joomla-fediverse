<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Domain\Federation;

/**
 * InboxItem Class
 *
 * Represent Inbox Item domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboxItem
{
    public ?int    $id            = null;
    public ?int    $localActorId  = null;
    public ?string $activityIdUri = null;
    public ?string $processedAt   = null;
    public ?string $error         = null;

    /**
     * Create an inbox item.
     *
     * Capture the required inbox payload and status metadata.
     *
     * @params string $type Activity type.
     * @params string $rawJson Raw activity JSON.
     * @params bool $signatureValid Signature verification result.
     * @params string $receivedAt Receipt timestamp.
     * @params string $status Processing status.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly string $type,
        public readonly string $rawJson,
        public readonly bool $signatureValid,
        public readonly string $receivedAt,
        public readonly string $status,
    ) {
    }

    /**
     * Create an inbox item from a database row.
     *
     * Map row fields into an inbox item instance and fill optional metadata.
     *
     * @params array<string,mixed>|object $row Source database row.
     *
     * @return  self  Inbox item instance populated from the row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromDbRow(array | object $row): self
    {
        $get = static function(string $k) use ($row) {
            return $row->$k ?? null;
        };

        $item = new self(
            type: (string) $get('type'),
            rawJson: (string) $get('raw_json'),
            signatureValid: (bool) ((int) ($get('signature_valid') ?? 0)),
            receivedAt: (string) $get('received_at'),
            status: (string) $get('status'),
        );

        if ($get('id') !== null) {
            $item->setId((int) $get('id'));
        }

        return $item
            ->setLocalActorId($get('local_actor_id') !== null ? (int) $get('local_actor_id') : null)
            ->setActivityIdUri($get('activity_id_uri') !== null ? (string) $get('activity_id_uri') : null)
            ->setProcessedAt($get('processed_at') !== null ? (string) $get('processed_at') : null)
            ->setError($get('error') !== null ? (string) $get('error') : null);
    }

    /**
     * Set the inbox item id.
     *
     * Attach the database identifier to the inbox item.
     *
     * @params int $id Inbox item identifier.
     *
     * @return  self  Updated inbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the local actor id.
     *
     * Assign or clear the local actor association.
     *
     * @params ?int $localActorId Local actor identifier.
     *
     * @return  self  Updated inbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setLocalActorId(?int $localActorId): self
    {
        $this->localActorId = $localActorId;

        return $this;
    }

    /**
     * Set the activity id URI.
     *
     * Assign or clear the ActivityPub activity identifier.
     *
     * @params ?string $activityIdUri Activity identifier URI.
     *
     * @return  self  Updated inbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setActivityIdUri(?string $activityIdUri): self
    {
        $this->activityIdUri = $activityIdUri;

        return $this;
    }

    /**
     * Set the processed timestamp.
     *
     * Store the time when processing completed.
     *
     * @params ?string $processedAt Processing timestamp.
     *
     * @return  self  Updated inbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setProcessedAt(?string $processedAt): self
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    /**
     * Set the error message.
     *
     * Store an error description when processing fails.
     *
     * @params ?string $error Error message.
     *
     * @return  self  Updated inbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }
}
