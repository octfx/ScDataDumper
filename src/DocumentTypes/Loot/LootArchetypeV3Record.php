<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class LootArchetypeV3Record extends RootDocument
{
    /**
     * @return list<LootArchetypeV3Entry>
     */
    public function getEntries(): array
    {
        $entries = [];

        foreach ($this->getAll('lootArchetype/LootArchetypeV3/entries/LootArchetypeV3Entry') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $entry = LootArchetypeV3Entry::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($entry instanceof LootArchetypeV3Entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
