<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_reactions
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$reactions = $reactions ?? ['available' => false];
if (!($reactions['available'] ?? false)) {
    return;
}

$moduleclass_sfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES);
$moduleId        = 'mod-fediverse-reactions-' . (int) $module->id;
$counts          = (array) ($reactions['counts'] ?? []);
$replies         = (array) ($reactions['replies'] ?? []);
$renderedComments = trim((string) ($reactions['rendered_comments'] ?? ''));
$showReactions   = (bool) ($reactions['show_reactions'] ?? true);
$showReplies     = (bool) ($reactions['show_replies'] ?? true);
$showLikeButton  = (int) $params->get('show_like_button', 1) === 1;
$showBoostButton = (int) $params->get('show_boost_button', 1) === 1;
$postUrl         = htmlspecialchars((string) ($reactions['post_url'] ?? ''), ENT_QUOTES);
$modalId         = 'fediverse-interact-modal-' . (int) $module->id;

$likes   = (int) ($counts['likes'] ?? 0);
$boosts  = (int) ($counts['boosts'] ?? 0);
$nReplies = (int) ($counts['replies'] ?? 0);

$hasReactions = $showReactions && ($likes > 0 || $boosts > 0 || $nReplies > 0);
$hasRenderedComments = $showReplies && $renderedComments !== '';
$hasReplies   = $showReplies && count($replies) > 0;
$hasButtons   = $showLikeButton || $showBoostButton;

