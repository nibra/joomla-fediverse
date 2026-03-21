<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Federation;

use NX\Component\Fediverse\Administrator\Adapter\ActivityPubAdapterInterface;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Model\InboxModelInterface;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;
use Throwable;

/**
 * InboxIngestService Class
 *
 * Provide Inbox Ingest services.
 *
 * @since  __DEPLOY_VERSION__
 */

final class InboxIngestService
{
    /**
     * Initialize the inbox ingest service.
     *
     * Store dependencies required to ingest inbound activities.
     *
     * @params ActivityPubAdapterInterface $adapter ActivityPub adapter.
     * @params HttpSignaturServiceInterface $sig HTTP signature verifier.
     * @params InboxModel $inboxModel Inbox model.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private ActivityPubAdapterInterface $adapter,
        private HttpSignaturServiceInterface $sig,
        private InboxModelInterface $inboxModel,
    ) {
    }

    /**
     * Ingest an inbound activity.
     *
     * Verify signature, parse the activity, and persist the raw payload.
     *
     * @params RequestContext $ctx Request context.
     * @params ?string $localActorHandle Local actor handle or null for shared inbox.
     *
     * @return  int  Inserted inbox item id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function ingest(RequestContext $ctx, ?string $localActorHandle): int
    {
        $raw = $ctx->getRawBody();

        try {
            $signatureValid = $this->sig->verifyInbound($ctx);
        } catch (Throwable) {
            $signatureValid = false;
        }

        try {
            $activity = $this->adapter->parseActivity($raw);
        } catch (Throwable) {
            $activity = null;
        }

        return $this->inboxModel->insert($localActorHandle, $activity, $raw, $signatureValid);
    }
}
