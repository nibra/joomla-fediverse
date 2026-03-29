<?php
/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \NX\Component\Fediverse\Administrator\View\Actor\HtmlView $this */
$actor     = $this->actor;
$analytics = is_array($this->analytics ?? null) ? $this->analytics : [];

$followers = is_array($analytics['followers'] ?? null) ? $analytics['followers'] : [];
$followersAccepted = (int) ($followers['accepted'] ?? 0);
$followersPending  = (int) ($followers['pending'] ?? 0);
$followersBlocked  = (int) ($followers['blocked'] ?? 0);
$followersTotal    = (int) ($followers['total'] ?? ($followersAccepted + $followersPending + $followersBlocked));

$interactions = is_array($analytics['interactions'] ?? null) ? $analytics['interactions'] : [];
$interactionsCreate   = (int) ($interactions['create'] ?? 0);
$interactionsLike     = (int) ($interactions['like'] ?? 0);
$interactionsAnnounce = (int) ($interactions['announce'] ?? 0);
$interactionsTotal    = (int) ($interactions['total'] ?? ($interactionsCreate + $interactionsLike + $interactionsAnnounce));
$profileData = is_array($this->profileData ?? null) ? $this->profileData : [];
$profileName = (string) ($profileData['name'] ?? '');
$profileSummary = (string) ($profileData['summary'] ?? '');
$profileUrl = (string) ($profileData['url'] ?? '');
$profileIconUrl = (string) ($profileData['icon_url'] ?? '');
$profileHeaderUrl = (string) ($profileData['image_url'] ?? '');
$profileMetadata1Label = (string) ($profileData['metadata_1_label'] ?? '');
$profileMetadata1Value = (string) ($profileData['metadata_1_value'] ?? '');
$profileMetadata2Label = (string) ($profileData['metadata_2_label'] ?? '');
$profileMetadata2Value = (string) ($profileData['metadata_2_value'] ?? '');
$profileMetadata3Label = (string) ($profileData['metadata_3_label'] ?? '');
$profileMetadata3Value = (string) ($profileData['metadata_3_value'] ?? '');
$featuredContentId = (string) ($profileData['featured_content_id'] ?? '');
$featuredContentOptions = is_array($this->featuredContentOptions ?? null) ? $this->featuredContentOptions : [];
$websiteVerifiedAt = (string) ($profileData['url_verified_at'] ?? '');
?>
<div class="com-fediverse-actor-edit">
    <h1 class="h2">
        <?php echo Text::_('COM_FEDIVERSE_ACTORS_EDIT_HEADING'); ?>:
        <?php echo htmlspecialchars($actor->preferredUsername, ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <section class="card mb-4" data-actor-analytics="summary">
        <div class="card-body">
            <h2 class="h5 mb-3"><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_HEADING'); ?></h2>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <h3 class="h6 mb-2"><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_FOLLOWERS'); ?></h3>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_ACCEPTED'); ?></span>
                            <strong data-actor-analytics-metric="followers-accepted" data-actor-analytics-count="<?php echo $followersAccepted; ?>">
                                <?php echo $followersAccepted; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_PENDING'); ?></span>
                            <strong data-actor-analytics-metric="followers-pending" data-actor-analytics-count="<?php echo $followersPending; ?>">
                                <?php echo $followersPending; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_BLOCKED'); ?></span>
                            <strong data-actor-analytics-metric="followers-blocked" data-actor-analytics-count="<?php echo $followersBlocked; ?>">
                                <?php echo $followersBlocked; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_TOTAL'); ?></span>
                            <strong data-actor-analytics-metric="followers-total" data-actor-analytics-count="<?php echo $followersTotal; ?>">
                                <?php echo $followersTotal; ?>
                            </strong>
                        </li>
                    </ul>
                </div>
                <div class="col-12 col-lg-6">
                    <h3 class="h6 mb-2"><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_INTERACTIONS'); ?></h3>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_CREATE'); ?></span>
                            <strong data-actor-analytics-metric="interactions-create" data-actor-analytics-count="<?php echo $interactionsCreate; ?>">
                                <?php echo $interactionsCreate; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_LIKE'); ?></span>
                            <strong data-actor-analytics-metric="interactions-like" data-actor-analytics-count="<?php echo $interactionsLike; ?>">
                                <?php echo $interactionsLike; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_ANNOUNCE'); ?></span>
                            <strong data-actor-analytics-metric="interactions-announce" data-actor-analytics-count="<?php echo $interactionsAnnounce; ?>">
                                <?php echo $interactionsAnnounce; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo Text::_('COM_FEDIVERSE_ACTORS_ANALYTICS_TOTAL'); ?></span>
                            <strong data-actor-analytics-metric="interactions-total" data-actor-analytics-count="<?php echo $interactionsTotal; ?>">
                                <?php echo $interactionsTotal; ?>
                            </strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <form action="<?php echo Route::_('index.php', false); ?>" method="post" id="adminForm" name="adminForm">
        <input type="hidden" name="option" value="com_fediverse">
        <input type="hidden" name="view" value="actor">
        <input type="hidden" name="task" value="">
        <input type="hidden" name="id" value="<?php echo (int) $actor->id; ?>">
        <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1">

        <section class="card mb-4" data-actor-profile="identity">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php echo Text::_('COM_FEDIVERSE_ACTORS_IDENTITY_HEADING'); ?></h2>

                <div class="mb-3">
                    <label for="fediverse-account-kind" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND'); ?>
                    </label>
                    <select id="fediverse-account-kind" name="account_kind" class="form-select">
                        <option value="person"<?php echo $this->accountKind === 'person' ? ' selected' : ''; ?>>
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND_PERSON'); ?>
                        </option>
                        <option value="service"<?php echo $this->accountKind === 'service' ? ' selected' : ''; ?>>
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND_SERVICE'); ?>
                        </option>
                        <option value="organization"<?php echo $this->accountKind === 'organization' ? ' selected' : ''; ?><?php echo !$this->isPro ? ' disabled' : ''; ?>>
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND_ORGANIZATION'); ?>
                        </option>
                        <option value="channel"<?php echo $this->accountKind === 'channel' ? ' selected' : ''; ?><?php echo !$this->isPro ? ' disabled' : ''; ?>>
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND_CHANNEL'); ?>
                        </option>
                    </select>
                    <div class="form-text">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_ACCOUNT_KIND_HELP'); ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="fediverse-profile-name" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_NAME'); ?>
                    </label>
                    <input
                        type="text"
                        id="fediverse-profile-name"
                        name="profile_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label for="fediverse-profile-summary" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_SUMMARY'); ?>
                    </label>
                    <textarea
                        id="fediverse-profile-summary"
                        name="profile_summary"
                        class="form-control"
                        rows="4"
                    ><?php echo htmlspecialchars($profileSummary, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="mb-0">
                    <label for="fediverse-profile-url" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_URL'); ?>
                    </label>
                    <input
                        type="url"
                        id="fediverse-profile-url"
                        name="profile_url"
                        class="form-control"
                        value="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mt-3 mb-0">
                    <label for="fediverse-profile-image-url" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_ICON_URL'); ?>
                    </label>
                    <input
                        type="url"
                        id="fediverse-profile-image-url"
                        name="profile_icon_url"
                        class="form-control"
                        value="<?php echo htmlspecialchars($profileIconUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mt-3 mb-0">
                    <label for="fediverse-profile-header-url" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_HEADER_URL'); ?>
                    </label>
                    <input
                        type="url"
                        id="fediverse-profile-header-url"
                        name="profile_header_url"
                        class="form-control"
                        value="<?php echo htmlspecialchars($profileHeaderUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="mt-3">
                    <?php if ($websiteVerifiedAt !== '') : ?>
                        <div class="alert alert-success py-2 mb-0" data-profile-link-status="verified">
                            <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_PROFILE_URL_VERIFIED', $websiteVerifiedAt); ?>
                        </div>
                    <?php else : ?>
                        <div class="form-text" data-profile-link-status="unverified">
                            <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_URL_VERIFICATION_HELP'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <label for="fediverse-featured-content-id" class="form-label">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_FEATURED_CONTENT'); ?>
                    </label>
                    <select
                        id="fediverse-featured-content-id"
                        name="featured_content_id"
                        class="form-select"
                    >
                        <option value=""><?php echo Text::_('COM_FEDIVERSE_ACTORS_FEATURED_CONTENT_NONE'); ?></option>
                        <?php
                        $foundFeaturedOption = $featuredContentId === '';
                        foreach ($featuredContentOptions as $option) :
                            $optionValue = (string) ($option['value'] ?? '');
                            $optionText = (string) ($option['text'] ?? '');
                            if ($optionValue === '' || $optionText === '') {
                                continue;
                            }
                            $foundFeaturedOption = $foundFeaturedOption || $optionValue === $featuredContentId;
                        ?>
                            <option
                                value="<?php echo htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $optionValue === $featuredContentId ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($optionText, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!$foundFeaturedOption && $featuredContentId !== '') : ?>
                            <option value="<?php echo htmlspecialchars($featuredContentId, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                <?php echo Text::sprintf('COM_FEDIVERSE_ACTORS_FEATURED_CONTENT_CURRENT', $featuredContentId); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_FEATURED_CONTENT_HELP'); ?>
                    </div>
                </div>

                <div class="mt-4">
                    <h3 class="h5 mb-3">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_HEADING'); ?>
                    </h3>
                    <p class="text-muted mb-3">
                        <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_HELP'); ?>
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="fediverse-profile-metadata-1-label" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_LABEL'); ?> 1
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-1-label"
                                name="profile_metadata_1_label"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata1Label, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="col-md-8">
                            <label for="fediverse-profile-metadata-1-value" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_VALUE'); ?> 1
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-1-value"
                                name="profile_metadata_1_value"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata1Value, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="fediverse-profile-metadata-2-label" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_LABEL'); ?> 2
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-2-label"
                                name="profile_metadata_2_label"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata2Label, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="col-md-8">
                            <label for="fediverse-profile-metadata-2-value" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_VALUE'); ?> 2
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-2-value"
                                name="profile_metadata_2_value"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata2Value, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="fediverse-profile-metadata-3-label" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_LABEL'); ?> 3
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-3-label"
                                name="profile_metadata_3_label"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata3Label, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="col-md-8">
                            <label for="fediverse-profile-metadata-3-value" class="form-label">
                                <?php echo Text::_('COM_FEDIVERSE_ACTORS_PROFILE_METADATA_VALUE'); ?> 3
                            </label>
                            <input
                                type="text"
                                id="fediverse-profile-metadata-3-value"
                                name="profile_metadata_3_value"
                                class="form-control"
                                value="<?php echo htmlspecialchars($profileMetadata3Value, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!$this->isPro) : ?>
            <?php
            $featureName = Text::_('COM_FEDIVERSE_ACTORS_PRO_ACCOUNT_PRESETS');
            $expired = (bool) ($this->licenseExpired ?? false);
            echo LayoutHelper::render('common.upgrade_cta', compact('featureName', 'expired'), JPATH_COMPONENT_ADMINISTRATOR . '/tmpl');
            ?>
        <?php endif; ?>

        <section class="card mb-0" data-actor-profile="publishing">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php echo Text::_('COM_FEDIVERSE_ACTORS_PUBLISHING_HEADING'); ?></h2>

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
            </div>
        </section>

    </form>

</div>
