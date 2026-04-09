<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadResources;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadResourcesCommandTest extends ScDataTestCase
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

    private const SALVAGE_ENTITY_UUID = '30000000-0000-0000-0000-00000000000d';

    private const PLANT_BASE_ENTITY_UUID = '30000000-0000-0000-0000-00000000000e';

    private const PLANT_PRESET_UUID = '30000000-0000-0000-0000-00000000000f';

    private const PLANT_FRUIT_ENTITY_UUID = '30000000-0000-0000-0000-000000000010';

    private const PLANT_FRUIT_PRESET_UUID = '30000000-0000-0000-0000-000000000011';

    private const PLANT_RESOURCE_UUID = '20000000-0000-0000-0000-000000000012';

    private const PLANT_PROVIDER_UUID = '30000000-0000-0000-0000-000000000013';

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
                '<EntityClassDefinition.SampleEntityThree __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_entity_three.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization><English Name="Sample Entity Three" /></Localization></AttachDef></SAttachableComponentParams><ResourceContainer immutable="1" defaultCompositionFillFactor="0.75"><capacity><SMicroCargoUnit microSCU="250" /></capacity><defaultComposition><ResourceContainerDefaultCompositionEntry entry="%2$s" weight="3" /><ResourceContainerDefaultCompositionEntry entry="%3$s" weight="1" /></defaultComposition></ResourceContainer></Components></EntityClassDefinition.SampleEntityThree>',
                self::ENTITY_THREE_UUID,
                self::RESOURCE_UUID,
                self::ENTITY_ONE_UUID,
            )
        );

        $salvageEntityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/salvageable/sample_salvage_entity.xml',
            sprintf(
                '<EntityClassDefinition.SampleSalvageEntity __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/salvageable/sample_salvage_entity.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="UNDEFINED" Size="1" Grade="1"><Localization><English Name="Sample Salvage Entity" /></Localization></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.SampleSalvageEntity>',
                self::SALVAGE_ENTITY_UUID,
            )
        );

        $plantBaseEntityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/harvestable/sample_plant_base.xml',
            sprintf(
                '<EntityClassDefinition.SamplePlantBase __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_plant_base.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization Name="@sample_plant_base" /></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.SamplePlantBase>',
                self::PLANT_BASE_ENTITY_UUID,
            )
        );

        $plantFruitEntityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/harvestable/sample_plant_fruit.xml',
            sprintf(
                '<EntityClassDefinition.SamplePlantFruit __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_plant_fruit.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization Name="@sample_plant_fruit" /></AttachDef></SAttachableComponentParams><ResourceContainer immutable="0" defaultCompositionFillFactor="1.0"><capacity><SMicroCargoUnit microSCU="600" /></capacity><defaultComposition><ResourceContainerDefaultCompositionEntry entry="%2$s" weight="1" /></defaultComposition></ResourceContainer></Components></EntityClassDefinition.SamplePlantFruit>',
                self::PLANT_FRUIT_ENTITY_UUID,
                self::PLANT_RESOURCE_UUID,
            )
        );

        $plantPresetPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_plant_preset.xml',
            sprintf(
                '<HarvestablePreset.SamplePlantPreset entityClass="%1$s" respawnInSlotTime="3600" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_plant_preset.xml"><subConfigBase><SubHarvestableConfigManual><subConfigManual><subHarvestables><SubHarvestableSlot harvestable="%3$s" relativeProbability="1" harvestableRespawnTimeMultiplier="2" minCount="1" maxCount="3" /></subHarvestables></subConfigManual></SubHarvestableConfigManual></subConfigBase><harvestBehaviour><despawnTimer despawnTimeSeconds="600" additionalWaitForNearbyPlayersSeconds="300" /></harvestBehaviour></HarvestablePreset.SamplePlantPreset>',
                self::PLANT_BASE_ENTITY_UUID,
                self::PLANT_PRESET_UUID,
                self::PLANT_FRUIT_PRESET_UUID,
            )
        );

        $plantFruitPresetPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_plant_fruit_preset.xml',
            sprintf(
                '<HarvestablePreset.SamplePlantFruitPreset entityClass="%1$s" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_plant_fruit_preset.xml" />',
                self::PLANT_FRUIT_ENTITY_UUID,
                self::PLANT_FRUIT_PRESET_UUID,
            )
        );

        $plantProviderPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton_plants.xml',
            sprintf(
                '<HarvestableProviderPreset.HPP_Stanton_Plants __type="HarvestableProviderPreset" __ref="%1$s" __path="libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton_plants.xml"><harvestableGroups><HarvestableElementGroup groupName="Plants" groupProbability="0.5"><harvestables><HarvestableElement harvestable="%2$s" relativeProbability="1" /></harvestables></HarvestableElementGroup></harvestableGroups></HarvestableProviderPreset.HPP_Stanton_Plants>',
                self::PLANT_PROVIDER_UUID,
                self::PLANT_PRESET_UUID,
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
                '<HarvestablePreset.SampleSalvage890 entityClass="%2$s" __type="HarvestablePreset" __ref="%1$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_salvage_harvestable.xml" />',
                self::SALVAGE_PRESET_UUID,
                self::SALVAGE_ENTITY_UUID,
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
                    'SampleSalvageEntity' => $salvageEntityPath,
                    'SamplePlantBase' => $plantBaseEntityPath,
                    'SamplePlantFruit' => $plantFruitEntityPath,
                ],
                'HarvestableProviderPreset' => [
                    'HPP_SpaceDerelict_General' => $salvageProviderPath,
                    'HPP_Stanton1' => $providerPath,
                    'HPP_Stanton_Plants' => $plantProviderPath,
                ],
                'HarvestablePreset' => [
                    'SampleHarvestable' => $harvestablePath,
                    'SampleHarvestableOnly' => $harvestableOnlyPath,
                    'SampleSalvage890' => $salvageHarvestablePath,
                    'SamplePlantPreset' => $plantPresetPath,
                    'SamplePlantFruitPreset' => $plantFruitPresetPath,
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
                strtolower(self::SALVAGE_ENTITY_UUID) => 'SampleSalvageEntity',
                strtolower(self::PLANT_BASE_ENTITY_UUID) => 'SamplePlantBase',
                strtolower(self::PLANT_FRUIT_ENTITY_UUID) => 'SamplePlantFruit',
                strtolower(self::PLANT_PRESET_UUID) => 'SamplePlantPreset',
                strtolower(self::PLANT_FRUIT_PRESET_UUID) => 'SamplePlantFruitPreset',
                strtolower(self::PLANT_PROVIDER_UUID) => 'HPP_Stanton_Plants',
                strtolower(self::PLANT_RESOURCE_UUID) => 'PlantResource',
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
                'SampleSalvageEntity' => strtolower(self::SALVAGE_ENTITY_UUID),
                'SamplePlantBase' => strtolower(self::PLANT_BASE_ENTITY_UUID),
                'SamplePlantFruit' => strtolower(self::PLANT_FRUIT_ENTITY_UUID),
                'SamplePlantPreset' => strtolower(self::PLANT_PRESET_UUID),
                'SamplePlantFruitPreset' => strtolower(self::PLANT_FRUIT_PRESET_UUID),
                'HPP_Stanton_Plants' => strtolower(self::PLANT_PROVIDER_UUID),
                'PlantResource' => strtolower(self::PLANT_RESOURCE_UUID),
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
                strtolower(self::SALVAGE_ENTITY_UUID) => $salvageEntityPath,
                strtolower(self::PLANT_BASE_ENTITY_UUID) => $plantBaseEntityPath,
                strtolower(self::PLANT_FRUIT_ENTITY_UUID) => $plantFruitEntityPath,
                strtolower(self::PLANT_PRESET_UUID) => $plantPresetPath,
                strtolower(self::PLANT_FRUIT_PRESET_UUID) => $plantFruitPresetPath,
                strtolower(self::PLANT_PROVIDER_UUID) => $plantProviderPath,
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
                'SampleSalvageEntity' => [
                    'uuid' => strtolower(self::SALVAGE_ENTITY_UUID),
                    'path' => $salvageEntityPath,
                    'type' => 'Misc',
                    'sub_type' => 'UNDEFINED',
                ],
                'SamplePlantBase' => [
                    'uuid' => strtolower(self::PLANT_BASE_ENTITY_UUID),
                    'path' => $plantBaseEntityPath,
                    'type' => 'Misc',
                    'sub_type' => 'Harvestable',
                ],
                'SamplePlantFruit' => [
                    'uuid' => strtolower(self::PLANT_FRUIT_ENTITY_UUID),
                    'path' => $plantFruitEntityPath,
                    'type' => 'Misc',
                    'sub_type' => 'Harvestable',
                ],
            ],
        );

        $this->writeResourceTypeCache([
            self::RESOURCE_UUID => sprintf(
                '<ResourceType.Carinite displayName="@resource_carinite" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/carinite.xml"><densityType><ResourceTypeDensity><densityUnit><GramsPerCubicCentimeter gramsPerCubicCentimeter="1.2" /></densityUnit></ResourceTypeDensity></densityType></ResourceType.Carinite>',
                self::RESOURCE_UUID,
            ),
            self::PLANT_RESOURCE_UUID => sprintf(
                '<ResourceType.PlantResource displayName="@resource_plant" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/plant_resource.xml"><densityType><ResourceTypeDensity><densityUnit><GramsPerCubicCentimeter gramsPerCubicCentimeter="0.5" /></densityUnit></ResourceTypeDensity></densityType></ResourceType.PlantResource>',
                self::PLANT_RESOURCE_UUID,
            ),
        ]);

        $this->writeExtractedTagFiles([
            ['uuid' => self::STANTON1_TAG_UUID, 'name' => 'Stanton1'],
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            "resource_carinite=Carinite\nsample_deposit=Sample Deposit\nsample_entity_two=Sample Entity Two\nresource_plant=Plant Resource\nsample_plant_base=Sample Plant Base\nsample_plant_fruit=Sample Plant Fruit\n"
        );
    }

    public function test_execute_exports_index_and_locations_into_resources_folder(): void
    {
        $tester = new CommandTester(new LoadResources);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $resourcesContents = file_get_contents($this->tempDir.'/resources/resources.json');
        self::assertNotFalse($resourcesContents);
        $resources = json_decode($resourcesContents, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(6, $resources);
        $resourcesByUuid = [];
        foreach ($resources as $resource) {
            $resourcesByUuid[$resource['uuid']] = $resource;
        }

        self::assertSame('mineable', $resourcesByUuid[self::ENTITY_ONE_UUID]['kind']);
        self::assertSame('Carinite', $resourcesByUuid[self::ENTITY_ONE_UUID]['composition']['parts'][0]['key']);
        self::assertSame('Carinite', $resourcesByUuid[self::ENTITY_ONE_UUID]['composition']['parts'][0]['name']);

        self::assertSame('mineable', $resourcesByUuid[self::ENTITY_TWO_UUID]['kind']);
        self::assertSame('mineable', $resourcesByUuid[self::ASTEROID_MINEABLE_UUID]['kind']);

        self::assertSame('harvestable', $resourcesByUuid[self::ENTITY_THREE_UUID]['kind']);
        self::assertArrayNotHasKey('global_params', $resourcesByUuid[self::ENTITY_THREE_UUID]);
        self::assertSame(self::HARVESTABLE_ONLY_UUID, $resourcesByUuid[self::ENTITY_THREE_UUID]['harvestable_uuid']);
        self::assertSame('SampleHarvestableOnly', $resourcesByUuid[self::ENTITY_THREE_UUID]['harvestable_key']);
        self::assertCount(1, $resourcesByUuid[self::ENTITY_THREE_UUID]['parts']);
        $part = $resourcesByUuid[self::ENTITY_THREE_UUID]['parts'][0];
        self::assertSame(self::ENTITY_THREE_UUID, $part['uuid']);
        self::assertSame('SampleEntityThree', $part['key']);
        self::assertCount(2, $part['resource_types']);
        self::assertSame(self::RESOURCE_UUID, $part['resource_types'][0]['resource_type_uuid']);
        self::assertSame('Carinite', $part['resource_types'][0]['name']);
        self::assertSame(3, $part['resource_types'][0]['weight']);
        self::assertSame(self::ENTITY_ONE_UUID, $part['resource_types'][1]['resource_type_uuid']);
        self::assertSame('SampleEntityOne', $part['resource_types'][1]['name']);
        self::assertSame(1, $part['resource_types'][1]['weight']);
        self::assertTrue($part['immutable']);
        self::assertSame(0.75, $part['fill_fraction']);
        self::assertSame('µSCU', $part['capacity']['unit_name']);
        self::assertSame(250, $part['capacity']['value']);
        self::assertArrayNotHasKey('resource_container', $resourcesByUuid[self::ENTITY_THREE_UUID]);

        self::assertSame('salvageable', $resourcesByUuid[self::SALVAGE_ENTITY_UUID]['kind']);

        self::assertArrayNotHasKey('tier', $resourcesByUuid[self::ENTITY_ONE_UUID]);
        self::assertArrayNotHasKey('tier', $resourcesByUuid[self::SALVAGE_ENTITY_UUID]);
        self::assertArrayNotHasKey('tier', $resourcesByUuid[self::ENTITY_THREE_UUID]);

        $plant = $resourcesByUuid[self::PLANT_BASE_ENTITY_UUID];
        self::assertSame('harvestable', $plant['kind']);
        self::assertSame('SamplePlantBase', $plant['key']);
        self::assertSame('Sample Plant Base', $plant['name']);
        self::assertSame(self::PLANT_PRESET_UUID, $plant['harvestable_uuid']);
        self::assertSame('SamplePlantPreset', $plant['harvestable_key']);
        self::assertSame(3600, $plant['respawn_in_slot_time']);
        self::assertSame(600, $plant['despawn_time_seconds']);
        self::assertSame(300, $plant['additional_wait_for_nearby_players_seconds']);
        self::assertCount(1, $plant['parts']);
        $plantPart = $plant['parts'][0];
        self::assertSame(self::PLANT_FRUIT_ENTITY_UUID, $plantPart['uuid']);
        self::assertSame('SamplePlantFruit', $plantPart['key']);
        self::assertSame('Sample Plant Fruit', $plantPart['name']);
        self::assertCount(1, $plantPart['resource_types']);
        self::assertSame(self::PLANT_RESOURCE_UUID, $plantPart['resource_types'][0]['resource_type_uuid']);
        self::assertSame('PlantResource', $plantPart['resource_types'][0]['key']);
        self::assertSame('Plant Resource', $plantPart['resource_types'][0]['name']);
        self::assertSame(1, $plantPart['resource_types'][0]['weight']);
        self::assertFalse($plantPart['immutable']);
        self::assertSame(1, $plantPart['fill_fraction']);
        self::assertSame('µSCU', $plantPart['capacity']['unit_name']);
        self::assertSame(600, $plantPart['capacity']['value']);
        self::assertSame(1, $plantPart['relative_probability']);
        self::assertSame(2, $plantPart['respawn_time_multiplier']);
        self::assertSame(1, $plantPart['min_count']);
        self::assertSame(3, $plantPart['max_count']);

        $locationsContents = file_get_contents($this->tempDir.'/resources/locations.json');
        self::assertNotFalse($locationsContents);
        $locations = json_decode($locationsContents, true, 512, JSON_THROW_ON_ERROR);
        $locationsByProvider = [];
        foreach ($locations as $location) {
            $locationsByProvider[$location['provider']['name']] = $location;
        }

        self::assertCount(3, $locations);

        self::assertSame('hpp_spacederelict_general', $locationsByProvider['HPP_SpaceDerelict_General']['provider']['presetFile']);
        self::assertSame('Space Derelict', $locationsByProvider['HPP_SpaceDerelict_General']['locations'][0]['name']);
        self::assertSame('special', $locationsByProvider['HPP_SpaceDerelict_General']['locations'][0]['type']);
        self::assertSame([], $locationsByProvider['HPP_SpaceDerelict_General']['areas']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups']);
        self::assertSame('Salvage_FreshDerelicts', $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['groupName']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits']);
        $salvageDeposit = $locationsByProvider['HPP_SpaceDerelict_General']['groups'][0]['deposits'][0];
        self::assertSame(self::SALVAGE_ENTITY_UUID, $salvageDeposit['resource_uuid']);
        self::assertSame(1, $salvageDeposit['relativeProbability']);
        self::assertSame(self::SALVAGE_SETUP_UUID, $salvageDeposit['harvestableSetup']['uuid']);
        self::assertSame(7200, $salvageDeposit['harvestableSetup']['respawn_in_slot_time']);
        self::assertEquals(100.0, $salvageDeposit['harvestableSetup']['movement_harvest_distance']);
        self::assertSame(0.25, $salvageDeposit['harvestableSetup']['required_damage_ratio']);
        self::assertTrue($salvageDeposit['harvestableSetup']['all_interactions_clear_spawn_point']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $salvageDeposit['harvestableSetup']['sub_harvestable_slots'][0]['harvestable']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $salvageDeposit['harvestableSetup']['sub_harvestable_slots'][0]['Harvestable']['__ref']);
        self::assertSame(1, $salvageDeposit['harvestableSetup']['sub_harvestable_slots'][0]['minCount']);
        self::assertSame(2, $salvageDeposit['harvestableSetup']['sub_harvestable_slots'][0]['maxCount']);
        self::assertArrayNotHasKey('quality_overrides', $salvageDeposit);
        self::assertArrayNotHasKey('resource_qualities', $salvageDeposit);
        self::assertSame(self::SALVAGE_CLUSTER_UUID, $salvageDeposit['clustering']['uuid']);
        self::assertSame('SalvageClusterLarge', $salvageDeposit['clustering']['key']);
        self::assertSame(1, $salvageDeposit['clustering']['probability_of_clustering']);
        self::assertSame(5, $salvageDeposit['clustering']['summary']['min_size']);
        self::assertSame(10, $salvageDeposit['clustering']['summary']['max_size']);
        self::assertSame(5, $salvageDeposit['clustering']['summary']['min_proximity']);
        self::assertSame(10, $salvageDeposit['clustering']['summary']['max_proximity']);
        self::assertArrayNotHasKey('probability_percent', $salvageDeposit['clustering']['summary']);
        self::assertSame(5, $salvageDeposit['clustering']['params'][0]['minSize']);
        self::assertSame(1, $salvageDeposit['clustering']['params'][0]['relativeProbability']);

        self::assertSame(self::PROVIDER_UUID, $locationsByProvider['HPP_Stanton1']['provider']['uuid']);
        self::assertSame('Stanton', $locationsByProvider['HPP_Stanton1']['locations'][0]['system']);
        self::assertSame('Stanton1', $locationsByProvider['HPP_Stanton1']['locations'][0]['name']);
        self::assertSame('unknown', $locationsByProvider['HPP_Stanton1']['locations'][0]['type']);
        self::assertSame(self::STARMAP_UUID, $locationsByProvider['HPP_Stanton1']['locations'][0]['object']);
        self::assertSame(self::STANTON1_TAG_UUID, $locationsByProvider['HPP_Stanton1']['locations'][0]['tag']);
        self::assertSame('tag', $locationsByProvider['HPP_Stanton1']['locations'][0]['matchStrategy']);
        self::assertCount(2, $locationsByProvider['HPP_Stanton1']['areas']);
        self::assertSame('Deserts', $locationsByProvider['HPP_Stanton1']['areas'][0]['name']);
        self::assertSame(1, $locationsByProvider['HPP_Stanton1']['areas'][0]['globalModifier']);
        self::assertSame('Savanna', $locationsByProvider['HPP_Stanton1']['areas'][1]['name']);
        self::assertSame(2.5, $locationsByProvider['HPP_Stanton1']['areas'][1]['globalModifier']);
        self::assertCount(1, $locationsByProvider['HPP_Stanton1']['groups']);
        self::assertCount(3, $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits']);

        $deposit0 = $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][0];
        self::assertSame(self::ENTITY_ONE_UUID, $deposit0['resource_uuid']);
        self::assertEquals(0.5, $deposit0['relativeProbability']);
        self::assertArrayNotHasKey('harvestableSetup', $deposit0);
        self::assertArrayNotHasKey('quality_overrides', $deposit0);
        self::assertArrayNotHasKey('resource_qualities', $deposit0);

        $deposit1 = $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][1];
        self::assertSame(self::ENTITY_TWO_UUID, $deposit1['resource_uuid']);
        self::assertEquals(1 / 6, $deposit1['relativeProbability']);
        self::assertArrayNotHasKey('harvestableSetup', $deposit1);
        self::assertArrayNotHasKey('quality_overrides', $deposit1);
        self::assertArrayNotHasKey('resource_qualities', $deposit1);

        $deposit2 = $locationsByProvider['HPP_Stanton1']['groups'][0]['deposits'][2];
        self::assertSame(self::ENTITY_THREE_UUID, $deposit2['resource_uuid']);
        self::assertEquals(1 / 3, $deposit2['relativeProbability']);
        self::assertArrayNotHasKey('harvestableSetup', $deposit2);
        self::assertArrayNotHasKey('quality_overrides', $deposit2);
        self::assertArrayNotHasKey('resource_qualities', $deposit2);

        self::assertFileDoesNotExist($this->tempDir.'/resources/quality-distributions.json');
        self::assertFileDoesNotExist($this->tempDir.'/resources/quality-location-overrides.json');
    }
}
