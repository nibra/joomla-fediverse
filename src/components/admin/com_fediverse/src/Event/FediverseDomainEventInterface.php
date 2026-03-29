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
 * FediverseDomainEventInterface Interface
 *
 * Define the shared contract for Fediverse domain events consumed by plugins.
 *
 * @since  __DEPLOY_VERSION__
 */
interface FediverseDomainEventInterface extends EventInterface
{
    /**
     * Return the Fediverse domain event name.
     *
     * @return  string  Event name.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getName(): string;

    /**
     * Return the acting Joomla user id.
     *
     * @return  ?int  Acting user id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getTriggeredByUserId(): ?int;

    /**
     * Return the canonical event payload.
     *
     * @return  array<string, mixed>  Event payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayload(): array;
}
