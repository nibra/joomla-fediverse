<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Http;

/**
 * RequestContext Class
 *
 * Provide Request Context HTTP utilities.
 *
 * @since  __DEPLOY_VERSION__
 */
final class RequestContext
{
    /**
     * Initialize a request context.
     *
     * Capture request method, path, body, and headers for later use.
     *
     * @params string $method HTTP method.
     * @params string $path Request path.
     * @params string $rawBody Raw request body.
     * @params array<string,string> $headers Request headers.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private string $method,
        private string $path,
        private string $rawBody,
        private array $headers = [],
    ) {
        $this->method = strtoupper(trim($this->method));
        $this->path   = $this->path !== '' ? $this->path : '/';
    }

    /**
     * Create a request context for tests.
     *
     * Build a context with explicit method, path, body, and headers.
     *
     * @params string $method HTTP method.
     * @params string $path Request path.
     * @params string $rawBody Raw request body.
     * @params array<string,string> $headers Request headers.
     *
     * @return  self  Request context instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function forTests(
        string $method,
        string $path,
        string $rawBody,
        array $headers = [],
    ): self {
        return new self($method, $path, $rawBody, $headers);
    }

    /**
     * Create a request context from globals.
     *
     * Read request data from PHP globals and headers.
     *
     * @return  self  Request context instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromGlobals(): self
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $raw    = (string) file_get_contents('php://input');

        $headers = [];

        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                foreach ($h as $k => $v) {
                    if (is_string($k) && (is_string($v) || is_numeric($v))) {
                        $headers[$k] = (string) $v;
                    }
                }
            }
        } else {
            foreach ($_SERVER as $k => $v) {
                if (is_string($k) && str_starts_with($k, 'HTTP_')) {
                    $name           = str_replace('_', '-', substr($k, 5));
                    $headers[$name] = (string) $v;
                }
            }
        }

        return new self($method, $path, $raw, $headers);
    }

    /**
     * Get the HTTP method.
     *
     * Return the normalized request method.
     *
     * @return  string  HTTP method.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the request path.
     *
     * Return the normalized request path.
     *
     * @return  string  Request path.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get all request headers.
     *
     * Return the captured header map.
     *
     * @return  array<string,string>  Request headers.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a request header by name.
     *
     * Perform a case-insensitive header lookup.
     *
     * @params string $name Header name.
     *
     * @return  ?string  Header value or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Get the raw request body.
     *
     * Return the captured raw body string.
     *
     * @return  string  Raw request body.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
