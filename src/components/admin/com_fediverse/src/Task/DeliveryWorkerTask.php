<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Task;

use Joomla\Component\Scheduler\Administrator\Task\Task;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryService;

/**
 * DeliveryWorkerTask Class
 *
 * Run the Delivery Worker task.
 *
 * @since  __DEPLOY_VERSION__
 */

final class DeliveryWorkerTask extends Task
{
    /**
     * Initialize the delivery worker task.
     *
     * Configure the scheduler task record and store the delivery service.
     *
     * @params DeliveryService $delivery Delivery service.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DeliveryService $delivery)
    {
        $record = (object) [
            'taskOption' => (object) ['langConstPrefix' => 'COM_FEDIVERSE_TASK'],
            'params'     => '{}',
        ];
        parent::__construct($record);
    }

    /**
     * Run the delivery worker task.
     *
     * Deliver a batch of queued outbound activities.
     *
     * @params array $options Task options.
     *
     * @return  bool  True when the task completes.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function run(array $options = []): bool
    {
        $count = $this->delivery->deliverNextBatch(50);

        return true;
    }
}
