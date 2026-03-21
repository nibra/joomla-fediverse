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
?>
<div class="com-fediverse-actors">

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_ACTORS_HEADING'); ?></h1>

    <?php if (!$this->isPro) : ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
            <span class="icon-info-circle" aria-hidden="true"></span>
            <span>
                <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_FREE', $this->actorLimit); ?>
                &mdash; <?php echo Text::_('COM_FEDIVERSE_LICENSE_PRO_REQUIRED'); ?>
            </span>
        </div>
    <?php elseif (count($this->items) >= $this->actorLimit && $this->actorLimit !== PHP_INT_MAX) : ?>
        <div class="alert alert-warning mb-3">
            <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_REACHED', $this->actorLimit); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($this->items)) : ?>
        <p><?php echo Text::_('COM_FEDIVERSE_ACTORS_EMPTY'); ?></p>
    <?php else : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_HANDLE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_USER_ID'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_ACTOR_TYPE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_ENABLED'); ?></th>
                    <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['preferred_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) ($item['user_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($item['actor_type'] ?? 'Person', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($item['object_type'] ?? 'Note', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if (!empty($item['is_enabled'])) : ?>
                            <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-danger"><?php echo Text::_('JNO'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-secondary"
                           href="<?php echo Route::_('index.php?option=com_fediverse&view=actor&id=' . (int) $item['id']); ?>">
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_EDIT'); ?>
                        </a>
                        <?php if (empty($item['is_enabled'])) : ?>
                            <a class="btn btn-sm btn-success"
                               href="<?php echo Route::_('index.php?option=com_fediverse&task=actors.enable&id=' . (int) $item['id'] . '&' . $token . '=1'); ?>">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_ENABLE'); ?>
                            </a>
                        <?php else : ?>
                            <a class="btn btn-sm btn-warning"
                               href="<?php echo Route::_('index.php?option=com_fediverse&task=actors.disable&id=' . (int) $item['id'] . '&' . $token . '=1'); ?>">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_DISABLE'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
