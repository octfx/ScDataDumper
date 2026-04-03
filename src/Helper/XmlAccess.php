<?php

namespace Octfx\ScDataDumper\Helper;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Octfx\ScDataDumper\Definitions\Element;

trait XmlAccess
{
    protected ?DOMXPath $domXPath = null;

    /**
     * Retrieve the first matching element or attribute using an XPath-like query.
     *
     * The query is evaluated with the current element as the context node (if this trait is used
     * in Element) or the document element otherwise. This means:
     * - Relative paths (e.g. "Components/AttachDef") are scoped to the current element.
     * - Absolute paths (e.g. "/Root/Components/AttachDef") are scoped to the document root.
     *
     * Attribute lookups are indicated with an @ segment outside predicates. Examples:
     * - "@Code" (attribute on current element)
     * - "Components/AttachDef@Type" (attribute on child element)
     * - "Components/AttachDef/@Type" (also supported)
     *
     * @ signs inside predicates are ignored when splitting (e.g. "Item[@Type='Weapon']@Code").
     *
     * Behavior and edge cases:
     * - Only the first matching node is returned; this method does not return NodeLists.
     * - If no node/attribute matches, $default is returned.
     * - Numeric attribute values are cast to float.
     * - If the query is only an attribute (e.g. "@Code"), the current node is used as the query.
     *
     * @param  string  $xPath  XPath-like query relative to the current node.
     * @param  mixed  $default  Returned when the query/attribute does not match.
     * @return float|Element|string|null Element for element queries, string/float for attributes, or $default.
     *
     * @example <caption>Element Query</caption>
     * $component = $element->get('Components/AttachDef');
     * // Returns: Element for AttachDef (first match)
     * @example <caption>Attribute Query</caption>
     * $type = $element->get('Components/AttachDef@Type');
     * // Returns: "Weapon" (string) or 1.0 (float) if numeric
     *
     * $code = $element->get('@Code');
     * // Returns: attribute from current element
     * @example <caption>Predicate and Defaults</caption>
     * $weapon = $element->get("Components/AttachDef[@Type='Weapon']");
     * // Returns: first AttachDef with Type=Weapon
     *
     * $missing = $element->get('Does/Not/Exist', 'fallback');
     * // Returns: "fallback"
     */
    public function get(string $xPath, $default = null): mixed
    {
        if ($this->domXPath === null) {
            $this->initXPath();
        }

        [$query, $attribute] = $this->splitXPathAttribute($xPath);

        // Use XPath context node for correct semantics of /, //, and ./ queries
        $document = $this->getDomDocument();
        $contextNode = $this instanceof Element
            ? $this->getNode()
            : ($document->documentElement ?? $document);

        // When querying only an attribute (e.g. "@name"), use the current context node
        if ($query === '') {
            $query = '.';
        }

        $nodes = $this->domXPath->query($query, $contextNode);

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
     * Retrieve all matching elements or attribute values using an XPath-like query.
     *
     * The query is evaluated with the current element as the context node (if this trait is used
     * in Element) or the document element otherwise. Relative and absolute path behavior matches
     * {@see get()}.
     *
     * Attribute lookups use the same @ syntax as {@see get()}. When querying attributes, values
     * are returned for every matched node that defines the attribute; missing attributes are
     * skipped. Numeric attribute values are cast to float.
     *
     * @return list<Element|float|string>
     */
    public function getAll(string $xPath): array
    {
        if ($this->domXPath === null) {
            $this->initXPath();
        }

        [$query, $attribute] = $this->splitXPathAttribute($xPath);

        $document = $this->getDomDocument();
        $contextNode = $this instanceof Element
            ? $this->getNode()
            : ($document->documentElement ?? $document);

        if ($query === '') {
            $query = '.';
        }

        $nodes = $this->domXPath->query($query, $contextNode);

        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $results = [];

        foreach ($nodes as $node) {
            if ($attribute !== null) {
                $value = $node?->attributes?->getNamedItem($attribute)?->nodeValue;

                if ($value === null) {
                    continue;
                }

                $results[] = is_numeric($value) ? (float) $value : $value;

                continue;
            }

            if ($node instanceof DOMElement) {
                $results[] = new Element($node);
            }
        }

        return $results;
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
        if ($this->domXPath === null) {
            $this->domXPath = new DOMXPath($this->getDomDocument());
        }
    }
}
