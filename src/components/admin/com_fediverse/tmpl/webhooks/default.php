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

HTMLHelper::_('behavior.multiselect');

$isReadOnly = !$this->isPro;
?>
<div class="com-fediverse-webhooks">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_HEADING'); ?></h1>

    <?php if ($isReadOnly) : ?>
        <?php
        $featureName = Text::_('COM_FEDIVERSE_WEBHOOKS_TITLE');
        $expired     = $this->licenseExpired;
        require __DIR__ . '/../common/upgrade_cta.php';
        ?>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&view=webhooks'); ?>" method="post" name="adminForm" id="adminForm">
        <?php if (empty($this->items)) : ?>
            <p><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-striped" id="webhooksList">
                <thead>
                    <tr>
                        <th width="1%" class="text-center">
                            <?php if (!$isReadOnly) : ?>
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            <?php endif; ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_COL_NAME'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_COL_URL'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_COL_EVENTS'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_COL_ENABLED'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_COL_LAST_DELIVERY'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $status = Text::_('COM_FEDIVERSE_WEBHOOKS_STATUS_IDLE');
                    if (($item['last_delivery_at'] ?? null) !== null && ($item['last_delivery_status'] ?? null) !== null) {
                        if (($item['last_delivery_status'] ?? 0) >= 200 && ($item['last_delivery_status'] ?? 0) < 300) {
                            $status = Text::sprintf(
                                'COM_FEDIVERSE_WEBHOOKS_STATUS_DELIVERED',
                                (int) $item['last_delivery_status'],
                                (string) $item['last_delivery_at']
                            );
                        } else {
                            $status = Text::sprintf(
                                'COM_FEDIVERSE_WEBHOOKS_STATUS_FAILED',
                                (string) (($item['last_error'] ?? '') !== '' ? $item['last_error'] : 'HTTP ' . (int) ($item['last_delivery_status'] ?? 0))
                            );
                        }
                    }
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php if (!$isReadOnly) : ?>
                                <?php echo HTMLHelper::_('grid.id', $i, (int) ($item['id'] ?? 0)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isReadOnly) : ?>
                                <?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            <?php else : ?>
                                <a href="<?php echo Route::_('index.php?option=com_fediverse&view=webhook&id=' . (int) ($item['id'] ?? 0)); ?>">
                                    <?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($item['target_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php foreach ((array) ($item['events'] ?? []) as $event) : ?>
                                <span class="badge bg-secondary"><?php echo Text::_($this->eventOptions[$event] ?? $event); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo ($item['is_enabled'] ?? false) ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ($item['is_enabled'] ?? false) ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="webhooks">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
