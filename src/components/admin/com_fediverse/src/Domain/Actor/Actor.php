<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Domain\Actor;

/**
 * Actor Class
 *
 * Represent Actor domain data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Actor
{
    public ?int    $id             = null;
    public ?int    $userId         = null;
    public ?string $sharedInboxUrl = null;
    public ?string $publicKeyPem   = null;
    public ?string $profileJson    = null;
    public bool    $isEnabled      = true;
    public ?string $actorType      = 'Person';
    public ?string $objectType     = 'Note';
    public ?string $createdAt      = null;
    public ?string $updatedAt      = null;

    /**
     * Create an actor instance.
     *
     * Capture the required actor fields for persistence and HTTP output.
     *
     * @params string $type Actor type like local or remote.
     * @params string $handle Stable handle for the actor.
     * @params string $preferredUsername Public-facing username.
     * @params string $uri Canonical actor URI.
     * @params string $inboxUrl Inbox endpoint URL.
     * @params string $outboxUrl Outbox endpoint URL.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly string $type, // 'local' | 'remote'
        public readonly string $handle,
        public readonly string $preferredUsername,
        public readonly string $uri,
        public readonly string $inboxUrl,
        public readonly string $outboxUrl,
    ) {
    }

    /**
     * Create a local actor.
     *
     * Build a local actor and apply optional metadata.
     *
     * @params int $userId Joomla user identifier.
     * @params string $handle Stable handle for the actor.
     * @params string $preferredUsername Public-facing username.
     * @params string $uri Canonical actor URI.
     * @params string $inboxUrl Inbox endpoint URL.
     * @params string $outboxUrl Outbox endpoint URL.
     * @params ?string $sharedInboxUrl Shared inbox URL if available.
     * @params ?string $publicKeyPem PEM-encoded public key.
     * @params ?string $profileJson Optional profile JSON.
     * @params bool $isEnabled Whether the actor is enabled.
     *
     * @return  self  Local actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function newLocal(
        int $userId,
        string $handle,
        string $preferredUsername,
        string $uri,
        string $inboxUrl,
        string $outboxUrl,
        ?string $sharedInboxUrl = null,
        ?string $publicKeyPem = null,
        ?string $profileJson = null,
        bool $isEnabled = true,
    ): self {
        $actor = new self(
            type: 'local',
            handle: $handle,
            preferredUsername: $preferredUsername,
            uri: $uri,
            inboxUrl: $inboxUrl,
            outboxUrl: $outboxUrl,
        );

        return $actor
            ->setUserId($userId)
            ->setSharedInboxUrl($sharedInboxUrl)
            ->setPublicKeyPem($publicKeyPem)
            ->setProfileJson($profileJson)
            ->setEnabled($isEnabled);
    }

    /**
     * Create a remote actor.
     *
     * Build a remote actor and apply optional metadata.
     *
     * @params string $handle Stable handle for the actor.
     * @params string $preferredUsername Public-facing username.
     * @params string $uri Canonical actor URI.
     * @params string $inboxUrl Inbox endpoint URL.
     * @params string $outboxUrl Outbox endpoint URL.
     * @params ?string $sharedInboxUrl Shared inbox URL if available.
     * @params ?string $publicKeyPem PEM-encoded public key.
     * @params ?string $profileJson Optional profile JSON.
     * @params bool $isEnabled Whether the actor is enabled.
     *
     * @return  self  Remote actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function newRemote(
        string $handle,
        string $preferredUsername,
        string $uri,
        string $inboxUrl,
        string $outboxUrl,
        ?string $sharedInboxUrl = null,
        ?string $publicKeyPem = null,
        ?string $profileJson = null,
        bool $isEnabled = true,
    ): self {
        $actor = new self(
            type: 'remote',
            handle: $handle,
            preferredUsername: $preferredUsername,
            uri: $uri,
            inboxUrl: $inboxUrl,
            outboxUrl: $outboxUrl,
        );

        return $actor
            ->setSharedInboxUrl($sharedInboxUrl)
            ->setPublicKeyPem($publicKeyPem)
            ->setProfileJson($profileJson)
            ->setEnabled($isEnabled);
    }

    /**
     * Create an actor from a database row.
     *
     * Map row fields into an actor instance and fill optional metadata.
     *
     * @params array<string,mixed>|object $row Source database row.
     *
     * @return  self  Actor instance populated from the row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromDbRow(array | object $row): self
    {
        $get = static function(string $k) use ($row) {
            return $row->$k ?? null;
        };

        $actor = new self(
            type: (string) $get('type'),
            handle: (string) $get('handle'),
            preferredUsername: (string) $get('preferred_username'),
            uri: (string) $get('uri'),
            inboxUrl: (string) $get('inbox_url'),
            outboxUrl: (string) $get('outbox_url'),
        );

        if ($get('id') !== null) {
            $actor->setId((int) $get('id'));
        }

        if ($get('user_id') !== null) {
            $actor->setUserId((int) $get('user_id'));
        }

        return $actor
            ->setSharedInboxUrl($get('shared_inbox_url') !== null ? (string) $get('shared_inbox_url') : null)
            ->setPublicKeyPem($get('public_key_pem') !== null ? (string) $get('public_key_pem') : null)
            ->setProfileJson($get('profile_json') !== null ? (string) $get('profile_json') : null)
            ->setEnabled((bool) ((int) ($get('is_enabled') ?? 0)))
            ->setActorType($get('actor_type') !== null ? (string) $get('actor_type') : 'Person')
            ->setObjectType($get('object_type') !== null ? (string) $get('object_type') : 'Note')
            ->setCreatedAt($get('created_at') !== null ? (string) $get('created_at') : null)
            ->setUpdatedAt($get('updated_at') !== null ? (string) $get('updated_at') : null);
    }

    /**
     * Set the actor id.
     *
     * Attach the database identifier to the actor instance.
     *
     * @params int $id Actor identifier.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the owning user id.
     *
     * Assign or clear the Joomla user association.
     *
     * @params ?int $userId Joomla user identifier.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Set the shared inbox URL.
     *
     * Store the shared inbox endpoint if available.
     *
     * @params ?string $sharedInboxUrl Shared inbox URL.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setSharedInboxUrl(?string $sharedInboxUrl): self
    {
        $this->sharedInboxUrl = $sharedInboxUrl;

        return $this;
    }

    /**
     * Set the public key PEM.
     *
     * Attach or clear the public key used for verification.
     *
     * @params ?string $publicKeyPem PEM-encoded public key.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setPublicKeyPem(?string $publicKeyPem): self
    {
        $this->publicKeyPem = $publicKeyPem;

        return $this;
    }

    /**
     * Set the profile JSON.
     *
     * Store a serialized profile payload if available.
     *
     * @params ?string $profileJson Profile JSON payload.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setProfileJson(?string $profileJson): self
    {
        $this->profileJson = $profileJson;

        return $this;
    }

    /**
     * Set the enabled flag.
     *
     * Mark the actor as enabled or disabled.
     *
     * @params bool $isEnabled Enabled state.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    /**
     * Set the creation timestamp.
     *
     * Store the creation time for persistence metadata.
     *
     * @params ?string $createdAt Creation timestamp.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Set the update timestamp.
     *
     * Store the last update time for persistence metadata.
     *
     * @params ?string $updatedAt Update timestamp.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Set the actor type.
     *
     * Store the ActivityPub actor type (Person or Service).
     *
     * @params ?string $actorType Actor type value.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setActorType(?string $actorType): self
    {
        $this->actorType = $actorType;

        return $this;
    }

    /**
     * Set the object type.
     *
     * Store the ActivityPub object type for published content.
     *
     * @params ?string $objectType Object type value.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setObjectType(?string $objectType): self
    {
        $this->objectType = $objectType;

        return $this;
    }

    /**
     * Return the decoded profile data.
     *
     * Normalize the stored JSON payload into a flat string map.
     *
     * @return  array<string, string>  Decoded profile data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getProfileData(): array
    {
        $json = trim((string) ($this->profileJson ?? ''));

        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $profileData = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || $key === '' || !is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);

            if ($normalized === '') {
                continue;
            }

            $profileData[$key] = $normalized;
        }

        return $profileData;
    }

    /**
     * Replace the stored profile data with a normalized map.
     *
     * @param   array<string, scalar|null>  $profileData  Profile data to persist.
     *
     * @return  self  Updated actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setProfileData(array $profileData): self
    {
        $normalized = [];

        foreach ($profileData as $key => $value) {
            if (!is_string($key) || $key === '' || !is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);

            if ($stringValue === '') {
                continue;
            }

            $normalized[$key] = $stringValue;
        }

        $this->profileJson = $normalized === []
            ? null
            : (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this;
    }

    /**
     * Return an actor with a new id.
     *
     * Produce an actor instance associated with the provided id.
     *
     * @params int $id Actor identifier.
     *
     * @return  self  Actor instance with the updated id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function withId(int $id): self
    {
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Check whether the actor is local.
     *
     * Determine if the actor represents a local user account.
     *
     * @return  bool  True when the actor is local.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isLocal(): bool
    {
        return $this->type === 'local';
    }
}
