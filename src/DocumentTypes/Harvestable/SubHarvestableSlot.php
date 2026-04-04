<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SubHarvestableSlot extends RootDocument
{
    public function getHarvestableReference(): ?string
    {
        return $this->getString('@harvestable');
    }

    public function getHarvestable(): ?HarvestablePreset
    {
        $harvestable = $this->getHydratedDocument('Harvestable', HarvestablePreset::class);

        if ($harvestable instanceof HarvestablePreset) {
            return $harvestable;
        }

        $reference = $this->getHarvestableReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getHarvestablePresetByReference($reference);

        return $resolved instanceof HarvestablePreset ? $resolved : null;
    }

    public function getMinCount(): ?int
    {
        return $this->getInt('@minCount');
    }

    public function getMaxCount(): ?int
    {
        return $this->getInt('@maxCount');
    }
}
