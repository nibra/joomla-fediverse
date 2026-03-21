<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Media;

use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;
use RuntimeException;

/**
 * MediaStorageService Class
 *
 * Persist uploaded media files and build public URLs.
 *
 * @since  __DEPLOY_VERSION__
 */
final class MediaStorageService
{
    private string $rootPath;
    private string $relativeBase;

    /**
     * Initialize media storage.
     *
     * Configure the root path and relative storage directory.
     *
     * @params BaseUrlProviderInterface $baseUrl Base URL provider.
     * @params ?string $rootPath Absolute root path for storage (defaults to JPATH_ROOT or cwd).
     * @params string $relativeBase Relative directory for uploads.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private BaseUrlProviderInterface $baseUrl,
        ?string $rootPath = null,
        string $relativeBase = 'images/fediverse'
    ) {
        $root = $rootPath;
        if ($root === null || trim($root) === '') {
            $root = defined('JPATH_ROOT') ? (string) JPATH_ROOT : getcwd();
        }

        $this->rootPath     = rtrim($root, '/\\');
        $this->relativeBase = trim($relativeBase, '/');
    }

    /**
     * Store an uploaded file.
     *
     * Move the uploaded file into the media storage directory.
     *
     * @params array $file Uploaded file data.
     *
     * @return  array{relative_path:string,filename:string,original_name:string,mime_type:string,size:int,url:string}  Upload info.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function storeUploadedFile(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->describeUploadError($error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !file_exists($tmpName)) {
            throw new RuntimeException('Upload file is missing.');
        }

        $originalName = (string) ($file['name'] ?? 'upload');
        $safeName     = $this->sanitizeFilename($originalName);
        $extension    = pathinfo($safeName, PATHINFO_EXTENSION);
        $token        = bin2hex(random_bytes(16));
        $filename     = $extension !== '' ? $token . '.' . $extension : $token;

        $relativePath = $this->relativeBase !== '' ? $this->relativeBase . '/' . $filename : $filename;
        $absoluteDir  = $this->absoluteBasePath();
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Failed to create media storage directory.');
        }

        if (is_uploaded_file($tmpName)) {
            if (!move_uploaded_file($tmpName, $absolutePath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
        } else {
            if (!rename($tmpName, $absolutePath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
        }

        $size = (int) ($file['size'] ?? filesize($absolutePath));
        $mime = $this->detectMimeType($absolutePath, (string) ($file['type'] ?? ''));

        return [
            'relative_path' => $relativePath,
            'filename'      => $filename,
            'original_name' => $originalName,
            'mime_type'     => $mime,
            'size'          => $size > 0 ? $size : 0,
            'url'           => $this->getPublicUrl($relativePath),
        ];
    }

    /**
     * Resolve an absolute file path.
     *
     * @params string $relativePath Relative file path.
     *
     * @return  string  Absolute file path.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    /**
     * Build the public URL for a stored file.
     *
     * @params string $relativePath Relative file path.
     *
     * @return  string  Public URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPublicUrl(string $relativePath): string
    {
        $base = rtrim($this->baseUrl->getBaseUrl(), '/');
        $path = '/' . ltrim($relativePath, '/');

        return $base . $path;
    }

    /**
     * Check whether a stored file exists.
     *
     * @params string $relativePath Relative file path.
     *
     * @return  bool  True when the file exists.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fileExists(string $relativePath): bool
    {
        $absolute = $this->getAbsolutePath($relativePath);

        return is_file($absolute);
    }

    /**
     * Resolve the absolute storage base path.
     *
     * @return  string  Absolute base path.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function absoluteBasePath(): string
    {
        if ($this->relativeBase === '') {
            return $this->rootPath;
        }

        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->relativeBase);
    }

    /**
     * Sanitize a file name for storage.
     *
     * @params string $name Original file name.
     *
     * @return  string  Sanitized file name.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'upload';
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'upload';
        $name = trim($name, '-');

        return $name !== '' ? $name : 'upload';
    }

    /**
     * Detect a file MIME type.
     *
     * @params string $absolutePath Absolute file path.
     * @params string $fallback Fallback MIME type.
     *
     * @return  string  MIME type.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function detectMimeType(string $absolutePath, string $fallback): string
    {
        $fallback = trim($fallback) !== '' ? trim($fallback) : 'application/octet-stream';
        if (!function_exists('finfo_open')) {
            return $fallback;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $fallback;
        }

        $mime = finfo_file($finfo, $absolutePath);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : $fallback;
    }

    /**
     * Describe an upload error.
     *
     * @params int $code Upload error code.
     *
     * @return  string  Error description.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function describeUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload exceeds file size limits.',
            UPLOAD_ERR_PARTIAL => 'Upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'No file provided.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension.',
            default => 'Upload failed.',
        };
    }
}
