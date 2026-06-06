<?php

declare(strict_types=1);

namespace Switon\Http\Request;

/**
 * Uploaded-file access boundary.
 *
 * Guidance: Treat instances as validated wrappers around one upload entry; call `moveTo()` before long-term storage use.
 *
 * Road-signs:
 * - metadata reads: key / size / name / tmp_name / mime / extension
 * - moveTo validates and relocates the uploaded file
 * - delete removes the temporary file
 *
 * @see \Switon\Http\Request\File
 */
interface FileInterface
{
    /**
     * Return the form field key.
     */
    public function getKey(): string;

    /**
     * Return file size in bytes.
     */
    public function getSize(): int;

    /**
     * Return the original client filename.
     */
    public function getName(): string;

    /**
     * Return the temporary upload path.
     */
    public function getTempName(): string;

    /**
     * Return MIME type.
     *
     * `$real=true` probes the temporary file; false returns the client-provided type.
     */
    public function getType(bool $real = true): string;

    /**
     * Move the uploaded file to the destination path.
     *
     * @throws \Switon\Http\Request\File\Exception If validation fails or move operation fails
     */
    public function moveTo(
        string $dst,
        string $allowedExtensions = 'jpg,jpeg,png,gif,doc,xls,pdf,zip',
        bool   $overwrite = false
    ): void;

    /**
     * Return the filename extension without the dot.
     */
    public function getExtension(): string;

    /**
     * Delete the temporary file.
     */
    public function delete(): void;
}
