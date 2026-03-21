<?php declare(strict_types=1);
/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Reusable Pro upgrade call-to-action partial.
 *
 * Variables expected:
 *   $featureName  string  Short label for the locked feature, e.g. "Domain Policies"
 *   $expired      bool    True when the user has a key but it's expired (default: false)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$featureName = $featureName ?? 'This feature';
$expired     = $expired     ?? false;

$msgKey  = $expired ? 'COM_FEDIVERSE_LICENSE_EXPIRED' : 'COM_FEDIVERSE_LICENSE_PRO_REQUIRED';
$iconCls = $expired ? 'icon-clock' : 'icon-lock';
?>
<div class="alert alert-warning d-flex align-items-start gap-3 mt-3">
    <span class="<?php echo $iconCls; ?> fa-2x flex-shrink-0" aria-hidden="true"></span>
    <div>
        <strong><?php echo htmlspecialchars($featureName, ENT_QUOTES, 'UTF-8'); ?></strong>
        &mdash;
        <?php echo Text::_($msgKey); ?>
    </div>
</div>
