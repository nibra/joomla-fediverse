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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$orderingParts = preg_split('/\s+/', trim((string) ($this->listFullordering ?? '')), 2) ?: [];
$listOrder = (string) ($orderingParts[0] ?? 'received_at');
$listDirn = strtoupper((string) ($orderingParts[1] ?? 'DESC'));
if ($listDirn !== 'ASC' && $listDirn !== 'DESC') {
    $listDirn = 'DESC';
}

$statusBadge = [
    'received'  => 'bg-info',
    'pending'   => 'bg-warning text-dark',
    'processed' => 'bg-success',
    'failed'    => 'bg-danger',
    'ignored'   => 'bg-secondary',
];

$moderationBadge = [
    'pending'  => 'bg-warning text-dark',
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
];
?>
<div class="com-fediverse-inboxitems">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_INBOX_HEADING'); ?></h1>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&view=inboxitems'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <p><?php echo Text::_('COM_FEDIVERSE_INBOX_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-striped" id="inboxList">
                <thead>
                    <tr>
                        <th width="1%" class="text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_INBOX_COL_ID', 'id', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_INBOX_COL_RECEIVED', 'received_at', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_HANDLE'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_TYPE'); ?></th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_INBOX_COL_STATUS', 'status', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_MODERATION'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_INBOX_COL_ERROR'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                    <?php $status = $item['status'] ?? 'pending'; ?>
                    <?php
                    $isReply = strcasecmp((string) ($item['type'] ?? ''), 'Create') === 0;
                    $moderation = (string) ($item['reply_moderation_state'] ?? '');
                    if ($isReply && $moderation === '') {
                        $moderation = 'approved';
                    }
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, (int) ($item['id'] ?? 0)); ?>
                        </td>
                        <td><?php echo (int) ($item['id'] ?? 0); ?></td>
                        <td><?php echo HTMLHelper::_('date', $item['received_at'], Text::_('DATE_FORMAT_LC2')); ?></td>
                        <td><?php echo htmlspecialchars($item['local_actor_handle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($item['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="badge <?php echo $statusBadge[$status] ?? 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isReply) : ?>
                                <span class="badge <?php echo $moderationBadge[$moderation] ?? 'bg-secondary'; ?>"
                                      data-reply-moderation-id="<?php echo (int) ($item['id'] ?? 0); ?>"
                                      data-reply-moderation-state="<?php echo htmlspecialchars($moderation, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo Text::_('COM_FEDIVERSE_MODERATION_' . strtoupper($moderation)); ?>
                                </span>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(mb_substr($item['error'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="inboxitems">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>
