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

    private const CAVE_BASE_ENTITY_UUID = '30000000-0000-0000-0000-000000000020';

    private const CAVE_PRESET_UUID = '30000000-0000-0000-0000-000000000021';

    private const CAVE_FRUIT_ENTITY_UUID = '30000000-0000-0000-0000-000000000022';

    private const CAVE_FRUIT_PRESET_UUID = '30000000-0000-0000-0000-000000000023';

    private const CAVE_RESOURCE_UUID = '20000000-0000-0000-0000-000000000024';

    private const CAVE_CONFIG_UUID = '30000000-0000-0000-0000-000000000025';

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

        $caveBaseEntityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/harvestable/sample_cave_base.xml',
            sprintf(
                '<EntityClassDefinition.SampleCaveBase __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_cave_base.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization Name="@sample_cave_base" /></AttachDef></SAttachableComponentParams><SSCSignatureSystemParams><radarProperties><SSCRadarContactProperites><baseSignatureParams><SSCSignatureSystemBaseSignatureParams><signatures><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="0" /><Signature value="2000" /></signatures></SSCSignatureSystemBaseSignatureParams></baseSignatureParams></SSCRadarContactProperites></radarProperties></SSCSignatureSystemParams></Components></EntityClassDefinition.SampleCaveBase>',
                self::CAVE_BASE_ENTITY_UUID,
            )
        );

        $caveFruitEntityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/harvestable/sample_cave_fruit.xml',
            sprintf(
                '<EntityClassDefinition.SampleCaveFruit __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/harvestable/sample_cave_fruit.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Harvestable" Size="1" Grade="1"><Localization Name="@sample_cave_fruit" /></AttachDef></SAttachableComponentParams><ResourceContainer immutable="0" defaultCompositionFillFactor="1.0"><capacity><SMicroCargoUnit microSCU="500" /></capacity><defaultComposition><ResourceContainerDefaultCompositionEntry entry="%2$s" weight="1" /></defaultComposition></ResourceContainer></Components></EntityClassDefinition.SampleCaveFruit>',
                self::CAVE_FRUIT_ENTITY_UUID,
                self::CAVE_RESOURCE_UUID,
            )
        );

        $caveFruitPresetPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_cave_fruit_preset.xml',
            sprintf(
                '<HarvestablePreset.SampleCaveFruitPreset entityClass="%1$s" respawnInSlotTime="3600" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_cave_fruit_preset.xml" />',
                self::CAVE_FRUIT_ENTITY_UUID,
                self::CAVE_FRUIT_PRESET_UUID,
            )
        );

        $cavePresetPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_cave_plant_preset.xml',
            sprintf(
                '<HarvestablePreset.SampleCavePlantPreset entityClass="%1$s" respawnInSlotTime="7200" __type="HarvestablePreset" __ref="%2$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_cave_plant_preset.xml"><subConfigBase><SubHarvestableConfigManual><subConfigManual><subHarvestables><SubHarvestableSlot harvestable="%3$s" relativeProbability="1" harvestableRespawnTimeMultiplier="1" minCount="1" maxCount="2" /></subHarvestables></subConfigManual></SubHarvestableConfigManual></subConfigBase><harvestBehaviour><despawnTimer despawnTimeSeconds="600" additionalWaitForNearbyPlayersSeconds="300" /></harvestBehaviour></HarvestablePreset.SampleCavePlantPreset>',
                self::CAVE_BASE_ENTITY_UUID,
                self::CAVE_PRESET_UUID,
                self::CAVE_FRUIT_PRESET_UUID,
            )
        );

        $caveConfigPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/slotpresets/sample_cave_config.xml',
            sprintf(
                '<SubHarvestableMultiConfigRecord.SampleCaveConfig __type="SubHarvestableMultiConfigRecord" __ref="%1$s" __path="libs/foundry/records/harvestable/slotpresets/sample_cave_config.xml"><multiConfig><taggedConfigs><TaggedSubHarvestableConfig name="CaveFlora"><tagList><HarvestableTagListTagEditor><tags><Reference value="00000000-0000-0000-0000-000000000099" /></tags></HarvestableTagListTagEditor></tagList><subConfig><SubHarvestableConfigSingleManual><subConfigManual initialSlotsProbability="0.8" configRespawnTimeMultiplier="1"><subHarvestables><SubHarvestableSlot harvestable="%2$s" relativeProbability="1" harvestableRespawnTimeMultiplier="1" /></subHarvestables></subConfigManual></SubHarvestableConfigSingleManual></subConfig></TaggedSubHarvestableConfig></taggedConfigs></multiConfig></SubHarvestableMultiConfigRecord.SampleCaveConfig>',
                self::CAVE_CONFIG_UUID,
                self::CAVE_PRESET_UUID,
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
                    'SampleCaveBase' => $caveBaseEntityPath,
                    'SampleCaveFruit' => $caveFruitEntityPath,
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
                    'SampleCaveFruitPreset' => $caveFruitPresetPath,
                    'SampleCavePlantPreset' => $cavePresetPath,
                ],
                'HarvestableSetup' => [
                    'V3HarvestableSetup_Salvageable_2H' => $salvageSetupPath,
                ],
                'HarvestableClusterPreset' => [
                    'SalvageClusterLarge' => $salvageClusterPath,
                ],
                'SubHarvestableMultiConfigRecord' => [
                    'SampleCaveConfig' => $caveConfigPath,
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
                strtolower(self::CAVE_BASE_ENTITY_UUID) => 'SampleCaveBase',
                strtolower(self::CAVE_FRUIT_ENTITY_UUID) => 'SampleCaveFruit',
                strtolower(self::CAVE_PRESET_UUID) => 'SampleCavePlantPreset',
                strtolower(self::CAVE_FRUIT_PRESET_UUID) => 'SampleCaveFruitPreset',
                strtolower(self::CAVE_CONFIG_UUID) => 'SampleCaveConfig',
                strtolower(self::CAVE_RESOURCE_UUID) => 'CaveResource',
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
                'SampleCaveBase' => strtolower(self::CAVE_BASE_ENTITY_UUID),
                'SampleCaveFruit' => strtolower(self::CAVE_FRUIT_ENTITY_UUID),
                'SampleCavePlantPreset' => strtolower(self::CAVE_PRESET_UUID),
                'SampleCaveFruitPreset' => strtolower(self::CAVE_FRUIT_PRESET_UUID),
                'SampleCaveConfig' => strtolower(self::CAVE_CONFIG_UUID),
                'CaveResource' => strtolower(self::CAVE_RESOURCE_UUID),
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
                strtolower(self::CAVE_BASE_ENTITY_UUID) => $caveBaseEntityPath,
                strtolower(self::CAVE_FRUIT_ENTITY_UUID) => $caveFruitEntityPath,
                strtolower(self::CAVE_PRESET_UUID) => $cavePresetPath,
                strtolower(self::CAVE_FRUIT_PRESET_UUID) => $caveFruitPresetPath,
                strtolower(self::CAVE_CONFIG_UUID) => $caveConfigPath,
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
                'SampleCaveBase' => [
                    'uuid' => strtolower(self::CAVE_BASE_ENTITY_UUID),
                    'path' => $caveBaseEntityPath,
                    'type' => 'Misc',
                    'sub_type' => 'Harvestable',
                ],
                'SampleCaveFruit' => [
                    'uuid' => strtolower(self::CAVE_FRUIT_ENTITY_UUID),
                    'path' => $caveFruitEntityPath,
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
            self::CAVE_RESOURCE_UUID => sprintf(
                '<ResourceType.CaveResource displayName="@resource_cave" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/cave_resource.xml"><densityType><ResourceTypeDensity><densityUnit><GramsPerCubicCentimeter gramsPerCubicCentimeter="0.8" /></densityUnit></ResourceTypeDensity></densityType></ResourceType.CaveResource>',
                self::CAVE_RESOURCE_UUID,
            ),
        ]);

        $this->writeExtractedTagFiles([
            ['uuid' => self::STANTON1_TAG_UUID, 'name' => 'Stanton1'],
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            "resource_carinite=Carinite\nsample_deposit=Sample Deposit\nsample_entity_two=Sample Entity Two\nresource_plant=Plant Resource\nsample_plant_base=Sample Plant Base\nsample_plant_fruit=Sample Plant Fruit\nresource_cave=Cave Resource\nsample_cave_base=Sample Cave Base\nsample_cave_fruit=Sample Cave Fruit\n"
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

        self::assertCount(7, $resources);
        $resourcesByUuid = [];
        foreach ($resources as $resource) {
            $resourcesByUuid[$resource['UUID']] = $resource;
        }

        self::assertSame('mineable', $resourcesByUuid[self::ENTITY_ONE_UUID]['Kind']);
        self::assertSame('Carinite', $resourcesByUuid[self::ENTITY_ONE_UUID]['Composition']['Parts'][0]['Key']);
        self::assertSame('Carinite', $resourcesByUuid[self::ENTITY_ONE_UUID]['Composition']['Parts'][0]['Name']);

        self::assertSame('mineable', $resourcesByUuid[self::ENTITY_TWO_UUID]['Kind']);
        self::assertSame('mineable', $resourcesByUuid[self::ASTEROID_MINEABLE_UUID]['Kind']);

        self::assertSame('harvestable', $resourcesByUuid[self::ENTITY_THREE_UUID]['Kind']);
        self::assertArrayNotHasKey('GlobalParams', $resourcesByUuid[self::ENTITY_THREE_UUID]);
        self::assertSame(self::HARVESTABLE_ONLY_UUID, $resourcesByUuid[self::ENTITY_THREE_UUID]['HarvestableUUID']);
        self::assertSame('SampleHarvestableOnly', $resourcesByUuid[self::ENTITY_THREE_UUID]['HarvestableKey']);
        self::assertCount(1, $resourcesByUuid[self::ENTITY_THREE_UUID]['Parts']);
        $part = $resourcesByUuid[self::ENTITY_THREE_UUID]['Parts'][0];
        self::assertSame(self::ENTITY_THREE_UUID, $part['UUID']);
        self::assertSame('SampleEntityThree', $part['Key']);
        self::assertCount(2, $part['ResourceTypes']);
        self::assertSame(self::RESOURCE_UUID, $part['ResourceTypes'][0]['ResourceTypeUUID']);
        self::assertSame('Carinite', $part['ResourceTypes'][0]['Name']);
        self::assertSame(3, $part['ResourceTypes'][0]['Weight']);
        self::assertSame(self::ENTITY_ONE_UUID, $part['ResourceTypes'][1]['ResourceTypeUUID']);
        self::assertSame('SampleEntityOne', $part['ResourceTypes'][1]['Name']);
        self::assertSame(1, $part['ResourceTypes'][1]['Weight']);
        self::assertTrue($part['Immutable']);
        self::assertSame(0.75, $part['FillFraction']);
        self::assertSame('µSCU', $part['Capacity']['UnitName']);
        self::assertSame(250, $part['Capacity']['Value']);
        self::assertArrayNotHasKey('ResourceContainer', $resourcesByUuid[self::ENTITY_THREE_UUID]);

        self::assertSame('salvageable', $resourcesByUuid[self::SALVAGE_ENTITY_UUID]['Kind']);

        self::assertArrayNotHasKey('Tier', $resourcesByUuid[self::ENTITY_ONE_UUID]);
        self::assertArrayNotHasKey('Tier', $resourcesByUuid[self::SALVAGE_ENTITY_UUID]);
        self::assertArrayNotHasKey('Tier', $resourcesByUuid[self::ENTITY_THREE_UUID]);

        $plant = $resourcesByUuid[self::PLANT_BASE_ENTITY_UUID];
        self::assertSame('harvestable', $plant['Kind']);
        self::assertSame('SamplePlantBase', $plant['Key']);
        self::assertSame('Sample Plant Base', $plant['Name']);
        self::assertSame(self::PLANT_PRESET_UUID, $plant['HarvestableUUID']);
        self::assertSame('SamplePlantPreset', $plant['HarvestableKey']);
        self::assertSame(3600, $plant['RespawnInSlotTime']);
        self::assertSame(600, $plant['DespawnTimeSeconds']);
        self::assertSame(300, $plant['AdditionalWaitForNearbyPlayersSeconds']);
        self::assertCount(1, $plant['Parts']);
        $plantPart = $plant['Parts'][0];
        self::assertSame(self::PLANT_FRUIT_ENTITY_UUID, $plantPart['UUID']);
        self::assertSame('SamplePlantFruit', $plantPart['Key']);
        self::assertSame('Sample Plant Fruit', $plantPart['Name']);
        self::assertCount(1, $plantPart['ResourceTypes']);
        self::assertSame(self::PLANT_RESOURCE_UUID, $plantPart['ResourceTypes'][0]['ResourceTypeUUID']);
        self::assertSame('PlantResource', $plantPart['ResourceTypes'][0]['Key']);
        self::assertSame('Plant Resource', $plantPart['ResourceTypes'][0]['Name']);
        self::assertSame(1, $plantPart['ResourceTypes'][0]['Weight']);
        self::assertFalse($plantPart['Immutable']);
        self::assertSame(1, $plantPart['FillFraction']);
        self::assertSame('µSCU', $plantPart['Capacity']['UnitName']);
        self::assertSame(600, $plantPart['Capacity']['Value']);
        self::assertSame(1, $plantPart['RelativeProbability']);
        self::assertSame(2, $plantPart['RespawnTimeMultiplier']);
        self::assertSame(1, $plantPart['MinCount']);
        self::assertSame(3, $plantPart['MaxCount']);

        $cave = $resourcesByUuid[self::CAVE_BASE_ENTITY_UUID];
        self::assertSame('cave_harvestable', $cave['Kind']);
        self::assertSame('SampleCaveBase', $cave['Key']);
        self::assertSame('Sample Cave Base', $cave['Name']);
        self::assertSame(2000, $cave['Signature']);
        self::assertSame(self::CAVE_PRESET_UUID, $cave['HarvestableUUID']);
        self::assertSame('SampleCavePlantPreset', $cave['HarvestableKey']);
        self::assertSame(7200, $cave['RespawnInSlotTime']);
        self::assertSame(600, $cave['DespawnTimeSeconds']);
        self::assertSame(300, $cave['AdditionalWaitForNearbyPlayersSeconds']);
        self::assertCount(1, $cave['Parts']);
        $cavePart = $cave['Parts'][0];
        self::assertSame(self::CAVE_FRUIT_ENTITY_UUID, $cavePart['UUID']);
        self::assertSame('SampleCaveFruit', $cavePart['Key']);
        self::assertSame('Sample Cave Fruit', $cavePart['Name']);
        self::assertCount(1, $cavePart['ResourceTypes']);
        self::assertSame(self::CAVE_RESOURCE_UUID, $cavePart['ResourceTypes'][0]['ResourceTypeUUID']);
        self::assertSame('CaveResource', $cavePart['ResourceTypes'][0]['Key']);
        self::assertSame('Cave Resource', $cavePart['ResourceTypes'][0]['Name']);
        self::assertSame(1, $cavePart['ResourceTypes'][0]['Weight']);
        self::assertFalse($cavePart['Immutable']);
        self::assertSame(1, $cavePart['FillFraction']);
        self::assertSame('µSCU', $cavePart['Capacity']['UnitName']);
        self::assertSame(500, $cavePart['Capacity']['Value']);
        self::assertSame(1, $cavePart['RelativeProbability']);
        self::assertSame(1, $cavePart['RespawnTimeMultiplier']);
        self::assertSame(1, $cavePart['MinCount']);
        self::assertSame(2, $cavePart['MaxCount']);

        $locationsContents = file_get_contents($this->tempDir.'/resources/locations.json');
        self::assertNotFalse($locationsContents);
        $locations = json_decode($locationsContents, true, 512, JSON_THROW_ON_ERROR);
        $locationsByProvider = [];
        foreach ($locations as $location) {
            $locationsByProvider[$location['Provider']['Name']] = $location;
        }

        self::assertCount(3, $locations);

        self::assertSame('hpp_spacederelict_general', $locationsByProvider['HPP_SpaceDerelict_General']['Provider']['PresetFile']);
        self::assertSame('Space Derelict', $locationsByProvider['HPP_SpaceDerelict_General']['Locations'][0]['Name']);
        self::assertSame('special', $locationsByProvider['HPP_SpaceDerelict_General']['Locations'][0]['Type']);
        self::assertSame([], $locationsByProvider['HPP_SpaceDerelict_General']['Areas']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['Groups']);
        self::assertSame('Salvage_FreshDerelicts', $locationsByProvider['HPP_SpaceDerelict_General']['Groups'][0]['GroupName']);
        self::assertCount(1, $locationsByProvider['HPP_SpaceDerelict_General']['Groups'][0]['Deposits']);
        $salvageDeposit = $locationsByProvider['HPP_SpaceDerelict_General']['Groups'][0]['Deposits'][0];
        self::assertSame(self::SALVAGE_ENTITY_UUID, $salvageDeposit['ResourceUUID']);
        self::assertSame(1, $salvageDeposit['RelativeProbability']);
        self::assertSame(self::SALVAGE_SETUP_UUID, $salvageDeposit['HarvestableSetup']['UUID']);
        self::assertSame(7200, $salvageDeposit['HarvestableSetup']['RespawnInSlotTime']);
        self::assertEquals(100.0, $salvageDeposit['HarvestableSetup']['MovementHarvestDistance']);
        self::assertSame(0.25, $salvageDeposit['HarvestableSetup']['RequiredDamageRatio']);
        self::assertTrue($salvageDeposit['HarvestableSetup']['AllInteractionsClearSpawnPoint']);
        self::assertSame(self::SALVAGE_PRESET_UUID, $salvageDeposit['HarvestableSetup']['SubHarvestableSlots'][0]['Harvestable']['Ref']);
        self::assertSame(1, $salvageDeposit['HarvestableSetup']['SubHarvestableSlots'][0]['MinCount']);
        self::assertSame(2, $salvageDeposit['HarvestableSetup']['SubHarvestableSlots'][0]['MaxCount']);
        self::assertArrayNotHasKey('QualityOverrides', $salvageDeposit);
        self::assertArrayNotHasKey('ResourceQualities', $salvageDeposit);
        self::assertSame(self::SALVAGE_CLUSTER_UUID, $salvageDeposit['Clustering']['UUID']);
        self::assertSame('SalvageClusterLarge', $salvageDeposit['Clustering']['Key']);
        self::assertSame(1, $salvageDeposit['Clustering']['ProbabilityOfClustering']);
        self::assertSame(5, $salvageDeposit['Clustering']['MinSize']);
        self::assertSame(10, $salvageDeposit['Clustering']['MaxSize']);
        self::assertSame(5, $salvageDeposit['Clustering']['MinProximity']);
        self::assertSame(10, $salvageDeposit['Clustering']['MaxProximity']);
        self::assertArrayNotHasKey('ProbabilityPercent', $salvageDeposit['Clustering']);
        self::assertSame(5, $salvageDeposit['Clustering']['Params'][0]['MinSize']);
        self::assertSame(1, $salvageDeposit['Clustering']['Params'][0]['RelativeProbability']);

        self::assertSame(self::PROVIDER_UUID, $locationsByProvider['HPP_Stanton1']['Provider']['UUID']);
        self::assertSame('Stanton', $locationsByProvider['HPP_Stanton1']['Locations'][0]['System']);
        self::assertSame('Stanton1', $locationsByProvider['HPP_Stanton1']['Locations'][0]['Name']);
        self::assertSame('unknown', $locationsByProvider['HPP_Stanton1']['Locations'][0]['Type']);
        self::assertSame(self::STARMAP_UUID, $locationsByProvider['HPP_Stanton1']['Locations'][0]['Object']);
        self::assertSame(self::STANTON1_TAG_UUID, $locationsByProvider['HPP_Stanton1']['Locations'][0]['Tag']);
        self::assertSame('tag', $locationsByProvider['HPP_Stanton1']['Locations'][0]['MatchStrategy']);
        self::assertCount(2, $locationsByProvider['HPP_Stanton1']['Areas']);
        self::assertSame('Deserts', $locationsByProvider['HPP_Stanton1']['Areas'][0]['Name']);
        self::assertSame(1, $locationsByProvider['HPP_Stanton1']['Areas'][0]['GlobalModifier']);
        self::assertSame('Savanna', $locationsByProvider['HPP_Stanton1']['Areas'][1]['Name']);
        self::assertSame(2.5, $locationsByProvider['HPP_Stanton1']['Areas'][1]['GlobalModifier']);
        self::assertCount(1, $locationsByProvider['HPP_Stanton1']['Groups']);
        self::assertCount(3, $locationsByProvider['HPP_Stanton1']['Groups'][0]['Deposits']);

        $deposit0 = $locationsByProvider['HPP_Stanton1']['Groups'][0]['Deposits'][0];
        self::assertSame(self::ENTITY_ONE_UUID, $deposit0['ResourceUUID']);
        self::assertEquals(0.5, $deposit0['RelativeProbability']);
        self::assertArrayNotHasKey('HarvestableSetup', $deposit0);
        self::assertArrayNotHasKey('QualityOverrides', $deposit0);
        self::assertArrayNotHasKey('ResourceQualities', $deposit0);

        $deposit1 = $locationsByProvider['HPP_Stanton1']['Groups'][0]['Deposits'][1];
        self::assertSame(self::ENTITY_TWO_UUID, $deposit1['ResourceUUID']);
        self::assertEquals(1 / 6, $deposit1['RelativeProbability']);
        self::assertArrayNotHasKey('HarvestableSetup', $deposit1);
        self::assertArrayNotHasKey('QualityOverrides', $deposit1);
        self::assertArrayNotHasKey('ResourceQualities', $deposit1);

        $deposit2 = $locationsByProvider['HPP_Stanton1']['Groups'][0]['Deposits'][2];
        self::assertSame(self::ENTITY_THREE_UUID, $deposit2['ResourceUUID']);
        self::assertEquals(1 / 3, $deposit2['RelativeProbability']);
        self::assertArrayNotHasKey('HarvestableSetup', $deposit2);
        self::assertArrayNotHasKey('QualityOverrides', $deposit2);
        self::assertArrayNotHasKey('ResourceQualities', $deposit2);

        self::assertFileDoesNotExist($this->tempDir.'/resources/quality-distributions.json');
        self::assertFileDoesNotExist($this->tempDir.'/resources/quality-location-overrides.json');
    }
}
