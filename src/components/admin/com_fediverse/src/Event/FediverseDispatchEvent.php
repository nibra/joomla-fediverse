<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Event;

use Joomla\Event\EventInterface;

/**
 * FediverseDispatchEvent Class
 *
 * Wrap one Fediverse domain event in a single Joomla dispatcher event channel.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseDispatchEvent implements EventInterface
{
    public const NAME = 'onFediverseEvent';

    private bool $stopped = false;

    /**
     * Initialize the dispatcher event.
     *
     * @params FediverseDomainEventInterface $domainEvent Wrapped domain event.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private readonly FediverseDomainEventInterface $domainEvent)
    {
    }

    /**
     * Return one event argument.
     *
     * @params mixed $name Argument name.
     * @params mixed $default Default value.
     *
     * @return  mixed  Argument value.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getArgument($name, $default = null)
    {
        return match ((string) $name) {
            'domainEvent' => $this->domainEvent,
            default => $default,
        };
    }

    /**
     * Return the Joomla dispatcher event name.
     *
     * @return  string  Event name.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Check whether propagation has been stopped.
     *
     * @return  bool  True when stopped.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Stop event propagation.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    /**
     * Return the wrapped Fediverse domain event.
     *
     * @return  FediverseDomainEventInterface  Domain event.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getDomainEvent(): FediverseDomainEventInterface
    {
        return $this->domainEvent;
    }
}
