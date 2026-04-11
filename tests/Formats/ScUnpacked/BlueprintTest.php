<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingBlueprintRecord;
use Octfx\ScDataDumper\Formats\ScUnpacked\Blueprint;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class BlueprintTest extends ScDataTestCase
{
    private const AMMO_BLUEPRINT_UUID = 'd1da140e-b7ee-46ba-b76a-f5dd33c0348c';

    private const WEAPON_BLUEPRINT_UUID = '40cb632c-44ae-4ec7-acdb-7157da27cbfb';

    private const ARMOUR_BLUEPRINT_UUID = '5b8ad061-dbd5-4d2f-b9f2-d0fc7c9ea05f';

    private const MULTI_RESOURCE_BLUEPRINT_UUID = '16fd8428-889d-4d83-8db7-42a8113f0bcb';

    private const MULTI_TIER_BLUEPRINT_UUID = '41f8c6b5-d7a2-42b0-aefa-8a4916d4fc41';

    private const NESTED_SELECT_BLUEPRINT_UUID = 'd6c03c98-2200-4fc2-ab9d-a33d240c56d3';

    private const DISMANTLE_BLUEPRINT_UUID = 'bb000000-0000-0000-0000-000000000001';

    private const DISMANTLE_BLUEPRINT_CLASS = 'GlobalGenericDismantle';

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
                                        <CraftingCost_Item entityClass="29dd6d59-9be2-434d-bac4-f463848f71c0" quantity="2" minQuality="500">
                                          <context>
                                            <CraftingCostContext_ResultGameplayPropertyModifiers>
                                              <gameplayPropertyModifiers>
                                                <CraftingGameplayPropertyModifiers_List>
                                                  <gameplayPropertyModifiers>
                                                    <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
                                                      <valueRanges>
                                                        <CraftingGameplayPropertyModifierValueRange_Linear startQuality="500" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.03" />
                                                      </valueRanges>
                                                    </CraftingGameplayPropertyModifierCommon>
                                                  </gameplayPropertyModifiers>
                                                </CraftingGameplayPropertyModifiers_List>
                                              </gameplayPropertyModifiers>
                                            </CraftingCostContext_ResultGameplayPropertyModifiers>
                                          </context>
                                        </CraftingCost_Item>
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

        $this->blueprintPaths['multi_resource'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/armour/combat/medium/bp_craft_test_multi_resource_backpack.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_multi_resource_backpack __type="CraftingBlueprintRecord" __ref="16fd8428-889d-4d83-8db7-42a8113f0bcb" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/armour/combat/medium/bp_craft_test_multi_resource_backpack.xml">
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
                                <TimeValue_Partitioned days="0" hours="0" minutes="2" seconds="15" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="2">
                                      <nameInfo debugName="ARMOURED CARAPACE" displayName="Armoured Carapace" />
                                      <options>
                                        <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="0">
                                          <context>
                                            <CraftingCostContext_ResultGameplayPropertyModifiers>
                                              <gameplayPropertyModifiers>
                                                <CraftingGameplayPropertyModifiers_List>
                                                  <gameplayPropertyModifiers>
                                                    <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
                                                      <valueRanges>
                                                        <CraftingGameplayPropertyModifierValueRange_Linear startQuality="500" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.075" />
                                                      </valueRanges>
                                                    </CraftingGameplayPropertyModifierCommon>
                                                  </gameplayPropertyModifiers>
                                                </CraftingGameplayPropertyModifiers_List>
                                              </gameplayPropertyModifiers>
                                            </CraftingCostContext_ResultGameplayPropertyModifiers>
                                          </context>
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.04" />
                                          </quantity>
                                        </CraftingCost_Resource>
                                        <CraftingCost_Resource resource="21825507-7923-4683-9bf3-9cfe316940e3" minQuality="500">
                                          <context>
                                            <CraftingCostContext_ResultGameplayPropertyModifiers>
                                              <gameplayPropertyModifiers>
                                                <CraftingGameplayPropertyModifiers_List>
                                                  <gameplayPropertyModifiers>
                                                    <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
                                                      <valueRanges>
                                                        <CraftingGameplayPropertyModifierValueRange_Linear startQuality="500" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.05" />
                                                      </valueRanges>
                                                    </CraftingGameplayPropertyModifierCommon>
                                                  </gameplayPropertyModifiers>
                                                </CraftingGameplayPropertyModifiers_List>
                                              </gameplayPropertyModifiers>
                                            </CraftingCostContext_ResultGameplayPropertyModifiers>
                                          </context>
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.04" />
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
            </CraftingBlueprintRecord.BP_CRAFT_test_multi_resource_backpack>
            XML
        );

        $this->blueprintPaths['multi_tier'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/weapons/pistol/bp_craft_test_multi_tier_weapon.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_multi_tier_weapon __type="CraftingBlueprintRecord" __ref="41f8c6b5-d7a2-42b0-aefa-8a4916d4fc41" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/weapons/pistol/bp_craft_test_multi_tier_weapon.xml">
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
                                <TimeValue_Partitioned days="0" hours="0" minutes="0" seconds="45" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="GRIP" displayName="Grip" />
                                      <options>
                                        <CraftingCost_Resource resource="21825507-7923-4683-9bf3-9cfe316940e3" minQuality="0">
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.01" />
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
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned days="0" hours="0" minutes="2" seconds="0" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="GRIP" displayName="Grip" />
                                      <options>
                                        <CraftingCost_Item entityClass="29dd6d59-9be2-434d-bac4-f463848f71c0" quantity="3" minQuality="600" />
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
            </CraftingBlueprintRecord.BP_CRAFT_test_multi_tier_weapon>
            XML
        );

        $this->blueprintPaths['nested_select'] = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/weapons/rifle/bp_craft_test_nested_select.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_nested_select __type="CraftingBlueprintRecord" __ref="d6c03c98-2200-4fc2-ab9d-a33d240c56d3" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/weapons/rifle/bp_craft_test_nested_select.xml">
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
                                <TimeValue_Partitioned days="0" hours="0" minutes="1" seconds="15" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="FRAME" displayName="Frame" />
                                      <context>
                                        <CraftingCostContext_ResultGameplayPropertyModifiers>
                                          <gameplayPropertyModifiers>
                                            <CraftingGameplayPropertyModifiers_List>
                                              <gameplayPropertyModifiers>
                                                <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
                                                  <valueRanges>
                                                    <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="1000" modifierAtStart="0.95" modifierAtEnd="1.05" />
                                                  </valueRanges>
                                                </CraftingGameplayPropertyModifierCommon>
                                              </gameplayPropertyModifiers>
                                            </CraftingGameplayPropertyModifiers_List>
                                          </gameplayPropertyModifiers>
                                        </CraftingCostContext_ResultGameplayPropertyModifiers>
                                      </context>
                                      <options>
                                        <CraftingCost_Select count="1">
                                          <nameInfo debugName="INNER GROUP" displayName="Inner Group" />
                                          <options>
                                            <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="250">
                                              <context>
                                                <CraftingCostContext_ResultGameplayPropertyModifiers>
                                                  <gameplayPropertyModifiers>
                                                    <CraftingGameplayPropertyModifiers_List>
                                                      <gameplayPropertyModifiers>
                                                        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="551b651c-8a34-438f-9d19-93fdffe56246">
                                                          <valueRanges>
                                                            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="250" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.2" />
                                                          </valueRanges>
                                                        </CraftingGameplayPropertyModifierCommon>
                                                      </gameplayPropertyModifiers>
                                                    </CraftingGameplayPropertyModifiers_List>
                                                  </gameplayPropertyModifiers>
                                                </CraftingCostContext_ResultGameplayPropertyModifiers>
                                              </context>
                                              <quantity>
                                                <SStandardCargoUnit standardCargoUnits="0.02" />
                                              </quantity>
                                            </CraftingCost_Resource>
                                            <CraftingCost_Custom customRef="custom-node" count="2" />
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
            </CraftingBlueprintRecord.BP_CRAFT_test_nested_select>
            XML
        );

        $dismantleBlueprintPath = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/dismantle/globalgenericdismantle.xml',
            <<<'XML'
            <CraftingBlueprintRecord.GlobalGenericDismantle __type="CraftingBlueprintRecord" __ref="bb000000-0000-0000-0000-000000000001" __path="libs/foundry/records/crafting/blueprints/dismantle/globalgenericdismantle.xml">
              <blueprint>
                <GenericCraftingBlueprint>
                  <processSpecificData>
                    <GenericCraftingProcess_Dismantle efficiency="0.5">
                      <dismantleTime>
                        <TimeValue_Partitioned days="0" hours="0" minutes="0" seconds="15" />
                      </dismantleTime>
                    </GenericCraftingProcess_Dismantle>
                  </processSpecificData>
                </GenericCraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.GlobalGenericDismantle>
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
                    'BP_CRAFT_test_multi_resource_backpack' => $this->blueprintPaths['multi_resource'],
                    'BP_CRAFT_test_multi_tier_weapon' => $this->blueprintPaths['multi_tier'],
                    'BP_CRAFT_test_nested_select' => $this->blueprintPaths['nested_select'],
                    'GlobalGenericDismantle' => $dismantleBlueprintPath,
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
                self::MULTI_RESOURCE_BLUEPRINT_UUID => 'BP_CRAFT_test_multi_resource_backpack',
                self::MULTI_TIER_BLUEPRINT_UUID => 'BP_CRAFT_test_multi_tier_weapon',
                self::NESTED_SELECT_BLUEPRINT_UUID => 'BP_CRAFT_test_nested_select',
                self::DISMANTLE_BLUEPRINT_UUID => self::DISMANTLE_BLUEPRINT_CLASS,
            ],
            classToUuidMap: [
                'lbco_sniper_energy_01_mag' => self::AMMO_OUTPUT_UUID,
                'behr_pistol_ballistic_01' => self::WEAPON_OUTPUT_UUID,
                'rsi_utility_heavy_backpack_02_01_01' => self::ARMOUR_OUTPUT_UUID,
                'hadanite' => self::ARMOUR_INPUT_UUID,
                'BP_CRAFT_lbco_sniper_energy_01_mag' => self::AMMO_BLUEPRINT_UUID,
                'BP_CRAFT_behr_pistol_ballistic_01' => self::WEAPON_BLUEPRINT_UUID,
                'BP_CRAFT_rsi_utility_heavy_backpack_02_01_01' => self::ARMOUR_BLUEPRINT_UUID,
                'BP_CRAFT_test_multi_resource_backpack' => self::MULTI_RESOURCE_BLUEPRINT_UUID,
                'BP_CRAFT_test_multi_tier_weapon' => self::MULTI_TIER_BLUEPRINT_UUID,
                'BP_CRAFT_test_nested_select' => self::NESTED_SELECT_BLUEPRINT_UUID,
                self::DISMANTLE_BLUEPRINT_CLASS => self::DISMANTLE_BLUEPRINT_UUID,
            ],
            uuidToPathMap: [
                self::AMMO_OUTPUT_UUID => $ammoOutputPath,
                self::WEAPON_OUTPUT_UUID => $weaponOutputPath,
                self::ARMOUR_OUTPUT_UUID => $armourOutputPath,
                self::ARMOUR_INPUT_UUID => $armourInputPath,
                self::AMMO_BLUEPRINT_UUID => $this->blueprintPaths['ammo'],
                self::WEAPON_BLUEPRINT_UUID => $this->blueprintPaths['weapon'],
                self::ARMOUR_BLUEPRINT_UUID => $this->blueprintPaths['armour'],
                self::MULTI_RESOURCE_BLUEPRINT_UUID => $this->blueprintPaths['multi_resource'],
                self::MULTI_TIER_BLUEPRINT_UUID => $this->blueprintPaths['multi_tier'],
                self::NESTED_SELECT_BLUEPRINT_UUID => $this->blueprintPaths['nested_select'],
                self::DISMANTLE_BLUEPRINT_UUID => $dismantleBlueprintPath,
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

    public function test_to_array_formats_v2_root_group_and_resource_leaf_with_plain_string_names(): void
    {
        $blueprint = $this->loadBlueprint('ammo');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertSame([
            'UUID' => self::AMMO_BLUEPRINT_UUID,
            'Key' => 'BP_CRAFT_lbco_sniper_energy_01_mag',
            'Kind' => 'creation',
            'CategoryUuid' => self::WEAPON_AND_AMMO_CATEGORY_UUID,
            'Output' => [
                'UUID' => self::AMMO_OUTPUT_UUID,
                'Class' => 'lbco_sniper_energy_01_mag',
                'Type' => 'WeaponAttachment',
                'Subtype' => 'Magazine',
                'Grade' => '1',
                'Name' => 'Atzkav Sniper Rifle Battery (8 cap)',
            ],
            'Availability' => [
                'Default' => false,
                'RewardPools' => [
                    [
                        'UUID' => self::REWARD_POOL_UUID,
                        'Key' => self::REWARD_POOL_KEY,
                    ],
                ],
            ],
            'Tiers' => [
                [
                    'TierIndex' => 0,
                    'CraftTimeSeconds' => 10,
                    'Requirements' => [
                        'Kind' => 'root',
                        'Children' => [
                            [
                                'Kind' => 'group',
                                'Key' => 'MAGAZINE',
                                'Name' => 'Magazine',
                                'RequiredCount' => 1,
                                'Children' => [
                                    [
                                        'Kind' => 'resource',
                                        'UUID' => self::HEPHAESTANITE_UUID,
                                        'Name' => 'Hephaestanite',
                                        'QuantityScu' => 0.0345,
                                        'MinQuality' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'Dismantle' => [
                'TimeSeconds' => 15,
                'Efficiency' => 0.5,
                'Returns' => [
                    [
                        'Kind' => 'resource',
                        'UUID' => self::HEPHAESTANITE_UUID,
                        'Name' => 'Hephaestanite',
                        'QuantityScu' => 0.01725,
                    ],
                ],
            ],
        ], $formatted);

        self::assertArrayNotHasKey('schema_version', $formatted);
        self::assertArrayNotHasKey('node_type', $formatted['Tiers'][0]['Requirements']);
    }

    public function test_to_array_resolves_relations_when_reference_hydration_is_disabled(): void
    {
        $blueprint = $this->loadBlueprint('ammo');
        $blueprint->setReferenceHydrationEnabled(false);

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertSame(self::AMMO_OUTPUT_UUID, $formatted['Output']['UUID']);
        self::assertSame('Atzkav Sniper Rifle Battery (8 cap)', $formatted['Output']['Name']);
        self::assertSame(self::HEPHAESTANITE_UUID, $formatted['Tiers'][0]['Requirements']['Children'][0]['Children'][0]['UUID']);
        self::assertSame('Hephaestanite', $formatted['Tiers'][0]['Requirements']['Children'][0]['Children'][0]['Name']);
    }

    public function test_to_array_preserves_required_input_count_and_resource_input_modifiers(): void
    {
        $blueprint = $this->loadBlueprint('multi_resource');

        $formatted = (new Blueprint($blueprint))->toArray();
        $slot = $formatted['Tiers'][0]['Requirements']['Children'][0];
        $hephaestanite = $slot['Children'][0];
        $gold = $slot['Children'][1];

        self::assertSame('group', $slot['Kind']);
        self::assertSame('ARMOURED CARAPACE', $slot['Key']);
        self::assertSame('Armoured Carapace', $slot['Name']);
        self::assertSame(2, $slot['RequiredCount']);

        self::assertSame('resource', $hephaestanite['Kind']);
        self::assertSame(self::HEPHAESTANITE_UUID, $hephaestanite['UUID']);
        self::assertSame('Hephaestanite', $hephaestanite['Name']);
        self::assertSame(0.04, $hephaestanite['QuantityScu']);
        self::assertSame(0, $hephaestanite['MinQuality']);
        self::assertSame(self::WEAPON_DAMAGE_PROPERTY_UUID, $hephaestanite['Modifiers'][0]['UUID']);
        self::assertArrayNotHasKey('StatModifiers', $hephaestanite);

        self::assertSame('resource', $gold['Kind']);
        self::assertSame(self::GOLD_UUID, $gold['UUID']);
        self::assertSame('Gold', $gold['Name']);
        self::assertSame(0.04, $gold['QuantityScu']);
        self::assertSame(500, $gold['MinQuality']);
        self::assertSame(self::WEAPON_DAMAGE_PROPERTY_UUID, $gold['Modifiers'][0]['UUID']);
    }

    public function test_to_array_preserves_unnamed_top_level_select_requirements(): void
    {
        $path = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_test_unnamed_select.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_unnamed_select __type="CraftingBlueprintRecord" __ref="3a266cfb-3eff-472f-a15c-228eb2f782f3" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_test_unnamed_select.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Unnamed Select Blueprint">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="8177489f-ed83-44ac-afd4-2b32a80fa0a6" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <mandatoryCost>
                                <CraftingCost_Select count="2">
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
                                          </gameplayPropertyModifiers>
                                        </CraftingGameplayPropertyModifiers_List>
                                      </gameplayPropertyModifiers>
                                    </CraftingCostContext_ResultGameplayPropertyModifiers>
                                  </context>
                                  <options>
                                    <CraftingCost_Item entityClass="29dd6d59-9be2-434d-bac4-f463848f71c0" quantity="2" />
                                    <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="0">
                                      <quantity>
                                        <SStandardCargoUnit standardCargoUnits="0.03" />
                                      </quantity>
                                    </CraftingCost_Resource>
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
            </CraftingBlueprintRecord.BP_CRAFT_test_unnamed_select>
            XML
        );

        $document = new CraftingBlueprintRecord;
        $document->load($path);

        $formatted = (new Blueprint($document))->toArray();
        $requirements = $formatted['Tiers'][0]['Requirements'];
        $group = $requirements['Children'][0];

        self::assertCount(1, $requirements['Children']);
        self::assertSame('group', $group['Kind']);
        self::assertArrayNotHasKey('Key', $group);
        self::assertArrayNotHasKey('Name', $group);
        self::assertSame(2, $group['RequiredCount']);
        self::assertSame(self::WEAPON_DAMAGE_PROPERTY_UUID, $group['Modifiers'][0]['UUID']);
        self::assertSame(['item', 'resource'], array_column($group['Children'], 'Kind'));
    }

    public function test_to_array_preserves_anonymous_choose_one_select_groups(): void
    {
        $path = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_test_anonymous_choose_one_select.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_anonymous_choose_one_select __type="CraftingBlueprintRecord" __ref="5322818d-3ec4-4431-a471-abddf5f37f89" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_test_anonymous_choose_one_select.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Anonymous Choose One Blueprint">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="8177489f-ed83-44ac-afd4-2b32a80fa0a6" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="BARREL" displayName="Barrel" />
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
                              </mandatoryCost>
                            </CraftingRecipeCosts>
                          </costs>
                        </CraftingRecipe>
                      </recipe>
                    </CraftingBlueprintTier>
                  </tiers>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_test_anonymous_choose_one_select>
            XML
        );

        $document = new CraftingBlueprintRecord;
        $document->load($path);

        $formatted = (new Blueprint($document))->toArray();
        $requirements = $formatted['Tiers'][0]['Requirements'];
        $group = $requirements['Children'][0];

        self::assertCount(1, $requirements['Children']);
        self::assertSame('group', $group['Kind']);
        self::assertArrayNotHasKey('Key', $group);
        self::assertArrayNotHasKey('Name', $group);
        self::assertSame(1, $group['RequiredCount']);
        self::assertSame(['BARREL', 'FRAME'], array_column($group['Children'], 'Key'));
    }

    public function test_to_array_formats_v2_multiple_tiers_without_flattening_them(): void
    {
        $blueprint = $this->loadBlueprint('multi_tier');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertCount(2, $formatted['Tiers']);
        self::assertSame(45, $formatted['Tiers'][0]['CraftTimeSeconds']);
        self::assertSame(120, $formatted['Tiers'][1]['CraftTimeSeconds']);

        $resourceSlot = $formatted['Tiers'][0]['Requirements']['Children'][0];
        $resourceLeaf = $resourceSlot['Children'][0];
        self::assertSame('group', $resourceSlot['Kind']);
        self::assertSame('GRIP', $resourceSlot['Key']);
        self::assertSame('Grip', $resourceSlot['Name']);
        self::assertSame('resource', $resourceLeaf['Kind']);
        self::assertSame(self::GOLD_UUID, $resourceLeaf['UUID']);
        self::assertSame('Gold', $resourceLeaf['Name']);

        $itemSlot = $formatted['Tiers'][1]['Requirements']['Children'][0];
        $itemLeaf = $itemSlot['Children'][0];
        self::assertSame('group', $itemSlot['Kind']);
        self::assertSame('GRIP', $itemSlot['Key']);
        self::assertSame('Grip', $itemSlot['Name']);
        self::assertSame('item', $itemLeaf['Kind']);
        self::assertSame(self::ARMOUR_INPUT_UUID, $itemLeaf['UUID']);
        self::assertSame('Hadanite', $itemLeaf['Name']);
        self::assertSame(3, $itemLeaf['Quantity']);
        self::assertSame(600, $itemLeaf['MinQuality']);
    }

    public function test_to_array_formats_v2_preserves_nested_selects_and_unknown_nodes(): void
    {
        $blueprint = $this->loadBlueprint('nested_select');

        $formatted = (new Blueprint($blueprint))->toArray();

        $frameNode = $formatted['Tiers'][0]['Requirements']['Children'][0];
        self::assertSame('group', $frameNode['Kind']);
        self::assertSame('FRAME', $frameNode['Key']);
        self::assertSame('Frame', $frameNode['Name']);
        self::assertSame(self::WEAPON_DAMAGE_PROPERTY_UUID, $frameNode['Modifiers'][0]['UUID']);

        $innerGroup = $frameNode['Children'][0];
        self::assertSame('group', $innerGroup['Kind']);
        self::assertSame('INNER GROUP', $innerGroup['Key']);
        self::assertSame('Inner Group', $innerGroup['Name']);

        $resource = $innerGroup['Children'][0];
        self::assertSame('resource', $resource['Kind']);
        self::assertSame(self::HEPHAESTANITE_UUID, $resource['UUID']);
        self::assertSame('Hephaestanite', $resource['Name']);
        self::assertSame(self::WEAPON_FIRERATE_PROPERTY_UUID, $resource['Modifiers'][0]['UUID']);

        $unknown = $innerGroup['Children'][1];
        self::assertSame('unknown', $unknown['Kind']);
        self::assertSame('CraftingCost_Custom', $unknown['XmlNodeName']);
        self::assertSame('custom-node', $unknown['Attributes']['CustomRef']);
        self::assertSame(2, $unknown['Attributes']['Count']);
        self::assertArrayNotHasKey('SourceNodeName', $unknown);
    }

    public function test_to_array_preserves_non_trivial_aspects_wrapper_counts(): void
    {
        $path = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_test_aspects_wrapper.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_test_aspects_wrapper __type="CraftingBlueprintRecord" __ref="c0a1f42b-8df1-4e2a-b1e6-3d1cb9f0ef4c" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_test_aspects_wrapper.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Aspects Wrapper Blueprint">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="1f3400e1-0aa3-48bf-8595-38f4f4218df9" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="2">
                                      <nameInfo debugName="ASPECTS" displayName="Aspects" />
                                      <options>
                                        <CraftingCost_Select count="1">
                                          <nameInfo debugName="BARREL" displayName="Barrel" />
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
                                        <CraftingCost_Select count="1">
                                          <nameInfo debugName="SIGHT" displayName="Sight" />
                                          <options>
                                            <CraftingCost_Item entityClass="29dd6d59-9be2-434d-bac4-f463848f71c0" quantity="1" />
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
            </CraftingBlueprintRecord.BP_CRAFT_test_aspects_wrapper>
            XML
        );

        $document = new CraftingBlueprintRecord;
        $document->load($path);

        $formatted = (new Blueprint($document))->toArray();
        $groups = $formatted['Tiers'][0]['Requirements']['Children'];
        $aspects = $groups[0];

        self::assertCount(1, $groups);
        self::assertSame('group', $aspects['Kind']);
        self::assertSame('ASPECTS', $aspects['Key']);
        self::assertSame('Aspects', $aspects['Name']);
        self::assertSame(2, $aspects['RequiredCount']);
        self::assertSame(['BARREL', 'FRAME', 'SIGHT'], array_column($aspects['Children'], 'Key'));
    }

    public function test_to_array_flattens_synthetic_aspects_wrapper(): void
    {
        $blueprint = $this->loadBlueprint('weapon');

        $formatted = (new Blueprint($blueprint))->toArray();
        $groups = $formatted['Tiers'][0]['Requirements']['Children'];

        self::assertCount(2, $groups);
        self::assertSame(['BARREL', 'FRAME'], array_column($groups, 'Key'));
        self::assertNotContains('ASPECTS', array_column($groups, 'Key'));
        self::assertSame('Barrel', $groups[0]['Name']);
        self::assertSame('Frame', $groups[1]['Name']);
    }

    public function test_dismantle_flattens_groups_into_flat_returns(): void
    {
        $blueprint = $this->loadBlueprint('multi_resource');

        $formatted = (new Blueprint($blueprint))->toArray();

        $dismantle = $formatted['Dismantle'];
        self::assertSame(15, $dismantle['TimeSeconds']);
        self::assertSame(0.5, $dismantle['Efficiency']);

        $returns = $dismantle['Returns'];
        self::assertCount(2, $returns);

        self::assertSame('resource', $returns[0]['Kind']);
        self::assertSame(self::HEPHAESTANITE_UUID, $returns[0]['UUID']);
        self::assertSame('Hephaestanite', $returns[0]['Name']);
        self::assertSame(0.02, $returns[0]['QuantityScu']);

        self::assertSame('resource', $returns[1]['Kind']);
        self::assertSame(self::GOLD_UUID, $returns[1]['UUID']);
        self::assertSame('Gold', $returns[1]['Name']);
        self::assertSame(0.02, $returns[1]['QuantityScu']);
    }

    public function test_dismantle_floors_item_quantities_and_excludes_zero(): void
    {
        $blueprint = $this->loadBlueprint('armour');

        $formatted = (new Blueprint($blueprint))->toArray();

        $dismantle = $formatted['Dismantle'];
        $returns = $dismantle['Returns'];

        $itemReturns = array_filter($returns, static fn (array $r): bool => $r['Kind'] === 'item');
        self::assertCount(1, $itemReturns);

        $itemReturn = reset($itemReturns);
        self::assertSame(self::ARMOUR_INPUT_UUID, $itemReturn['UUID']);
        self::assertSame('Hadanite', $itemReturn['Name']);
        self::assertSame(1, $itemReturn['Quantity']);
    }

    public function test_dismantle_aggregates_across_all_tiers(): void
    {
        $blueprint = $this->loadBlueprint('multi_tier');

        $formatted = (new Blueprint($blueprint))->toArray();

        $dismantle = $formatted['Dismantle'];
        $returns = $dismantle['Returns'];

        $kinds = array_column($returns, 'Kind');
        self::assertContains('resource', $kinds);
        self::assertContains('item', $kinds);

        $resourceReturn = array_values(array_filter($returns, static fn (array $r): bool => $r['Kind'] === 'resource'))[0];
        self::assertSame(self::GOLD_UUID, $resourceReturn['UUID']);
        self::assertSame(0.005, $resourceReturn['QuantityScu']);

        $itemReturn = array_values(array_filter($returns, static fn (array $r): bool => $r['Kind'] === 'item'))[0];
        self::assertSame(self::ARMOUR_INPUT_UUID, $itemReturn['UUID']);
        self::assertSame(1, $itemReturn['Quantity']);
    }

    public function test_dismantle_is_null_when_no_global_dismantle_blueprint(): void
    {
        $classToPathMapPath = sprintf('%s%sclassToPathMap-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, PHP_OS_FAMILY);
        $map = json_decode(file_get_contents($classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);
        unset($map['CraftingBlueprintRecord']['GlobalGenericDismantle']);
        file_put_contents($classToPathMapPath, json_encode($map, JSON_THROW_ON_ERROR));

        ServiceFactory::reset();
        $this->initializeBlueprintFormattingServices();

        $blueprint = $this->loadBlueprint('ammo');

        $formatted = (new Blueprint($blueprint))->toArray();

        self::assertArrayNotHasKey('Dismantle', $formatted);
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
