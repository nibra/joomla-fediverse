<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_diagnostics
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$tiles = isset($tiles) && is_array($tiles) ? $tiles : [];
$publicBaseUrl = isset($publicBaseUrl) && is_string($publicBaseUrl) ? $publicBaseUrl : '';
$schedulerTaskSettings = isset($schedulerTaskSettings) && is_array($schedulerTaskSettings) ? $schedulerTaskSettings : [];
$isFediverseDashboard = !empty($isFediverseDashboard);
$statusClass = [
    'pass' => 'success',
    'warn' => 'warning',
    'fail' => 'danger',
];
$schedulerTaskLabelMap = [
    'fediverse.inbox_worker'    => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_INBOX_WORKER'),
    'fediverse.delivery_worker' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_DELIVERY_WORKER'),
    'fediverse.key_rotation'    => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_KEY_ROTATION'),
    'fediverse.cleanup'         => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_CLEANUP'),
];

Factory::getApplication()->getDocument()->addStyleDeclaration(
    '.fediverse-diagnostics-quickicons .quickicon{width:100%;max-width:none;margin:0 0 1rem;}'
    . '.fediverse-diagnostics-notification-form{display:block;width:100%;}'
    . '.fediverse-diagnostics-notification-button{display:block;width:100%;padding:0;border:0;background:none;text-align:left;}'
    . '.fediverse-diagnostics-notification-button.success,.fediverse-diagnostics-notification-button.warning,.fediverse-diagnostics-notification-button.danger{border-radius:inherit;}'
    . '.fediverse-diagnostics-quickicons .quickicon a,.fediverse-diagnostics-notification-button{border-radius:.5rem;}'
    . '.fediverse-diagnostics-quickicons .quickicon-icon{font-size:1.5rem;line-height:1;}'
);

