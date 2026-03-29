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
use NX\Component\Fediverse\Administrator\Event\InboxDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\InboxRepliesModeratedEvent;
use NX\Component\Fediverse\Administrator\Model\InboxModel;
use NX\Component\Fediverse\Administrator\Service\Access\PermissionService;
use Joomla\CMS\Factory;
use NX\Component\Fediverse\Administrator\Service\Events\FediverseDomainEventDispatcherInterface;

/**
 * InboxItemsController Class
 *
 * Handle Inbox Items requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboxItemsController extends BaseController
{
    /**
     * Approve selected inbound reply moderation entries.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function approveReplies(): void
    {
        $this->updateSelectedReplyModerationState('approved', 'COM_FEDIVERSE_INBOX_APPROVE_SUCCESS');
    }

    /**
     * Reject selected inbound reply moderation entries.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rejectReplies(): void
    {
        $this->updateSelectedReplyModerationState('rejected', 'COM_FEDIVERSE_INBOX_REJECT_SUCCESS');
    }

    /**
     * Delete selected inbox rows.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function delete(): void
    {
        $app = $this->app;
        $user = $app->getIdentity();
        $permissions = Factory::getContainer()->get(PermissionService::class);

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$permissions->canManageInbox($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=inboxitems');
            return;
        }

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );

        if ($ids === []) {
            $singleId = (int) $app->input->getInt('id', 0);
            if ($singleId > 0) {
                $ids[] = $singleId;
            }
        }

        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=inboxitems');
            return;
        }

        try {
            $model = $this->getInboxModel();
            $deleted = $model->deleteByIds($ids);
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new InboxDeletedEvent(
                    $ids,
                    $deleted,
                    (int) $user->id
                )
            );
            $app->enqueueMessage(Text::sprintf('COM_FEDIVERSE_INBOX_DELETE_SUCCESS', $deleted), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=inboxitems');
    }

    /**
     * Update moderation state for selected reply entries.
     *
     * @params string $state Moderation state.
     * @params string $messageKey Success message key.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function updateSelectedReplyModerationState(string $state, string $messageKey): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();
        $permissions = Factory::getContainer()->get(PermissionService::class);

        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        if (!$permissions->canManageInbox($user)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=inboxitems');

            return;
        }

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=inboxitems');

            return;
        }

        try {
            $model = $this->getInboxModel();
            $updated = $model->updateReplyModerationByInboxIds($ids, $state);
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new InboxRepliesModeratedEvent(
                    $ids,
                    $updated,
                    $state,
                    (int) $user->id
                )
            );
            $app->enqueueMessage(Text::sprintf($messageKey, $updated), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=inboxitems');
    }

    /**
     * Load the inbox model.
     *
     * @return  InboxModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getInboxModel(): InboxModel
    {
        /** @var InboxModel|false $model */
        $model = $this->getModel('Inbox', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof InboxModel) {
            throw new \RuntimeException('Unable to load inbox model.');
        }

        return $model;
    }
}
