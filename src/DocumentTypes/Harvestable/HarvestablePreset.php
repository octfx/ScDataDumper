<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class HarvestablePreset extends RootDocument
{
    public function getEntityClassReference(): ?string
    {
        return $this->getString('@entityClass');
    }

    public function getEntityClass(): ?EntityClassDefinition
    {
        $resolved = $this->resolveRelatedDocument(
            'EntityClass',
            EntityClassDefinition::class,
            $this->getEntityClassReference(),
            static fn (string $reference): ?EntityClassDefinition => ServiceFactory::getItemService()->getByReference($reference)
        );

        return $resolved instanceof EntityClassDefinition ? $resolved : null;
    }

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

    /**
     * @return list<SubHarvestableSlot>
     */
    public function getSubHarvestableSlots(): array
    {
        return $this->hydrateAll('subConfigBase/SubHarvestableConfigManual/subConfigManual/subHarvestables/SubHarvestableSlot', SubHarvestableSlot::class, $this->isReferenceHydrationEnabled());
    }
}
