<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Exception\UserNotFoundException;
use NX\Component\Fediverse\Administrator\Helper\ActorProfileDocumentDecorator;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use Throwable;

/**
 * ActorController Class
 *
 * Handle Actor requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class ActorController extends BaseController
{
    /**
     * Handle an actor GET request.
     *
     * Resolve an actor by handle and return the ActivityPub actor document.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function get(string $handle): void
    {
        $handle = trim($handle);
        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400, 'application/json');

            return;
        }

        try {
            $actor = $this->resolvePublicLocalActor($handle);

            if ($actor === null) {
                $this->sendJson(['error' => 'Actor not found'], 404, 'application/json');

                return;
            }

            [$keyId, $publicKeyPem] = $this->resolveActorKeyMaterial($actor);

            if ($publicKeyPem === null || trim($publicKeyPem) === '') {
                $this->sendJson(['error' => 'Actor key not available'], 503, 'application/json');

                return;
            }

            $accept      = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
            if ($this->prefersHtmlProfilePage($accept)) {
                $this->sendHtml($this->buildActorProfilePage($actor), 200);

                return;
            }

            $contentType = self::negotiateActorContentType($accept);

            $payload = self::buildActorDocument($actor, $keyId, $publicKeyPem);
            $this->sendJson($payload, 200, $contentType);
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Handle an actor key request.
     *
     * Resolve and return the public key document for the actor.
     *
     * @params string $handle Actor handle.
     * @params string $keyPart Key identifier segment.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function key(string $handle, string $keyPart = 'main-key'): void
    {
        $handle = trim($handle);
        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400, 'application/json');

            return;
        }

        $keyPart = trim($keyPart);
        if ($keyPart === '') {
            $keyPart = 'main-key';
        }

        if (!str_starts_with($keyPart, 'main-key')) {
            $this->sendJson(['error' => 'Invalid key identifier'], 400, 'application/json');

            return;
        }

        try {
            $actor = $this->resolvePublicLocalActor($handle);

            if ($actor === null) {
                $this->sendJson(['error' => 'Actor not found'], 404, 'application/json');

                return;
            }

            $keyIdUri = $actor->uri . '/' . $keyPart;
            $legacyKeyIdUri = self::legacyKeyIdUri($keyIdUri);
            $publicKeyPem = $actor->publicKeyPem;
            $keyMetaFound = false;

            if ($actor->id !== null) {
                $keysModel = $this->factory->createModel('Keys', 'Administrator');
                if ($keysModel instanceof KeysModel) {
                    $meta = $keysModel->getByKeyIdUri($legacyKeyIdUri);
                    if ($meta !== null) {
                        $keyMetaFound = true;
                        $publicKeyPem = is_string($meta->public_key_pem ?? null)
                            ? (string) $meta->public_key_pem
                            : $publicKeyPem;
                    }
                }
            }

            if (!$keyMetaFound && $keyPart !== 'main-key') {
                $this->sendJson(['error' => 'Key not found'], 404, 'application/json');

                return;
            }

            if ($publicKeyPem === null || trim($publicKeyPem) === '') {
                $this->sendJson(['error' => 'Actor key not available'], 503, 'application/json');

                return;
            }

            $normalizedKeyId = self::normalizeKeyIdUri($legacyKeyIdUri);
            $payload = [
                '@context' => [
                    'https://w3id.org/security/v1',
                    'https://www.w3.org/ns/activitystreams',
                ],
                'id'                => $actor->uri,
                'preferredUsername' => $actor->preferredUsername,
                'publicKey'         => [
                    'id'           => $normalizedKeyId,
                    'owner'        => $actor->uri,
                    'type'         => 'Key',
                    'publicKeyPem' => $publicKeyPem,
                ],
                'type' => $actor->actorType ?? 'Person',
            ];

            $this->sendJson($payload, 200, 'application/activity+json');
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Handle an actor featured collection request.
     *
     * Return an ordered collection containing the configured profile introduction object.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function featured(string $handle): void
    {
        $handle = trim($handle);

        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400, 'application/json');

            return;
        }

        try {
            $actor = $this->resolvePublicLocalActor($handle);

            if ($actor === null) {
                $this->sendJson(['error' => 'Actor not found'], 404, 'application/json');

                return;
            }

            $profileData = $actor->getProfileData();
            $featuredContentId = (int) ($profileData['featured_content_id'] ?? 0);
            $orderedItems = [];

            if ($featuredContentId > 0) {
                $orderedItems[] = self::buildFeaturedObjectUrl($actor, $featuredContentId);
            }

            $payload = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => rtrim($actor->uri, '/') . '/featured',
                'type' => 'OrderedCollection',
                'totalItems' => count($orderedItems),
                'orderedItems' => $orderedItems,
            ];

            $this->sendJson($payload, 200, self::negotiateActorContentType((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Resolve a public local actor by handle.
     *
     * Refresh and provision on demand, then filter out missing, remote, or disabled actors.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?Actor  Local enabled actor or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolvePublicLocalActor(string $handle): ?Actor
    {
        $actorsModel = $this->factory->createModel('Actors', 'Administrator');

        if (!$actorsModel instanceof ActorsModel) {
            throw new \RuntimeException('Internal error');
        }

        $actor = $actorsModel->getLocalByHandle($handle);

        if ($actor !== null && $actor->isLocal() && $actor->userId !== null) {
            $actor = $this->refreshLocalActor($actor);
        }

        if ($actor === null) {
            $actor = $this->provisionActorOnDemand($handle);
        }

        if ($actor === null || !$actor->isLocal() || !$actor->isEnabled) {
            return null;
        }

        return $actor;
    }

    /**
     * Refresh a local actor for the current request host.
     *
     * Rebuild the local actor when the stored URLs do not match the current host.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  Actor  Refreshed actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function refreshLocalActor(Actor $actor): Actor
    {
        if (!$this->shouldRefreshLocalActor($actor)) {
            return $actor;
        }

        if ($actor->userId === null) {
            return $actor;
        }

        try {
            /** @var ActorResolverService $resolver */
            $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);

            return $resolver->ensureLocalActorForUserId((int) $actor->userId);
        } catch (Throwable) {
            return $actor;
        }
    }

    /**
     * Check if a local actor needs refresh for the current host.
     *
     * Compare the stored actor URI host against the current request host.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  bool  True when the actor should be refreshed.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function shouldRefreshLocalActor(Actor $actor): bool
    {
        if ($actor->userId === null) {
            return false;
        }

        $currentHost = Uri::getInstance()->getHost();
        if ($currentHost === '') {
            return false;
        }

        $currentScheme = strtolower((string) Uri::getInstance()->getScheme());
        $actorHost = parse_url($actor->uri, PHP_URL_HOST) ?? '';
        if ($actorHost === '') {
            return true;
        }

        if (!hash_equals($actorHost, $currentHost)) {
            return true;
        }

        $actorScheme = strtolower((string) (parse_url($actor->uri, PHP_URL_SCHEME) ?? ''));
        if ($actorScheme === '') {
            return true;
        }

        return $currentScheme !== '' && !hash_equals($actorScheme, $currentScheme);
    }

    /**
     * Build the actor document payload.
     *
     * Assemble an ActivityPub actor document from a local actor.
     *
     * @params Actor $actor Actor instance.
     *
     * @return  array<string,mixed>  Actor document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildActorDocument(Actor $actor, ?string $keyIdUri = null, ?string $publicKeyPem = null): array
    {
        $keyIdUri = is_string($keyIdUri) && trim($keyIdUri) !== '' ? trim($keyIdUri) : $actor->uri . '#main-key';
        $publicKeyPem = is_string($publicKeyPem) && trim($publicKeyPem) !== '' ? $publicKeyPem : $actor->publicKeyPem;
        $keyIdUri = self::normalizeKeyIdUri($keyIdUri);

        $doc = [
            '@context'          => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id'                => $actor->uri,
            'type'              => $actor->actorType ?? 'Person',
            'preferredUsername' => $actor->preferredUsername,
            'inbox'             => $actor->inboxUrl,
            'outbox'            => $actor->outboxUrl,
            'publicKey'         => [
                'id'           => $keyIdUri,
                'owner'        => $actor->uri,
                'type'         => 'Key',
                'publicKeyPem' => $publicKeyPem,
            ],
        ];

        $profileData = $actor->getProfileData();

        $doc = ActorProfileDocumentDecorator::applyProfileData($doc, $profileData);

        if ($actor->sharedInboxUrl !== null && trim($actor->sharedInboxUrl) !== '') {
            $doc['endpoints'] = ['sharedInbox' => $actor->sharedInboxUrl];
        }

        return $doc;
    }

    /**
     * Build the canonical object URL for featured intro content.
     *
     * Derive the site origin from the actor URI and point to the article object endpoint.
     *
     * @params Actor $actor Actor instance.
     * @params int $contentId Joomla article identifier.
     *
     * @return  string  Canonical ActivityPub object URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildFeaturedObjectUrl(Actor $actor, int $contentId): string
    {
        $parts = parse_url($actor->uri);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        if ($host === '') {
            return '/ap/objects/article-' . $contentId;
        }

        return $scheme . '://' . $host . $port . '/ap/objects/article-' . $contentId;
    }

    /**
     * Check whether the current request should receive a human-readable profile page.
     *
     * Honor an explicit format override and otherwise prefer HTML only for browser-style Accept headers.
     *
     * @params string $accept Accept header value.
     *
     * @return  bool  True when the response should be HTML.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function prefersHtmlProfilePage(string $accept): bool
    {
        $format = strtolower(trim((string) $this->input->getString('format', '')));

        if (in_array($format, ['activitypub', 'activity+json', 'ld+json', 'json'], true)) {
            return false;
        }

        if (in_array($format, ['html', 'page'], true)) {
            return true;
        }

        $accept = strtolower($accept);

        return str_contains($accept, 'text/html')
            && !str_contains($accept, 'application/activity+json')
            && !str_contains($accept, 'application/ld+json');
    }

    /**
     * Build a human-readable actor profile page.
     *
     * Render a lightweight public HTML page for browsers while keeping ActivityPub JSON on explicit request.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  string  Complete HTML response body.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildActorProfilePage(Actor $actor): string
    {
        $profileData = $actor->getProfileData();
        $displayName = trim((string) ($profileData['name'] ?? ''));
        if ($displayName === '') {
            $displayName = $actor->preferredUsername;
        }

        $summary = trim((string) ($profileData['summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Follow this publishing identity from the Fediverse on its own domain.';
        }

        $websiteUrl = trim((string) ($profileData['url'] ?? ''));
        $websiteVerifiedAt = trim((string) ($profileData['url_verified_at'] ?? ''));
        $iconUrl = trim((string) ($profileData['icon_url'] ?? ''));
        $headerUrl = trim((string) ($profileData['image_url'] ?? ''));
        $jsonUrl = $actor->uri . '?format=activitypub';
        $featuredContentId = (int) ($profileData['featured_content_id'] ?? 0);
        $featuredTitle = $this->resolveFeaturedContentTitle($featuredContentId);
        $featuredUrl = $featuredContentId > 0 ? self::buildSiteArticleUrl($actor, $featuredContentId) : '';
        $metadata = ActorProfileDocumentDecorator::buildMetadataAttachments($profileData);

        $safeTitle = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $safeSummary = nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'));
        $safeHandle = htmlspecialchars($actor->handle, ENT_QUOTES, 'UTF-8');
        $safeActorUrl = htmlspecialchars($actor->uri, ENT_QUOTES, 'UTF-8');
        $safeJsonUrl = htmlspecialchars($jsonUrl, ENT_QUOTES, 'UTF-8');
        $safeJsonLabel = htmlspecialchars('ActivityPub JSON for @' . $actor->handle, ENT_QUOTES, 'UTF-8');
        $safeAvatarAlt = htmlspecialchars('Profile image for ' . $displayName, ENT_QUOTES, 'UTF-8');

        $headerMarkup = $headerUrl !== ''
            ? '<div class="actor-hero"><img src="' . htmlspecialchars($headerUrl, ENT_QUOTES, 'UTF-8') . '" alt=""></div>'
            : '';

        $iconMarkup = $iconUrl !== ''
            ? '<img class="actor-avatar" src="' . htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeAvatarAlt . '">'
            : '<div class="actor-avatar actor-avatar--placeholder" aria-hidden="true">' . strtoupper(substr($safeHandle, 0, 1)) . '</div>';

        $websiteMarkup = $websiteUrl !== ''
            ? '<a class="actor-link actor-link--primary" data-verified-link-status="' . ($websiteVerifiedAt !== '' ? 'verified' : 'unverified') . '" href="'
                . htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8')
                . '" rel="me external nofollow noopener noreferrer">Visit Website</a>'
            : '';
        $websiteStatusMarkup = $websiteUrl !== '' && $websiteVerifiedAt !== ''
            ? '<span class="actor-badge actor-badge--verified" role="status">Verified from linked site</span>'
            : '';

        $featuredMarkup = '';
        if ($featuredTitle !== null && $featuredUrl !== '') {
            $featuredMarkup = '<section class="actor-card actor-card--featured" aria-labelledby="actor-featured-heading">'
                . '<h2 id="actor-featured-heading">Featured Introduction</h2>'
                . '<p>Start here if you want one clear post that explains this publishing identity.</p>'
                . '<a class="actor-link actor-link--feature" href="' . htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8')
                . '</a>'
                . '</section>';
        }

        $metadataMarkup = '';
        if ($metadata !== []) {
            $items = '';
            foreach ($metadata as $item) {
                $items .= '<div class="actor-fact"><dt>' . htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8')
                    . '</dt><dd>' . htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</dd></div>';
            }

            $metadataMarkup = '<section class="actor-card" aria-labelledby="actor-facts-heading"><h2 id="actor-facts-heading">Profile Facts</h2><dl class="actor-facts">' . $items . '</dl></section>';
        }

        return '<!doctype html>'
            . '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>'
            . ':root{--bg:#f5efe3;--ink:#182126;--muted:#5a646a;--panel:#fffaf2;--line:#d9cdb7;--accent:#0f6a73;--accent-2:#d97706;}'
            . '*{box-sizing:border-box}body{margin:0;font-family:Georgia,"Times New Roman",serif;background:radial-gradient(circle at top,#fffaf2 0,#f5efe3 45%,#eadfcd 100%);color:var(--ink)}'
            . '.skip-link{position:absolute;left:16px;top:-48px;padding:10px 14px;border-radius:999px;background:#182126;color:#fff;text-decoration:none;font-weight:700;z-index:10}.skip-link:focus{top:16px}'
            . '.actor-shell{max-width:920px;margin:0 auto;padding:32px 20px 64px}.actor-hero{height:220px;border-radius:28px;overflow:hidden;background:linear-gradient(135deg,#18343b,#3e6d72 55%,#d97706);box-shadow:0 24px 60px rgba(24,33,38,.18)}'
            . '.actor-hero img{width:100%;height:100%;object-fit:cover;display:block}.actor-card{background:rgba(255,250,242,.88);border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 18px 44px rgba(24,33,38,.08)}'
            . '.actor-card--lead{margin-top:-56px;position:relative}.actor-identity{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}.actor-avatar{width:112px;height:112px;border-radius:28px;object-fit:cover;border:4px solid rgba(255,250,242,.9);background:#d8c5a0}'
            . '.actor-avatar--placeholder{display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:700;color:#fff;background:linear-gradient(135deg,#0f6a73,#d97706)}'
            . '.actor-copy{flex:1;min-width:240px}.actor-kicker{text-transform:uppercase;letter-spacing:.14em;font-size:.76rem;color:var(--accent);margin:0 0 10px;font-family:"Courier New",monospace}'
            . 'h1{margin:0 0 10px;font-size:clamp(2rem,5vw,3.8rem);line-height:1}.actor-handle{margin:0 0 16px;color:var(--muted);font-family:"Courier New",monospace}.actor-summary{margin:0;font-size:1.08rem;line-height:1.6;max-width:56ch}'
            . '.actor-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}.actor-link{display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;border-radius:999px;text-decoration:none;font-weight:700}'
            . '.actor-link--primary{background:var(--accent);color:#fff}.actor-link--secondary{border:1px solid var(--line);color:var(--ink);background:transparent}.actor-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:24px}'
            . '.actor-badge{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;font-size:.92rem;font-weight:700}.actor-badge--verified{background:#d8f0df;color:#14532d}'
            . '.actor-card h2{margin:0 0 12px;font-size:1.15rem}.actor-card p{margin:0 0 16px;color:var(--muted);line-height:1.55}.actor-link--feature{color:var(--ink);background:#f2e0bb;border:1px solid #e0c57b}'
            . '.actor-facts{margin:0}.actor-fact{display:grid;grid-template-columns:minmax(90px,140px) 1fr;gap:10px;padding:10px 0;border-top:1px solid rgba(217,205,183,.7)}.actor-fact:first-child{border-top:0;padding-top:0}'
            . '.actor-fact dt{font-weight:700}.actor-fact dd{margin:0;color:var(--muted)}.actor-endpoints{margin-top:24px;padding:18px 20px;border-radius:20px;background:rgba(24,33,38,.04);font-family:"Courier New",monospace;font-size:.9rem;overflow:auto}'
            . '.actor-endpoints a{color:var(--accent)}@media (max-width:720px){.actor-shell{padding:20px 14px 48px}.actor-card--lead{margin-top:16px}.actor-identity{flex-direction:column}.actor-avatar{width:88px;height:88px;border-radius:22px}}'
            . '</style></head><body class="com-fediverse-actor-page"><main class="actor-shell" data-actor-handle="' . $safeHandle . '">'
            . '<a class="skip-link" href="#actor-profile-details">Skip to profile details</a>'
            . $headerMarkup
            . '<section class="actor-card actor-card--lead" aria-labelledby="actor-profile-heading" aria-describedby="actor-profile-summary"><div class="actor-identity">' . $iconMarkup
            . '<div class="actor-copy"><p class="actor-kicker">Fediverse Publishing Identity</p><h1 id="actor-profile-heading">' . $safeTitle . '</h1>'
            . '<p class="actor-handle">@' . $safeHandle . '</p><p class="actor-summary" id="actor-profile-summary">' . $safeSummary . '</p>'
            . '<div class="actor-actions">' . $websiteMarkup
            . $websiteStatusMarkup
            . '<a class="actor-link actor-link--secondary" href="' . $safeJsonUrl . '" aria-label="' . $safeJsonLabel . '">ActivityPub JSON</a></div></div></div>'
            . '<section class="actor-endpoints" aria-labelledby="actor-endpoints-heading"><h2 id="actor-endpoints-heading" class="actor-kicker">Actor Endpoint</h2>Actor URI: <a href="' . $safeActorUrl . '">' . $safeActorUrl . '</a></section></section>'
            . '<div class="actor-grid" id="actor-profile-details">' . $featuredMarkup . $metadataMarkup . '</div></main></body></html>';
    }

    /**
     * Resolve the title of the configured featured intro article.
     *
     * @params int $contentId Joomla article identifier.
     *
     * @return  ?string  Published article title or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveFeaturedContentTitle(int $contentId): ?string
    {
        if ($contentId <= 0) {
            return null;
        }

        $actorsModel = $this->factory->createModel('Actors', 'Administrator');

        if (!$actorsModel instanceof ActorsModel) {
            return null;
        }

        return $actorsModel->getPublishedContentTitle($contentId);
    }

    /**
     * Build a human-facing Joomla article URL for featured intro content.
     *
     * @params Actor $actor Actor instance.
     * @params int $contentId Joomla article identifier.
     *
     * @return  string  Absolute article URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildSiteArticleUrl(Actor $actor, int $contentId): string
    {
        $parts = parse_url($actor->uri);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        if ($host === '') {
            return '/index.php?option=com_content&view=article&id=' . $contentId;
        }

        return $scheme . '://' . $host . $port . '/index.php?option=com_content&view=article&id=' . $contentId;
    }

    /**
     * Resolve the active key id and public key for an actor.
     *
     * Prefer the active key record, fall back to actor metadata.
     *
     * @params Actor $actor Actor instance.
     *
     * @return  array{0:?string,1:?string}  Key id URI and public key PEM.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveActorKeyMaterial(Actor $actor): array
    {
        $keyId = null;
        $publicKeyPem = $actor->publicKeyPem;

        if ($actor->id !== null) {
            $keysModel = $this->factory->createModel('Keys', 'Administrator');
            if ($keysModel instanceof KeysModel) {
                $meta = $keysModel->getActiveKeyMeta((int) $actor->id);
                if ($meta !== null) {
                    $keyId = is_string($meta->key_id_uri ?? null) ? (string) $meta->key_id_uri : null;
                    $publicKeyPem = is_string($meta->public_key_pem ?? null) ? (string) $meta->public_key_pem : $publicKeyPem;
                }
            }
        }

        if (!is_string($keyId) || trim($keyId) === '') {
            $keyId = $actor->uri . '#main-key';
        }

        return [$keyId, $publicKeyPem];
    }

    /**
     * Normalize a key id URI for outbound use.
     *
     * Convert fragment-based key ids to path-based key ids.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Normalized key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeKeyIdUri(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)#(main-key.*)$~', $keyId, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return $keyId;
    }

    /**
     * Convert key id URIs back to legacy fragment format.
     *
     * Map path-based key ids to fragment-based ids for storage lookup.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Legacy key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function legacyKeyIdUri(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)/main-key(.*)$~', $keyId, $matches)) {
            return $matches[1] . '#main-key' . $matches[2];
        }

        return $keyId;
    }

    /**
     * Negotiate the actor response content type.
     *
     * Select the appropriate content type based on the Accept header.
     *
     * @params string $acceptHeader Accept header value.
     *
     * @return  string  Selected content type.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function negotiateActorContentType(string $acceptHeader): string
    {
        $accept = strtolower($acceptHeader);

        if (str_contains($accept, 'application/ld+json')) {
            return 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        }

        return 'application/activity+json';
    }

    /**
     * Provision a local actor on demand.
     *
     * Create a local actor for a matching handle when possible.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?Actor  Local actor instance or null when not available.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function provisionActorOnDemand(string $handle): ?Actor
    {
        if (!preg_match('/^u([1-9][0-9]*)$/', $handle, $m)) {
            return null;
        }

        /** @var ActorResolverService $resolver */
        $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);

        try {
            return $resolver->ensureLocalActorForUserId((int) $m[1]);
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * Send a JSON response.
     *
     * Encode a payload and emit a JSON response with headers.
     *
     * @params array<string,mixed> $payload Response payload.
     * @params int $status HTTP status code.
     * @params string $contentType Response content type.
     *
     * @return  void  None.
     * @throws  \JsonException  if JSON encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(array $payload, int $status, string $contentType): void
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType . '; charset=utf-8');
            header('Vary: Accept');
        }

        echo $json;

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }

    /**
     * Send an HTML response.
     *
     * Emit a complete HTML document with the given HTTP status.
     *
     * @params string $html HTML response body.
     * @params int $status HTTP status code.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendHtml(string $html, int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            header('Vary: Accept');
        }

        echo $html;

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }
}
