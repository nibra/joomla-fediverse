<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\License;

/**
 * LicenseValidatorInterface
 *
 * Implementations validate a raw license key string and return a
 * LicenseValidationResult. Swap the concrete class in the DI container to
 * change the license backend (JWT, LemonSqueezy, etc.) without touching any
 * other code.
 *
 * @since  __DEPLOY_VERSION__
 */
interface LicenseValidatorInterface
{
    /**
     * Validate a license key and return the result.
     *
     * Implementations must never throw. They should return a Free result on
     * any failure, including activation or validation failures.
     *
     * @param   string   $key           Raw license key as entered by the site admin.
     * @param   ?string  $instanceId    Stored LemonSqueezy instance id, if any.
     * @param   ?string  $instanceName  Human-readable site identifier for first activation.
     *
     * @return  LicenseValidationResult  Validation result.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function validate(string $key, ?string $instanceId = null, ?string $instanceName = null): LicenseValidationResult;
}
