<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Announce;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;

/**
 * WebfingerService Class
 *
 * Provide Webfinger services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class WebfingerService
{
    /**
     * Initialize the WebFinger service.
     *
     * Store dependencies needed to resolve account identifiers.
     *
     * @params MVCFactoryInterface $mvcFactory MVC factory.
     * @params ActorResolverServiceInterface $actorResolver Actor resolver service.
     * @params BaseUrlProviderInterface $baseUrl Base URL provider.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private MVCFactoryInterface $mvcFactory,
        private ActorResolverServiceInterface $actorResolver,
        private BaseUrlProviderInterface $baseUrl,
    ) {
    }

    /**
     * Resolve a WebFinger resource.
     *
     * Map an acct: resource to the corresponding local actor, returning an empty JRD when unknown.
     *
     * @params string $resource WebFinger resource string.
     *
     * @return  array  WebFinger response payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resolve(string $resource): array
    {
        $host = Uri::getInstance()->getHost();

        // Example: acct:alice@example.org
        if (!str_starts_with($resource, 'acct:')) {
            return [
                'subject' => $resource,
                'links'   => [],
            ];
        }

        $acct = substr($resource, 5);
        if ($acct === false || $acct === '') {
            return [
                'subject' => $resource,
                'links'   => [],
            ];
        }

        [$userPart, $domainPart] = array_pad(explode('@', $acct, 2), 2, '');
        if ($userPart === '') {
            return [
                'subject' => $resource,
                'links'   => [],
            ];
        }

        $userModel = $this->mvcFactory->createModel('Users', 'Administrator');
        $userId    = $userModel->findUserIdByUsername($userPart);
        $subjectHost = $host !== '' ? $host : $domainPart;
        $subject = 'acct:' . $userPart . ($subjectHost !== '' ? '@' . $subjectHost : '');

        if ($userId === null) {
            return [
                'subject' => $subject,
                'links'   => [],
            ];
        }

        $handle   = $this->actorResolver->stableHandle($userId);
        $actorUrl = $this->baseUrl->getBaseUrl() . '/ap/actors/' . $handle;

        return [
            'subject' => $subject,
            'links'   => [
                [
                    'rel'  => 'self',
                    'type' => 'application/activity+json',
                    'href' => $actorUrl,
                ],
            ],
        ];
    }
}
