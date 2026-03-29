<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Mapper;

use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * JoomlaContentMapper Class
 *
 * Map Joomla Content data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaContentMapper implements JoomlaContentMapperInterface
{
    private const PUBLIC_AUDIENCE = 'https://www.w3.org/ns/activitystreams#Public';

    /**
     * Initialize the content mapper.
     *
     * Store the actor mapper dependency used for URL generation.
     *
     * @params JoomlaUserActorMapper $actorMapper Actor mapper.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private JoomlaUserActorMapper $actorMapper)
    {
    }

    /**
     * Map an article to a create or update activity.
     *
     * Build an ActivityPub activity for article creation or update.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params bool $isNew Whether the article is new.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for create or update.
     * @throws  \Exception  if token generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $article, bool $isNew, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $object     = $this->toObject($actor, $article, $baseUrl);
        $audience   = $this->buildAudience($actor);

        $json = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => $isNew ? 'Create' : 'Update',
            'actor'    => $actor->uri,
            'object'   => $object,
            'to'       => $audience['to'],
            'cc'       => $audience['cc'],
        ];

        return ActivityEnvelope::fromJson($json);
    }

    /**
     * Map an article to a delete activity.
     *
     * Build an ActivityPub delete activity for the article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for delete.
     * @throws  \Exception  if token generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $article, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $objectId   = $this->actorMapper->objectUriForArticle($baseUrl, (int) $article->id);
        $audience   = $this->buildAudience($actor);

        $json = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => 'Delete',
            'actor'    => $actor->uri,
            'object'   => [
                'id'   => $objectId,
                'type' => 'Tombstone',
            ],
            'to'       => $audience['to'],
            'cc'       => $audience['cc'],
        ];

        return ActivityEnvelope::fromJson($json);
    }

    /**
     * Map an article to an ActivityPub object.
     *
     * Build an ActivityPub object representation for a Joomla article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  array<string,mixed>  ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $article, string $baseUrl): array
    {
        $objectId   = $this->actorMapper->objectUriForArticle($baseUrl, (int) $article->id);
        $audience   = $this->buildAudience($actor);
        $published  = $this->formatTimestamp($article->created ?? null);
        $updated    = $this->formatTimestamp($article->modified ?? null);
        $controls   = $this->resolvePostControls($article);
        $content    = $controls['useIntroText'] ? $this->resolveIntroContent($article) : $controls['excerpt'];
        if ($content === '') {
            $content = $this->resolveIntroContent($article);
        }

        $content = $this->applyHashtagMode($content, $article, $controls['hashtagSource']);
        $articleUrl = $this->articleUrl($baseUrl, (int) $article->id);

        $object = [
            'id'           => $objectId,
            'type'         => $actor->objectType ?? 'Note',
            'attributedTo' => $actor->uri,
            'name'         => (string) ($article->title ?? ''),
            'content'      => $content,
            'to'           => $audience['to'],
            'cc'           => $audience['cc'],
        ];

        if ($controls['includeLink']) {
            $object['url'] = $articleUrl;
        }

        $image = $this->resolveArticleImage($article, $baseUrl, $controls['imageSource']);
        if ($image !== null) {
            $object['image'] = $image;
        }

        if ($published !== null) {
            $object['published'] = $published;
        }

        if ($updated !== null) {
            $object['updated'] = $updated;
        }

        return $object;
    }

    /**
     * Resolve per-post federation controls from article metadata.
     *
     * Read optional controls from article attribs or direct fields.
     *
     * @params object $article Joomla article object.
     *
     * @return  array{excerpt:string,useIntroText:bool,includeLink:bool,imageSource:string,hashtagSource:string}  Post controls.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolvePostControls(object $article): array
    {
        $attribs = $this->decodeAssocValue($article->attribs ?? null);

        $excerptValue = $attribs['fediverse_excerpt'] ?? ($article->fediverse_excerpt ?? null);
        $excerpt      = is_string($excerptValue) ? trim($excerptValue) : '';

        return [
            'excerpt'         => $excerpt,
            'useIntroText'    => $this->resolveBooleanControl(
                $attribs['fediverse_use_intro_text'] ?? ($article->fediverse_use_intro_text ?? null),
                true
            ),
            'includeLink'     => $this->resolveBooleanControl(
                $attribs['fediverse_include_link'] ?? ($article->fediverse_include_link ?? null),
                true
            ),
            'imageSource'     => $this->resolveImageSourceControl($attribs, $article),
            'hashtagSource'   => $this->resolveHashtagSourceControl($attribs, $article),
        ];
    }

    /**
     * Decode an article value into an associative array.
     *
     * Supports array, object, and JSON-string representations.
     *
     * @params mixed $value Raw value.
     *
     * @return  array<string,mixed>  Decoded array.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function decodeAssocValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Resolve a boolean federation control value.
     *
     * Converts mixed control values to a deterministic boolean.
     *
     * @params mixed $value Control value.
     * @params bool $default Default value when input is not explicit.
     *
     * @return  bool  Normalized boolean.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveBooleanControl(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * Resolve the selected Joomla image source for federation output.
     *
     * Accepts the explicit image source field and tolerates the old boolean flag during transition.
     *
     * @params array<string,mixed> $attribs Decoded Joomla attribs.
     * @params object $article Joomla article object.
     *
     * @return  string  One of none, intro, or full.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveImageSourceControl(array $attribs, object $article): string
    {
        $value = $attribs['fediverse_image_source'] ?? ($article->fediverse_image_source ?? null);

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['none', 'intro', 'full'], true)) {
                return $normalized;
            }
        }

        $legacyValue = $attribs['fediverse_include_image'] ?? ($article->fediverse_include_image ?? null);

        return $this->resolveBooleanControl($legacyValue, true) ? 'intro' : 'none';
    }

    /**
     * Resolve the configured hashtag source.
     *
     * Accepts the explicit source selector and tolerates the old include/strip toggle during transition.
     *
     * @params array<string,mixed> $attribs Decoded Joomla attribs.
     * @params object $article Joomla article object.
     *
     * @return  string  One of none, text, tags, or both.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveHashtagSourceControl(array $attribs, object $article): string
    {
        $value = $attribs['fediverse_hashtag_source'] ?? ($article->fediverse_hashtag_source ?? null);

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['none', 'text', 'tags', 'both'], true)) {
                return $normalized;
            }
        }

        $legacyValue = $attribs['fediverse_include_hashtags'] ?? ($article->fediverse_include_hashtags ?? null);

        return $this->resolveBooleanControl($legacyValue, true) ? 'text' : 'none';
    }

    /**
     * Resolve an article image object for federation output.
     *
     * Reads intro/full image values, normalizes to an absolute URL, and preserves alt text when available.
     *
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for local URL normalization.
     * @params string $imageSource Selected image source.
     *
     * @return  ?array<string,string>  Image object or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveArticleImage(object $article, string $baseUrl, string $imageSource): ?array
    {
        if ($imageSource === 'none') {
            return null;
        }

        $images = $this->decodeAssocValue($article->images ?? null);
        $image  = null;
        $altText = '';
        $field = $imageSource === 'full' ? 'image_fulltext' : 'image_intro';
        $value = $images[$field] ?? null;

        if (is_string($value) && trim($value) !== '') {
            $image = trim($value);
            $altField = $field . '_alt';
            $emptyField = $field . '_alt_empty';
            $altEmpty = $this->resolveBooleanControl($images[$emptyField] ?? null, false);

            if (!$altEmpty) {
                $altValue = $images[$altField] ?? null;
                $altText = is_string($altValue) ? trim($altValue) : '';
            }
        }

        if ($image === null || $image === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $image) === 1) {
            $imageUrl = $image;
        } elseif (str_starts_with($image, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'https';

            $imageUrl = $scheme . ':' . $image;
        } else {
            $uri = clone Uri::getInstance($baseUrl);
            $uri->setQuery('');
            $uri->setFragment('');

            $basePath = rtrim((string) $uri->getPath(), '/');
            $suffix   = '/' . ltrim($image, '/');
            $uri->setPath($basePath . $suffix);
            $imageUrl = $uri->toString();
        }

        $imageObject = [
            'type' => 'Image',
            'url'  => $imageUrl,
        ];

        if ($altText !== '') {
            $imageObject['name'] = $altText;
        }

        return $imageObject;
    }

    /**
     * Apply the configured hashtag source to the federated content.
     *
     * Keeps the chosen text readable and appends only the missing hashtags from the selected sources.
     *
     * @params string $content Chosen federated text.
     * @params object $article Joomla article object.
     * @params string $hashtagSource Configured hashtag source.
     *
     * @return  string  Final content.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function applyHashtagMode(string $content, object $article, string $hashtagSource): string
    {
        $baseContent = in_array($hashtagSource, ['none', 'tags'], true) ? $this->stripHashtags($content) : $content;

        $existing = $this->extractHashtags($baseContent);
        $toAppend = [];

        if (in_array($hashtagSource, ['text', 'both'], true)) {
            $articleHashtags = $this->extractHashtags($this->resolveFullArticleContent($article));
            $toAppend = array_merge($toAppend, $this->diffHashtags($articleHashtags, $existing));
            $existing = array_merge($existing, $toAppend);
        }

        if (in_array($hashtagSource, ['tags', 'both'], true)) {
            $tagHashtags = $this->extractTagHashtags($article);
            $missingTags = $this->diffHashtags($tagHashtags, $existing);
            $toAppend = array_merge($toAppend, $missingTags);
        }

        $toAppend = array_values(array_unique($toAppend));

        if ($toAppend === []) {
            return trim($baseContent);
        }

        $trimmedContent = trim($baseContent);
        if ($trimmedContent === '') {
            return implode(' ', $toAppend);
        }

        return $trimmedContent . "\n\n" . implode(' ', $toAppend);
    }

    /**
     * Extract hashtag tokens from a text string.
     *
     * @params string $content Content string.
     *
     * @return  array<int,string>  Unique hashtag tokens including the # prefix.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractHashtags(string $content): array
    {
        if (!preg_match_all('/(?<!\w)(#[\p{L}\p{N}_]+)/u', $content, $matches)) {
            return [];
        }

        $hashtags = array_map(
            static fn (string $hashtag): string => mb_strtolower(trim($hashtag)),
            $matches[1]
        );

        return array_values(array_unique($hashtags));
    }

    /**
     * Extract hashtags from Joomla article tags.
     *
     * Supports tags as arrays, objects, or simple strings.
     *
     * @params object $article Joomla article object.
     *
     * @return  array<int,string>  Unique hashtag tokens including the # prefix.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractTagHashtags(object $article): array
    {
        $rawTags = $article->tags ?? [];

        if (!is_array($rawTags) && !($rawTags instanceof \Traversable)) {
            return [];
        }

        $hashtags = [];

        foreach ($rawTags as $tag) {
            $candidate = null;

            if (is_string($tag)) {
                $candidate = $tag;
            } elseif (is_array($tag)) {
                $candidate = $tag['alias'] ?? $tag['title'] ?? $tag['path'] ?? null;
            } elseif (is_object($tag)) {
                $candidate = $tag->alias ?? $tag->title ?? $tag->path ?? null;
            }

            if (!is_string($candidate)) {
                continue;
            }

            $normalized = preg_replace('/[^\p{L}\p{N}_]+/u', '_', trim($candidate));
            if (!is_string($normalized)) {
                continue;
            }

            $normalized = trim($normalized, '_');
            if ($normalized === '') {
                continue;
            }

            $hashtags[] = '#' . mb_strtolower($normalized);
        }

        return array_values(array_unique($hashtags));
    }

    /**
     * Return hashtags from the candidate list that are not already present.
     *
     * @params array<int,string> $candidates Candidate hashtags.
     * @params array<int,string> $existing Existing hashtags.
     *
     * @return  array<int,string>  Missing hashtags.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function diffHashtags(array $candidates, array $existing): array
    {
        return array_values(array_diff(array_unique($candidates), array_unique($existing)));
    }

    /**
     * Strip hashtag tokens from federated content.
     *
     * Removes #tags while keeping surrounding sentence structure readable.
     *
     * @params string $content Content string.
     *
     * @return  string  Content without hashtags.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function stripHashtags(string $content): string
    {
        $stripped = preg_replace('/(^|\s+)#[A-Za-z0-9_]+/u', '$1', $content);
        if (!is_string($stripped)) {
            return $content;
        }

        $stripped = preg_replace('/[ \t]{2,}/', ' ', $stripped);
        if (!is_string($stripped)) {
            return trim($content);
        }

        return trim($stripped);
    }

    /**
     * Resolve the best article content field.
     *
     * Prefer the composed text field, falling back to intro/full text.
     *
     * @params object $article Joomla article object.
     *
     * @return  string  Content string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveFullArticleContent(object $article): string
    {
        $intro = is_string($article->introtext ?? null) ? (string) $article->introtext : '';
        $full  = is_string($article->fulltext ?? null) ? (string) $article->fulltext : '';

        if ($intro !== '' && $full !== '') {
            return $intro . "\n\n" . $full;
        }

        if ($intro !== '') {
            return $intro;
        }

        if ($full !== '') {
            return $full;
        }

        $text = $article->text ?? null;
        if (is_string($text) && trim($text) !== '') {
            return $text;
        }

        return '';
    }

    /**
     * Resolve the intro-based text used as the default federated post text.
     *
     * Prefer the Joomla intro text and fall back to fulltext or text when needed.
     *
     * @params object $article Joomla article object.
     *
     * @return  string  Intro-based content string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveIntroContent(object $article): string
    {
        $intro = is_string($article->introtext ?? null) ? trim((string) $article->introtext) : '';
        if ($intro !== '') {
            return $intro;
        }

        $full = is_string($article->fulltext ?? null) ? trim((string) $article->fulltext) : '';
        if ($full !== '') {
            return $full;
        }

        $text = $article->text ?? null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        return '';
    }

    /**
     * Format a Joomla datetime string as ISO 8601.
     *
     * Convert common Joomla datetime values to an ActivityPub-compatible format.
     *
     * @params mixed $value Datetime string or null.
     *
     * @return  ?string  ISO 8601 timestamp or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function formatTimestamp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = new \DateTimeImmutable($trimmed);
        } catch (\Throwable) {
            return null;
        }

        return $dt->format(\DateTimeInterface::ATOM);
    }

    /**
     * Build ActivityPub audience fields.
     *
     * Emit Public + followers targets for standard delivery.
     *
     * @params Actor $actor Local actor.
     *
     * @return  array{to:array<int,string>,cc:array<int,string>}  Audience map.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildAudience(Actor $actor): array
    {
        $followers = rtrim($actor->uri, '/') . '/followers';

        return [
            'to' => [self::PUBLIC_AUDIENCE],
            'cc' => [$followers],
        ];
    }

    /**
     * Build a public article URL.
     *
     * Construct the article URL from the base site URL and article id.
     *
     * @params string $baseUrl Base URL for building links.
     * @params int $articleId Joomla article identifier.
     *
     * @return  string  Article URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function articleUrl(string $baseUrl, int $articleId): string
    {
        $uri = clone Uri::getInstance($baseUrl);
        $uri->setQuery('');
        $uri->setFragment('');

        $basePath = rtrim((string) $uri->getPath(), '/');
        $uri->setPath($basePath . '/index.php');
        $uri->setQuery([
            'option' => 'com_content',
            'view'   => 'article',
            'id'     => $articleId,
        ]);

        return $uri->toString();
    }
}
