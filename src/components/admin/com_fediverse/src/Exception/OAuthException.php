<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Exception;

use RuntimeException;

/**
 * OAuthException Class
 *
 * Represent OAuth protocol errors with HTTP status codes.
 *
 * @since  __DEPLOY_VERSION__
 */
final class OAuthException extends RuntimeException
{
    /**
     * Initialize an OAuth error.
     *
     * @params string $error OAuth error code.
     * @params string $description Error description.
     * @params int $statusCode HTTP status code.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private string $error,
        string $description = '',
        int $statusCode = 400
    ) {
        parent::__construct($description, $statusCode);
    }

    /**
     * Get the OAuth error code.
     *
     * @return  string  OAuth error code.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Get the HTTP status code.
     *
     * @return  int  HTTP status code.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getStatusCode(): int
    {
        $code = (int) $this->getCode();

        return $code > 0 ? $code : 400;
    }
}
