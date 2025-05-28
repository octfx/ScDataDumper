<?php

namespace Octfx\ScDataDumper\Loader;

use DOMNode;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\ElementDefinitionFactory;

class ElementLoader
{
    /**
     * Recursively walk all nodes and hydrate them with definitions found in `Components`
     */
    public static function load(RootDocument $entity): void
    {
        self::walkDomNodes($entity->documentElement, static function (DOMNode $node) use ($entity) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                return;
            }

            $def = ElementDefinitionFactory::getDefinition($node);
            if (! $def) {
                return;
            }

            $def->initialize($entity);
        });
    }

    /**
     * Walks every node in the DOM tree and calls the callback function on each node
     */
    private static function walkDomNodes(DOMNode $node, callable $callback): void
    {
        $callback($node);

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                self::walkDomNodes($child, $callback);
            }
        }
    }
}
