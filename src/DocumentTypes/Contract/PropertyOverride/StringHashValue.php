<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class StringHashValue extends RootDocument
{
    /**
     * @return list<array{textId: ?string, weighting: int, value: ?string}>
     */
    public function getOptions(): array
    {
        $results = [];
        $nodes = $this->getAll('options/MissionPropertyValueOption_StringHash');

        foreach ($nodes as $node) {
            $results[] = [
                'textId' => $node->get('@textId'),
                'weighting' => (int) ($node->get('@weighting') ?? 1),
                'value' => $node->get('@value'),
            ];
        }

        return $results;
    }
}
