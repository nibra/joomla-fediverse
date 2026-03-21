<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_profile
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$profile = $profile ?? ['available' => false];
if (!($profile['available'] ?? false)) {
    return;
}

$moduleclass_sfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES);
$moduleId = 'mod-fediverse-profile-' . (int) $module->id;
$acct = (string) ($profile['acct'] ?? '');
$actorUrl = (string) ($profile['actor_url'] ?? '');
$displayHandle = (string) ($profile['display_handle'] ?? '');
?>

<div id="<?php echo $moduleId; ?>" class="mod-fediverse-profile<?php echo $moduleclass_sfx !== '' ? ' ' . $moduleclass_sfx : ''; ?>">
    <div class="mod-fediverse-profile__handle">
        <span class="mod-fediverse-profile__label"><?php echo Text::_('MOD_FEDIVERSE_PROFILE_LABEL_HANDLE'); ?></span>
        <span class="mod-fediverse-profile__value"><?php echo htmlspecialchars($displayHandle, ENT_QUOTES); ?></span>
    </div>

    <?php if ($params->get('show_actor_link', 1) && $actorUrl !== '') : ?>
        <div class="mod-fediverse-profile__actor">
            <a class="mod-fediverse-profile__actor-link" rel="me noopener" href="<?php echo htmlspecialchars($actorUrl, ENT_QUOTES); ?>">
                <?php echo Text::_('MOD_FEDIVERSE_PROFILE_VIEW_PROFILE'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($params->get('show_follow_link', 1) && $acct !== '') : ?>
        <form class="mod-fediverse-profile__follow" id="<?php echo $moduleId; ?>-follow" data-acct="<?php echo htmlspecialchars($acct, ENT_QUOTES); ?>">
            <label class="mod-fediverse-profile__label" for="<?php echo $moduleId; ?>-instance">
                <?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_LABEL'); ?>
            </label>
            <div class="mod-fediverse-profile__follow-row">
                <input
                    class="mod-fediverse-profile__input"
                    id="<?php echo $moduleId; ?>-instance"
                    name="instance"
                    type="url"
                    inputmode="url"
                    placeholder="<?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_PLACEHOLDER'); ?>"
                    aria-label="<?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_LABEL'); ?>"
                    required
                />
                <button class="mod-fediverse-profile__button" type="submit">
                    <?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_BUTTON'); ?>
                </button>
            </div>
            <div class="mod-fediverse-profile__hint">
                <?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_HELP'); ?>
            </div>
            <noscript>
                <div class="mod-fediverse-profile__hint">
                    <?php echo Text::_('MOD_FEDIVERSE_PROFILE_FOLLOW_NOSCRIPT'); ?>
                </div>
            </noscript>
        </form>
        <script>
            (function() {
                var form = document.getElementById('<?php echo $moduleId; ?>-follow');
                if (!form) {
                    return;
                }
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    var input = form.querySelector('input[name="instance"]');
                    if (!input) {
                        return;
                    }
                    var instance = (input.value || '').trim();
                    if (!instance) {
                        return;
                    }
                    if (!/^https?:\/\//i.test(instance)) {
                        instance = 'https://' + instance;
                    }
                    instance = instance.replace(/\/+$/, '');
                    var acct = form.getAttribute('data-acct');
                    if (!acct) {
                        return;
                    }
                    var target = instance + '/authorize_interaction?uri=' + encodeURIComponent(acct);
                    window.open(target, '_blank', 'noopener');
                });
            })();
        </script>
    <?php endif; ?>
</div>
