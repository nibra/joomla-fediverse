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

$statusClass = [
    'pass' => 'bg-success',
    'warn' => 'bg-warning text-dark',
    'fail' => 'bg-danger',
];

$statusLabel = [
    'pass' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_STATUS_PASS'),
    'warn' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_STATUS_WARN'),
    'fail' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_STATUS_FAIL'),
];

$titleMap = [
    'public_base_url' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_CHECK_PUBLIC_BASE_URL_TITLE'),
    'plugins'         => Text::_('COM_FEDIVERSE_DIAGNOSTICS_CHECK_PLUGINS_TITLE'),
    'scheduler'       => Text::_('COM_FEDIVERSE_DIAGNOSTICS_CHECK_SCHEDULER_TITLE'),
    'actors'          => Text::_('COM_FEDIVERSE_DIAGNOSTICS_CHECK_ACTORS_TITLE'),
];

$schedulerTaskLabelMap = [
    'fediverse.inbox_worker'    => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_INBOX_WORKER'),
    'fediverse.delivery_worker' => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_DELIVERY_WORKER'),
    'fediverse.key_rotation'    => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_KEY_ROTATION'),
    'fediverse.cleanup'         => Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_TASK_CLEANUP'),
];
?>

<section class="card mt-4 com-fediverse-diagnostics" data-fediverse-diagnostics-panel="1">
    <div class="card-body">
        <h2 class="h4"><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_HEADING'); ?></h2>
        <p><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_INTRO'); ?></p>

        <section class="alert alert-info mb-4" data-fediverse-compatibility-panel="1">
            <h3 class="h6"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_HEADING'); ?></h3>
            <p class="mb-2"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_INTRO'); ?></p>
            <ul class="mb-2">
                <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_PRESENTATION'); ?></li>
                <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_MEDIA'); ?></li>
                <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_INTERACTIONS'); ?></li>
                <li><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_POINT_TESTING'); ?></li>
            </ul>
            <p class="mb-0 text-muted"><?php echo Text::_('COM_FEDIVERSE_COMPATIBILITY_GUIDE_HINT'); ?></p>
        </section>

        <p>
            <strong><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_OVERALL_LABEL'); ?>:</strong>
            <span
                class="badge <?php echo $statusClass[$this->overallStatus] ?? 'bg-secondary'; ?>"
                data-diagnostic-overall="<?php echo htmlspecialchars($this->overallStatus, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <?php echo htmlspecialchars($statusLabel[$this->overallStatus] ?? strtoupper($this->overallStatus), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </p>

        <ol id="fediverse-diagnostics-checks" class="list-group list-group-numbered">
            <?php foreach ($this->checks as $check) : ?>
                <?php
                $id      = (string) ($check['id'] ?? '');
                $status  = (string) ($check['status'] ?? '');
                $message = (string) ($check['message'] ?? '');
                $impact  = (string) ($check['impact'] ?? '');
                $remediation = (string) ($check['remediation'] ?? '');
                ?>
                <li
                    class="list-group-item d-flex justify-content-between align-items-start"
                    data-diagnostic-check="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars($titleMap[$id] ?? $id, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <p class="mb-1 small"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($impact !== '') : ?>
                            <p class="mb-1 small text-muted" data-diagnostic-impact="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                <strong><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_IMPACT_LABEL'); ?>:</strong>
                                <?php echo htmlspecialchars($impact, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($remediation !== '') : ?>
                            <p class="mb-0 small text-muted" data-diagnostic-remediation="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                <strong><?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_REMEDIATION_LABEL'); ?>:</strong>
                                <?php echo htmlspecialchars($remediation, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <div class="mt-2">
                            <?php if ($id === 'public_base_url') : ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-diagnostic-action="configure-public-base-url"
                                    data-bs-toggle="modal"
                                    data-bs-target="#fediverse-public-base-url-modal"
                                >
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_CONFIGURE_PUBLIC_BASE_URL'); ?>
                                </button>
                            <?php elseif ($id === 'plugins') : ?>
                                <form action="<?php echo Route::_('index.php?option=com_fediverse&task=diagnostics.activateCorePlugins'); ?>" method="post" class="d-inline">
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-primary"
                                        data-diagnostic-action="activate-plugins"
                                    >
                                        <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_ACTIVATE_PLUGINS'); ?>
                                    </button>
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                </form>
                            <?php elseif ($id === 'scheduler') : ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-diagnostic-action="configure-scheduler-tasks"
                                    data-bs-toggle="modal"
                                    data-bs-target="#fediverse-scheduler-tasks-modal"
                                >
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_CONFIGURE_SCHEDULER'); ?>
                                </button>
                            <?php elseif ($id === 'actors') : ?>
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="<?php echo Route::_('index.php?option=com_fediverse&view=actors'); ?>"
                                    data-diagnostic-action="open-actors"
                                >
                                    <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_ACTION_OPEN_ACTORS'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span
                        class="badge <?php echo $statusClass[$status] ?? 'bg-secondary'; ?> rounded-pill"
                        data-diagnostic-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <?php echo htmlspecialchars($statusLabel[$status] ?? strtoupper($status), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var wireModal = function (triggerSelector, modalId) {
        var trigger = document.querySelector(triggerSelector);
        var modalEl = document.getElementById(modalId);
        var closeButtons = modalEl ? modalEl.querySelectorAll('[data-bs-dismiss="modal"]') : [];

        if (!trigger || !modalEl) {
            return;
        }

        var showFallback = function () {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.removeAttribute('aria-hidden');
        };

        var hideFallback = function () {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
        };

        trigger.addEventListener('click', function () {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }

            showFallback();
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    return;
                }

                hideFallback();
            });
        });
    };

    wireModal('[data-diagnostic-action="configure-public-base-url"]', 'fediverse-public-base-url-modal');
    wireModal('[data-diagnostic-action="configure-scheduler-tasks"]', 'fediverse-scheduler-tasks-modal');

    var schedulerRuleTypeFields = document.querySelectorAll('[data-scheduler-rule-type]');

    schedulerRuleTypeFields.forEach(function (field) {
        var taskType = field.getAttribute('data-scheduler-rule-type');
        var intervalContainer = document.querySelector('[data-scheduler-interval-for="' + taskType + '"]');

        if (!intervalContainer) {
            return;
        }

        var intervalInput = intervalContainer.querySelector('input');
        var syncIntervalVisibility = function () {
            var isIntervalMode = field.value === 'interval-minutes';
            intervalContainer.style.display = isIntervalMode ? '' : 'none';

            if (intervalInput) {
                intervalInput.disabled = !isIntervalMode;
            }
        };

        field.addEventListener('change', syncIntervalVisibility);
        syncIntervalVisibility();
    });
});
</script>

