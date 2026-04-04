<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableSetup;
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
}
