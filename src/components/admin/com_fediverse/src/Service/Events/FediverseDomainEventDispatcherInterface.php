<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Events;

use NX\Component\Fediverse\Administrator\Event\FediverseDomainEventInterface;

/**
 * FediverseDomainEventDispatcherInterface Interface
 *
 * Define the dispatcher contract for Fediverse domain events.
 *
 * @since  __DEPLOY_VERSION__
 */
interface FediverseDomainEventDispatcherInterface
{
    /**
     * Dispatch one Fediverse domain event to subscriber plugins.
     *
     * @params FediverseDomainEventInterface $event Event object.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function dispatch(FediverseDomainEventInterface $event): void;
}
