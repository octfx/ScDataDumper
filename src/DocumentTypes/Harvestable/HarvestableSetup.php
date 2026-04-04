<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableSetup extends RootDocument
{
    public function getRespawnInSlotTime(): ?int
    {
        return $this->getInt('@respawnInSlotTime');
    }

    public function getDespawnTimeSeconds(): ?int
    {
        return $this->getInt('harvestBehaviour/despawnTimer@despawnTimeSeconds');
    }

    public function getAdditionalWaitForNearbyPlayersSeconds(): ?int
    {
        return $this->getInt('harvestBehaviour/despawnTimer@additionalWaitForNearbyPlayersSeconds');
    }

    public function getMovementHarvestDistance(): ?float
    {
        return $this->getFloat('harvestBehaviour/harvestConditions/HarvestConditionMovement@distance');
    }

    public function getRequiredHealthRatio(): ?float
    {
        return $this->getFloat('harvestBehaviour/harvestConditions/HarvestConditionHealth@healthRatio');
    }

    public function getRequiredDamageRatio(): ?float
    {
        return $this->getFloat('harvestBehaviour/harvestConditions/HarvestConditionDamageMap@damageRatio');
    }

    public function includesAttachedChildrenForInteraction(): ?bool
    {
        return $this->getNullableBool(
            'harvestBehaviour/harvestConditions/HarvestConditionInteraction@includeAttachedChildren'
        );
    }

    public function doAllInteractionsClearSpawnPoint(): ?bool
    {
        return $this->getNullableBool(
            'harvestBehaviour/harvestConditions/HarvestConditionInteraction@allInteractionsClearSpawnPoint'
        );
    }

    public function getSpecialHarvestableString(): ?string
    {
        return $this->getString('@specialHarvestableString');
    }

    /**
     * @return list<SubHarvestableSlot>
     */
    public function getSubHarvestableSlots(): array
    {
        $slots = [];

        foreach ($this->getAll('subHarvestableSlots/SubHarvestableSlot') as $node) {
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
}
