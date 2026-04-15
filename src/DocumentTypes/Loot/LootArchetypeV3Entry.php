<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class LootArchetypeV3Entry extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getWeight(): ?float
    {
        return $this->getFloat('@weight');
    }

    /**
     * @return list<string>
     */
    public function getPositiveTags(): array
    {
        return $this->queryAttributeValues(
            'selector/LootArchetypeV3Selector_Tags/tags/positiveTags/Reference',
            'value'
        );
    }

    /**
     * @return list<string>
     */
    public function getNegativeTags(): array
    {
        return $this->queryAttributeValues(
            'selector/LootArchetypeV3Selector_Tags/tags/negativeTags/Reference',
            'value'
        );
    }

    public function getStackSizeMin(): ?float
    {
        return $this->getFloat('optionalData/ArchetypeOptionalDataV3_StackSize/stackSize/QuantityRange_Linear@min');
    }

    public function getStackSizeMax(): ?float
    {
        return $this->getFloat('optionalData/ArchetypeOptionalDataV3_StackSize/stackSize/QuantityRange_Linear@max');
    }

    public function getSpawnWithName(): ?string
    {
        return $this->getString('optionalData/ArchetypeOptionalDataV3_SpawnWith@name');
    }

    public function getSpawnWithChance(): ?float
    {
        return $this->getFloat('optionalData/ArchetypeOptionalDataV3_SpawnWith@chanceToSpawnWith');
    }

    public function getSpawnWithMode(): ?string
    {
        return $this->getString('optionalData/ArchetypeOptionalDataV3_SpawnWith@mode');
    }

    public function getSpawnWithAmountMin(): ?float
    {
        return $this->getFloat('optionalData/ArchetypeOptionalDataV3_SpawnWith/amountToSpawn/QuantityRange_Linear@min');
    }

    public function getSpawnWithAmountMax(): ?float
    {
        return $this->getFloat('optionalData/ArchetypeOptionalDataV3_SpawnWith/amountToSpawn/QuantityRange_Linear@max');
    }

    /**
     * @return list<string>
     */
    public function getSpawnWithPositiveTags(): array
    {
        return $this->queryAttributeValues(
            'optionalData/ArchetypeOptionalDataV3_SpawnWith/selector/SpawnWithV3Selector_Tags/tags/positiveTags/Reference',
            'value'
        );
    }
}
