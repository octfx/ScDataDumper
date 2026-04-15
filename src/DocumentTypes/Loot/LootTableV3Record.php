<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class LootTableV3Record extends RootDocument
{
    /**
     * @return list<LootTableV3Entry>
     */
    public function getEntries(): array
    {
        $entries = [];

        foreach ($this->getAll('lootTable/LootTableV3/lootArchetypes/LootTableV3Entry') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $entry = LootTableV3Entry::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($entry instanceof LootTableV3Entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
