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

        // When no dot is present and no @ is specified assume that this query is for an attribute
        if (! str_contains($xPath, '@') && ! str_contains($xPath, '/')) {
            $xPath = '@'.$xPath;
        }

        // Split attribute and query
        $parts = explode('@', $xPath);
        $query = array_shift($parts);
        $attribute = array_shift($parts);

        // When querying from an element prefix the xpath by the node path so that each query is scoped to the current element
        if ($this instanceof Element) {
            $query = rtrim($this->getNode()->getNodePath().'/'.ltrim($query, '/'), '/');
        }

        if (empty($query)) {
            $query = '.';
        }

        $nodes = $this->domXPath->query($query);

        if ($nodes->length > 0) {
            $node = $nodes->item(0);

            if ($attribute) {
                $attribute = $node?->attributes?->getNamedItem($attribute)?->nodeValue;

                if (is_numeric($attribute)) {
                    return (float) $attribute;
                }

                return $attribute;
            }

            return $node ? new Element($node) : $default;
        }

        return $default;
    }

    abstract protected function getDomDocument(): DOMDocument;

    public function initXPath(): void
    {
        $this->domXPath = new DOMXPath($this->getDomDocument());
    }
}
