<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use DOMDocument;
use DOMElement;
use Switon\Core\Attribute\Autowired;
use Switon\Http\ResponseInterface;

use function is_array;
use function is_numeric;
use function is_object;

/**
 * XML response renderer.
 *
 * Guidance: Use array/object payloads for structured XML trees; scalar values become a single root element.
 *
 * Road-signs:
 * - options override configured root/version/encoding/item/format defaults
 * - arrays and objects recurse into nested elements
 * - numeric keys become the configured item element name
 *
 * @see \Switon\Http\Response\XmlRendererInterface
 */
class XmlRenderer implements XmlRendererInterface
{
    #[Autowired] protected ResponseInterface $response;

    #[Autowired] protected string $root = 'root';
    #[Autowired] protected string $version = '1.0';
    #[Autowired] protected string $encoding = 'UTF-8';
    #[Autowired] protected string $item = 'item';
    #[Autowired] protected bool $format = false;

    /**
     * Render one payload as an XML response.
     *
     * @param array<string, mixed> $options
     */
    public function render(mixed $data, array $options = []): ResponseInterface
    {
        $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8');

        // Parse options, using configured defaults
        $root = $options['root'] ?? $this->root;
        $version = $options['version'] ?? $this->version;
        $encoding = $options['encoding'] ?? $this->encoding;
        $itemName = $options['item'] ?? $this->item;
        $format = $options['format'] ?? $this->format;

        // Create XML document
        $dom = new DOMDocument($version, $encoding);
        $dom->formatOutput = $format;

        // Convert data to XML
        if (is_array($data) || is_object($data)) {
            $rootElement = $dom->createElement($root);
            $dom->appendChild($rootElement);
            $this->arrayToXml($data, $rootElement, $dom, $itemName);
        } else {
            // Scalar value
            $rootElement = $dom->createElement($root, (string)$data);
            $dom->appendChild($rootElement);
        }

        $this->response->setContent($dom->saveXML());
        return $this->response;
    }

    /**
     * Append nested XML nodes for one array/object payload.
     */
    protected function arrayToXml(mixed $data, DOMElement $parent, DOMDocument $dom, string $itemName): void
    {
        if (is_object($data)) {
            $data = (array)$data;
        }

        foreach ($data as $key => $value) {
            // Handle numeric keys
            if (is_numeric($key)) {
                $key = $itemName;
            }

            if (is_array($value) || is_object($value)) {
                // Nested array/object
                $element = $dom->createElement($key);
                $parent->appendChild($element);
                $this->arrayToXml($value, $element, $dom, $itemName);
            } else {
                // Scalar value
                $element = $dom->createElement($key, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
                $parent->appendChild($element);
            }
        }
    }
}
