<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class SecondaryChoicesSingleLayerRecord extends RootDocument
{
    /**
     * @return list<SecondaryChoiceEntry>
     */
    public function getChoices(): array
    {
        $choices = [];

        foreach ($this->getAll('choices/LootV3SecondaryChoiceEntry') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $entry = SecondaryChoiceEntry::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($entry instanceof SecondaryChoiceEntry) {
                $choices[] = $entry;
            }
        }

        return $choices;
    }
}
