<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class FloatValue extends RootDocument
{
    /**
     * @return list<array{textId: ?string, weighting: int, value: float, variation: float}>
     */
    public function getOptions(): array
    {
        $results = [];
        $nodes = $this->getAll('options/MissionPropertyValueOption_Float');

        foreach ($nodes as $node) {
            $results[] = [
                'textId' => $node->get('@textId'),
                'weighting' => (int) ($node->get('@weighting') ?? 1),
                'value' => (float) ($node->get('@value') ?? 0),
                'variation' => (float) ($node->get('@variation') ?? 0),
            ];
        }

        return $results;
    }
}
