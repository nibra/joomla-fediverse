<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Profile;

use DOMDocument;
use DOMElement;

/**
 * ExternalProfileLinkVerifier Class
 *
 * Verify external profile links through reciprocal rel=me proofs.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ExternalProfileLinkVerifier
{
    /**
     * Initialize the verifier.
     *
     * @param   null|callable(string):?string  $fetcher  Optional HTML fetch callback for tests.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private $fetcher = null)
    {
    }

    /**
     * Verify that an external profile page links back to the actor URL with rel=me.
     *
     * @param   string  $profileUrl  External profile URL to verify.
     * @param   string  $actorUrl    Canonical actor page URL.
     *
     * @return  bool  True when reciprocal proof is present.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function verifyOwnership(string $profileUrl, string $actorUrl): bool
    {
        $profileUrl = $this->normalizeComparableUrl($profileUrl);
        $actorUrl   = $this->normalizeComparableUrl($actorUrl);

        if ($profileUrl === '' || $actorUrl === '') {
            return false;
        }

        $html = $this->fetchHtml($profileUrl);

        if (!is_string($html) || trim($html) === '') {
            return false;
        }

        return $this->hasRelMeBackLink($html, $profileUrl, $actorUrl);
    }

    /**
     * Fetch the remote HTML document.
     *
     * @param   string  $url  Absolute URL.
     *
     * @return  ?string  HTML response body or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function fetchHtml(string $url): ?string
    {
        if (is_callable($this->fetcher)) {
            $html = ($this->fetcher)($url);

            return is_string($html) ? $html : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "Accept: text/html\r\nUser-Agent: Joomla-Fediverse-LinkVerifier/1.0\r\n",
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        return is_string($html) ? $html : null;
    }

    /**
     * Check whether the fetched HTML contains a reciprocal rel=me link.
     *
     * @param   string  $html       HTML response body.
     * @param   string  $baseUrl    Source page URL.
     * @param   string  $actorUrl   Canonical actor URL.
     *
     * @return  bool  True when a reciprocal proof link exists.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function hasRelMeBackLink(string $html, string $baseUrl, string $actorUrl): bool
    {
        $internalErrors = libxml_use_internal_errors(true);
        $dom            = new DOMDocument();

        try {
            if (!$dom->loadHTML($html)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        foreach (['a', 'link'] as $tagName) {
            foreach ($dom->getElementsByTagName($tagName) as $element) {
                if (!$element instanceof DOMElement) {
                    continue;
                }

                if (!$this->hasRelMe($element->getAttribute('rel'))) {
                    continue;
                }

                $href = $this->resolveUrl($element->getAttribute('href'), $baseUrl);

                if ($href !== '' && hash_equals($actorUrl, $href)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check whether a rel attribute contains me.
     *
     * @param   string  $rel  Raw rel attribute.
     *
     * @return  bool  True when rel includes me.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function hasRelMe(string $rel): bool
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];

        return in_array('me', $tokens, true);
    }

    /**
     * Resolve a possibly relative href against the source page URL.
     *
     * @param   string  $href     Link target.
     * @param   string  $baseUrl  Source page URL.
     *
     * @return  string  Absolute comparable URL or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveUrl(string $href, string $baseUrl): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $href)) {
            return $this->normalizeComparableUrl($href);
        }

        $base = parse_url($baseUrl);
        $scheme = (string) ($base['scheme'] ?? '');
        $host = (string) ($base['host'] ?? '');

        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($base['port']) ? ':' . (int) $base['port'] : '';

        if (str_starts_with($href, '//')) {
            return $this->normalizeComparableUrl($scheme . ':' . $href);
        }

        if (str_starts_with($href, '/')) {
            return $this->normalizeComparableUrl($scheme . '://' . $host . $port . $href);
        }

        $basePath = (string) ($base['path'] ?? '/');
        $baseDir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';

        return $this->normalizeComparableUrl($scheme . '://' . $host . $port . $baseDir . $href);
    }

    /**
     * Normalize a URL for comparison.
     *
     * @param   string  $url  Candidate URL.
     *
     * @return  string  Normalized absolute URL or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeComparableUrl(string $url): string
    {
        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return rtrim($scheme . '://' . $host . $port . $path, '/') . $query;
    }
}
