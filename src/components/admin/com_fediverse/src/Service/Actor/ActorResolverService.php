<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Actor;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ModelInterface;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Exception\UserNotFoundException;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\Model\UsersModel;
use NX\Component\Fediverse\Administrator\Service\Security\KeyVaultService;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;
use RuntimeException;

/**
 * ActorResolverService Class
 *
 * Provide Actor Resolver services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorResolverService implements ActorResolverServiceInterface
{
    /** @var UsersModel */
    private ModelInterface $usersModel;

    /** @var ActorsModel */
    private ModelInterface $actorsModel;

    /** @var KeysModel */
    private ModelInterface $keysModel;

    /**
     * Initialize the actor resolver service.
     *
     * Build required models and store supporting services.
     *
     * @params MVCFactoryInterface $mvcFactory MVC factory.
     * @params KeyVaultService $keyVault Key vault service.
     * @params JoomlaUserActorMapper $actorMapper Actor mapper.
     * @params BaseUrlProviderInterface $baseUrl Base URL provider.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        MVCFactoryInterface $mvcFactory,
        private KeyVaultService $keyVault,
        private JoomlaUserActorMapper $actorMapper,
        private BaseUrlProviderInterface $baseUrl,
    ) {
        $this->usersModel  = $mvcFactory->createModel('Users', 'Administrator');
        $this->actorsModel = $mvcFactory->createModel('Actors', 'Administrator');
        $this->keysModel   = $mvcFactory->createModel('Keys', 'Administrator');
    }

    /**
     * Get a stable handle for a user.
     *
     * Return the deterministic handle for a Joomla user id.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  string  Actor handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function stableHandle(int $userId): string
    {
        return $this->actorMapper->handleForUserId($userId);
    }

    /**
     * Ensure a local actor exists for a user.
     *
     * Provision a local actor and active key as needed.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  Actor  Local actor instance.
     * @throws  UserNotFoundException  if the user does not exist.
     * @throws  RuntimeException  if the actor cannot be provisioned.
     * @throws  \Exception  if key generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function ensureLocalActorForUserId(int $userId): Actor
    {
        $user = $this->usersModel->getById($userId);
        if ($user === null) {
            throw new UserNotFoundException($userId);
        }

        $baseUrl = $this->baseUrl->getBaseUrl();

        $actor  = $this->actorsModel->getLocalByUserId($userId);
        $mapped = $this->actorMapper->mapLocalActor($userId, (string) $user->username, $baseUrl);

        if ($actor === null) {
            $actorId = $this->actorsModel->insertLocal($mapped);
            $actor   = $mapped->withId($actorId);
        } else {
            // keep handle stable, but refresh mutable fields
            $this->actorsModel->updateLocalCoreFields((int) $actor->id, $mapped);
            $actor = $this->actorsModel->getById((int) $actor->id) ?? $actor;
        }

        if ($actor->id === null) {
            throw new RuntimeException('Actor missing id after provisioning');
        }

        $activeKey = $this->keysModel->getActiveKeyMeta((int) $actor->id);
        if ($activeKey === null) {
            $pair = $this->keyVault->generateRsaKeyPair(2048);

            $keyIdUri   = $this->buildKeyIdUri($actor->uri);
            $privateEnc = $this->keyVault->encryptPrivateKey($pair['private_key_pem']);
            $publicPem  = $pair['public_key_pem'];

            // Keep invariants: at most one active key.
            $this->keysModel->revokeActiveKeysForActor((int) $actor->id);
            $this->keysModel->insertActiveKey((int) $actor->id, $keyIdUri, $privateEnc, $publicPem);
            $this->actorsModel->setPublicKeyPem((int) $actor->id, $publicPem);

            $actor = $this->actorsModel->getById((int) $actor->id) ?? $actor;
        }

        return $actor;
    }

    /**
     * Disable a local actor for a user.
     *
     * Mark the actor disabled and revoke active keys.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disableLocalActorForUserId(int $userId): void
    {
        $actor = $this->actorsModel->getLocalByUserId($userId);
        if ($actor === null || $actor->id === null) {
            return;
        }

        $this->actorsModel->disable((int) $actor->id);
        $this->keysModel->revokeActiveKeysForActor((int) $actor->id);
    }

    /**
     * Rotate the signing key for a local actor by handle.
     *
     * Generate a new key pair and set it as the active key.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     * @throws  RuntimeException  when the actor cannot be found or updated.
     * @throws  \Exception  when key generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateSigningKeyForHandle(string $handle): void
    {
        $actor = $this->actorsModel->getLocalByHandle($handle);
        if ($actor === null || $actor->id === null) {
            throw new RuntimeException('Local actor not found');
        }

        $actorUri = trim((string) $actor->uri);
        if ($actorUri === '') {
            $actorUri = $this->actorMapper->actorUri($this->baseUrl->getBaseUrl(), $actor->handle);
        }

        $pair = $this->keyVault->generateRsaKeyPair(2048);
        $keyIdUri = $this->buildKeyIdUri($actorUri);
        $privateEnc = $this->keyVault->encryptPrivateKey($pair['private_key_pem']);
        $publicPem = $pair['public_key_pem'];

        $this->keysModel->releaseKeyIdUriForActor((int) $actor->id, $keyIdUri);
        $this->keysModel->rotateActiveKeysForActor((int) $actor->id);
        $this->keysModel->insertActiveKey((int) $actor->id, $keyIdUri, $privateEnc, $publicPem);
        $this->actorsModel->setPublicKeyPem((int) $actor->id, $publicPem);
    }

    /**
     * Rotate the signing key for a local actor by user id.
     *
     * Generate a new key pair and set it as the active key.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     * @throws  RuntimeException  when the actor cannot be found or updated.
     * @throws  \Exception  when key generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateSigningKeyForUserId(int $userId): void
    {
        $actor = $this->actorsModel->getLocalByUserId($userId);
        if ($actor === null || $actor->id === null) {
            throw new RuntimeException('Local actor not found');
        }

        $this->rotateSigningKeyForHandle($actor->handle);
    }

    /**
     * Build a unique key id URI for an actor.
     *
     * Append a unique suffix to avoid key id reuse across rotations.
     *
     * @params string $actorUri Actor URI.
     *
     * @return  string  Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildKeyIdUri(string $actorUri): string
    {
        $actorUri = trim($actorUri);
        if ($actorUri === '') {
            throw new RuntimeException('Actor URI is empty');
        }

        $suffix = bin2hex(random_bytes(6));
        $timestamp = gmdate('YmdHis');

        return $actorUri . '#main-key-' . $timestamp . '-' . $suffix;
    }

}
