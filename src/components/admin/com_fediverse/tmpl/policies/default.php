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
use Joomla\CMS\Session\Session;

$token = Session::getFormToken();

$policyBadge = [
    'allow' => 'bg-success',
    'block' => 'bg-danger',
];

$editItem = $this->editItem;

if (!$this->isPro) {
    $featureName = Text::_('COM_FEDIVERSE_POLICIES_TITLE');
    $expired     = $this->licenseExpired;
    require __DIR__ . '/../common/upgrade_cta.php';
    return;
}
?>
<div class="com-fediverse-policies">

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_POLICIES_HEADING'); ?></h1>

    <?php if (empty($this->items)) : ?>
        <p><?php echo Text::_('COM_FEDIVERSE_POLICIES_EMPTY'); ?></p>
    <?php else : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_FEDIVERSE_POLICIES_COL_DOMAIN'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_POLICIES_COL_POLICY'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_POLICIES_COL_REASON'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_POLICIES_COL_ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                <?php $policy = $item['policy'] ?? 'block'; ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['domain'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="badge <?php echo $policyBadge[$policy] ?? 'bg-secondary'; ?>">
                            <?php echo htmlspecialchars($policy, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($item['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a class="btn btn-sm btn-danger"
                           href="<?php echo Route::_('index.php?option=com_fediverse&task=policies.delete&domain=' . urlencode($item['domain'] ?? '') . '&' . $token . '=1'); ?>">
                            <?php echo Text::_('COM_FEDIVERSE_POLICIES_DELETE'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>

    <h2 class="h4"><?php echo Text::_('COM_FEDIVERSE_POLICIES_ADD_HEADING'); ?></h2>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&task=policies.save'); ?>" method="post" class="form-horizontal">
        <div class="mb-3">
            <label class="form-label" for="fediverse-policy-domain">
                <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_DOMAIN'); ?>
            </label>
            <input class="form-control"
                   id="fediverse-policy-domain"
                   name="domain"
                   type="text"
                   required
                   value="<?php echo htmlspecialchars($editItem['domain'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="fediverse-policy-policy">
                <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_POLICY'); ?>
            </label>
            <select class="form-select" id="fediverse-policy-policy" name="policy">
                <option value="block"<?php echo ($editItem['policy'] ?? 'block') === 'block' ? ' selected' : ''; ?>>Block</option>
                <option value="allow"<?php echo ($editItem['policy'] ?? '') === 'allow' ? ' selected' : ''; ?>>Allow</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="fediverse-policy-reason">
                <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_REASON'); ?>
            </label>
            <input class="form-control"
                   id="fediverse-policy-reason"
                   name="reason"
                   type="text"
                   value="<?php echo htmlspecialchars($editItem['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <button class="btn btn-primary" type="submit">
                <?php echo Text::_('COM_FEDIVERSE_POLICIES_SUBMIT'); ?>
            </button>
        </div>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>
