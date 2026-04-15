<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class LootGenerationGlobalParams extends RootDocument
{
    /**
     * @return list<SpecialEventArchetype>
     */
    public function getSpecialEventArchetypes(): array
    {
        $events = [];

        foreach ($this->getAll('specialEventArchetypes/LootGenerationSpecialEventArchetype') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $event = SpecialEventArchetype::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($event instanceof SpecialEventArchetype) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
