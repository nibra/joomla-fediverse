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
 * LicenseValidationResult Value Object
 *
 * Immutable result returned by any LicenseValidatorInterface implementation.
 *
 * @since  __DEPLOY_VERSION__
 */
final class LicenseValidationResult
{
    public function __construct(
        public readonly LicenseTier           $tier    = LicenseTier::Free,
        public readonly bool                  $valid   = false,
        public readonly bool                  $expired = false,
        public readonly ?\DateTimeImmutable   $expiry  = null,
        public readonly ?string               $domain  = null,
    ) {}

    /**
     * Convenience factory for a Free / invalid result.
     *
     * @return  self  Free result.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function free(): self
    {
        return new self(tier: LicenseTier::Free, valid: false);
    }
}
