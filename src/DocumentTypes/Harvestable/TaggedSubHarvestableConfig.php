<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class TaggedSubHarvestableConfig extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    /**
     * @return list<string>
     */
    public function getTagReferences(): array
    {
        $refs = [];

        foreach ($this->getAll('tagList/HarvestableTagListTagEditor/tags/Reference@value') as $value) {
            if (is_string($value) && $value !== '') {
                $refs[] = $value;
            }
        }

        return $refs;
    }

    public function getInitialSlotsProbability(): ?float
    {
        return $this->getFloat('subConfig/SubHarvestableConfigSingleManual/subConfigManual@initialSlotsProbability');
    }

    public function getConfigRespawnTimeMultiplier(): ?float
    {
        return $this->getFloat('subConfig/SubHarvestableConfigSingleManual/subConfigManual@configRespawnTimeMultiplier');
    }

    /**
     * @return list<SubHarvestableSlot>
     */
    public function getSubHarvestableSlots(): array
    {
        $slots = [];

        foreach ($this->getAll('subConfig/SubHarvestableConfigSingleManual/subConfigManual/subHarvestables/SubHarvestableSlot') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $slot = SubHarvestableSlot::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($slot instanceof SubHarvestableSlot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    public function getSubConfigReference(): ?string
    {
        return $this->getString('subConfig/SubHarvestableConfigSingleRef@subConfigRef');
    }
}
