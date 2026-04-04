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

    private bool $referenceHydrationEnabled = false;

    public static function fromNode(?DOMNode $node, bool $referenceHydrationEnabled = false): ?self
    {
        if (! $node) {
            return null;
        }

        $xml = $node->ownerDocument->saveXML($node);

        $instance = new static;
        $instance->setReferenceHydrationEnabled($referenceHydrationEnabled);
        $instance->loadXML($xml);

        return $instance;
    }

    public function load(string $filename, int $options = 0): bool
    {
        if (! parent::load($filename, $options | LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
            throw new RuntimeException('Failed to load document');
        }

        $this->initializeLoadedDocument();

        return true;
    }

    public function loadXML(string $source, int $options = 0): bool
    {
        if (! parent::loadXML($source, $options | LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
            throw new RuntimeException('Failed to load document');
        }

        $this->initializeLoadedDocument();

        return true;
    }

    public function setReferenceHydrationEnabled(bool $enabled): static
    {
        $this->referenceHydrationEnabled = $enabled;

        return $this;
    }

    public function isReferenceHydrationEnabled(): bool
    {
        return $this->referenceHydrationEnabled;
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
     * Value from `__path` or empty string if not found
     */
    public function getPath(): string
    {
        return $this->documentElement->attributes->getNamedItem('__path')?->nodeValue ?? '';
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
     * @return list<string>
     */
    protected function queryAttributeValues(string $xPath, string $attribute): array
    {
        $values = [];

        foreach ($this->getAll($xPath.'/@'.$attribute) as $value) {
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    protected function getString(string $path): ?string
    {
        $this->assertAttributePath($path);
        $value = $this->get($path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function getInt(string $path): ?int
    {
        $this->assertAttributePath($path);
        $value = $this->get($path);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function getFloat(string $path): ?float
    {
        $this->assertAttributePath($path);
        $value = $this->get($path);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function getBool(string $path): bool
    {
        $this->assertAttributePath($path);

        return (int) ($this->get($path) ?? 0) === 1;
    }

    protected function getNullableBool(string $path): ?bool
    {
        $this->assertAttributePath($path);

        $value = $this->get($path);

        return is_numeric($value) ? (int) $value === 1 : null;
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function getHydratedDocument(string $path, string $class): ?RootDocument
    {
        $node = $this->get($path);

        if (! $node instanceof Element) {
            return null;
        }

        $document = $class::fromNode($node->getNode(), $this->referenceHydrationEnabled);

        return $document instanceof $class ? $document : null;
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T>  $class
     * @param  callable(string): ?T  $resolver
     * @return T|null
     */
    protected function resolveRelatedDocument(string $path, string $class, ?string $reference, callable $resolver): ?RootDocument
    {
        $document = $this->getHydratedDocument($path, $class);

        if ($document instanceof $class) {
            return $document;
        }

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $resolved = $resolver($reference);

        return $resolved instanceof $class ? $resolved : null;
    }

    /**
     * @template T of RootDocument
     *
     * @param  list<T>  $documents
     * @param  list<string>  $references
     * @param  callable(string): ?T  $resolver
     * @return list<T>
     */
    protected function resolveRelatedDocuments(array $documents, array $references, callable $resolver): array
    {
        if ($documents !== []) {
            return $documents;
        }

        $resolved = [];

        foreach ($references as $reference) {
            $document = $resolver($reference);

            if ($document instanceof self) {
                $resolved[] = $document;
            }
        }

        return $resolved;
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

    private function assertAttributePath(string $path): void
    {
        if (! str_contains($path, '@')) {
            throw new RuntimeException(sprintf(
                'Attribute path must include "@": %s',
                $path
            ));
        }
    }

    private function initializeLoadedDocument(): void
    {
        if ($this->referenceHydrationEnabled) {
            ElementLoader::load($this);
        }

        $this->initXPath();
    }
}
