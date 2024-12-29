<?php

namespace Octfx\ScDataDumper\Definitions;

use DOMDocument;
use DOMException;
use DOMNode;
use DOMXPath;
use Generator;
use Octfx\ScDataDumper\Helper\XmlAccess;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

class Element
{
    protected bool $initialized = false;

    use XmlAccess;

    public function __construct(protected readonly DOMNode $node)
    {
        $this->domXPath = new DOMXPath($this->getDomDocument());
    }

    protected function getDomDocument(): DOMDocument
    {
        return $this->node->ownerDocument ?? $this->node;
    }

    public function initialize(DOMDocument $document): void
    {
        $this->initialized = true;
    }

    public function getNode(): DOMNode
    {
        return $this->node;
    }

    /**
     * Appends `$import` after `$this->node`
     *
     * @param  string|null  $elementName  Optional XML Tag name that holds the content of $import
     *                                    This is useful when importing whole XML files as without this argument the root tag of the imported document is used.
     *                                    For manufacturers this would be (example) `<Manufacturer.APAR>` instead of `<Manufacturer>`
     */
    protected function appendNode(DOMDocument $document, ?DOMDocument $import, ?string $elementName = null): void
    {
        if (! $import) {
            return;
        }

        $importedNode = $document->importNode($import->documentElement, true);

        if ($importedNode === false) {
            throw new RuntimeException('Failed to import node');
        }

        if ($elementName) {
            try {
                $renamedElement = $document->createElement($elementName);
                $renamedElement->append(...$importedNode->childNodes);
                $this->node->appendChild($renamedElement);

                $importedNode = $renamedElement;
            } catch (DOMException $e) {

            }
        }

        $this->node->appendChild($importedNode);
    }

    /**
     * Turns Element Attributes into an array
     */
    public function attributesToArray(?array $ignore = []): array
    {
        $attributes = [];

        foreach ($this->node->attributes as $attribute) {
            if (! $attribute) {
                continue;
            }

            $name = $attribute->nodeName;
            $value = $attribute->nodeValue;

            if (! in_array($attribute->nodeName, $ignore, true)) {
                if (is_numeric($value)) {
                    $attributes[$name] = (float) $value;
                } elseif (str_starts_with((string) $value, '@')) {
                    $attributes[$name] = ServiceFactory::getLocalizationService()->getTranslation((string) $value);
                } else {
                    $attributes[$name] = (string) $value;
                }
            }
        }

        unset($attributes['__type'], $attributes['__polymorphicType']);

        return $attributes;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->node->{$name}(...$arguments);
    }

    public function __get(string $name)
    {
        return $this->node->{$name};
    }

    /**
     * Returns all child elements and wraps it in DOMElementProxy
     */
    public function children(): Generator
    {
        foreach ($this->node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            yield new self($child);
        }
    }
}