if (!$hasReactions && !$hasRenderedComments && !$hasReplies && !$hasButtons) {
    return;
}
?>
<div id="<?php echo $moduleId; ?>" class="mod-fediverse-reactions<?php echo $moduleclass_sfx !== '' ? ' ' . $moduleclass_sfx : ''; ?>">
    <h3 class="mod-fediverse-reactions__heading"><?php echo Text::_('MOD_FEDIVERSE_REACTIONS_HEADING'); ?></h3>

    <?php if ($hasReactions) : ?>
        <div class="mod-fediverse-reactions__counts" aria-label="<?php echo Text::_('MOD_FEDIVERSE_REACTIONS_HEADING'); ?>">
            <?php if ($likes > 0) : ?>
                <span class="mod-fediverse-reactions__count mod-fediverse-reactions__count--likes">
                    &#9829; <?php echo sprintf(Text::_('MOD_FEDIVERSE_REACTIONS_COUNT_LIKES'), $likes); ?>
                </span>
            <?php endif; ?>
            <?php if ($boosts > 0) : ?>
                <span class="mod-fediverse-reactions__count mod-fediverse-reactions__count--boosts">
                    &#8635; <?php echo sprintf(Text::_('MOD_FEDIVERSE_REACTIONS_COUNT_BOOSTS'), $boosts); ?>
                </span>
            <?php endif; ?>
            <?php if ($nReplies > 0) : ?>
                <span class="mod-fediverse-reactions__count mod-fediverse-reactions__count--replies">
                    &#128172; <?php echo sprintf(Text::_('MOD_FEDIVERSE_REACTIONS_COUNT_REPLIES'), $nReplies); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($hasRenderedComments) : ?>
        <div class="mod-fediverse-reactions__comments-renderer" data-fediverse-comments-renderer="1">
            <?php echo $renderedComments; ?>
        </div>
    <?php elseif ($hasReplies) : ?>
        <section class="mod-fediverse-reactions__replies" aria-label="<?php echo Text::_('MOD_FEDIVERSE_REACTIONS_REPLIES_HEADING'); ?>">
            <h4 class="mod-fediverse-reactions__replies-heading"><?php echo Text::_('MOD_FEDIVERSE_REACTIONS_REPLIES_HEADING'); ?></h4>
            <ul class="mod-fediverse-reactions__reply-list">
                <?php foreach ($replies as $reply) : ?>
                    <?php
                    $actorLabel  = htmlspecialchars((string) ($reply['actor_label'] ?? ''), ENT_QUOTES);
                    $actorUrl    = htmlspecialchars((string) ($reply['actor_url'] ?? ''), ENT_QUOTES);
                    $content     = htmlspecialchars((string) ($reply['content'] ?? ''), ENT_QUOTES);
                    $createdAt   = htmlspecialchars((string) ($reply['created_at'] ?? ''), ENT_QUOTES);
                    if ($content === '') {
                        continue;
                    }
                    ?>
                    <li class="mod-fediverse-reactions__reply">
                        <div class="mod-fediverse-reactions__reply-actor">
                            <?php if ($actorUrl !== '') : ?>
                                <a class="mod-fediverse-reactions__actor-link" href="<?php echo $actorUrl; ?>" rel="noopener noreferrer" target="_blank">
                                    <?php echo $actorLabel !== '' ? $actorLabel : $actorUrl; ?>
                                </a>
                            <?php else : ?>
                                <span class="mod-fediverse-reactions__actor-name"><?php echo $actorLabel !== '' ? $actorLabel : Text::_('MOD_FEDIVERSE_REACTIONS_ACTOR_UNKNOWN'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mod-fediverse-reactions__reply-content"><?php echo $content; ?></div>
                        <?php if ($createdAt !== '') : ?>
                            <time class="mod-fediverse-reactions__reply-time" datetime="<?php echo $createdAt; ?>">
                                <?php echo $createdAt; ?>
                            </time>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php elseif ($showReplies) : ?>
        <p class="mod-fediverse-reactions__empty"><?php echo Text::_('MOD_FEDIVERSE_REACTIONS_EMPTY'); ?></p>
    <?php endif; ?>

    <?php if ($hasButtons) : ?>
        <div class="mod-fediverse-reactions__actions">
            <?php if ($showLikeButton) : ?>
                <button type="button"
                        class="mod-fediverse-reactions__action-btn mod-fediverse-reactions__action-btn--like"
                        onclick="document.getElementById('<?php echo $modalId; ?>').style.display='flex'"
                        aria-haspopup="dialog">
                    &#9829; <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_LIKE_THIS_POST'); ?>
                </button>
            <?php endif; ?>
            <?php if ($showBoostButton) : ?>
                <button type="button"
                        class="mod-fediverse-reactions__action-btn mod-fediverse-reactions__action-btn--boost"
                        onclick="document.getElementById('<?php echo $modalId; ?>').style.display='flex'"
                        aria-haspopup="dialog">
                    &#8635; <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_BOOST_THIS_POST'); ?>
                </button>
            <?php endif; ?>
        </div>

        <div id="<?php echo $modalId; ?>"
             class="mod-fediverse-reactions__modal"
             role="dialog"
             aria-modal="true"
             aria-label="<?php echo Text::_('MOD_FEDIVERSE_REACTIONS_INTERACT_HEADING'); ?>"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
            <div class="mod-fediverse-reactions__modal-inner"
                 style="background:#fff;border-radius:6px;padding:1.5rem;max-width:480px;width:90%;position:relative;">
                <button type="button"
                        class="mod-fediverse-reactions__modal-close"
                        onclick="document.getElementById('<?php echo $modalId; ?>').style.display='none'"
                        aria-label="<?php echo Text::_('JCLOSE'); ?>"
                        style="position:absolute;top:.5rem;right:.75rem;background:none;border:none;font-size:1.25rem;cursor:pointer;">&times;</button>

                <h5 class="mod-fediverse-reactions__modal-heading">
                    <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_INTERACT_HEADING'); ?>
                </h5>

                <p class="mod-fediverse-reactions__modal-help">
                    <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_WHY_HANDLE_EXPLANATION'); ?>
                </p>

                <div class="mod-fediverse-reactions__modal-row" style="margin-bottom:1rem;">
                    <label for="<?php echo $modalId; ?>-post-url"
                           style="display:block;font-weight:600;margin-bottom:.25rem;">
                        <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_POST_URL'); ?>
                    </label>
                    <div style="display:flex;gap:.5rem;">
                        <input id="<?php echo $modalId; ?>-post-url"
                               type="text"
                               readonly
                               value="<?php echo $postUrl; ?>"
                               style="flex:1;padding:.35rem .5rem;border:1px solid #ccc;border-radius:4px;" />
                        <button type="button"
                                onclick="(function(id){var el=document.getElementById(id);if(el)navigator.clipboard.writeText(el.value);}('<?php echo $modalId; ?>-post-url'))"
                                style="padding:.35rem .75rem;cursor:pointer;">
                            <?php echo Text::_('MOD_FEDIVERSE_REACTIONS_COPY'); ?>
                        </button>
                    </div>
                </div>

                <details class="mod-fediverse-reactions__modal-why">
                    <summary><?php echo Text::_('MOD_FEDIVERSE_REACTIONS_WHY_HANDLE'); ?></summary>
                    <p><?php echo Text::_('MOD_FEDIVERSE_REACTIONS_WHY_HANDLE_EXPLANATION'); ?></p>
                </details>
            </div>
        </div>

        <script>
        (function () {
            var modalId = <?php echo json_encode($modalId); ?>;
            var modal   = document.getElementById(modalId);
            if (!modal) { return; }
            modal.addEventListener('click', function (e) {
                if (e.target === modal) { modal.style.display = 'none'; }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.style.display !== 'none') {
                    modal.style.display = 'none';
                }
            });
        }());
        </script>
    <?php endif; ?>
</div>
