<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

if (!defined('JPATH_FEDIVERSE')) {
    define('JPATH_FEDIVERSE', JPATH_ADMINISTRATOR . '/components/com_fediverse');
}

return require JPATH_FEDIVERSE . '/services/provider.php';
