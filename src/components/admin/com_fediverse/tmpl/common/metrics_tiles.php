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

$metricsHeadingId = 'fediverse-metrics-heading';
$metricsDescId = 'fediverse-metrics-desc';
$tiles = isset($tiles) && is_array($tiles) ? $tiles : [];
$showLinks = !isset($showLinks) || (bool) $showLinks;
?>
<section
    class="fediverse-metrics-section"
    data-fediverse-metrics-region="1"
    aria-labelledby="<?php echo $metricsHeadingId; ?>"
    aria-describedby="<?php echo $metricsDescId; ?>"
>
    <h2 id="<?php echo $metricsHeadingId; ?>" class="visually-hidden">
        <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_METRICS_HEADING'); ?>
    </h2>
    <p id="<?php echo $metricsDescId; ?>" class="visually-hidden">
        <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_METRICS_DESC'); ?>
    </p>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 fediverse-metrics-grid">
    <?php foreach ($tiles as $tile) : ?>
        <?php
        $key = htmlspecialchars((string) ($tile['key'] ?? ''), ENT_QUOTES, 'UTF-8');
        $label = (string) ($tile['label'] ?? '');
        $value = (int) ($tile['value'] ?? 0);
        $tone = isset($tile['tone']) ? (string) $tile['tone'] : 'primary';
        $link = isset($tile['link']) ? (string) $tile['link'] : '';
        $labelId = 'fediverse-metric-label-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($tile['key'] ?? 'metric'));
        $valueId = 'fediverse-metric-value-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($tile['key'] ?? 'metric'));
        ?>
        <div class="col">
            <article
                class="card h-100 shadow-sm border-<?php echo htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>"
                data-fediverse-metric="<?php echo $key; ?>"
                data-fediverse-count="<?php echo $value; ?>"
                aria-labelledby="<?php echo $labelId; ?>"
                aria-describedby="<?php echo $valueId; ?>"
            >
                <div class="card-body position-relative">
                    <dl class="mb-0">
                        <dt id="<?php echo $labelId; ?>" class="text-uppercase text-muted small fw-semibold mb-2">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </dt>
                        <dd id="<?php echo $valueId; ?>" class="display-6 fw-bold mb-0">
                            <span class="visually-hidden">
                                <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_METRIC_VALUE_LABEL'); ?>:
                            </span>
                            <?php echo $value; ?>
                        </dd>
                    </dl>
                    <?php if ($showLinks && $link !== '') : ?>
                        <a
                            class="stretched-link"
                            href="<?php echo $link; ?>"
                            aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_FEDIVERSE_DASHBOARD_METRIC_LINK_ARIA', $label, $value), ENT_QUOTES, 'UTF-8'); ?>"
                        ></a>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
</section>
