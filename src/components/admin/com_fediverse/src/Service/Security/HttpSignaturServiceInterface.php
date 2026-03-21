<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Security;

use NX\Component\Fediverse\Administrator\Http\RequestContext;

/**
 * HttpSignaturServiceInterface Interface
 *
 * Define the Http Signatur Service contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface HttpSignaturServiceInterface
{
    /**
     * Build a signed POST request for delivery to a remote inbox.
     *
     * Create a request object that includes HTTP signature headers.
     *
     * @params string $targetInboxUrl Target inbox URL.
     * @params string $payload Serialized activity payload.
     * @params ?string $localActorKeyId Key identifier for signing.
     *
     * @return  mixed  Request object understood by the HTTP client.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function signedPostRequest(string $targetInboxUrl, string $payload, ?string $localActorKeyId): mixed;

    /**
     * Verify an inbound request signature (if present).
     *
     * Check the signature headers on an inbound request context.
     *
     * @params RequestContext $ctx Request context to verify.
     *
     * @return  bool  True when the signature is valid.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function verifyInbound(RequestContext $ctx): bool;
}
