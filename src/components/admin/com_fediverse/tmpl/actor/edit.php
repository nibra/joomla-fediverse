<?php
/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \NX\Component\Fediverse\Administrator\View\Actor\HtmlView $this */
$actor = $this->actor;
?>
<div class="com-fediverse-actor-edit">

    <?php require __DIR__ . '/../_navbar.php'; ?>

    <h1 class="h2">
        <?php echo Text::_('COM_FEDIVERSE_ACTORS_EDIT_HEADING'); ?>:
        <?php echo htmlspecialchars($actor->preferredUsername, ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <form action="<?php echo Route::_('index.php', false); ?>" method="post">
        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="task" value="actors.save">
        <input type="hidden" name="id" value="<?php echo (int) $actor->id; ?>">
        <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1">

        <div class="mb-3">
            <label for="actor_type" class="form-label">
                <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACTOR_TYPE'); ?>
            </label>
            <select id="actor_type" name="actor_type" class="form-select">
                <option value="Person"<?php echo $actor->actorType === 'Person' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACTOR_TYPE_PERSON'); ?>
                </option>
                <option value="Service"<?php echo $actor->actorType === 'Service' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACTOR_TYPE_SERVICE'); ?>
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label for="object_type" class="form-label">
                <?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE'); ?>
            </label>
            <select id="object_type" name="object_type" class="form-select">
                <option value="Note"<?php echo $actor->objectType === 'Note' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE_NOTE'); ?>
                </option>
                <option value="Article"<?php echo $actor->objectType === 'Article' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE_ARTICLE'); ?>
                </option>
                <option value="Image"<?php echo $actor->objectType === 'Image' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE_IMAGE'); ?>
                </option>
                <option value="Video"<?php echo $actor->objectType === 'Video' ? ' selected' : ''; ?>>
                    <?php echo Text::_('COM_FEDIVERSE_ACTORS_OBJECT_TYPE_VIDEO'); ?>
                </option>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <?php echo Text::_('COM_FEDIVERSE_ACTORS_SAVE'); ?>
            </button>
            <a class="btn btn-secondary"
               href="<?php echo Route::_('index.php?option=com_fediverse&view=actors'); ?>">
                <?php echo Text::_('JCANCEL'); ?>
            </a>
        </div>
    </form>

</div>
