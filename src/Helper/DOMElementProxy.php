<?php

namespace Octfx\ScDataDumper\Helper;

use Generator;
use Octfx\ScDataDumper\Definitions\Element;

class DOMElementProxy extends Element
{
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

            yield new DOMElementProxy($child);
        }
    }
}
