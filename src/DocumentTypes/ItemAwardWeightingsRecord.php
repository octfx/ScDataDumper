<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

/**
 * Wraps a standalone ItemAwardWeightingsRecord foundry file (the iawr_*.xml pool definitions)
 * and is also reused to parse the inline itemAwardStructure carried by ContractResult_ItemsWeighting
 * blocks, since both share the same itemAwardStructure child shape.
 */
final class ItemAwardWeightingsRecord extends RootDocument
{
    /**
     * @return list<array{weight: int, items: list<array{entityClass: ?string, amount: int}>}>
     */
    public function getAwardSets(): array
    {
        $sets = [];

        foreach ($this->getAll('itemAwardStructure/ItemAwardWeightings') as $weighting) {
            $items = [];

            foreach ($weighting->getAll('awards/ItemAwardEntityClass') as $award) {
                $items[] = [
                    'entityClass' => $award->get('@entityClass'),
                    'amount' => (int) ($award->get('@amountToAward') ?? 0),
                ];
            }

            $sets[] = [
                'weight' => (int) ($weighting->get('@weighting') ?? 0),
                'items' => $items,
            ];
        }

        return $sets;
    }
}
