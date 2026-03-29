<?php
/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \NX\Component\Fediverse\Administrator\View\Policy\HtmlView $this */
$item = is_array($this->item ?? null) ? $this->item : [];
$isReadOnly = !$this->isPro;
?>
<div class="com-fediverse-policy-edit">
    <h1 class="h2">
        <?php echo Text::_($this->isNew ? 'COM_FEDIVERSE_POLICIES_NEW_TITLE' : 'COM_FEDIVERSE_POLICIES_EDIT_TITLE'); ?>
    </h1>

    <?php if ($isReadOnly) : ?>
        <?php
        $featureName = Text::_('COM_FEDIVERSE_POLICIES_TITLE');
        $expired = $this->licenseExpired;
        echo LayoutHelper::render('common.upgrade_cta', compact('featureName', 'expired'), JPATH_COMPONENT_ADMINISTRATOR . '/tmpl');
        ?>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php', false); ?>" method="post" id="adminForm" name="adminForm">
        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="policy">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="original_domain" value="<?php echo htmlspecialchars((string) ($item['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1">

        <section class="card mb-0">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="fediverse-policy-domain">
                        <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_DOMAIN'); ?>
                    </label>
                    <input
                        class="form-control"
                        id="fediverse-policy-domain"
                        name="domain"
                        type="text"
                        <?php echo $isReadOnly ? 'disabled' : 'required'; ?>
                        value="<?php echo htmlspecialchars((string) ($item['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="fediverse-policy-policy">
                        <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_POLICY'); ?>
                    </label>
                    <select class="form-select" id="fediverse-policy-policy" name="policy"<?php echo $isReadOnly ? ' disabled' : ''; ?>>
                        <option value="block"<?php echo ((string) ($item['policy'] ?? 'block')) === 'block' ? ' selected' : ''; ?>>
                            Block
                        </option>
                        <option value="allow"<?php echo ((string) ($item['policy'] ?? '')) === 'allow' ? ' selected' : ''; ?>>
                            Allow
                        </option>
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label" for="fediverse-policy-reason">
                        <?php echo Text::_('COM_FEDIVERSE_POLICIES_FIELD_REASON'); ?>
                    </label>
                    <input
                        class="form-control"
                        id="fediverse-policy-reason"
                        name="reason"
                        type="text"
                        <?php echo $isReadOnly ? 'disabled' : ''; ?>
                        value="<?php echo htmlspecialchars((string) ($item['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>
            </div>
        </section>
    </form>
</div>
