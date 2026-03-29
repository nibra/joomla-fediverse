<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\License\Validator;

use NX\Component\Fediverse\Administrator\Service\License\LicenseValidationResult;
use NX\Component\Fediverse\Administrator\Service\License\LicenseValidatorInterface;

/**
 * CompositeLicenseValidator Class
 *
 * Try multiple license validators in order and return the first valid result.
 *
 * @since  __DEPLOY_VERSION__
 */
final class CompositeLicenseValidator implements LicenseValidatorInterface
{
    /**
     * Initialize the composite validator.
     *
     * @params array<int, LicenseValidatorInterface> $validators Validators to try in order.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private array $validators)
    {
    }

    /**
     * Validate a license key through the configured validator chain.
     *
     * @params string $key Raw license key.
     *
     * @return  LicenseValidationResult  First valid result, or free when none validate.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function validate(string $key, ?string $instanceId = null, ?string $instanceName = null): LicenseValidationResult
    {
        $fallback = LicenseValidationResult::free();

        foreach ($this->validators as $validator) {
            $result = $validator->validate($key, $instanceId, $instanceName);
            $fallback = $result;

            if ($result->valid) {
                return $result;
            }
        }

        return $fallback;
    }
}
