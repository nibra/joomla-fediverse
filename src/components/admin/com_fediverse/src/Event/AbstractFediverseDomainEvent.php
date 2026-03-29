<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Event;

use Joomla\Event\AbstractEvent;

/**
 * AbstractFediverseDomainEvent Class
 *
 * Provide a reusable base for Fediverse domain events dispatched through Joomla.
 *
 * @since  __DEPLOY_VERSION__
 */
abstract class AbstractFediverseDomainEvent extends AbstractEvent implements FediverseDomainEventInterface
{
    /**
     * Initialize the domain event.
     *
     * @params ?int                $triggeredByUserId Acting Joomla user id.
     * @params array<string,mixed> $payload Canonical event payload.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private readonly ?int $triggeredByUserId,
        private readonly array $payload,
    ) {
        parent::__construct(
            $this->getName(),
            [
                'triggered_by_user_id' => $this->triggeredByUserId,
                'payload' => $this->payload,
            ]
        );
    }

    /**
     * Return the event name.
     *
     * @return  string  Event name.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * Return the acting Joomla user id.
     *
     * @return  ?int  Acting user id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getTriggeredByUserId(): ?int
    {
        return $this->triggeredByUserId;
    }

    /**
     * Return the canonical event payload.
     *
     * @return  array<string, mixed>  Event payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Prevent mutation through ArrayAccess.
     *
     * @params mixed $offset Ignored array offset.
     * @params mixed $value Ignored replacement value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Fediverse domain events are immutable.');
    }

    /**
     * Prevent mutation through ArrayAccess.
     *
     * @params mixed $offset Ignored array offset.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Fediverse domain events are immutable.');
    }

    /**
     * Serialize the Joomla event state and immutable Fediverse payload.
     *
     * @return  array<string, mixed>  Serialized event data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->getName(),
            'arguments' => [
                'triggered_by_user_id' => $this->triggeredByUserId,
                'payload' => $this->payload,
            ],
            'stopped' => $this->isStopped(),
            'triggered_by_user_id' => $this->triggeredByUserId,
            'payload' => $this->payload,
        ];
    }

    /**
     * Restore serialized Joomla event state and immutable Fediverse payload.
     *
     * @params array<string, mixed> $data Serialized event data.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __unserialize(array $data): void
    {
        $this->name              = (string) ($data['name'] ?? static::NAME);
        $this->arguments         = (array) ($data['arguments'] ?? []);
        $this->stopped           = (bool) ($data['stopped'] ?? false);
        $this->triggeredByUserId = isset($data['triggered_by_user_id']) ? (int) $data['triggered_by_user_id'] : null;
        $this->payload           = (array) ($data['payload'] ?? []);
    }
}
