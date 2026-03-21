<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Http;

/**
 * HttpClientInterface Interface
 *
 * Define the Http Client contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface HttpClientInterface
{
    /**
     * Send an HTTP request.
     *
     * Dispatch a request object using the client implementation.
     *
     * @params mixed $req Request object or payload.
     *
     * @return  mixed  Response object or result.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function send($req);
}
