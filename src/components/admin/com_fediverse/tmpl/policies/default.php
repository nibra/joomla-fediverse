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
$listOrder = (string) ($orderingParts[0] ?? 'domain');
$listDirn = strtoupper((string) ($orderingParts[1] ?? 'ASC'));
if ($listDirn !== 'ASC' && $listDirn !== 'DESC') {
    $listDirn = 'ASC';
}

$policyBadge = [
    'allow' => 'bg-success',
    'block' => 'bg-danger',
];

$isReadOnly = !$this->isPro;
?>
<div class="com-fediverse-policies">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_POLICIES_HEADING'); ?></h1>

    <?php if ($isReadOnly) : ?>
        <?php
        $featureName = Text::_('COM_FEDIVERSE_POLICIES_TITLE');
        $expired     = $this->licenseExpired;
        require __DIR__ . '/../common/upgrade_cta.php';
        ?>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&view=policies'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <p><?php echo Text::_('COM_FEDIVERSE_POLICIES_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-striped" id="policiesList">
                <thead>
                    <tr>
                        <th width="1%" class="text-center">
                            <?php if (!$isReadOnly) : ?>
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            <?php endif; ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_POLICIES_COL_DOMAIN', 'domain', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_POLICIES_COL_POLICY', 'policy', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_POLICIES_COL_REASON'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                    <?php $policy = $item['policy'] ?? 'block'; ?>
                    <tr>
                        <td class="text-center">
                            <?php if (!$isReadOnly) : ?>
                                <?php echo HTMLHelper::_('grid.id', $i, (string) ($item['domain'] ?? '')); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isReadOnly) : ?>
                                <?php echo htmlspecialchars($item['domain'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            <?php else : ?>
                                <a href="<?php echo Route::_('index.php?option=com_fediverse&view=policy&domain=' . urlencode((string) ($item['domain'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars($item['domain'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $policyBadge[$policy] ?? 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars($policy, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($item['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="policies">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
