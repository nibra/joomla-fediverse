<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Http;

use Joomla\Http\Response;

/**
 * ResponseFactory Class
 *
 * Provide Response Factory HTTP utilities.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ResponseFactory
{
    /**
     * Build a JSON response.
     *
     * Encode the result payload and wrap it in a Joomla response.
     *
     * @params mixed $result Response payload.
     * @params int $int HTTP status code.
     *
     * @return  Response  JSON response instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function json(mixed $result, int $int)
    {
        return new Response(json_encode($result), $int, ['Content-Type' => 'application/json']);
    }
}
