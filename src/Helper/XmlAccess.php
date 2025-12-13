<?php

namespace Octfx\ScDataDumper\Helper;

use DOMDocument;
use DOMXPath;
use Octfx\ScDataDumper\Definitions\Element;
use RuntimeException;

trait XmlAccess
{
    protected ?DOMXPath $domXPath = null;

    /**
     * Retrieve a child or attribute by xPath, e.g. `Components/AttachDef` to retrieve the `AttachDef` child from `Components`.
     * Or `Components/AttachDef@Type` to retrieve the value of `Type` from AttachDef
     *
     * When not specifying a path i.e. no `/` is found in `$xPath` this method assumes that an attribute shall be retrieved.
     *
     * Queries are always scoped to the current element, the path to the current node is prepended to each `$xPath`.
     *
     * @return float|Element|string|null
     */
    public function get(string $xPath, $default = null): mixed
    {
        if ($this->domXPath === null) {
            throw new RuntimeException('DOMXPath object not set');
        }

        // When no slash is present and no @ is specified assume that this query is for an attribute
        if (! str_contains($xPath, '@') && ! str_contains($xPath, '/')) {
            $xPath = '@'.$xPath;
        }

        // Split attribute and query
        [$query, $attribute] = $this->splitXPathAttribute($xPath);

        // When querying from an element prefix the xpath by the node path so that each query is scoped to the current element
        if ($this instanceof Element) {
            $query = rtrim($this->getNode()->getNodePath().'/'.ltrim($query, '/'), '/');
        }

        if (empty($query)) {
            $query = '/';
        }

        $nodes = $this->domXPath->query($query);

        if ($nodes !== false && $nodes->length > 0) {
            $node = $nodes->item(0);

            if ($attribute) {
                $attribute = $node?->attributes?->getNamedItem($attribute)?->nodeValue;

                if ($attribute === null) {
                    return $default;
                }

                if (is_numeric($attribute)) {
                    return (float) $attribute;
                }

                return $attribute;
            }

            return $node ? new Element($node) : $default;
        }

        return $default;
    }

    /**
     * Splits the xPath into query and attribute parts while ignoring @ signs inside predicates.
     *
     * @return array{0:string,1:string|null}
     */
    private function splitXPathAttribute(string $xPath): array
    {
        $depth = 0;
        $attributePos = -1;

        for ($i = 0, $length = strlen($xPath); $i < $length; $i++) {
            $char = $xPath[$i];

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($char === '@' && $depth === 0) {
                $attributePos = $i;
            }
        }

        if ($attributePos === -1) {
            return [$xPath, null];
        }

        $query = rtrim(substr($xPath, 0, $attributePos), '/');
        $attribute = substr($xPath, $attributePos + 1) ?: null;

        return [$query, $attribute];
    }

    abstract protected function getDomDocument(): DOMDocument;

    public function initXPath(): void
    {
        $this->domXPath = new DOMXPath($this->getDomDocument());
    }
}
