<?php
/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$statusBadge = [
    'pending'   => 'bg-warning text-dark',
    'processed' => 'bg-success',
    'failed'    => 'bg-danger',
    'ignored'   => 'bg-secondary',
];
?>
<div class="com-fediverse-inboxitems">

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_INBOX_HEADING'); ?></h1>

    <?php if (empty($this->items)) : ?>
        <p><?php echo Text::_('COM_FEDIVERSE_INBOX_EMPTY'); ?></p>
    <?php else : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_ID'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_RECEIVED'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_HANDLE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_TYPE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_STATUS'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_ERROR'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                <?php $status = $item['status'] ?? 'pending'; ?>
                <tr>
                    <td><?php echo (int) $item['id']; ?></td>
                    <td><?php echo HTMLHelper::_('date', $item['received_at'], Text::_('DATE_FORMAT_LC2')); ?></td>
                    <td><?php echo htmlspecialchars($item['local_actor_handle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($item['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="badge <?php echo $statusBadge[$status] ?? 'bg-secondary'; ?>">
                            <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(mb_substr($item['error'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
