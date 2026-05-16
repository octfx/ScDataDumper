<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;

final class CraftingQualityQuantizationRecord extends RootDocument
{
    /**
     * @return list<array{start: int, end: int, mappedValue: int}>
     */
    public function getBands(): array
    {
        $bands = [];

        foreach ($this->getAll('qualityQuantization/CraftingQualityQuantization/bands/CraftingQualityQuantizationBand') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $start = $node->get('@start');
            $end = $node->get('@end');
            $mappedValue = $node->get('@mappedValue');

            if (is_numeric($start) && is_numeric($end) && is_numeric($mappedValue)) {
                $bands[] = [
                    'start' => (int) $start,
                    'end' => (int) $end,
                    'mappedValue' => (int) $mappedValue,
                ];
            }
        }

        return $bands;
    }
}
