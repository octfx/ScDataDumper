<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SpecialEventArchetype extends RootDocument
{
    public function getEventString(): ?string
    {
        return $this->getString('@eventString');
    }

    public function getProbabilityPerContainer(): ?float
    {
        return $this->getFloat('@probabilityPerContainer');
    }

    public function getMinEntriesPerContainer(): ?int
    {
        return $this->getInt('@minEntriesPerContainer');
    }

    public function getMaxEntriesPerContainer(): ?int
    {
        return $this->getInt('@maxEntriesPerContainer');
    }

    public function getArchetypeV3Reference(): ?string
    {
        return $this->getString('archetypeV3/LootArchetypeV3_RecordRef@lootArchetypeRecord');
    }

    public function getArchetypeV3(): ?LootArchetypeV3Record
    {
        $ref = $this->getArchetypeV3Reference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getLootArchetypeV3ByReference($ref);

        return $resolved instanceof LootArchetypeV3Record ? $resolved : null;
    }
}
