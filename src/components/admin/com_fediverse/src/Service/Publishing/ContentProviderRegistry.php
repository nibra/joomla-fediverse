<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Publishing;

/**
 * ContentProviderRegistry Class
 *
 * Register and resolve shareable content providers.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ContentProviderRegistry
{
    /**
     * @var array<string, ContentProviderInterface>
     */
    private array $providers = [];

    /**
     * Initialize the registry.
     *
     * @params ContentProviderInterface[] $providers Provider instances.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            if ($provider instanceof ContentProviderInterface) {
                $this->addProvider($provider);
            }
        }
    }

    /**
     * Add a provider.
     *
     * @params ContentProviderInterface $provider Provider instance.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function addProvider(ContentProviderInterface $provider): void
    {
        $key = trim($provider->getKey());
        if ($key === '') {
            return;
        }

        $this->providers[$key] = $provider;
    }

    /**
     * Get a provider for a context.
     *
     * @params string $context Joomla content context.
     *
     * @return  ?ContentProviderInterface  Provider instance or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getProviderForContext(string $context): ?ContentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsContext($context)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Get a provider for an object id.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  ?ContentProviderInterface  Provider instance or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getProviderForObjectId(string $objectId): ?ContentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->parseObjectId($objectId) !== null) {
                return $provider;
            }
        }

        return null;
    }
}
