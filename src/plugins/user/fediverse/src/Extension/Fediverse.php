<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_user_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\User\Fediverse\Extension;

use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;

/**
 * Fediverse Class
 *
 * Provide the Fediverse user plugin.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Fediverse extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * Get subscribed Joomla events.
     *
     * Return the event map for user lifecycle hooks.
     *
     * @return  array  Subscribed event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave'   => 'onUserAfterSave',
            'onUserAfterDelete' => 'onUserAfterDelete',
        ];
    }

    /**
     * Handle user save events.
     *
     * Ensure a local actor exists for the saved user.
     *
     * @params array $user User data array.
     * @params bool $isNew Whether the user is new.
     * @params bool $success Whether the save succeeded.
     * @params string $msg Status message.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserAfterSave(AfterSaveEvent $event): void
    {
        $user = $event->getArgument('subject');
        $isNew = $event->getArgument('isNew');
        $success = $event->getArgument('savingResult');
        $msg = $event->getArgument('errorMessage');

        if (!$success) {
            return;
        }

        /** @var ActorResolverService $svc */
        $svc = Factory::getContainer()->get(ActorResolverServiceInterface::class);
        $svc->ensureLocalActorForUserId($user['id']);
    }

    /**
     * Handle user delete events.
     *
     * Disable the local actor for the deleted user.
     *
     * @params array $user User data array.
     * @params bool $success Whether the delete succeeded.
     * @params string $msg Status message.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserAfterDelete(array $user, bool $success, string $msg): void
    {
        if (!$success) {
            return;
        }

        $userId = isset($user['id']) ? (int) $user['id'] : 0;
        if ($userId <= 0) {
            return;
        }

        /** @var ActorResolverService $svc */
        $svc = Factory::getContainer()->get(ActorResolverServiceInterface::class);
        $svc->disableLocalActorForUserId($userId);
    }
}
