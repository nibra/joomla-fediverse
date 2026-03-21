<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

?>

<div class="com-fediverse-dashboard">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_HEADING'); ?></h1>

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <p><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_INTRO'); ?></p>

    <?php
    $actors = $this->summary['actors'] ?? ['local' => 0, 'remote' => 0];
    $deliveries = $this->summary['deliveries'] ?? [
        'queued' => 0,
        'delivering' => 0,
        'delivered' => 0,
        'failed' => 0,
    ];
    ?>

    <div class="fediverse-summary">
        <div class="fediverse-metric" data-fediverse-metric="actors-local" data-fediverse-count="<?php echo (int) $actors['local']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_ACTORS_LOCAL_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $actors['local']; ?></span>
        </div>
        <div class="fediverse-metric" data-fediverse-metric="actors-remote" data-fediverse-count="<?php echo (int) $actors['remote']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_ACTORS_REMOTE_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $actors['remote']; ?></span>
        </div>
        <div class="fediverse-metric" data-fediverse-metric="delivery-queued" data-fediverse-count="<?php echo (int) $deliveries['queued']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_QUEUED_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $deliveries['queued']; ?></span>
        </div>
        <div class="fediverse-metric" data-fediverse-metric="delivery-delivering" data-fediverse-count="<?php echo (int) $deliveries['delivering']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_DELIVERING_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $deliveries['delivering']; ?></span>
        </div>
        <div class="fediverse-metric" data-fediverse-metric="delivery-delivered" data-fediverse-count="<?php echo (int) $deliveries['delivered']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_DELIVERED_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $deliveries['delivered']; ?></span>
        </div>
        <div class="fediverse-metric" data-fediverse-metric="delivery-failed" data-fediverse-count="<?php echo (int) $deliveries['failed']; ?>">
            <span class="metric-label"><?php echo Text::_('COM_FEDIVERSE_DASHBOARD_DELIVERY_FAILED_LABEL'); ?></span>
            <span class="metric-value"><?php echo (int) $deliveries['failed']; ?></span>
        </div>
    </div>

    <div class="fediverse-key-rotation">
        <h2 class="h4"><?php echo Text::_('COM_FEDIVERSE_KEY_ROTATION_HEADING'); ?></h2>
        <p><?php echo Text::_('COM_FEDIVERSE_KEY_ROTATION_DESC'); ?></p>
        <form action="<?php echo Route::_('index.php?option=com_fediverse&task=keys.rotate'); ?>" method="post">
            <div class="control-group">
                <label class="control-label" for="fediverse-rotate-user-id">
                    <?php echo Text::_('COM_FEDIVERSE_KEY_ROTATION_USER_ID_LABEL'); ?>
                </label>
                <div class="controls">
                    <input id="fediverse-rotate-user-id" name="user_id" type="number" min="1" step="1">
                </div>
            </div>
            <div class="control-group">
                <label class="control-label" for="fediverse-rotate-handle">
                    <?php echo Text::_('COM_FEDIVERSE_KEY_ROTATION_HANDLE_LABEL'); ?>
                </label>
                <div class="controls">
                    <input id="fediverse-rotate-handle" name="handle" type="text">
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <button class="btn btn-primary" type="submit">
                        <?php echo Text::_('COM_FEDIVERSE_KEY_ROTATION_SUBMIT'); ?>
                    </button>
                </div>
            </div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
</div>
