<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JsonException;
use Octfx\ScDataDumper\ElementDefinitionFactory;
use Octfx\ScDataDumper\Helper\DOMElementProxy;
use Octfx\ScDataDumper\Helper\XmlAccess;
use RuntimeException;

/**
 * Base XML File
 */
abstract class RootDocument extends DOMDocument
{
    use XmlAccess;

    public function load(string $filename, int $options = 0): bool
    {
        $success = parent::load($filename, $options);

        if (! $success) {
            throw new RuntimeException('Failed to load document');
        }

        $this->domXPath = new DOMXPath($this->getDomDocument());

        return $success;
    }

    /**
     * Checks that the instantiated definition matches the xml one
     */
    public function checkValidity(?string $nodeName = null): void
    {
        if (! str_contains($this->documentElement->nodeName, '.')) {
            throw new RuntimeException('Invalid definition name');
        }

        $parts = explode('.', $nodeName ?? $this->documentElement->nodeName);

        if (! str_contains(static::class, $parts[0])) {
            throw new RuntimeException(sprintf('Tried instantiating %s while definition is %s', static::class, $parts[0]));
        }
    }

    /**
     * First part of root node name, split by `.`.
     */
    public function getClassName(): string
    {
        return explode('.', $this->documentElement->nodeName)[1];
    }

    /**
     * Value from `__type` or empty string if not found
     */
    public function getType(): string
    {
        return $this->documentElement->attributes->getNamedItem('__type')?->nodeValue ?? '';
    }

    /**
     * Value from `__ref` or empty string if not found
     */
    public function getUuid(): string
    {
        return $this->documentElement->attributes?->getNamedItem('__ref')?->nodeValue ?? '';
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function toArray(): array
    {
        return $this->toArrayRecursive($this->documentElement);
    }

    /**
     * Recursively walks the given xml $element and turns it into an array.
     * If a node name matches a class in Definitions, it is passed to the matching definition and `toArray()` is called on the instance
     *
     * @see ElementDefinitionFactory
     */
    protected function toArrayRecursive(DOMElement|DOMElementProxy $element): array
    {
        if (! $element instanceof DOMElementProxy) {
            $element = new DOMElementProxy($element);
        }

        $data = $element->attributesToArray();

        if ($element->childNodes->length > 0) {
            $isArray = false;

            foreach ($element->children() as $child) {
                if ($child->getNode()->nextSibling !== null && $child->getNode()->nodeName === $child->getNode()->nextSibling->nodeName) {
                    $isArray = true;

                    $data = [
                        $data,
                    ];
                }

                if ($isArray) {
                    $data[] = $this->toArrayRecursive($child);
                } else {
                    $data[$child->nodeName] = $this->toArrayRecursive($child);
                }
            }
        }

        unset($data['@attributes']);

        return $data;
    }

    protected function getDomDocument(): DOMDocument
    {
        return $this;
    }
}
