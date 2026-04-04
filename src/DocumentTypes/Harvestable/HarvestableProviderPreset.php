<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableProviderPreset extends RootDocument
{
    /**
     * @return list<HarvestableElementGroup>
     */
    public function getHarvestableGroups(): array
    {
        $groups = [];

        foreach ($this->getAll('harvestableGroups/HarvestableElementGroup') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $group = HarvestableElementGroup::fromNode($node->getNode());

            if ($group instanceof HarvestableElementGroup) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @return list<HarvestableElement>
     */
    public function getHarvestableElements(): array
    {
        $elements = [];

        foreach ($this->getHarvestableGroups() as $group) {
            foreach ($group->getHarvestableElements() as $element) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}
