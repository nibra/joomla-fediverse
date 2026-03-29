<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Events;

use Joomla\Event\DispatcherInterface;
use NX\Component\Fediverse\Administrator\Event\FediverseDispatchEvent;
use NX\Component\Fediverse\Administrator\Event\FediverseDomainEventInterface;

/**
 * FediverseDomainEventDispatcher Class
 *
 * Import Fediverse subscriber plugins and dispatch one typed domain event.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseDomainEventDispatcher implements FediverseDomainEventDispatcherInterface
{
    /**
     * Initialize the dispatcher service.
     *
     * @params DispatcherInterface $dispatcher Joomla event dispatcher.
     * @params callable            $pluginImporter Callback that imports Fediverse plugins.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DispatcherInterface $dispatcher, private $pluginImporter)
    {
    }

    /**
     * Dispatch one Fediverse domain event.
     *
     * @params FediverseDomainEventInterface $event Event object.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function dispatch(FediverseDomainEventInterface $event): void
    {
        ($this->pluginImporter)();
        $this->dispatcher->dispatch($event->getName(), $event);
    }
}
