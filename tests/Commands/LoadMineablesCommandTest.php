<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadMineables;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadMineablesCommandTest extends ScDataTestCase
{
    private const GLOBAL_PARAMS_UUID = '20000000-0000-0000-0000-000000000003';
    private const COMPOSITION_UUID = '20000000-0000-0000-0000-000000000004';
    private const ELEMENT_UUID = '20000000-0000-0000-0000-000000000005';
    private const RESOURCE_UUID = '20000000-0000-0000-0000-000000000006';
    private const ASTEROID_MINEABLE_UUID = '20000000-0000-0000-0000-000000000007';
    private const PROVIDER_UUID = '30000000-0000-0000-0000-000000000001';
    private const HARVESTABLE_UUID = '30000000-0000-0000-0000-000000000002';
    private const ENTITY_ONE_UUID = '30000000-0000-0000-0000-000000000003';
    private const ENTITY_TWO_UUID = '30000000-0000-0000-0000-000000000004';
    private const ENTITY_THREE_UUID = '30000000-0000-0000-0000-00000000000b';
    private const STARMAP_UUID = '30000000-0000-0000-0000-000000000005';
    private const STANTON1_TAG_UUID = '30000000-0000-0000-0000-000000000006';
    private const SALVAGE_PROVIDER_UUID = '30000000-0000-0000-0000-000000000007';
    private const SALVAGE_PRESET_UUID = '30000000-0000-0000-0000-000000000008';
    private const SALVAGE_SETUP_UUID = '30000000-0000-0000-0000-000000000009';
    private const SALVAGE_CLUSTER_UUID = '30000000-0000-0000-0000-00000000000a';
    private const HARVESTABLE_ONLY_UUID = '30000000-0000-0000-0000-00000000000c';

    protected function setUp(): void
    {
        parent::setUp();

        $mineablePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_entity_one.xml',
            sprintf(
                '<EntityClassDefinition.SampleEntityOne __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/mineable/sample_entity_one.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" Size="1" Grade="1"><Localization><English Name="Sample Entity One" /></Localization></AttachDef></SAttachableComponentParams><MineableParams globalParams="%2$s" composition="%3$s" /><SSCSignatureSystemParams><radarProperties><SSCRadarContactProperites><baseSignatureParams><SSCSignatureSystemBaseSignatureParams><signatures><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="3900" /></signatures></SSCSignatureSystemBaseSignatureParams></baseSignatureParams></SSCRadarContactProperites></radarProperties></SSCSignatureSystemParams></Components></EntityClassDefinition.SampleEntityOne>',
                self::ENTITY_ONE_UUID,
                self::GLOBAL_PARAMS_UUID,
                self::COMPOSITION_UUID,
            )
        );

        $entityTwoPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_entity_two.xml',
            sprintf(
                '<EntityClassDefinition.SampleEntityTwo __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/mineable/sample_entity_two.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" Size="1" Grade="1"><Localization Name="@sample_entity_two" /></AttachDef></SAttachableComponentParams><MineableParams globalParams="%2$s" composition="%3$s" /><SSCSignatureSystemParams><radarProperties><SSCRadarContactProperites><baseSignatureParams><SSCSignatureSystemBaseSignatureParams><signatures><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="3200" /></signatures></SSCSignatureSystemBaseSignatureParams></baseSignatureParams></SSCRadarContactProperites></radarProperties></SSCSignatureSystemParams></Components></EntityClassDefinition.SampleEntityTwo>',
                self::ENTITY_TWO_UUID,
                self::GLOBAL_PARAMS_UUID,
                self::COMPOSITION_UUID,
            )
        );

        $asteroidMineablePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_asteroid_mineable.xml',
            sprintf(
                '<EntityClassDefinition.SampleAsteroidMineable __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/mineable/sample_asteroid_mineable.xml"><Components><MineableParams globalParams="%2$s" composition="%3$s" /></Components></EntityClassDefinition.SampleAsteroidMineable>',
                self::ASTEROID_MINEABLE_UUID,
                self::GLOBAL_PARAMS_UUID,
                self::COMPOSITION_UUID,
            )
        );

        $entityThreePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/harvestable/sample_entity_three.xml',
            sprintf(
                '<EntityClassDefinition.SampleEntityThree __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_entity_three.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization><English Name="Sample Entity Three" /></Localization></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.SampleEntityThree>',
                self::ENTITY_THREE_UUID,
            )
        );

        $globalParamsPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/miningglobalparams/sample_global_params.xml',
            sprintf(
                '<MiningGlobalParams.SampleGlobalParams powerCapacityPerMass="5" decayPerMass="0.2" optimalWindowSize="0.5" optimalWindowFactor="0.5" optimalWindowMaxSize="0.7" resistanceCurveFactor="0.33" optimalWindowThinnessCurveFactor="0.7" cSCUPerVolume="3" defaultMass="1" __type="MiningGlobalParams" __ref="%1$s" __path="libs/foundry/records/mining/miningglobalparams/sample_global_params.xml" />',
                self::GLOBAL_PARAMS_UUID,
            )
        );

        $compositionPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/rockcompositionpresets/sample_composition.xml',
            sprintf(
                '<MineableComposition.SampleComposition depositName="@sample_deposit" minimumDistinctElements="1" __type="MineableComposition" __ref="%1$s" __path="libs/foundry/records/mining/rockcompositionpresets/sample_composition.xml"><compositionArray><MineableCompositionPart mineableElement="%2$s" minPercentage="30" maxPercentage="70" probability="1" /></compositionArray></MineableComposition.SampleComposition>',
                self::COMPOSITION_UUID,
                self::ELEMENT_UUID,
            )
        );

        $elementPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/mineableelements/sample_element.xml',
            sprintf(
                '<MineableElement.SampleElement resourceType="%2$s" elementInstability="50" elementResistance="-0.7" __type="MineableElement" __ref="%1$s" __path="libs/foundry/records/mining/mineableelements/sample_element.xml" />',
                self::ELEMENT_UUID,
                self::RESOURCE_UUID,
            )
        );

        $providerPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton1.xml',
            sprintf(
                '<HarvestableProviderPreset.HPP_Stanton1 __type="HarvestableProviderPreset" __ref="%1$s" __path="libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton1.xml"><harvestableGroups><HarvestableElementGroup groupName="SpaceShip_Mineables" groupProbability="0.4"><harvestables><HarvestableElement harvestable="%2$s" relativeProbability="3" /><HarvestableElement harvestableEntityClass="%3$s" relativeProbability="1" /><HarvestableElement harvestable="%4$s" relativeProbability="2" /></harvestables></HarvestableElementGroup></harvestableGroups><areas><Area name="Deserts" globalModifier="1" /><Area name="Savanna" globalModifier="2.5" /></areas></HarvestableProviderPreset.HPP_Stanton1>',
                self::PROVIDER_UUID,
                self::HARVESTABLE_UUID,
                self::ENTITY_TWO_UUID,
                self::HARVESTABLE_ONLY_UUID,
            )
        );

        $salvageProviderPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_spacederelict_general.xml',
            sprintf(
                '<HarvestableProviderPreset.HPP_SpaceDerelict_General __type="HarvestableProviderPreset" __ref="%1$s" __path="libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_spacederelict_general.xml"><harvestableGroups><HarvestableElementGroup groupName="Salvage_FreshDerelicts" groupProbability="0.04"><harvestables><HarvestableElement harvestable="%2$s" harvestableSetup="%3$s" clustering="%4$s" relativeProbability="5" /></harvestables></HarvestableElementGroup></harvestableGroups></HarvestableProviderPreset.HPP_SpaceDerelict_General>',
                self::SALVAGE_PROVIDER_UUID,
                self::SALVAGE_PRESET_UUID,
                self::SALVAGE_SETUP_UUID,
                self::SALVAGE_CLUSTER_UUID,
            )
        );

        $harvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_harvestable.xml',
            sprintf(
                '<HarvestablePreset.SampleHarvestable entityClass="%1$s" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_harvestable.xml" />',
                self::ENTITY_ONE_UUID,
                self::HARVESTABLE_UUID,
            )
        );

        $salvageHarvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_salvage_harvestable.xml',
            sprintf(
                '<HarvestablePreset.SampleSalvage890 __type="HarvestablePreset" __ref="%1$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_salvage_harvestable.xml" />',
                self::SALVAGE_PRESET_UUID,
            )
        );

        $harvestableOnlyPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_harvestable_only.xml',
            sprintf(
                '<HarvestablePreset.SampleHarvestableOnly entityClass="%1$s" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_harvestable_only.xml" />',
                self::ENTITY_THREE_UUID,
                self::HARVESTABLE_ONLY_UUID,
            )
        );

        $salvageSetupPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/sample_salvage_setup.xml',
            sprintf(
                '<HarvestableSetup.V3HarvestableSetup_Salvageable_2H respawnInSlotTime="7200" __type="HarvestableSetup" __ref="%1$s" __path="libs/foundry/records/harvestable/harvestablesetups/sample_salvage_setup.xml"><harvestBehaviour><harvestConditions><HarvestConditionMovement distance="100" /><HarvestConditionInteraction includeAttachedChildren="0" allInteractionsClearSpawnPoint="1" /><HarvestConditionHealth healthRatio="0.5" /><HarvestConditionDamageMap damageRatio="0.25" /></harvestConditions><despawnTimer despawnTimeSeconds="0" additionalWaitForNearbyPlayersSeconds="30" /></harvestBehaviour><subHarvestableSlots><SubHarvestableSlot harvestable="%2$s" minCount="1" maxCount="2" /></subHarvestableSlots></HarvestableSetup.V3HarvestableSetup_Salvageable_2H>',
                self::SALVAGE_SETUP_UUID,
                self::SALVAGE_PRESET_UUID,
            )
        );

        $salvageClusterPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/clusteringpresets/sample_salvage_cluster.xml',
            sprintf(
                '<HarvestableClusterPreset.SalvageClusterLarge probabilityOfClustering="100" __type="HarvestableClusterPreset" __ref="%1$s" __path="libs/foundry/records/harvestable/clusteringpresets/sample_salvage_cluster.xml"><clusterParamsArray><HarvestableClusterParams relativeProbability="1" minSize="5" maxSize="10" minProximity="5" maxProximity="10" /></clusterParamsArray></HarvestableClusterPreset.SalvageClusterLarge>',
                self::SALVAGE_CLUSTER_UUID,
            )
        );

        $starmapPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/stanton1.xml',
            sprintf(
                '<StarMapObject.Stanton1 name="@LOC_UNINITIALIZED" description="" locationHierarchyTag="%1$s" __type="StarMapObject" __ref="%2$s" __path="libs/foundry/records/starmap/pu/system/stanton/stanton1.xml" />',
                self::STANTON1_TAG_UUID,
                self::STARMAP_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleAsteroidMineable' => $asteroidMineablePath,
                    'SampleEntityOne' => $mineablePath,
                    'SampleEntityThree' => $entityThreePath,
                    'SampleEntityTwo' => $entityTwoPath,
                ],
                'HarvestableProviderPreset' => [
                    'HPP_SpaceDerelict_General' => $salvageProviderPath,
                    'HPP_Stanton1' => $providerPath,
                ],
                'HarvestablePreset' => [
                    'SampleHarvestable' => $harvestablePath,
                    'SampleHarvestableOnly' => $harvestableOnlyPath,
                    'SampleSalvage890' => $salvageHarvestablePath,
                ],
                'HarvestableSetup' => [
                    'V3HarvestableSetup_Salvageable_2H' => $salvageSetupPath,
                ],
                'HarvestableClusterPreset' => [
                    'SalvageClusterLarge' => $salvageClusterPath,
                ],
                'StarMapObject' => [
                    'Stanton1' => $starmapPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::ASTEROID_MINEABLE_UUID) => 'SampleAsteroidMineable',
                strtolower(self::ENTITY_ONE_UUID) => 'SampleEntityOne',
                strtolower(self::ENTITY_THREE_UUID) => 'SampleEntityThree',
                strtolower(self::ENTITY_TWO_UUID) => 'SampleEntityTwo',
                strtolower(self::GLOBAL_PARAMS_UUID) => 'SampleGlobalParams',
                strtolower(self::COMPOSITION_UUID) => 'SampleComposition',
                strtolower(self::ELEMENT_UUID) => 'SampleElement',
                strtolower(self::PROVIDER_UUID) => 'HPP_Stanton1',
                strtolower(self::SALVAGE_PROVIDER_UUID) => 'HPP_SpaceDerelict_General',
                strtolower(self::HARVESTABLE_UUID) => 'SampleHarvestable',
                strtolower(self::SALVAGE_PRESET_UUID) => 'SampleSalvage890',
                strtolower(self::HARVESTABLE_ONLY_UUID) => 'SampleHarvestableOnly',
                strtolower(self::SALVAGE_SETUP_UUID) => 'V3HarvestableSetup_Salvageable_2H',
                strtolower(self::SALVAGE_CLUSTER_UUID) => 'SalvageClusterLarge',
                strtolower(self::STARMAP_UUID) => 'Stanton1',
            ],
            classToUuidMap: [
                'SampleAsteroidMineable' => strtolower(self::ASTEROID_MINEABLE_UUID),
                'SampleEntityOne' => strtolower(self::ENTITY_ONE_UUID),
                'SampleEntityThree' => strtolower(self::ENTITY_THREE_UUID),
                'SampleEntityTwo' => strtolower(self::ENTITY_TWO_UUID),
                'SampleGlobalParams' => strtolower(self::GLOBAL_PARAMS_UUID),
                'SampleComposition' => strtolower(self::COMPOSITION_UUID),
                'SampleElement' => strtolower(self::ELEMENT_UUID),
                'HPP_Stanton1' => strtolower(self::PROVIDER_UUID),
                'HPP_SpaceDerelict_General' => strtolower(self::SALVAGE_PROVIDER_UUID),
                'SampleHarvestable' => strtolower(self::HARVESTABLE_UUID),
                'SampleHarvestableOnly' => strtolower(self::HARVESTABLE_ONLY_UUID),
                'SampleSalvage890' => strtolower(self::SALVAGE_PRESET_UUID),
                'V3HarvestableSetup_Salvageable_2H' => strtolower(self::SALVAGE_SETUP_UUID),
                'SalvageClusterLarge' => strtolower(self::SALVAGE_CLUSTER_UUID),
                'Stanton1' => strtolower(self::STARMAP_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::ASTEROID_MINEABLE_UUID) => $asteroidMineablePath,
                strtolower(self::ENTITY_ONE_UUID) => $mineablePath,
                strtolower(self::ENTITY_THREE_UUID) => $entityThreePath,
                strtolower(self::ENTITY_TWO_UUID) => $entityTwoPath,
                strtolower(self::GLOBAL_PARAMS_UUID) => $globalParamsPath,
                strtolower(self::COMPOSITION_UUID) => $compositionPath,
                strtolower(self::ELEMENT_UUID) => $elementPath,
                strtolower(self::PROVIDER_UUID) => $providerPath,
                strtolower(self::SALVAGE_PROVIDER_UUID) => $salvageProviderPath,
                strtolower(self::HARVESTABLE_UUID) => $harvestablePath,
                strtolower(self::HARVESTABLE_ONLY_UUID) => $harvestableOnlyPath,
                strtolower(self::SALVAGE_PRESET_UUID) => $salvageHarvestablePath,
                strtolower(self::SALVAGE_SETUP_UUID) => $salvageSetupPath,
                strtolower(self::SALVAGE_CLUSTER_UUID) => $salvageClusterPath,
                strtolower(self::STARMAP_UUID) => $starmapPath,
            ],
            entityMetadataMap: [
                'SampleAsteroidMineable' => [
                    'uuid' => strtolower(self::ASTEROID_MINEABLE_UUID),
                    'path' => $asteroidMineablePath,
                    'type' => 'Default',
                    'sub_type' => null,
                ],
                'SampleEntityOne' => [
                    'uuid' => strtolower(self::ENTITY_ONE_UUID),
                    'path' => $mineablePath,
                    'type' => 'Misc',
                    'sub_type' => 'Mineable',
                ],
                'SampleEntityThree' => [
                    'uuid' => strtolower(self::ENTITY_THREE_UUID),
                    'path' => $entityThreePath,
                    'type' => 'Misc',
                    'sub_type' => 'Harvestable',
                ],
                'SampleEntityTwo' => [
                    'uuid' => strtolower(self::ENTITY_TWO_UUID),
                    'path' => $entityTwoPath,
                    'type' => 'Misc',
                    'sub_type' => 'Mineable',
                ],
            ],
        );

        $this->writeResourceTypeCache([
            self::RESOURCE_UUID => sprintf(
                '<ResourceType.Carinite displayName="@resource_carinite" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/carinite.xml"><densityType><ResourceTypeDensity><densityUnit><GramsPerCubicCentimeter gramsPerCubicCentimeter="1.2" /></densityUnit></ResourceTypeDensity></densityType></ResourceType.Carinite>',
                self::RESOURCE_UUID,
            ),
        ]);

        $this->writeExtractedTagFiles([
            ['uuid' => self::STANTON1_TAG_UUID, 'name' => 'Stanton1'],
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            "resource_carinite=Carinite\nsample_deposit=Sample Deposit\nsample_entity_two=Sample Entity Two\n"
        );
    }

    public function test_execute_exports_index_and_locations_into_mineables_folder(): void
    {
        $tester = new CommandTester(new LoadMineables);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $mineablesContents = file_get_contents($this->tempDir.'/mineables/mineables.json');
        self::assertNotFalse($mineablesContents);
        $mineables = json_decode($mineablesContents, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(4, $mineables);
        self::assertSame(['Sample Entity One', 'Sample Entity Three', 'Sample Entity Two', 'SampleAsteroidMineable'], array_column($mineables, 'name'));
        self::assertSame('Carinite', $mineables[0]['composition']['parts'][0]['resource_type']['key']);
        self::assertSame('Carinite', $mineables[0]['composition']['parts'][0]['resource_type']['name']);
        $mineablesByUuid = [];
        foreach ($mineables as $mineable) {
            $mineablesByUuid[$mineable['uuid']] = $mineable;
        }
        self::assertSame(['mineable_entity', 'harvestable_preset_entity_class'], $mineablesByUuid[self::ENTITY_ONE_UUID]['sources']);
        self::assertSame(['harvestable_preset_entity_class'], $mineablesByUuid[self::ENTITY_THREE_UUID]['sources']);
        self::assertNull($mineablesByUuid[self::ENTITY_THREE_UUID]['signature']);
        self::assertNull($mineablesByUuid[self::ENTITY_THREE_UUID]['global_params']);
        self::assertNull($mineablesByUuid[self::ENTITY_THREE_UUID]['composition']);
        self::assertSame(['mineable_entity', 'harvestable_entity_class'], $mineablesByUuid[self::ENTITY_TWO_UUID]['sources']);
        self::assertSame(['mineable_entity'], $mineablesByUuid[self::ASTEROID_MINEABLE_UUID]['sources']);

        $locationsContents = file_get_contents($this->tempDir.'/mineables/locations.json');
        self::assertNotFalse($locationsContents);
        $locations = json_decode($locationsContents, true, 512, JSON_THROW_ON_ERROR);
        $locationsByProvider = [];
        foreach ($locations as $location) {
            $locationsByProvider[$location['provider']['name']] = $location;
        }

        self::assertCount(2, $locations);
        self::assertSame('hpp_spacederelict_general', $locationsByProvider['HPP_SpaceDerelict_General']['provider']['presetFile']);
        self::assertSame('Space Derelict', $locationsByProvider['HPP_SpaceDerelict_General']['location']['name']);
        self::assertSame('special', $locationsByProvider['HPP_SpaceDerelict_General']['location']['type']);
        self::assertSame([], $locationsByProvider['HPP_SpaceDerelict_General']['areas']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups']);
        self::assertSame('Salvage_FreshDerelicts', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['groupName']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits']);
        self::assertSame(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['relativeProbability']);
        self::assertSame('SampleSalvage890', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['name']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestablePreset']['uuid']);
        self::assertSame('SampleSalvage890', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestablePreset']['key']);
        self::assertSame(self::SALVAGE_SETUP_UUID, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['uuid']);
        self::assertSame(7200, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['respawn_in_slot_time']);
        self::assertEquals(100.0, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['movement_harvest_distance']);
        self::assertSame(0.25, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['required_damage_ratio']);
        self::assertTrue($locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['all_interactions_clear_spawn_point']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['sub_harvestable_slots'][0]['harvestable']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['sub_harvestable_slots'][0]['Harvestable']['__ref']);
        self::assertSame(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['sub_harvestable_slots'][0]['minCount']);
        self::assertSame(2, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['harvestableSetup']['sub_harvestable_slots'][0]['maxCount']);
        self::assertSame(self::SALVAGE_CLUSTER_UUID, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['uuid']);
        self::assertSame('SalvageClusterLarge', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['key']);
        self::assertSame(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['probability_of_clustering']);
        self::assertSame(5, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['summary']['min_size']);
        self::assertSame(10, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['summary']['max_size']);
        self::assertSame(5, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['summary']['min_proximity']);
        self::assertSame(10, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['summary']['max_proximity']);
        self::assertArrayNotHasKey('probability_percent', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['summary']);
        self::assertSame(5, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['params'][0]['minSize']);
        self::assertSame(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]['clustering']['params'][0]['relativeProbability']);
        self::assertArrayNotHasKey('harvestablePresetUuid', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]);
        self::assertArrayNotHasKey('harvestablePresetKey', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]);
        self::assertArrayNotHasKey('harvestableSetupGuid', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]);
        self::assertArrayNotHasKey('clusteringPresetGuid', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]);
        self::assertArrayNotHasKey('signature', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0]);

        self::assertSame(self::PROVIDER_UUID, $locationsByProvider['HPP_Stanton1']['provider']['uuid']);
        self::assertSame('Stanton', $locationsByProvider['HPP_Stanton1']['location']['system']);
        self::assertSame('Hurston', $locationsByProvider['HPP_Stanton1']['location']['name']);
        self::assertSame('planet', $locationsByProvider['HPP_Stanton1']['location']['type']);
        self::assertSame(self::STARMAP_UUID, $locationsByProvider['HPP_Stanton1']['starmap']['object']);
        self::assertSame(self::STANTON1_TAG_UUID, $locationsByProvider['HPP_Stanton1']['starmap']['tag']);
        self::assertSame('tag', $locationsByProvider['HPP_Stanton1']['starmap']['matchStrategy']);
        self::assertCount(2, $locationsByProvider['HPP_Stanton1']['areas']);
        self::assertSame('Deserts', $locationsByProvider['HPP_Stanton1']['areas'][0]['name']);
        self::assertSame(1, $locationsByProvider['HPP_Stanton1']['areas'][0]['globalModifier']);
        self::assertSame('Savanna', $locationsByProvider['HPP_Stanton1']['areas'][1]['name']);
        self::assertSame(2.5, $locationsByProvider['HPP_Stanton1']['areas'][1]['globalModifier']);
        self::assertCount(1, $locationsByProvider['HPP_Stanton1']['groups']);
        self::assertCount(3, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits']);
        self::assertSame(self::ENTITY_ONE_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['uuid']);
        self::assertSame('Sample Entity One', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['name']);
        self::assertEquals(0.5, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['relativeProbability']);
        self::assertEquals(3900.0, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['signature']);
        self::assertSame(self::COMPOSITION_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['composition']['uuid']);
        self::assertSame('Sample Deposit', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['composition']['deposit_name']);
        self::assertSame(1, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['composition']['minimum_distinct_elements']);
        self::assertSame('Carinite', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['composition']['parts'][0]['resource']['name']);
        self::assertArrayNotHasKey('compositionGuid', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]);
        self::assertArrayNotHasKey('resource_type', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0]['composition']['parts'][0]);
        self::assertSame(self::ENTITY_TWO_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]['uuid']);
        self::assertSame('Sample Entity Two', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]['name']);
        self::assertEquals(1 / 6, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]['relativeProbability']);
        self::assertEquals(3200.0, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]['signature']);
        self::assertSame(self::COMPOSITION_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]['composition']['uuid']);
        self::assertArrayNotHasKey('compositionGuid', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1]);
        self::assertSame(self::ENTITY_THREE_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]['uuid']);
        self::assertSame('Sample Entity Three', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]['name']);
        self::assertEquals(1 / 3, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]['relativeProbability']);
        self::assertSame(self::HARVESTABLE_ONLY_UUID, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]['harvestablePreset']['uuid']);
        self::assertArrayNotHasKey('signature', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]);
        self::assertArrayNotHasKey('composition', $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2]);
    }
}
