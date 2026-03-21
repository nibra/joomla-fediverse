<?php

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$navItems = [
    ['url' => Route::_('index.php?option=com_fediverse&view=dashboard'),  'label' => Text::_('COM_FEDIVERSE_NAV_DASHBOARD')],
    ['url' => Route::_('index.php?option=com_fediverse&view=actors'),     'label' => Text::_('COM_FEDIVERSE_NAV_ACTORS')],
    ['url' => Route::_('index.php?option=com_fediverse&view=inboxitems'), 'label' => Text::_('COM_FEDIVERSE_NAV_INBOX')],
    ['url' => Route::_('index.php?option=com_fediverse&view=deliveries'), 'label' => Text::_('COM_FEDIVERSE_NAV_DELIVERIES')],
    ['url' => Route::_('index.php?option=com_fediverse&view=policies'),   'label' => Text::_('COM_FEDIVERSE_NAV_POLICIES')],
];
?>
<div class="com-fediverse-nav mb-3">
    <ul class="nav nav-tabs">
        <?php foreach ($navItems as $nav) : ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo $nav['url']; ?>"><?php echo $nav['label']; ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
