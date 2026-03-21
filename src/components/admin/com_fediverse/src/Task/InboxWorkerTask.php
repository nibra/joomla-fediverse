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
use NX\Component\Fediverse\Administrator\Service\Federation\InboxProcessService;

// InboxWorkerTask → InboxProcessService::processNextBatch()
/**
 * InboxWorkerTask Class
 *
 * Run the Inbox Worker task.
 *
 * @since  __DEPLOY_VERSION__
 */

final class InboxWorkerTask extends Task
{
    /**
     * Initialize the inbox worker task.
     *
     * Configure the scheduler task record and store the inbox processor.
     *
     * @params InboxProcessService $inbox Inbox processor service.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private InboxProcessService $inbox)
    {
        $record = (object) [
            'taskOption' => (object) ['langConstPrefix' => 'COM_FEDIVERSE_TASK'],
            'params'     => '{}',
        ];
        parent::__construct($record);
    }

    /**
     * Run the inbox worker task.
     *
     * Process a batch of inbox items for the scheduler.
     *
     * @params array $options Task options.
     *
     * @return  bool  True when the task completes.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function run(array $options = []): bool
    {
        $this->inbox->processNextBatch(50);

        return true;
    }
}
