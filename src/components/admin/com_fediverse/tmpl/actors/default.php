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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use NX\Component\Fediverse\Administrator\Helper\ActorAccountPresets;

$projectUrl = Text::_('COM_FEDIVERSE_PROJECT_URL');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$orderingParts = preg_split('/\s+/', trim((string) ($this->listFullordering ?? '')), 2) ?: [];
$listOrder = (string) ($orderingParts[0] ?? 'preferred_username');
$listDirn = strtoupper((string) ($orderingParts[1] ?? 'ASC'));
if ($listDirn !== 'ASC' && $listDirn !== 'DESC') {
    $listDirn = 'ASC';
}
?>
<div class="com-fediverse-actors">
    <h1 class="h2"><?php echo Text::_('COM_FEDIVERSE_ACTORS_HEADING'); ?></h1>

    <?php if (!$this->isPaid) : ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
            <span class="icon-info-circle" aria-hidden="true"></span>
            <span>
                <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_FREE', $this->actorLimit); ?>
                &mdash; <?php echo Text::sprintf('COM_FEDIVERSE_LICENSE_PRO_REQUIRED', $projectUrl); ?>
            </span>
        </div>
    <?php elseif ($this->localActorCount >= $this->actorLimit && $this->actorLimit !== PHP_INT_MAX) : ?>
        <div class="alert alert-warning mb-3">
            <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_REACHED', $this->actorLimit); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_fediverse&view=actors'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <p><?php echo Text::_('COM_FEDIVERSE_ACTORS_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-striped" id="actorList">
                <thead>
                    <tr>
                        <th width="1%" class="text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_COL_HANDLE', 'preferred_username', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_COL_USER_ID', 'user_id', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_COL_SCOPE', 'type', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_ACTOR_TYPE', 'actor_type', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_OBJECT_TYPE', 'object_type', $listDirn, $listOrder); ?>
                        </th>
                        <th>
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_FEDIVERSE_ACTORS_COL_ENABLED', 'is_enabled', $listDirn, $listOrder); ?>
                        </th>
                        <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_FOLLOWERS'); ?></th>
                        <th><?php echo Text::_('COM_FEDIVERSE_ACTORS_COL_INTERACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                    <?php $isLocalActor = (($item['type'] ?? '') === 'local'); ?>
                    <tr>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, (int) ($item['id'] ?? 0)); ?>
                        </td>
                        <td>
                            <?php if ($isLocalActor) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_fediverse&view=actor&id=' . (int) ($item['id'] ?? 0)); ?>">
                                    <?php echo htmlspecialchars($item['preferred_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php else : ?>
                                <?php echo htmlspecialchars($item['preferred_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $userName = trim((string) ($item['user_name'] ?? ''));
                            if ($userName === '' && (int) ($item['user_id'] ?? 0) > 0) {
                                $userName = '#' . (string) ((int) ($item['user_id'] ?? 0));
                            }
                            if ($userName === '') {
                                $userName = '—';
                            }
                            echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
                            ?>
                        </td>
                        <td>
                            <?php
                            $scope = (string) ($item['type'] ?? '');
                            if ($scope === 'local') {
                                echo Text::_('COM_FEDIVERSE_ACTORS_SCOPE_LOCAL');
                            } elseif ($scope === 'remote') {
                                echo Text::_('COM_FEDIVERSE_ACTORS_SCOPE_REMOTE');
                            } else {
                                echo htmlspecialchars($scope !== '' ? $scope : '—', ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                        </td>
                        <td><?php echo Text::_(ActorAccountPresets::labelKeyForActorType($item['actor_type'] ?? 'Person')); ?></td>
                        <td><?php echo htmlspecialchars($item['object_type'] ?? 'Note', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if (!empty($item['is_enabled'])) : ?>
                                <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo Text::_('JNO'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isLocalActor) : ?>
                                <?php
                                $followersAccepted = (int) ($item['followers_accepted_count'] ?? 0);
                                $followersPending  = (int) ($item['followers_pending_count'] ?? 0);
                                $followersBlocked  = (int) ($item['followers_blocked_count'] ?? 0);
                                $followersTooltip  = Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_ACCEPTED') . ': ' . $followersAccepted
                                    . ' | ' . Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_PENDING') . ': ' . $followersPending
                                    . ' | ' . Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_BLOCKED') . ': ' . $followersBlocked;
                                ?>
                                <span
                                    class="hasTooltip"
                                    data-actor-list-analytics="followers"
                                    title="<?php echo htmlspecialchars($followersTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="<?php echo htmlspecialchars($followersTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                    tabindex="0"
                                >
                                    &#10003; <?php echo $followersAccepted; ?> &middot;
                                    &#8987; <?php echo $followersPending; ?> &middot;
                                    &#10007; <?php echo $followersBlocked; ?>
                                </span>
                            <?php else : ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isLocalActor) : ?>
                                <?php
                                $interactionsLike     = (int) ($item['interactions_like_count'] ?? 0);
                                $interactionsAnnounce = (int) ($item['interactions_announce_count'] ?? 0);
                                $interactionsCreate   = (int) ($item['interactions_create_count'] ?? 0);
                                $interactionsTooltip  = Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_LIKE') . ': ' . $interactionsLike
                                    . ' | ' . Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_ANNOUNCE') . ': ' . $interactionsAnnounce
                                    . ' | ' . Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_CREATE') . ': ' . $interactionsCreate;
                                ?>
                                <span
                                    class="hasTooltip"
                                    data-actor-list-analytics="interactions"
                                    title="<?php echo htmlspecialchars($interactionsTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="<?php echo htmlspecialchars($interactionsTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                    tabindex="0"
                                >
                                    &#9829; <?php echo $interactionsLike; ?> &middot;
                                    &#8635; <?php echo $interactionsAnnounce; ?> &middot;
                                    &#128172; <?php echo $interactionsCreate; ?>
                                </span>
                            <?php else : ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="9">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="actors">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <input type="hidden" name="actors_scope_changed" id="actors_scope_changed" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var scopeField = document.getElementById('filter_type');
    var scopeChanged = document.getElementById('actors_scope_changed');
    if (!scopeField || !scopeChanged) {
        return;
    }
    scopeField.addEventListener('change', function () {
        scopeChanged.value = '1';
    }, true);
});
</script>
