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
 * OutboxItem Class
 *
 * Represent Outbox Item domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class OutboxItem
{
    public ?int $id = null;

    /**
     * Create an outbox item.
     *
     * Capture the required outbox payload and status metadata.
     *
     * @params int $localActorId Local actor identifier.
     * @params string $activityIdUri Activity identifier URI.
     * @params string $type Activity type.
     * @params string $rawJson Raw activity JSON.
     * @params string $state Outbox state.
     * @params string $createdAt Creation timestamp.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly int $localActorId,
        public readonly string $activityIdUri,
        public readonly string $type,
        public readonly string $rawJson,
        public readonly string $state,
        public readonly string $createdAt,
    ) {
    }

    /**
     * Create an outbox item from a database row.
     *
     * Map row fields into an outbox item instance and fill optional metadata.
     *
     * @params array<string,mixed>|object $row Source database row.
     *
     * @return  self  Outbox item instance populated from the row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromDbRow(array | object $row): self
    {
        $get = static function(string $k) use ($row) {
            return $row->$k ?? null;
        };

        $item = new self(
            localActorId: (int) $get('local_actor_id'),
            activityIdUri: (string) $get('activity_id_uri'),
            type: (string) $get('type'),
            rawJson: (string) $get('raw_json'),
            state: (string) $get('state'),
            createdAt: (string) $get('created_at'),
        );

        if ($get('id') !== null) {
            $item->setId((int) $get('id'));
        }

        return $item;
    }

    /**
     * Set the outbox item id.
     *
     * Attach the database identifier to the outbox item.
     *
     * @params int $id Outbox item identifier.
     *
     * @return  self  Updated outbox item instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
