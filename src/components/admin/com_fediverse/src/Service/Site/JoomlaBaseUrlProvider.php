<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Site;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;

/**
 * JoomlaBaseUrlProvider Class
 *
 * Provide Joomla Base Url Provider services.
 *
 * @since  __DEPLOY_VERSION__
 */

final class JoomlaBaseUrlProvider implements BaseUrlProviderInterface
{
    /**
     * Initialize the base URL provider.
     *
     * Store the component configuration for overrides.
     *
     * @params ?FediverseConfig $config Component configuration.
     *
     * @return  void None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private ?FediverseConfig $config = null)
    {
        $this->config = $config ?? new FediverseConfig();
    }

    /**
     * Get the canonical base URL.
     *
     * Return the Joomla site root without a trailing slash.
     *
     * @return  string  Base URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getBaseUrl(): string
    {
        $configured = $this->config->getPublicBaseUrl();
        if ($configured !== null) {
            return $configured;
        }

        $base = $this->readLiveSite();
        if ($base === null) {
            $base = Uri::root();
        }

        $base = rtrim($base, '/');

        return $base !== '' ? $base : rtrim(Uri::root(), '/');
    }

    /**
     * Read the Joomla live_site config value.
     *
     * @return  ?string  Live site URL or null when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function readLiveSite(): ?string
    {
        try {
            $config = Factory::getApplication()->getConfig();
        } catch (\Throwable) {
            $config = null;
        }

        if (!is_object($config) || !method_exists($config, 'get')) {
            return null;
        }

        $liveSite = (string) $config->get('live_site');
        $liveSite = trim($liveSite);

        return $liveSite !== '' ? $liveSite : null;
    }

}
