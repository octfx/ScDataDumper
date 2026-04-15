<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Loot\LootTableV3Record;
use Octfx\ScDataDumper\DocumentTypes\Loot\PoolFilterRecord;
use Octfx\ScDataDumper\DocumentTypes\Loot\SecondaryChoicesMultiLayerRecord;
use Octfx\ScDataDumper\DocumentTypes\Loot\SecondaryChoicesSingleLayerRecord;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SubHarvestableSlot extends RootDocument
{
    public function getHarvestableReference(): ?string
    {
        return $this->getString('@harvestable');
    }

    public function getHarvestableEntityClassReference(): ?string
    {
        return $this->getString('@harvestableEntityClass');
    }

    public function getHarvestable(): ?HarvestablePreset
    {
        $resolved = $this->resolveRelatedDocument(
            'Harvestable',
            HarvestablePreset::class,
            $this->getHarvestableReference(),
            static fn (string $reference): ?HarvestablePreset => ServiceFactory::getFoundryLookupService()
                ->getHarvestablePresetByReference($reference)
        );

        return $resolved instanceof HarvestablePreset ? $resolved : null;
    }

    public function getHarvestableEntityClass(): ?EntityClassDefinition
    {
        $ref = $this->getHarvestableEntityClassReference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getItemService()->getByReference($ref);

        return $resolved instanceof EntityClassDefinition ? $resolved : null;
    }

    public function getMinCount(): ?int
    {
        return $this->getInt('@minCount');
    }

    public function getRelativeProbability(): ?float
    {
        return $this->getFloat('@relativeProbability');
    }

    public function getHarvestableRespawnTimeMultiplier(): ?float
    {
        return $this->getFloat('@harvestableRespawnTimeMultiplier');
    }

    public function getMaxCount(): ?int
    {
        return $this->getInt('@maxCount');
    }

    public function getLootTableV3Reference(): ?string
    {
        return $this->getString('lootConfig/LootConfig@lootTableV3');
    }

    public function getLootTableV3(): ?LootTableV3Record
    {
        $ref = $this->getLootTableV3Reference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getLootTableV3ByReference($ref);

        return $resolved instanceof LootTableV3Record ? $resolved : null;
    }

    public function getPoolFilterReference(): ?string
    {
        return $this->getString('lootConfig/LootConfig/lootConstraints@poolFilter');
    }

    public function getPoolFilter(): ?PoolFilterRecord
    {
        $ref = $this->getPoolFilterReference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getPoolFilterByReference($ref);

        return $resolved instanceof PoolFilterRecord ? $resolved : null;
    }

    public function getTotalResultsLimit(): ?int
    {
        return $this->getInt('lootConfig/LootConfig/lootConstraints@totalResultsLimit');
    }

    public function getChanceToGenerate(): ?float
    {
        return $this->getFloat('lootConfig/LootConfig/lootConstraints@chanceToGenerate');
    }

    public function getChanceToGenerateAdditionalAttachedInventories(): ?float
    {
        return $this->getFloat('lootConfig/LootConfig/lootConstraints@chanceToGenerateAdditionalAttachedInventories');
    }

    public function getSecondaryChoicesMultiLayerReference(): ?string
    {
        return $this->getString('lootConfig/LootConfig/lootConstraints/secondaryChoices/LootV3SecondaryChoicesRecordRef_MultiLayer@multiLayerRecord');
    }

    public function getSecondaryChoicesSingleLayerReference(): ?string
    {
        return $this->getString('lootConfig/LootConfig/lootConstraints/secondaryChoices/LootV3SecondaryChoicesRecordRef_SingleLayer@singleLayerRecord');
    }

    public function getSecondaryChoicesMultiLayer(): ?SecondaryChoicesMultiLayerRecord
    {
        $ref = $this->getSecondaryChoicesMultiLayerReference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getSecondaryChoicesMultiLayerByReference($ref);

        return $resolved instanceof SecondaryChoicesMultiLayerRecord ? $resolved : null;
    }

    public function getSecondaryChoicesSingleLayer(): ?SecondaryChoicesSingleLayerRecord
    {
        $ref = $this->getSecondaryChoicesSingleLayerReference();
        if ($ref === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getSecondaryChoicesSingleLayerByReference($ref);

        return $resolved instanceof SecondaryChoicesSingleLayerRecord ? $resolved : null;
    }

    public function getFullnessFactorMin(): ?float
    {
        return $this->getFloat('lootConfig/LootConfig/lootConstraints/fullnessFactorRange@min');
    }

    public function getFullnessFactorMax(): ?float
    {
        return $this->getFloat('lootConfig/LootConfig/lootConstraints/fullnessFactorRange@max');
    }

    public function getPruningLevel(): ?string
    {
        return $this->getString('lootConfig/LootConfig/lootConstraints/advanced@pruningLevel');
    }

    public function getFullnessMode(): ?string
    {
        return $this->getString('lootConfig/LootConfig/lootConstraints/advanced@fullnessMode');
    }
}
