<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_fediverse_fediverse_actionlog
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\Fediverse\FediverseActionlog\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Event\ActorDisabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorEnabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorProfileUpdatedEvent;
use NX\Component\Fediverse\Administrator\Event\ConfigurationExportedEvent;
use NX\Component\Fediverse\Administrator\Event\ConfigurationImportedEvent;
use NX\Component\Fediverse\Administrator\Event\FediverseDomainEventInterface;
use NX\Component\Fediverse\Administrator\Event\InboxDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\InboxRepliesModeratedEvent;
use NX\Component\Fediverse\Administrator\Event\KeyRotatedEvent;
use NX\Component\Fediverse\Administrator\Event\PolicyDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\PolicySavedEvent;
use NX\Component\Fediverse\Administrator\Service\Observability\AuditLogService;

/**
 * FediverseActionlog Class
 *
 * Subscribe to Fediverse domain events and write Joomla User Action Log entries.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseActionlog extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * @var array<string, array{key: string, context: string}>
     */
    private const AUDIT_MAP = [
        ActorEnabledEvent::NAME          => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_ACTORS_ENABLED',
            'context' => 'com_fediverse.actors',
        ],
        ActorDisabledEvent::NAME         => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_ACTORS_DISABLED',
            'context' => 'com_fediverse.actors',
        ],
        ActorProfileUpdatedEvent::NAME   => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_ACTOR_TYPES_UPDATED',
            'context' => 'com_fediverse.actors',
        ],
        InboxRepliesModeratedEvent::NAME => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_INBOX_REPLIES_MODERATED',
            'context' => 'com_fediverse.inbox',
        ],
        InboxDeletedEvent::NAME          => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_INBOX_DELETED',
            'context' => 'com_fediverse.inbox',
        ],
        PolicySavedEvent::NAME           => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_POLICY_SAVED',
            'context' => 'com_fediverse.policies',
        ],
        PolicyDeletedEvent::NAME         => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_POLICY_DELETED',
            'context' => 'com_fediverse.policies',
        ],
        KeyRotatedEvent::NAME            => [
            'key'     => '',
            'context' => 'com_fediverse.keys',
        ],
        ConfigurationExportedEvent::NAME => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_TRANSFER_EXPORTED',
            'context' => 'com_fediverse.transfer',
        ],
        ConfigurationImportedEvent::NAME => [
            'key'     => 'COM_FEDIVERSE_ACTIONLOG_TRANSFER_IMPORTED',
            'context' => 'com_fediverse.transfer',
        ],
    ];

    /**
     * Initialize the plugin.
     *
     * @params AuditLogService $auditLogService Audit log service.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private readonly AuditLogService $auditLogService, $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Return subscribed events.
     *
     * @return  array<string, string>  Event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return array_combine(
            array_keys(self::AUDIT_MAP),
            array_fill(0, count(self::AUDIT_MAP), 'onFediverseEvent')
        );
    }

    /**
     * Handle one Fediverse domain event.
     *
     * @params FediverseDomainEventInterface $event Joomla wrapper event.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onFediverseEvent(FediverseDomainEventInterface $event): void
    {
        $mapping = self::AUDIT_MAP[$event->getName()] ?? null;

        if ($mapping === null) {
            return;
        }

        $payload = $event->getPayload();
        if ($payload === []) {
            return;
        }

        $messageKey = $mapping['key'];

        if ($event->getName() === KeyRotatedEvent::NAME) {
            $messageKey = !empty($payload['handle'])
                ? 'COM_FEDIVERSE_ACTIONLOG_KEY_ROTATED_HANDLE'
                : 'COM_FEDIVERSE_ACTIONLOG_KEY_ROTATED_USER';
        }

        $this->auditLogService->record(
            $messageKey,
            $mapping['context'],
            $payload,
            $event->getTriggeredByUserId()
        );
    }
}
