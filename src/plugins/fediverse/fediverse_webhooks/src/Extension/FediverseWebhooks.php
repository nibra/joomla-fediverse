<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_fediverse_fediverse_webhooks
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\Fediverse\FediverseWebhooks\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Event\ActorDisabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorEnabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorProfileUpdatedEvent;
use NX\Component\Fediverse\Administrator\Event\ConfigurationExportedEvent;
use NX\Component\Fediverse\Administrator\Event\ConfigurationImportedEvent;
use NX\Component\Fediverse\Administrator\Event\ContentDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\ContentPublishedEvent;
use NX\Component\Fediverse\Administrator\Event\ContentUpdatedEvent;
use NX\Component\Fediverse\Administrator\Event\FediverseDomainEventInterface;
use NX\Component\Fediverse\Administrator\Event\InboxDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\InboxRepliesModeratedEvent;
use NX\Component\Fediverse\Administrator\Event\KeyRotatedEvent;
use NX\Component\Fediverse\Administrator\Event\PolicyDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\PolicySavedEvent;
use NX\Component\Fediverse\Administrator\Service\Automation\WebhookAutomationService;

/**
 * FediverseWebhooks Class
 *
 * Subscribe to Fediverse domain events and deliver configured outbound webhooks.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseWebhooks extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * @var array<string, string>
     */
    private const WEBHOOK_MAP = [
        ActorEnabledEvent::NAME          => 'admin.actor_enabled',
        ActorDisabledEvent::NAME         => 'admin.actor_disabled',
        ActorProfileUpdatedEvent::NAME   => 'admin.actor_profile_updated',
        InboxRepliesModeratedEvent::NAME => 'admin.inbox_replies_moderated',
        InboxDeletedEvent::NAME          => 'admin.inbox_deleted',
        PolicySavedEvent::NAME           => 'admin.policy_saved',
        PolicyDeletedEvent::NAME         => 'admin.policy_deleted',
        KeyRotatedEvent::NAME            => 'admin.key_rotated',
        ConfigurationExportedEvent::NAME => 'admin.config_exported',
        ConfigurationImportedEvent::NAME => 'admin.config_imported',
        ContentPublishedEvent::NAME      => 'content.published',
        ContentUpdatedEvent::NAME        => 'content.updated',
        ContentDeletedEvent::NAME        => 'content.deleted',
    ];

    /**
     * Initialize the plugin.
     *
     * @params WebhookAutomationService $webhookAutomationService Webhook delivery service.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private readonly WebhookAutomationService $webhookAutomationService, $config = [])
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
            array_keys(self::WEBHOOK_MAP),
            array_fill(0, count(self::WEBHOOK_MAP), 'onFediverseEvent')
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
        $webhookEvent = self::WEBHOOK_MAP[$event->getName()] ?? '';

        if ($webhookEvent === '') {
            return;
        }

        $this->webhookAutomationService->dispatch(
            $webhookEvent,
            $event->getPayload()
        );
    }
}
