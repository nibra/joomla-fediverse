<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_dashboard
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$buttons = isset($buttons) && is_array($buttons) ? $buttons : [];
$logoUrl = rtrim(Uri::base(true), '/') . '/modules/mod_fediverse_dashboard/assets/logo.png';
$metricsHeadingId = 'fediverse-metrics-heading';
$metricsDescId = 'fediverse-metrics-desc';
Factory::getApplication()->getDocument()->addStyleDeclaration(
    '.icon-fediverse{'
    . 'display:inline-block;'
    . 'width:1.2rem;'
    . 'height:1.2rem;'
    . 'vertical-align:-0.2rem;'
    . 'background:url("' . $logoUrl . '") center/contain no-repeat;'
    . '}'
    . '.icon-fediverse::before{content:none!important;}'
    . '.fediverse-dashboard-quickicon-value{'
    . 'display:inline-flex;'
    . 'align-items:center;'
    . 'justify-content:center;'
    . 'min-width:2.4rem;'
    . 'height:2.4rem;'
    . 'padding:0 .4rem;'
    . 'border-radius:.4rem;'
    . 'font-size:1.1rem;'
    . 'font-weight:900;'
    . 'line-height:1;'
    . '}'
    . '.fediverse-dashboard-quickicon.disabled{pointer-events:none;opacity:.6;}'
);
?>
<?php if (!empty($buttons)) : ?>
    <section
        class="fediverse-dashboard-module"
        data-fediverse-dashboard-module="stats"
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
    <nav class="quick-icons px-3 pb-3" aria-label="<?php echo Text::_('MOD_FEDIVERSE_DASHBOARD_NAV_LABEL') . ' ' . htmlspecialchars($module->title, ENT_QUOTES, 'UTF-8'); ?>">
        <ul class="nav flex-wrap">
            <?php foreach ($buttons as $button) : ?>
                <?php
                $id = trim((string) ($button['id'] ?? ''));
                $metricKey = trim((string) ($button['metricKey'] ?? ''));
                $class = trim((string) ($button['class'] ?? ''));
                $link = (string) ($button['link'] ?? '#');
                $name = (string) ($button['name'] ?? '');
                $value = trim((string) ($button['value'] ?? $button['text'] ?? ''));
                $value = $value !== '' ? $value : '0';
                $onclick = trim((string) ($button['onclick'] ?? ''));
                $metricSlug = preg_replace('/[^a-z0-9_-]+/i', '-', $metricKey !== '' ? $metricKey : 'metric') ?? 'metric';
                $labelId = 'fediverse-metric-label-' . $metricSlug;
                $valueId = 'fediverse-metric-value-' . $metricSlug;
                ?>
                <li
                    class="quickicon quickicon-single"
                    data-fediverse-metric="<?php echo htmlspecialchars($metricKey, ENT_QUOTES, 'UTF-8'); ?>"
                    data-fediverse-count="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-labelledby="<?php echo $labelId; ?>"
                    aria-describedby="<?php echo $valueId; ?>"
                >
                    <a
                        <?php if ($id !== '') : ?>id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                        <?php if ($class !== '') : ?>class="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                        href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if ($onclick !== '') : ?>onclick="<?php echo htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                        aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_FEDIVERSE_DASHBOARD_METRIC_LINK_ARIA', Text::_($name), (int) $value), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="quickicon-info">
                            <div class="quickicon-icon">
                                <div id="<?php echo $valueId; ?>" class="fediverse-dashboard-quickicon-value">
                                    <span class="visually-hidden">
                                        <?php echo Text::_('COM_FEDIVERSE_DASHBOARD_METRIC_VALUE_LABEL'); ?>:
                                    </span>
                                    <?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        </div>
                        <div id="<?php echo $labelId; ?>" class="quickicon-name d-flex align-items-end">
                            <?php echo htmlspecialchars(Text::_($name), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    </section>
<?php endif; ?>
