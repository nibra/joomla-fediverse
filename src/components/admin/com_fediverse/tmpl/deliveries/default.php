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
$listOrder = (string) ($orderingParts[0] ?? 'created_at');
$listDirn = strtoupper((string) ($orderingParts[1] ?? 'DESC'));
if ($listDirn !== 'ASC' && $listDirn !== 'DESC') {
    $listDirn = 'DESC';
}

$stateBadge = [
    'queued'    => 'bg-info',
    'inflight'  => 'bg-warning text-dark',
    'delivered' => 'bg-success',
    'failed'    => 'bg-danger',
    'dead'      => 'bg-secondary',
];
?>
<div class="com-fediverse-deliveries">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_HEADING'); ?></h1>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&view=deliveries'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <p><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-striped" id="deliveryList">
                <thead>
                    <tr>
                        <th width="1%" class="text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_DELIVERIES_COL_ID', 'id', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_DELIVERIES_COL_CREATED', 'created_at', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_TARGET'); ?></th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_DELIVERIES_COL_STATE', 'state', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_DELIVERIES_COL_ATTEMPTS', 'attempts', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_NEXT_RETRY'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_DELIVERIES_COL_ERROR'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                    <?php $state = $item['state'] ?? 'queued'; ?>
                    <tr>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, (int) ($item['id'] ?? 0)); ?>
                        </td>
                        <td><?php echo (int) ($item['id'] ?? 0); ?></td>
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
        <input type="hidden" name="view" value="deliveries">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>
