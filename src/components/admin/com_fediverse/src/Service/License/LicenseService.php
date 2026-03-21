<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\License;

use Joomla\CMS\Component\ComponentHelper;

/**
 * LicenseService Class
 *
 * Thin façade over a LicenseValidatorInterface implementation. Handles
 * lazy validation, per-request caching, and the component-params key lookup.
 *
 * To switch license backends (JWT → LemonSqueezy), inject a different
 * LicenseValidatorInterface implementation via the DI container.
 *
 * @since  __DEPLOY_VERSION__
 */
final class LicenseService
{
    private ?LicenseValidationResult $result = null;

    /**
     * @param  LicenseValidatorInterface  $validator    Concrete validator to use.
     * @param  string|null                $overrideKey  Bypass component params (for tests).
     */
    public function __construct(
        private readonly LicenseValidatorInterface $validator,
        private readonly ?string $overrideKey = null,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the active license tier.
     *
     * @return  LicenseTier  The tier (defaults to Free if no valid key).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getTier(): LicenseTier
    {
        return $this->getResult()->tier;
    }

    /**
     * Check if the installation has an active, non-expired Pro license.
     *
     * @return  bool  True if tier is Personal, Developer, or Agency and not expired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isPro(): bool
    {
        $result = $this->getResult();

        return $result->tier->isPro() && !$result->expired;
    }

    /**
     * Return the expiry date, or null for Free / unlimited licenses.
     *
     * @return  ?\DateTimeImmutable  Expiry date.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getExpiry(): ?\DateTimeImmutable
    {
        return $this->getResult()->expiry;
    }

    /**
     * Return number of days until expiry, or null when there is no expiry.
     *
     * @return  ?int  Days remaining (negative = already expired).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getDaysRemaining(): ?int
    {
        $expiry = $this->getExpiry();
        if ($expiry === null) {
            return null;
        }

        return (int) (new \DateTimeImmutable())->diff($expiry)->format('%r%a');
    }

    /**
     * Check if the license has expired.
     *
     * @return  bool  True if the key was valid but is past its expiry date.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isExpired(): bool
    {
        return $this->getResult()->expired;
    }

    /**
     * Return the licensed domain, or null when not applicable.
     *
     * @return  ?string  Domain.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getLicensedDomain(): ?string
    {
        return $this->getResult()->domain;
    }

    /**
     * Assert that a Pro license is active and not expired.
     *
     * @return  void
     *
     * @throws  \RuntimeException  When not Pro or when expired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function requirePro(): void
    {
        if ($this->isExpired()) {
            throw new \RuntimeException(
                'Your Joomla Fediverse Pro license has expired. '
                . 'Please renew at https://code.nibra.net/pkg_fediverse.',
                403
            );
        }

        if (!$this->isPro()) {
            throw new \RuntimeException(
                'This feature requires a Joomla Fediverse Pro license. '
                . 'Please upgrade at https://code.nibra.net/pkg_fediverse.',
                403
            );
        }
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Return the cached validation result, running validation if needed.
     *
     * @return  LicenseValidationResult  Cached result.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getResult(): LicenseValidationResult
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $key          = $this->overrideKey ?? $this->getStoredKey();
        $this->result = $this->validator->validate($key);

        return $this->result;
    }

    /**
     * Read the license key from the component parameters.
     *
     * @return  string  Raw key (may be empty).
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getStoredKey(): string
    {
        return trim((string) ComponentHelper::getParams('com_fediverse')->get('license_key', ''));
    }
}
