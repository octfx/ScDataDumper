<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableSetup;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableSlot;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class HarvestableSetupTest extends ScDataTestCase
{
    public function test_exposes_player_relevant_accessors_for_default_setup(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/default_setup.xml',
            <<<'XML'
            <HarvestableSetup.DefaultHarvestableSetup respawnInSlotTime="3600" __type="HarvestableSetup" __ref="aa7a552f-0aec-448a-b029-5ba7832851ca" __path="libs/foundry/records/harvestable/harvestablesetups/default_setup.xml">
              <harvestBehaviour>
                <harvestConditions>
                  <HarvestConditionHealth healthRatio="0" />
                  <HarvestConditionInteraction includeAttachedChildren="0" allInteractionsClearSpawnPoint="0" />
                  <HarvestConditionMovement distance="5" />
                </harvestConditions>
                <despawnTimer despawnTimeSeconds="600" additionalWaitForNearbyPlayersSeconds="300" />
              </harvestBehaviour>
              <transformParams minScale="1" maxScale="1" terrainNormalAlignment="1" minZOffset="0" maxZOffset="0" minSlope="0" maxSlope="90" minElevation="-10000" maxElevation="10000">
                <localRotationOffset x="0" y="0" z="0" />
                <rotationRange x="0" y="0" z="360" />
                <positionOffset x="0" y="0" z="0" />
              </transformParams>
            </HarvestableSetup.DefaultHarvestableSetup>
            XML
        );

        $document = new HarvestableSetup;
        $document->load($path);

        self::assertSame(3600, $document->getRespawnInSlotTime());
        self::assertSame(600, $document->getDespawnTimeSeconds());
        self::assertSame(300, $document->getAdditionalWaitForNearbyPlayersSeconds());
        self::assertSame(5.0, $document->getMovementHarvestDistance());
        self::assertSame(0.0, $document->getRequiredHealthRatio());
        self::assertNull($document->getRequiredDamageRatio());
        self::assertFalse($document->includesAttachedChildrenForInteraction());
        self::assertFalse($document->doAllInteractionsClearSpawnPoint());
        self::assertNull($document->getSpecialHarvestableString());
    }

    public function test_exposes_player_relevant_accessors_for_salvage_setup(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/salvage_setup.xml',
            <<<'XML'
            <HarvestableSetup.V3HarvestableSetup_Salvageable_2H respawnInSlotTime="7200" __type="HarvestableSetup" __ref="00b0e002-680f-4324-974f-149cc5a4b2fc" __path="libs/foundry/records/harvestable/harvestablesetups/salvage_setup.xml">
              <harvestBehaviour>
                <harvestConditions>
                  <HarvestConditionMovement distance="100" />
                  <HarvestConditionInteraction includeAttachedChildren="0" allInteractionsClearSpawnPoint="1" />
                  <HarvestConditionHealth healthRatio="0.5" />
                  <HarvestConditionDamageMap damageRatio="0.5" />
                </harvestConditions>
                <despawnTimer despawnTimeSeconds="0" additionalWaitForNearbyPlayersSeconds="0" />
              </harvestBehaviour>
              <transformParams minScale="1" maxScale="1" terrainNormalAlignment="1" minZOffset="0" maxZOffset="0" minSlope="0" maxSlope="90" minElevation="-10000" maxElevation="10000">
                <localRotationOffset x="0" y="0" z="0" />
                <rotationRange x="0" y="0" z="1" />
                <positionOffset x="0" y="0" z="0" />
              </transformParams>
            </HarvestableSetup.V3HarvestableSetup_Salvageable_2H>
            XML
        );

        $document = new HarvestableSetup;
        $document->load($path);

        self::assertSame(7200, $document->getRespawnInSlotTime());
        self::assertSame(0, $document->getDespawnTimeSeconds());
        self::assertSame(0, $document->getAdditionalWaitForNearbyPlayersSeconds());
        self::assertSame(100.0, $document->getMovementHarvestDistance());
        self::assertSame(0.5, $document->getRequiredHealthRatio());
        self::assertSame(0.5, $document->getRequiredDamageRatio());
        self::assertFalse($document->includesAttachedChildrenForInteraction());
        self::assertTrue($document->doAllInteractionsClearSpawnPoint());
        self::assertNull($document->getSpecialHarvestableString());
    }

    public function test_returns_null_for_missing_optional_conditions_and_exposes_special_event_string(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/special_event_setup.xml',
            <<<'XML'
            <HarvestableSetup.NewYearSpecialEventHarvestableSetup respawnInSlotTime="1200" specialHarvestableString="NewYear" __type="HarvestableSetup" __ref="08ddd192-e526-4912-a75e-a9e0ce63115c" __path="libs/foundry/records/harvestable/harvestablesetups/special_event_setup.xml">
              <harvestBehaviour>
                <harvestConditions>
                  <HarvestConditionInteraction includeAttachedChildren="0" allInteractionsClearSpawnPoint="1" />
                </harvestConditions>
                <despawnTimer despawnTimeSeconds="600" additionalWaitForNearbyPlayersSeconds="300" />
              </harvestBehaviour>
              <transformParams minScale="1" maxScale="1" terrainNormalAlignment="1" minZOffset="0" maxZOffset="0" minSlope="0" maxSlope="90" minElevation="-10000" maxElevation="10000">
                <localRotationOffset x="0" y="0" z="0" />
                <rotationRange x="0" y="0" z="360" />
                <positionOffset x="0" y="0" z="0" />
              </transformParams>
            </HarvestableSetup.NewYearSpecialEventHarvestableSetup>
            XML
        );

        $document = new HarvestableSetup;
        $document->load($path);

        self::assertSame(1200, $document->getRespawnInSlotTime());
        self::assertSame(600, $document->getDespawnTimeSeconds());
        self::assertSame(300, $document->getAdditionalWaitForNearbyPlayersSeconds());
        self::assertNull($document->getMovementHarvestDistance());
        self::assertNull($document->getRequiredHealthRatio());
        self::assertNull($document->getRequiredDamageRatio());
        self::assertFalse($document->includesAttachedChildrenForInteraction());
        self::assertTrue($document->doAllInteractionsClearSpawnPoint());
        self::assertSame('NewYear', $document->getSpecialHarvestableString());
    }

    public function test_hydrates_and_exposes_sub_harvestable_slots(): void
    {
        $harvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sub_slot_harvestable.xml',
            <<<'XML'
            <HarvestablePreset.SubSlotHarvestable entityClass="11111111-1111-1111-1111-111111111111" __type="HarvestablePreset" __ref="03259b76-f95a-45d6-bad5-6bedeee7a3b8" __path="libs/foundry/records/harvestable/harvestablepresets/sub_slot_harvestable.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'HarvestablePreset' => [
                    'SubSlotHarvestable' => $harvestablePath,
                ],
            ],
            uuidToClassMap: [
                '03259b76-f95a-45d6-bad5-6bedeee7a3b8' => 'SubSlotHarvestable',
            ],
            classToUuidMap: [
                'SubSlotHarvestable' => '03259b76-f95a-45d6-bad5-6bedeee7a3b8',
            ],
            uuidToPathMap: [
                '03259b76-f95a-45d6-bad5-6bedeee7a3b8' => $harvestablePath,
            ],
        );

        $this->writeFile('Data/Localization/english/global.ini', '');
        (new ServiceFactory($this->tempDir))->initialize();

        $path = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/sub_slot_setup.xml',
            <<<'XML'
            <HarvestableSetup.SubSlotSetup respawnInSlotTime="1200" __type="HarvestableSetup" __ref="08ddd192-e526-4912-a75e-a9e0ce63115c" __path="libs/foundry/records/harvestable/harvestablesetups/sub_slot_setup.xml">
              <subHarvestableSlots>
                <SubHarvestableSlot harvestable="03259b76-f95a-45d6-bad5-6bedeee7a3b8" minCount="1" maxCount="2" />
              </subHarvestableSlots>
            </HarvestableSetup.SubSlotSetup>
            XML
        );

        $document = new HarvestableSetup;
        $document->load($path);

        $slots = $document->getSubHarvestableSlots();

        self::assertCount(1, $slots);
        self::assertContainsOnlyInstancesOf(SubHarvestableSlot::class, $slots);
        self::assertSame('03259b76-f95a-45d6-bad5-6bedeee7a3b8', $slots[0]->getHarvestableReference());
        self::assertSame(1, $slots[0]->getMinCount());
        self::assertSame(2, $slots[0]->getMaxCount());
        self::assertSame('03259b76-f95a-45d6-bad5-6bedeee7a3b8', $slots[0]->getHarvestable()?->getUuid());
        self::assertSame('11111111-1111-1111-1111-111111111111', $slots[0]->getHarvestable()?->getEntityClassReference());
    }

    public function test_resolves_sub_harvestable_slots_when_reference_hydration_is_disabled(): void
    {
        $harvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sub_slot_harvestable.xml',
            <<<'XML'
            <HarvestablePreset.SubSlotHarvestable entityClass="11111111-1111-1111-1111-111111111111" __type="HarvestablePreset" __ref="03259b76-f95a-45d6-bad5-6bedeee7a3b8" __path="libs/foundry/records/harvestable/harvestablepresets/sub_slot_harvestable.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'HarvestablePreset' => [
                    'SubSlotHarvestable' => $harvestablePath,
                ],
            ],
            uuidToClassMap: [
                '03259b76-f95a-45d6-bad5-6bedeee7a3b8' => 'SubSlotHarvestable',
            ],
            classToUuidMap: [
                'SubSlotHarvestable' => '03259b76-f95a-45d6-bad5-6bedeee7a3b8',
            ],
            uuidToPathMap: [
                '03259b76-f95a-45d6-bad5-6bedeee7a3b8' => $harvestablePath,
            ],
        );

        $this->writeFile('Data/Localization/english/global.ini', '');
        (new ServiceFactory($this->tempDir))->initialize();

        $path = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/sub_slot_setup.xml',
            <<<'XML'
            <HarvestableSetup.SubSlotSetup respawnInSlotTime="1200" __type="HarvestableSetup" __ref="08ddd192-e526-4912-a75e-a9e0ce63115c" __path="libs/foundry/records/harvestable/harvestablesetups/sub_slot_setup.xml">
              <subHarvestableSlots>
                <SubHarvestableSlot harvestable="03259b76-f95a-45d6-bad5-6bedeee7a3b8" minCount="1" maxCount="2" />
              </subHarvestableSlots>
            </HarvestableSetup.SubSlotSetup>
            XML
        );

        $document = (new HarvestableSetup)
            ->setReferenceHydrationEnabled(false);
        $document->load($path);

        $slots = $document->getSubHarvestableSlots();

        self::assertCount(1, $slots);
        self::assertSame('03259b76-f95a-45d6-bad5-6bedeee7a3b8', $slots[0]->getHarvestable()?->getUuid());
        self::assertSame('11111111-1111-1111-1111-111111111111', $slots[0]->getHarvestable()?->getEntityClassReference());
    }
}
