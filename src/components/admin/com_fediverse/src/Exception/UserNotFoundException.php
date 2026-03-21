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
 * UserNotFoundException Class
 *
 * Represent a User Not Found error.
 *
 * @since  __DEPLOY_VERSION__
 */
class UserNotFoundException extends RuntimeException
{
    /**
     * Initialize a user not found exception.
     *
     * Build a message for a missing Joomla user id.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(int $userId)
    {
        parent::__construct('User not found: ' . $userId);
    }
}
