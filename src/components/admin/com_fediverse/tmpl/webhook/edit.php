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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \NX\Component\Fediverse\Administrator\View\Webhook\HtmlView $this */
$item = is_array($this->item ?? null) ? $this->item : [];
$selectedEvents = is_array($item['events'] ?? null) ? $item['events'] : [];
$isReadOnly = !$this->isPro;
$eventOptions = [];

foreach ($this->eventOptions as $event => $labelKey) {
    $eventOptions[] = HTMLHelper::_('select.option', $event, Text::_($labelKey));
}
?>
<div class="com-fediverse-webhook-edit">
    <h1 class="h2">
        <?php echo Text::_($this->isNew ? 'COM_FEDIVERSE_WEBHOOKS_NEW_TITLE' : 'COM_FEDIVERSE_WEBHOOKS_EDIT_TITLE'); ?>
    </h1>

    <?php if ($isReadOnly) : ?>
        <?php
        $featureName = Text::_('COM_FEDIVERSE_WEBHOOKS_TITLE');
        $expired = $this->licenseExpired;
        echo LayoutHelper::render('common.upgrade_cta', compact('featureName', 'expired'), JPATH_COMPONENT_ADMINISTRATOR . '/tmpl');
        ?>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php', false); ?>" method="post" id="adminForm" name="adminForm">
        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="webhook">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
        <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1">

        <section class="card mb-0">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="fediverse-webhook-name">
                        <?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_NAME'); ?>
                    </label>
                    <input
                        class="form-control"
                        id="fediverse-webhook-name"
                        name="name"
                        type="text"
                        <?php echo $isReadOnly ? 'disabled' : 'required'; ?>
                        value="<?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="fediverse-webhook-target-url">
                        <?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_TARGET_URL'); ?>
                    </label>
                    <input
                        class="form-control"
                        id="fediverse-webhook-target-url"
                        name="target_url"
                        type="url"
                        <?php echo $isReadOnly ? 'disabled' : 'required'; ?>
                        value="<?php echo htmlspecialchars((string) ($item['target_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="fediverse-webhook-secret">
                        <?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_SECRET'); ?>
                    </label>
                    <input
                        class="form-control"
                        id="fediverse-webhook-secret"
                        name="secret"
                        type="text"
                        <?php echo $isReadOnly ? 'disabled' : ''; ?>
                        value=""
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="fediverse-webhook-events">
                        <?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_EVENTS'); ?>
                    </label>
                    <?php echo LayoutHelper::render(
                        'joomla.form.field.tag',
                        [
                            'id' => 'fediverse-webhook-events',
                            'name' => 'events[]',
                            'value' => $selectedEvents,
                            'options' => $eventOptions,
                            'multiple' => true,
                            'required' => true,
                            'readonly' => false,
                            'disabled' => $isReadOnly,
                            'class' => '',
                            'hint' => Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_EVENTS_HINT'),
                            'autocomplete' => 'off',
                            'autofocus' => false,
                            'onchange' => '',
                            'onclick' => '',
                            'pattern' => '',
                            'spellcheck' => false,
                            'validate' => '',
                            'dataAttribute' => '',
                            'allowCustom' => false,
                            'remoteSearch' => false,
                            'minTermLength' => 0,
                        ]
                    ); ?>
                </div>

                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        id="fediverse-webhook-enabled"
                        name="is_enabled"
                        type="checkbox"
                        value="1"
                        <?php echo (bool) ($item['is_enabled'] ?? true) ? 'checked' : ''; ?>
                        <?php echo $isReadOnly ? 'disabled' : ''; ?>
                    >
                    <label class="form-check-label" for="fediverse-webhook-enabled">
                        <?php echo Text::_('COM_FEDIVERSE_WEBHOOKS_FIELD_ENABLED'); ?>
                    </label>
                </div>
            </div>
        </section>
    </form>
</div>
