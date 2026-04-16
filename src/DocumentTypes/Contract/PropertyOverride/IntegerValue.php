<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class IntegerValue extends RootDocument
{
    /**
     * @return list<array{textId: ?string, weighting: int, value: int, variation: float}>
     */
    public function getOptions(): array
    {
        $results = [];
        $nodes = $this->getAll('options/MissionPropertyValueOption_Integer');

        foreach ($nodes as $node) {
            $results[] = [
                'textId' => $node->get('@textId'),
                'weighting' => (int) ($node->get('@weighting') ?? 1),
                'value' => (int) ($node->get('@value') ?? 0),
                'variation' => (float) ($node->get('@variation') ?? 0),
            ];
        }

        return $results;
    }
}
