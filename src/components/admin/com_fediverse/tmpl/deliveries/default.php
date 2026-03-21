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

$stateBadge = [
    'queued'    => 'bg-info text-dark',
    'inflight'  => 'bg-warning text-dark',
    'delivered' => 'bg-success',
    'failed'    => 'bg-danger',
    'dead'      => 'bg-secondary',
];
?>
<div class="com-fediverse-deliveries">

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_HEADING'); ?></h1>

    <?php if (empty($this->items)) : ?>
        <p><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_EMPTY'); ?></p>
    <?php else : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_ID'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_CREATED'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_TARGET'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_STATE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_ATTEMPTS'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_NEXT_RETRY'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_ERROR'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                <?php $state = $item['state'] ?? 'queued'; ?>
                <tr>
                    <td><?php echo (int) $item['id']; ?></td>
                    <td><?php echo HTMLHelper::_('date', $item['created_at'], Text::_('DATE_FORMAT_LC2')); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr($item['target_inbox_url'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="badge <?php echo $stateBadge[$state] ?? 'bg-secondary'; ?>">
                            <?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?php echo (int) ($item['attempts'] ?? 0); ?></td>
                    <td>
                        <?php if (!empty($item['next_retry_at'])) : ?>
                            <?php echo HTMLHelper::_('date', $item['next_retry_at'], Text::_('DATE_FORMAT_LC2')); ?>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(mb_substr($item['last_error'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
