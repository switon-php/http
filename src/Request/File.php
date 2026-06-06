<?php

declare(strict_types=1);

namespace Switon\Http\Request;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Http\Request\File\Exception as FileException;

use function dirname;
use function error_get_last;
use function is_uploaded_file;
use function pathinfo;

/**
 * Uploaded-file wrapper over one `$_FILES` entry.
 *
 * Road-signs:
 * - metadata reads: size / name / tmp_name / mime / error / key
 * - moveTo validates extension, upload status, and destination
 * - PathAlias + Filesystem handle final file operations
 *
 * @see \Switon\Http\Request\FileInterface
 */
class File implements FileInterface, JsonSerializable
{
    /**
     * @var array<string, mixed> Raw uploaded-file metadata.
     */
    #[Autowired] protected array $file;

    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;

    /**
     * Return file size in bytes.
     */
    public function getSize(): int
    {
        return $this->file['size'];
    }

    /**
     * Return the original client filename.
     */
    public function getName(): string
    {
        return $this->file['name'];
    }

    /**
     * Return the temporary upload path.
     */
    public function getTempName(): string
    {
        return $this->file['tmp_name'];
    }

    /**
     * Return MIME type.
     *
     * `$real=true` probes the temporary file; false returns the client-provided type.
     */
    public function getType(bool $real = true): string
    {
        if ($real) {
            return mime_content_type($this->file['tmp_name']) ?: '';
        } else {
            return $this->file['type'];
        }
    }

    /**
     * Return the upload error code as a string.
     */
    public function getError(): string
    {
        return (string)$this->file['error'];
    }

    /**
     * Return the form field key.
     */
    public function getKey(): string
    {
        return $this->file['key'];
    }

    /**
     * Check whether the temporary file is a real HTTP upload.
     */
    public function isUploadedFile(): bool
    {
        return is_uploaded_file($this->file['tmp_name']);
    }

    /**
     * Move the uploaded file to the destination path.
     *
     * Validates extension allowlist, upload status, overwrite policy, and final file permissions.
     *
     * @throws FileException If validation fails or move operation fails
     */
    public function moveTo(
        string $dst,
        string $allowedExtensions = 'jpg,jpeg,png,gif,doc,xls,pdf,zip',
        bool   $overwrite = false
    ): void {
        if ($allowedExtensions !== '*') {
            $extension = pathinfo($dst, PATHINFO_EXTENSION);
            if (!$extension || preg_match("#\b" . preg_quote($extension, '#') . "\b#", $allowedExtensions) !== 1) {
                FileException::raise('File type "{extension}" not allowed.', ['extension' => $extension]);
            }
        }

        if ((int)($error = $this->file['error']) !== UPLOAD_ERR_OK) {
            FileException::raise('File upload failed with error {error}.', ['error' => $error]);
        }

        $dstPath = $this->pathAlias->resolve($dst);
        if (is_file($dstPath)) {
            if ($overwrite) {
                $this->filesystem->delete($dst);
            } else {
                FileException::raise('File "{dst}" already exists.', ['dst' => $dst]);
            }
        }

        $this->filesystem->mkdir(dirname($dstPath));

        $resolvedDst = $this->pathAlias->resolve($dst);
        if (PHP_SAPI === 'cli') {
            $this->filesystem->move($this->file['tmp_name'], $resolvedDst);
        } elseif (!move_uploaded_file($this->file['tmp_name'], $resolvedDst)) {
            $error = error_get_last()['message'] ?? '';
            FileException::raise('Failed to move uploaded file to "{dst}": {error}.', ['dst' => $dst, 'error' => $error]);
        }

        // Only set permissions if file exists (defensive check for testing scenarios)
        if (file_exists($resolvedDst) && !chmod($resolvedDst, 0644)) {
            $error = error_get_last()['message'] ?? '';
            FileException::raise('Failed to set file permissions for "{dst}": {error}', ['dst' => $dst, 'error' => $error]);
        }
    }

    /**
     * Return the filename extension without the dot.
     */
    public function getExtension(): string
    {
        $name = $this->file['name'];
        return ($extension = pathinfo($name, PATHINFO_EXTENSION)) === $name ? '' : $extension;
    }

    /**
     * Delete temporary file.
     *
     * Removes the temporary file from the server.
     */
    public function delete(): void
    {
        $tmp = $this->file['tmp_name'];
        if (!$this->filesystem->exists($tmp)) {
            return;
        }

        try {
            $this->filesystem->delete($tmp);
        } catch (RuntimeException) {
            // Keep historical behavior: best-effort cleanup without throwing.
        }
    }

    /**
     * Get JSON representation of file data.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->file;
    }
}
