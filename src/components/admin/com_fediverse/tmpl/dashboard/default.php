<?php declare(strict_types=1);

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

?>

<div class="com-fediverse-dashboard">
    <h1 class="h2">
        <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_HEADING'); ?>
        <span class="badge bg-info" data-fediverse-plan-name="1">
            <?php echo htmlspecialchars($this->planLabel, ENT_QUOTES, 'UTF-8'); ?>
        </span>
    </h1>

    <p><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_INTRO'); ?></p>

    <div class="cpanel-modules fediverse-dashboard-modules mt-4">
        <div class="card-columns">
            <?php echo $this->statsModuleHtml; ?>
            <?php echo $this->diagnosticsModuleHtml; ?>
        </div>
    </div>

    <section class="alert alert-info mt-4" data-fediverse-compatibility-panel="1">
        <h2 class="h6"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_HEADING'); ?></h2>
        <p class="mb-2"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_INTRO'); ?></p>
        <ul class="mb-2">
            <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_PRESENTATION'); ?></li>
            <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_MEDIA'); ?></li>
            <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_INTERACTIONS'); ?></li>
            <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_TESTING'); ?></li>
        </ul>
        <p class="mb-0 text-muted"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_GUIDE_HINT'); ?></p>
    </section>

    <?php if ($this->isPro) : ?>
        <div class="modal fade" id="fediverse-transfer-import-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?php echo Route::_('index.php?option=com_fediverse&task=dashboard.importConfigPackage'); ?>" method="post" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h2 class="modal-title h5">
                                <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_HEADING'); ?>
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_DESC'); ?></p>
                            <p class="mb-3 text-muted"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_EXCLUDED'); ?></p>
                            <div class="mb-3">
                                <label class="form-label" for="fediverse-transfer-package">
                                    <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_FILE_LABEL'); ?>
                                </label>
                                <input
                                    class="form-control"
                                    type="file"
                                    id="fediverse-transfer-package"
                                    name="transfer_package"
                                    accept="application/json,.json"
                                    required
                                >
                            </div>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="fediverse-transfer-confirm"
                                    name="confirm_replace"
                                    value="1"
                                >
                                <label class="form-check-label" for="fediverse-transfer-confirm">
                                    <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_CONFIRM'); ?>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <?php echo Text::_('JCANCEL'); ?>
                            </button>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                data-fediverse-transfer-action="import"
                            >
                                <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_TRANSFER_IMPORT_ACTION'); ?>
                            </button>
                        </div>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
