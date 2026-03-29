<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprintRecord;
use Octfx\ScDataDumper\Formats\ScUnpacked\Blueprint;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class BlueprintTest extends ScDataTestCase
{
    private const AMMO_BLUEPRINT_UUID = 'd1da140e-b7ee-46ba-b76a-f5dd33c0348c';

    private const WEAPON_BLUEPRINT_UUID = '40cb632c-44ae-4ec7-acdb-7157da27cbfb';

    private const ARMOUR_BLUEPRINT_UUID = '5b8ad061-dbd5-4d2f-b9f2-d0fc7c9ea05f';

    private const AMMO_OUTPUT_UUID = '8177489f-ed83-44ac-afd4-2b32a80fa0a6';

    private const WEAPON_OUTPUT_UUID = '1f3400e1-0aa3-48bf-8595-38f4f4218df9';

    private const ARMOUR_OUTPUT_UUID = '7cc9fffb-6ba0-4f49-9d42-8ac8f8c2f713';

    private const ARMOUR_INPUT_UUID = '29dd6d59-9be2-434d-bac4-f463848f71c0';

    private const HEPHAESTANITE_UUID = '61189578-ed7a-4491-9774-37ae2f82b8b0';

    private const GOLD_UUID = '21825507-7923-4683-9bf3-9cfe316940e3';

    private const WEAPON_DAMAGE_PROPERTY_UUID = 'cfc129ce-488a-46f2-92f7-9272cd0cfdfb';

    private const WEAPON_FIRERATE_PROPERTY_UUID = '551b651c-8a34-438f-9d19-93fdffe56246';

    private const WEAPON_AND_AMMO_CATEGORY_UUID = 'f9ccf95d-ad0e-4c33-97e0-e56c847a7e37';

    private const ARMOUR_CATEGORY_UUID = 'a46c86b2-2f2c-40a7-8bd2-0401d1db4b37';

    private const REWARD_POOL_UUID = 'e9947de1-6160-4d62-a319-2f4693140c88';

    private const REWARD_POOL_KEY = 'BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB';

    /**
     * @var array<string, string>
     */
    private array $blueprintPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $ammoOutputPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/fps_weapons/ammo/lbco_sniper_energy_01_mag.xml',
            <<<'XML'
            <EntityClassDefinition.lbco_sniper_energy_01_mag __type="EntityClassDefinition" __ref="8177489f-ed83-44ac-afd4-2b32a80fa0a6" __path="libs/foundry/records/entities/scitem/fps_weapons/ammo/lbco_sniper_energy_01_mag.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAttachment" SubType="Magazine" Grade="1">
                    <Localization>
                      <English Name="Atzkav Sniper Rifle Battery (8 cap)" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.lbco_sniper_energy_01_mag>
            XML
        );

        $weaponOutputPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/weapons/behr_pistol_ballistic_01.xml',
            <<<'XML'
            <EntityClassDefinition.behr_pistol_ballistic_01 __type="EntityClassDefinition" __ref="1f3400e1-0aa3-48bf-8595-38f4f4218df9" __path="libs/foundry/records/entities/scitem/weapons/behr_pistol_ballistic_01.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponPersonal" SubType="Small" Grade="1">
                    <Localization>
                      <English Name="S38 Pistol" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.behr_pistol_ballistic_01>
            XML
        );

        $armourOutputPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/armour/rsi_utility_heavy_backpack_02_01_01.xml',
            <<<'XML'
            <EntityClassDefinition.rsi_utility_heavy_backpack_02_01_01 __type="EntityClassDefinition" __ref="7cc9fffb-6ba0-4f49-9d42-8ac8f8c2f713" __path="libs/foundry/records/entities/scitem/armour/rsi_utility_heavy_backpack_02_01_01.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Char_Armor_Backpack" SubType="Heavy" Grade="1">
                    <Localization>
                      <English Name="MacFlex Rucksack" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.rsi_utility_heavy_backpack_02_01_01>
            XML
        );

        $armourInputPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/crafting_materials/hadanite.xml',
            <<<'XML'
            <EntityClassDefinition.hadanite __type="EntityClassDefinition" __ref="29dd6d59-9be2-434d-bac4-f463848f71c0" __path="libs/foundry/records/entities/scitem/crafting_materials/hadanite.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="CraftingMaterial" SubType="Gem" Grade="1">
                    <Localization>
                      <English Name="Hadanite" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.hadanite>
            XML
        );

        $this->writeFile(
            'Data/Game2.xml',
            <<<'XML'
            <GameData>
              <ResourceType.Hephaestanite displayName="Hephaestanite" __type="ResourceType" __ref="61189578-ed7a-4491-9774-37ae2f82b8b0" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
              <ResourceType.Gold displayName="Gold" __type="ResourceType" __ref="21825507-7923-4683-9bf3-9cfe316940e3" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
              <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
              <CraftingGameplayPropertyDef.GPP_Weapon_FireRate propertyName="Weapon Fire Rate" unitFormat="RPM" __type="CraftingGameplayPropertyDef" __ref="551b651c-8a34-438f-9d19-93fdffe56246" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_firerate.xml" />
            </GameData>
            XML
        );

        $this->writeFile(
            'Data/Localization/english/global.ini',
            <<<'TEXT'
            LOC_EMPTY=
            TEXT
        );

        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/globalparams/craftingglobalparams.xml',
            <<<'XML'
            <CraftingGlobalParams.CraftingGlobalParams __type="CraftingGlobalParams" __ref="f99cff9b-c0b5-4d03-83f7-c7209d92b51d" __path="libs/foundry/records/crafting/globalparams/craftingglobalparams.xml">
              <defaultBlueprintSelection>
                <DefaultBlueprintSelection_Whitelist>
                  <blueprintRecords>
                    <Reference value="5b8ad061-dbd5-4d2f-b9f2-d0fc7c9ea05f" />
                  </blueprintRecords>
                </DefaultBlueprintSelection_Whitelist>
              </defaultBlueprintSelection>
            </CraftingGlobalParams.CraftingGlobalParams>
            XML
        );

        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprintrewards/blueprintmissionpools/bp_missionreward_headhunters_mercenaryfps_eliminateall_regionab.xml',
            <<<'XML'
            <BlueprintPoolRecord.BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB __type="BlueprintPoolRecord" __ref="e9947de1-6160-4d62-a319-2f4693140c88" __path="libs/foundry/records/crafting/blueprintrewards/blueprintmissionpools/bp_missionreward_headhunters_mercenaryfps_eliminateall_regionab.xml">
              <blueprintRewards>
                <BlueprintReward weight="1" blueprintRecord="d1da140e-b7ee-46ba-b76a-f5dd33c0348c" />
              </blueprintRewards>
            </BlueprintPoolRecord.BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB>
            XML
        );

        $this->blueprintPaths['ammo'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/ammo/electron/bp_craft_lbco_sniper_energy_01_mag.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_lbco_sniper_energy_01_mag __type="CraftingBlueprintRecord" __ref="d1da140e-b7ee-46ba-b76a-f5dd33c0348c" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/ammo/electron/bp_craft_lbco_sniper_energy_01_mag.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Atzkav Sniper Rifle Battery">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="8177489f-ed83-44ac-afd4-2b32a80fa0a6" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned days="0" hours="0" minutes="0" seconds="10" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="MAGAZINE" displayName="Magazine" />
                                      <options>
                                        <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="0">
                                          <context>
                                            <CraftingCostContext_QuantityMultiplier quantityMultiplier="1.15" />
                                          </context>
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.03" />
                                          </quantity>
                                        </CraftingCost_Resource>
                                      </options>
                                    </CraftingCost_Select>
                                  </options>
                                </CraftingCost_Select>
                              </mandatoryCost>
                            </CraftingRecipeCosts>
                          </costs>
                        </CraftingRecipe>
                      </recipe>
                    </CraftingBlueprintTier>
                  </tiers>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_lbco_sniper_energy_01_mag>
            XML
        );

        $this->blueprintPaths['weapon'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/weapons/pistol/bp_craft_behr_pistol_ballistic_01.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_behr_pistol_ballistic_01 __type="CraftingBlueprintRecord" __ref="40cb632c-44ae-4ec7-acdb-7157da27cbfb" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/weapons/pistol/bp_craft_behr_pistol_ballistic_01.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="S38 Pistol">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="1f3400e1-0aa3-48bf-8595-38f4f4218df9" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned days="0" hours="0" minutes="1" seconds="30" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="2">
                                      <nameInfo debugName="ASPECTS" displayName="Aspects" />
                                      <options>
                                        <CraftingCost_Select count="1">
                                          <nameInfo debugName="BARREL" displayName="Barrel" />
                                          <context>
                                            <CraftingCostContext_ResultGameplayPropertyModifiers>
                                              <gameplayPropertyModifiers>
                                                <CraftingGameplayPropertyModifiers_List>
                                                  <gameplayPropertyModifiers>
                                                    <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
                                                      <valueRanges>
                                                        <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="1000" modifierAtStart="0.925" modifierAtEnd="1.075" />
                                                      </valueRanges>
                                                    </CraftingGameplayPropertyModifierCommon>
                                                    <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="551b651c-8a34-438f-9d19-93fdffe56246">
                                                      <valueRanges>
                                                        <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="1000" modifierAtStart="0.88" modifierAtEnd="1.12" />
                                                      </valueRanges>
                                                    </CraftingGameplayPropertyModifierCommon>
                                                  </gameplayPropertyModifiers>
                                                </CraftingGameplayPropertyModifiers_List>
                                              </gameplayPropertyModifiers>
                                            </CraftingCostContext_ResultGameplayPropertyModifiers>
                                          </context>
                                          <options>
                                            <CraftingCost_Resource resource="21825507-7923-4683-9bf3-9cfe316940e3" minQuality="0">
                                              <quantity>
                                                <SStandardCargoUnit standardCargoUnits="0.01" />
                                              </quantity>
                                            </CraftingCost_Resource>
                                          </options>
                                        </CraftingCost_Select>
                                        <CraftingCost_Select count="1">
                                          <nameInfo debugName="FRAME" displayName="Frame" />
                                          <options>
                                            <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="0">
                                              <quantity>
                                                <SStandardCargoUnit standardCargoUnits="0.02" />
                                              </quantity>
                                            </CraftingCost_Resource>
                                          </options>
                                        </CraftingCost_Select>
                                      </options>
                                    </CraftingCost_Select>
                                  </options>
                                </CraftingCost_Select>
                              </mandatoryCost>
                            </CraftingRecipeCosts>
                          </costs>
                        </CraftingRecipe>
                      </recipe>
                    </CraftingBlueprintTier>
                  </tiers>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_behr_pistol_ballistic_01>
            XML
        );

        $this->blueprintPaths['armour'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/armour/radiation/heavy/bp_craft_rsi_utility_heavy_backpack_02_01_01.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_rsi_utility_heavy_backpack_02_01_01 __type="CraftingBlueprintRecord" __ref="5b8ad061-dbd5-4d2f-b9f2-d0fc7c9ea05f" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/armour/radiation/heavy/bp_craft_rsi_utility_heavy_backpack_02_01_01.xml">
              <blueprint>
                <CraftingBlueprint category="a46c86b2-2f2c-40a7-8bd2-0401d1db4b37" blueprintName="MacFlex Rucksack">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="7cc9fffb-6ba0-4f49-9d42-8ac8f8c2f713" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned days="0" hours="0" minutes="6" seconds="30" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="FILTER" displayName="Filter" />
                                      <options>
                                        <CraftingCost_Item entityClass="29dd6d59-9be2-434d-bac4-f463848f71c0" quantity="2" minQuality="500" />
                                      </options>
                                    </CraftingCost_Select>
                                  </options>
                                </CraftingCost_Select>
                              </mandatoryCost>
                            </CraftingRecipeCosts>
                          </costs>
                        </CraftingRecipe>
                      </recipe>
                    </CraftingBlueprintTier>
                  </tiers>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_rsi_utility_heavy_backpack_02_01_01>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'lbco_sniper_energy_01_mag' => $ammoOutputPath,
                    'behr_pistol_ballistic_01' => $weaponOutputPath,
                    'rsi_utility_heavy_backpack_02_01_01' => $armourOutputPath,
                    'hadanite' => $armourInputPath,
                ],
                'CraftingBlueprintRecord' => [
                    'BP_CRAFT_lbco_sniper_energy_01_mag' => $this->blueprintPaths['ammo'],
                    'BP_CRAFT_behr_pistol_ballistic_01' => $this->blueprintPaths['weapon'],
                    'BP_CRAFT_rsi_utility_heavy_backpack_02_01_01' => $this->blueprintPaths['armour'],
                ],
            ],
            uuidToClassMap: [
                self::AMMO_OUTPUT_UUID => 'lbco_sniper_energy_01_mag',
                self::WEAPON_OUTPUT_UUID => 'behr_pistol_ballistic_01',
                self::ARMOUR_OUTPUT_UUID => 'rsi_utility_heavy_backpack_02_01_01',
                self::ARMOUR_INPUT_UUID => 'hadanite',
                self::AMMO_BLUEPRINT_UUID => 'BP_CRAFT_lbco_sniper_energy_01_mag',
                self::WEAPON_BLUEPRINT_UUID => 'BP_CRAFT_behr_pistol_ballistic_01',
                self::ARMOUR_BLUEPRINT_UUID => 'BP_CRAFT_rsi_utility_heavy_backpack_02_01_01',
            ],
            classToUuidMap: [
                'lbco_sniper_energy_01_mag' => self::AMMO_OUTPUT_UUID,
                'behr_pistol_ballistic_01' => self::WEAPON_OUTPUT_UUID,
                'rsi_utility_heavy_backpack_02_01_01' => self::ARMOUR_OUTPUT_UUID,
                'hadanite' => self::ARMOUR_INPUT_UUID,
                'BP_CRAFT_lbco_sniper_energy_01_mag' => self::AMMO_BLUEPRINT_UUID,
                'BP_CRAFT_behr_pistol_ballistic_01' => self::WEAPON_BLUEPRINT_UUID,
                'BP_CRAFT_rsi_utility_heavy_backpack_02_01_01' => self::ARMOUR_BLUEPRINT_UUID,
            ],
            uuidToPathMap: [
                self::AMMO_OUTPUT_UUID => $ammoOutputPath,
                self::WEAPON_OUTPUT_UUID => $weaponOutputPath,
                self::ARMOUR_OUTPUT_UUID => $armourOutputPath,
                self::ARMOUR_INPUT_UUID => $armourInputPath,
                self::AMMO_BLUEPRINT_UUID => $this->blueprintPaths['ammo'],
                self::WEAPON_BLUEPRINT_UUID => $this->blueprintPaths['weapon'],
                self::ARMOUR_BLUEPRINT_UUID => $this->blueprintPaths['armour'],
            ],
        );
        $this->writeResourceTypeCache([
            self::HEPHAESTANITE_UUID => '<ResourceType.Hephaestanite displayName="Hephaestanite" __type="ResourceType" __ref="61189578-ed7a-4491-9774-37ae2f82b8b0" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />',
            self::GOLD_UUID => '<ResourceType.Gold displayName="Gold" __type="ResourceType" __ref="21825507-7923-4683-9bf3-9cfe316940e3" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />',
        ]);
        $this->writeCraftingGameplayPropertyCache([
            self::WEAPON_DAMAGE_PROPERTY_UUID => '<CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />',
            self::WEAPON_FIRERATE_PROPERTY_UUID => '<CraftingGameplayPropertyDef.GPP_Weapon_FireRate propertyName="Weapon Fire Rate" unitFormat="RPM" __type="CraftingGameplayPropertyDef" __ref="551b651c-8a34-438f-9d19-93fdffe56246" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_firerate.xml" />',
        ]);

        $this->initializeBlueprintFormattingServices();
    }

    public function test_to_array_formats_resource_only_ammo_blueprint_and_applies_quantity_multiplier(): void
    {
        $blueprint = $this->loadBlueprint('ammo');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertSame([
            'uuid' => self::AMMO_BLUEPRINT_UUID,
            'key' => 'BP_CRAFT_lbco_sniper_energy_01_mag',
            'kind' => 'creation',
            'category_uuid' => self::WEAPON_AND_AMMO_CATEGORY_UUID,
            'output' => [
                'uuid' => self::AMMO_OUTPUT_UUID,
                'class' => 'lbco_sniper_energy_01_mag',
                'type' => 'WeaponAttachment',
                'subtype' => 'Magazine',
                'grade' => '1',
                'name' => 'Atzkav Sniper Rifle Battery (8 cap)',
            ],
            'craft_time_seconds' => 10,
            'availability' => [
                'default' => false,
                'reward_pools' => [
                    [
                        'uuid' => self::REWARD_POOL_UUID,
                        'key' => self::REWARD_POOL_KEY,
                    ],
                ],
            ],
            'slots' => [
                [
                    'slot_key' => 'MAGAZINE',
                    'slot_name' => 'Magazine',
                    'inputs' => [
                        [
                            'kind' => 'resource',
                            'uuid' => self::HEPHAESTANITE_UUID,
                            'name' => 'Hephaestanite',
                            'quantity_scu' => 0.0345,
                            'min_quality' => 0,
                        ],
                    ],
                ],
            ],
        ], $formatted);
    }

    public function test_to_array_formats_quality_sensitive_weapon_blueprint_stat_modifiers(): void
    {
        $blueprint = $this->loadBlueprint('weapon');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertSame([
            'uuid' => self::WEAPON_BLUEPRINT_UUID,
            'key' => 'BP_CRAFT_behr_pistol_ballistic_01',
            'kind' => 'creation',
            'category_uuid' => self::WEAPON_AND_AMMO_CATEGORY_UUID,
            'output' => [
                'uuid' => self::WEAPON_OUTPUT_UUID,
                'class' => 'behr_pistol_ballistic_01',
                'type' => 'WeaponPersonal',
                'subtype' => 'Small',
                'grade' => '1',
                'name' => 'S38 Pistol',
            ],
            'craft_time_seconds' => 90,
            'availability' => [
                'default' => false,
                'reward_pools' => [],
            ],
            'quality_sensitive' => true,
            'slots' => [
                [
                    'slot_key' => 'BARREL',
                    'slot_name' => 'Barrel',
                    'inputs' => [
                        [
                            'kind' => 'resource',
                            'uuid' => self::GOLD_UUID,
                            'name' => 'Gold',
                            'quantity_scu' => 0.01,
                            'min_quality' => 0,
                        ],
                    ],
                    'stat_modifiers' => [
                        [
                            'property_uuid' => self::WEAPON_DAMAGE_PROPERTY_UUID,
                            'property_key' => 'weapon_damage',
                            'quality_range' => [
                                'min' => 0,
                                'max' => 1000,
                            ],
                            'modifier_range' => [
                                'at_min_quality' => 0.925,
                                'at_max_quality' => 1.075,
                            ],
                        ],
                        [
                            'property_uuid' => self::WEAPON_FIRERATE_PROPERTY_UUID,
                            'property_key' => 'weapon_firerate',
                            'quality_range' => [
                                'min' => 0,
                                'max' => 1000,
                            ],
                            'modifier_range' => [
                                'at_min_quality' => 0.88,
                                'at_max_quality' => 1.12,
                            ],
                        ],
                    ],
                ],
                [
                    'slot_key' => 'FRAME',
                    'slot_name' => 'Frame',
                    'inputs' => [
                        [
                            'kind' => 'resource',
                            'uuid' => self::HEPHAESTANITE_UUID,
                            'name' => 'Hephaestanite',
                            'quantity_scu' => 0.02,
                            'min_quality' => 0,
                        ],
                    ],
                ],
            ],
        ], $formatted);
    }

    public function test_to_array_formats_armour_blueprint_item_inputs_and_minimum_quality(): void
    {
        $blueprint = $this->loadBlueprint('armour');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertSame([
            'uuid' => self::ARMOUR_BLUEPRINT_UUID,
            'key' => 'BP_CRAFT_rsi_utility_heavy_backpack_02_01_01',
            'kind' => 'creation',
            'category_uuid' => self::ARMOUR_CATEGORY_UUID,
            'output' => [
                'uuid' => self::ARMOUR_OUTPUT_UUID,
                'class' => 'rsi_utility_heavy_backpack_02_01_01',
                'type' => 'Char_Armor_Backpack',
                'subtype' => 'Heavy',
                'grade' => '1',
                'name' => 'MacFlex Rucksack',
            ],
            'craft_time_seconds' => 390,
            'availability' => [
                'default' => true,
                'reward_pools' => [],
            ],
            'slots' => [
                [
                    'slot_key' => 'FILTER',
                    'slot_name' => 'Filter',
                    'inputs' => [
                        [
                            'kind' => 'item',
                            'uuid' => self::ARMOUR_INPUT_UUID,
                            'name' => 'Hadanite',
                            'quantity' => 2,
                            'min_quality' => 500,
                        ],
                    ],
                ],
            ],
        ], $formatted);
    }

    private function loadBlueprint(string $fixture): CraftingBlueprintRecord
    {
        $document = new CraftingBlueprintRecord;
        $document->load($this->blueprintPaths[$fixture]);

        return $document;
    }

    private function initializeBlueprintFormattingServices(): void
    {
        (new ServiceFactory($this->tempDir))->initialize();
    }
}
