<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Model\DeliveryQueueModel;

/**
 * DeliveriesController Class
 *
 * Handle Deliveries requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DeliveriesController extends BaseController
{
    /**
     * Delete selected delivery rows.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function delete(): void
    {
        $app = $this->app;
        $user = $app->getIdentity();

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=deliveries');
            return;
        }

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=deliveries');
            return;
        }

        try {
            $model = $this->getDeliveriesModel();
            $deleted = $model->deleteByIds($ids);
            $app->enqueueMessage(Text::sprintf('COM_FEDIVERSE_DELIVERIES_DELETE_SUCCESS', $deleted), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=deliveries');
    }

    /**
     * Load the delivery queue model.
     *
     * @return  DeliveryQueueModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getDeliveriesModel(): DeliveryQueueModel
    {
        /** @var DeliveryQueueModel|false $model */
        $model = $this->getModel('DeliveryQueue', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof DeliveryQueueModel) {
            throw new \RuntimeException('Unable to load delivery queue model.');
        }

        return $model;
    }
}
