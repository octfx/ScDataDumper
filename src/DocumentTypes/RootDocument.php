<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use DOMDocument;
use DOMElement;
use DOMNode;
use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\ElementDefinitionFactory;
use Octfx\ScDataDumper\Helper\XmlAccess;
use Octfx\ScDataDumper\Loader\ElementLoader;
use RuntimeException;

/**
 * Base XML File
 */
abstract class RootDocument extends DOMDocument
{
    use XmlAccess;

    public static function fromNode(?DOMNode $node): ?self
    {
        if (! $node) {
            return null;
        }

        $xml = $node->ownerDocument->saveXML($node);

        $instance = new static;
        $instance->loadXML($xml);

        ElementLoader::load($instance);
        $instance->initXPath();

        return $instance;
    }

    public function load(string $filename, int $options = 0): bool
    {
        $success = parent::load($filename, $options | LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);

        if (! $success) {
            throw new RuntimeException('Failed to load document');
        }

        ElementLoader::load($this);
        $this->initXPath();

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
        return explode('.', $this->documentElement->nodeName)[1] ?? $this->documentElement->nodeName;
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
    protected function toArrayRecursive(DOMElement|Element $element): array
    {
        if (! $element instanceof Element) {
            $element = new Element($element);
        }

        $data = $element->attributesToArray();

        if ($element->childNodes->length > 0) {
            foreach ($element->children() as $child) {
                if (
                    ($child->getNode()->previousSibling && $child->getNode()->nodeName === $child->getNode()->previousSibling->nodeName) ||
                    ($child->getNode()->nextSibling && $child->getNode()->nodeName === $child->getNode()->nextSibling->nodeName)
                ) {
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
