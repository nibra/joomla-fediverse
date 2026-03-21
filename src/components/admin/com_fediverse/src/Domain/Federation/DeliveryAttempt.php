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
 * DeliveryAttempt Class
 *
 * Represent Delivery Attempt domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DeliveryAttempt
{
    public ?int $id = null;

    /**
     * Create a delivery attempt.
     *
     * Capture the required delivery state and target metadata.
     *
     * @params int $outboxId Outbox item identifier.
     * @params int $localActorId Local actor identifier.
     * @params string $targetInboxUrl Target inbox URL.
     * @params string $payload Serialized activity payload.
     * @params string $localActorKeyId Actor key identifier URI.
     * @params int $attempts Number of attempts so far.
     * @params string $state Delivery state.
     * @params string $nextAttemptAt Next attempt timestamp.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly int $outboxId,
        public readonly int $localActorId,
        public readonly string $targetInboxUrl,
        public readonly string $payload,
        public readonly string $localActorKeyId,
        public readonly int $attempts,
        public readonly string $state,
        public readonly string $nextAttemptAt,
    ) {
    }

    /**
     * Set the delivery attempt id.
     *
     * Attach the database identifier to the delivery attempt.
     *
     * @params int $id Delivery attempt identifier.
     *
     * @return  self  Updated delivery attempt instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