if ($isFediverseDashboard) {
    HTMLHelper::_('bootstrap.modal', '#fediverse-public-base-url-modal');
    HTMLHelper::_('bootstrap.modal', '#fediverse-scheduler-tasks-modal');
}
?>
<?php if (!empty($tiles)) : ?>
    <section class="fediverse-diagnostics-module" data-fediverse-diagnostics-module="1">
        <nav class="quick-icons px-3 pb-3 fediverse-diagnostics-quickicons" aria-label="<?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_HEADING'); ?>">
            <ul class="nav flex-wrap">
                <?php foreach ($tiles as $tile) : ?>
                    <?php
                    $id = (string) ($tile['id'] ?? '');
                    $status = (string) ($tile['status'] ?? 'warn');
                    $message = (string) ($tile['message'] ?? '');
                    $icon = (string) ($tile['icon'] ?? 'icon-info-circle');
                    $dashboardAction = (array) ($tile['dashboardAction'] ?? []);
                    $homeLink = (string) ($tile['homeLink'] ?? Route::_('index.php?option=com_fediverse&view=dashboard'));
                    $tileClass = (string) ($statusClass[$status] ?? 'warning');
                    ?>
                    <li class="quickicon quickicon-single" data-diagnostic-tile="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (!$isFediverseDashboard) : ?>
                            <a
                                href="<?php echo htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8'); ?>"
                                class="<?php echo htmlspecialchars($tileClass, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <div class="quickicon-info">
                                    <div class="quickicon-icon">
                                        <div class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                                    </div>
                                </div>
                                <div class="quickicon-name d-flex align-items-center">
                                    <span class="j-links-link"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                        <?php else : ?>
                            <?php if (($dashboardAction['type'] ?? '') === 'modal') : ?>
                                <a
                                    href="<?php echo htmlspecialchars((string) ($dashboardAction['target'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                                    class="<?php echo htmlspecialchars($tileClass, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-diagnostic-action="<?php echo htmlspecialchars((string) ($dashboardAction['selector'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="<?php echo htmlspecialchars((string) ($dashboardAction['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <div class="quickicon-info">
                                        <div class="quickicon-icon">
                                            <div class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                                        </div>
                                    </div>
                                    <div class="quickicon-name d-flex align-items-center">
                                        <span class="j-links-link"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            <?php elseif (($dashboardAction['type'] ?? '') === 'form') : ?>
                                <form action="<?php echo htmlspecialchars((string) ($dashboardAction['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" method="post" class="fediverse-diagnostics-notification-form">
                                    <button
                                        type="submit"
                                        class="<?php echo htmlspecialchars($tileClass . ' fediverse-diagnostics-notification-button', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-diagnostic-action="<?php echo htmlspecialchars((string) ($dashboardAction['selector'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <div class="quickicon-info">
                                            <div class="quickicon-icon">
                                                <div class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                                            </div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                            <span class="j-links-link"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </button>
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                </form>
                            <?php else : ?>
                                <a
                                    class="<?php echo htmlspecialchars($tileClass, ENT_QUOTES, 'UTF-8'); ?>"
                                    href="<?php echo htmlspecialchars((string) ($dashboardAction['href'] ?? $homeLink), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-diagnostic-action="<?php echo htmlspecialchars((string) ($dashboardAction['selector'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <div class="quickicon-info">
                                        <div class="quickicon-icon">
                                            <div class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                                        </div>
                                    </div>
                                    <div class="quickicon-name d-flex align-items-center">
                                        <span class="j-links-link"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if ($isFediverseDashboard) : ?>
            <div class="modal fade" id="fediverse-public-base-url-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="<?php echo Route::_('index.php?option=com_fediverse&task=diagnostics.setPublicBaseUrl'); ?>" method="post">
                            <div class="modal-header">
                                <h2 class="modal-title h5">
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_PUBLIC_BASE_URL_MODAL_TITLE'); ?>
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label" for="fediverse-public-base-url-input">
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_PUBLIC_BASE_URL_FIELD_LABEL'); ?>
                                </label>
                                <input
                                    class="form-control"
                                    id="fediverse-public-base-url-input"
                                    name="public_base_url"
                                    type="url"
                                    required
                                    value="<?php echo htmlspecialchars($publicBaseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <small class="text-muted">
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_PUBLIC_BASE_URL_FIELD_DESC'); ?>
                                </small>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <?php echo Text::_('JCANCEL'); ?>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo Text::_('JSAVE'); ?>
                                </button>
                            </div>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="fediverse-scheduler-tasks-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form action="<?php echo Route::_('index.php?option=com_fediverse&task=diagnostics.saveSchedulerTasks'); ?>" method="post">
                            <div class="modal-header">
                                <h2 class="modal-title h5"><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_MODAL_TITLE'); ?></h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                            </div>
                            <div class="modal-body">
                                <?php foreach ($schedulerTaskSettings as $taskType => $taskSettings) : ?>
                                    <?php
                                    $taskTypeString = (string) $taskType;
                                    $taskSlug = preg_replace('/[^a-z0-9]+/i', '-', $taskTypeString) ?? 'task';
                                    $taskTitle = (string) ($schedulerTaskLabelMap[$taskTypeString] ?? ($taskSettings['title'] ?? $taskTypeString));
                                    $isEnabled = ((int) ($taskSettings['enabled'] ?? 0)) === 1;
                                    $ruleType = (string) ($taskSettings['ruleType'] ?? 'interval-minutes');
                                    $interval = max(1, (int) ($taskSettings['intervalMinutes'] ?? 5));
                                    ?>
                                    <section class="border rounded p-3 mb-3" data-scheduler-task="<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>">
                                        <h3 class="h6 mb-2"><?php echo htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="small text-muted mb-3"><?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        role="switch"
                                                        id="fediverse-task-enabled-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>"
                                                        name="tasks[<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>][enabled]"
                                                        value="1"
                                                        <?php echo $isEnabled ? 'checked' : ''; ?>
                                                    >
                                                    <label class="form-check-label" for="fediverse-task-enabled-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_FIELD_ENABLED'); ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="fediverse-task-rule-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_FIELD_RULE'); ?>
                                                </label>
                                                <select
                                                    class="form-select"
                                                    id="fediverse-task-rule-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>"
                                                    name="tasks[<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>][rule_type]"
                                                >
                                                    <option value="interval-minutes"<?php echo $ruleType === 'interval-minutes' ? ' selected' : ''; ?>>
                                                        <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_RULE_INTERVAL'); ?>
                                                    </option>
                                                    <option value="manual"<?php echo $ruleType === 'manual' ? ' selected' : ''; ?>>
                                                        <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_RULE_MANUAL'); ?>
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="fediverse-task-interval-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_FIELD_INTERVAL_MINUTES'); ?>
                                                </label>
                                                <input
                                                    class="form-control"
                                                    id="fediverse-task-interval-<?php echo htmlspecialchars($taskSlug, ENT_QUOTES, 'UTF-8'); ?>"
                                                    name="tasks[<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>][interval_minutes]"
                                                    type="number"
                                                    min="1"
                                                    max="10080"
                                                    step="1"
                                                    value="<?php echo (int) $interval; ?>"
                                                >
                                            </div>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <?php echo Text::_('JCANCEL'); ?>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo Text::_('JSAVE'); ?>
                                </button>
                            </div>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
