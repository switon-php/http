<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Core\Attribute\Autowired;
use Switon\Http\ResponseInterface;

use function array_keys;
use function current;
use function explode;
use function fclose;
use function fopen;
use function fprintf;
use function fputcsv;
use function is_array;
use function is_string;
use function pathinfo;
use function rewind;
use function stream_get_contents;

/**
 * CSV response renderer.
 *
 * Guidance: Pass tabular rows only; attachment headers and final response body are handled here.
 *
 * Road-signs:
 * - prepends UTF-8 BOM for spreadsheet compatibility
 * - headers come from argument or first row keys
 * - AttachmentRenderer sets download headers and content type
 *
 * @see \Switon\Http\Response\CsvRendererInterface
 */
class CsvRenderer implements CsvRendererInterface
{
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected AttachmentRendererInterface $attachmentRenderer;

    #[Autowired] protected string $delimiter = ',';
    #[Autowired] protected string $enclosure = '"';
    #[Autowired] protected string $escape = '\\';

    /**
     * Render rows as one downloadable CSV response.
     *
     * @param array<int, array<string, mixed>|object> $rows
     */
    public function render(array $rows, string $filename, null|string|array $headers = null): ResponseInterface
    {
        // Ensure filename has .csv extension
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'csv') {
            $filename .= '.csv';
        }

        // Generate CSV content
        $file = fopen('php://temp', 'rb+');
        fprintf($file, "\xEF\xBB\xBF");  // BOM for Excel compatibility

        // Auto-detect headers from first row if not provided
        if (is_string($headers)) {
            $headers = explode(',', $headers);
        } elseif ($headers === null && $first = current($rows)) {
            $headers = array_keys(is_array($first) ? $first : $first->toArray());
        }

        // Write headers
        if ($headers !== null) {
            fputcsv($file, $headers, $this->delimiter, $this->enclosure, $this->escape);
        }

        // Write rows
        foreach ($rows as $row) {
            fputcsv($file, is_array($row) ? $row : $row->toArray(), $this->delimiter, $this->enclosure, $this->escape);
        }

        rewind($file);
        $csvContent = stream_get_contents($file);
        fclose($file);

        // Use AttachmentRenderer to set download headers and content
        $this->attachmentRenderer->render($csvContent, $filename, 'text/csv; charset=utf-8');
        return $this->response;
    }
}
