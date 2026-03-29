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
use Joomla\Database\DatabaseInterface;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;

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
    private const PROJECT_URL = 'https://code.nibra.net/pkg_fediverse';
    private const PARAM_INSTANCE_ID = 'license_instance_id';
    private const PARAM_KEY_HASH = 'license_key_hash';

    private ?LicenseValidationResult $result = null;

    /**
     * @param  LicenseValidatorInterface  $validator    Concrete validator to use.
     * @param  ?DatabaseInterface         $db           Database connection for persisted instance binding.
     * @param  ?BaseUrlProviderInterface  $baseUrlProvider  Canonical site URL provider.
     * @param  string|null                $overrideKey  Bypass component params (for tests).
     * @param  string|null                $overrideInstanceId  Bypass stored instance id (for tests).
     */
    public function __construct(
        private readonly LicenseValidatorInterface $validator,
        private readonly ?DatabaseInterface $db = null,
        private readonly ?BaseUrlProviderInterface $baseUrlProvider = null,
        private readonly ?string $overrideKey = null,
        private readonly ?string $overrideInstanceId = null,
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
     * Check if the installation has an active, non-expired paid plan.
     *
     * @return  bool  True if tier is Personal or Pro and not expired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isPaid(): bool
    {
        $result = $this->getResult();

        return $result->tier->isPaid() && !$result->expired;
    }

    /**
     * Check if the installation has an active, non-expired Pro plan.
     *
     * @return  bool  True only for the Pro tier and not expired.
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
        $this->requireTier(LicenseTier::Pro);
    }

    /**
     * Assert that a minimum tier is active and not expired.
     *
     * @param   LicenseTier  $requiredTier  Minimum required tier.
     *
     * @return  void
     *
     * @throws  \RuntimeException  When not licensed for the required tier or expired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function requireTier(LicenseTier $requiredTier): void
    {
        if ($this->isExpired()) {
            throw new \RuntimeException(
                'Your Joomla Fediverse Pro license has expired. '
                . 'Please renew at ' . self::PROJECT_URL . '.',
                403
            );
        }

        $tier = $this->getTier();
        if (!$tier->isAtLeast($requiredTier)) {
            throw new \RuntimeException(
                'This feature requires a Joomla Fediverse Pro license. '
                . 'Please upgrade at ' . self::PROJECT_URL . '.',
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

        $key = $this->overrideKey ?? $this->getStoredKey();

        if ($key === '') {
            if ($this->db !== null) {
                $this->persistBinding(null, null);
            }

            $this->result = $this->validator->validate('');

            return $this->result;
        }

        $instanceId = $this->resolveInstanceId($key);
        $instanceName = $this->resolveInstanceName();

        $this->result = $this->validator->validate($key, $instanceId, $instanceName);

        if ($this->db !== null) {
            if ($this->result->valid && $this->result->instanceId !== null) {
                $this->persistBinding($this->result->instanceId, $this->hashKey($key));
            } elseif ($this->result->valid && $instanceId !== null) {
                $this->persistBinding($instanceId, $this->hashKey($key));
            }
        }

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

    /**
     * Resolve the stored instance id for the current key.
     *
     * @param   string  $key  Active license key.
     *
     * @return  ?string  Stored instance id or null when the binding is stale.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveInstanceId(string $key): ?string
    {
        if ($this->overrideKey !== null && $this->overrideInstanceId === null) {
            return null;
        }

        if ($this->overrideInstanceId !== null) {
            $instanceId = trim($this->overrideInstanceId);

            return $instanceId !== '' ? $instanceId : null;
        }

        $params = ComponentHelper::getParams('com_fediverse');
        $storedHash = trim((string) $params->get(self::PARAM_KEY_HASH, ''));

        if ($storedHash === '' || !hash_equals($storedHash, $this->hashKey($key))) {
            return null;
        }

        $instanceId = trim((string) $params->get(self::PARAM_INSTANCE_ID, ''));

        return $instanceId !== '' ? $instanceId : null;
    }

    /**
     * Resolve a stable, human-readable installation name for LemonSqueezy.
     *
     * @return  string  Installation name.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveInstanceName(): string
    {
        $baseUrl = $this->baseUrlProvider?->getBaseUrl() ?? '';
        $host = is_string(parse_url($baseUrl, PHP_URL_HOST)) ? (string) parse_url($baseUrl, PHP_URL_HOST) : '';

        if ($host !== '') {
            return $host;
        }

        return $baseUrl !== '' ? $baseUrl : 'joomla-fediverse';
    }

    /**
     * Persist the current LemonSqueezy binding metadata.
     *
     * @param   ?string  $instanceId  Activated LemonSqueezy instance id.
     * @param   ?string  $keyHash     Hash of the currently bound key.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function persistBinding(?string $instanceId, ?string $keyHash): void
    {
        if ($this->db === null) {
            return;
        }

        if ($this->overrideKey !== null) {
            $params = [];
        } else {
            $params = json_decode(json_encode(ComponentHelper::getParams('com_fediverse'), JSON_UNESCAPED_SLASHES) ?: '{}', true);
        }

        if (!is_array($params)) {
            $params = [];
        }

        if ($instanceId === null || trim($instanceId) === '') {
            unset($params[self::PARAM_INSTANCE_ID]);
        } else {
            $params[self::PARAM_INSTANCE_ID] = trim($instanceId);
        }

        if ($keyHash === null || trim($keyHash) === '') {
            unset($params[self::PARAM_KEY_HASH]);
        } else {
            $params[self::PARAM_KEY_HASH] = trim($keyHash);
        }

        $encoded = json_encode($params, JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            return;
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__extensions'))
            ->set($this->db->quoteName('params') . ' = ' . $this->db->quote($encoded))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'));

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Hash a raw license key for binding change detection.
     *
     * @param   string  $key  Raw license key.
     *
     * @return  string  Stable hash.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function hashKey(string $key): string
    {
        return hash('sha256', trim($key));
    }
}