<div class="modal fade" id="fediverse-scheduler-tasks-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?php echo Route::_('index.php?option=com_fediverse&task=diagnostics.saveSchedulerTasks'); ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title h5">
                        <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_MODAL_TITLE'); ?>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($this->schedulerTaskSettings as $taskType => $taskSettings) : ?>
                        <?php
                        $taskTypeString = (string) $taskType;
                        $taskSlug       = preg_replace('/[^a-z0-9]+/i', '-', $taskTypeString) ?? 'task';
                        $taskTitle      = (string) ($schedulerTaskLabelMap[$taskTypeString] ?? ($taskSettings['title'] ?? $taskTypeString));
                        $isEnabled      = ((int) ($taskSettings['enabled'] ?? 0)) === 1;
                        $ruleType       = (string) ($taskSettings['ruleType'] ?? 'interval-minutes');
                        $interval       = max(1, (int) ($taskSettings['intervalMinutes'] ?? 5));
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
                                        data-scheduler-rule-type="<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <option value="interval-minutes"<?php echo $ruleType === 'interval-minutes' ? ' selected' : ''; ?>>
                                            <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_RULE_INTERVAL'); ?>
                                        </option>
                                        <option value="manual"<?php echo $ruleType === 'manual' ? ' selected' : ''; ?>>
                                            <?php echo Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_RULE_MANUAL'); ?>
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4" data-scheduler-interval-for="<?php echo htmlspecialchars($taskTypeString, ENT_QUOTES, 'UTF-8'); ?>">
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
                        value="<?php echo htmlspecialchars((string) $this->publicBaseUrl, ENT_QUOTES, 'UTF-8'); ?>"
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
