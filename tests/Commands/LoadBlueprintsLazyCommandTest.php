<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadBlueprints;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadBlueprintsLazyCommandTest extends ScDataTestCase
{
    public function test_execute_writes_lazy_resolved_blueprint_export(): void
    {
        $outputItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/output_ammo.xml',
            <<<'XML'
            <EntityClassDefinition.OUTPUT_AMMO __type="EntityClassDefinition" __ref="8177489f-ed83-44ac-afd4-2b32a80fa0a6" __path="libs/foundry/records/entities/items/output_ammo.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAmmo" SubType="Magazine" Grade="1">
                    <Localization>
                      <English Name="Output Ammo" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.OUTPUT_AMMO>
            XML
        );
        $inputItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/input_material.xml',
            <<<'XML'
            <EntityClassDefinition.INPUT_MATERIAL __type="EntityClassDefinition" __ref="4a9a0bce-0f8b-4688-8ddf-dbd57e59af01" __path="libs/foundry/records/entities/items/input_material.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="CraftingMaterial" SubType="Catalyst" Grade="1">
                    <Localization>
                      <English Name="Input Material" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.INPUT_MATERIAL>
            XML
        );
        $blueprintPath = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_phase_two.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_PHASE_TWO __type="CraftingBlueprintRecord" __ref="d1da140e-b7ee-46ba-b76a-f5dd33c0348c" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_phase_two.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Phase Two Blueprint">
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
                                    <CraftingCost_Item entityClass="4a9a0bce-0f8b-4688-8ddf-dbd57e59af01" quantity="2" />
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
            </CraftingBlueprintRecord.BP_CRAFT_PHASE_TWO>
            XML
        );

        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/globalparams/craftingglobalparams.xml',
            <<<'XML'
            <CraftingGlobalParams.CraftingGlobalParams __type="CraftingGlobalParams" __ref="f99cff9b-c0b5-4d03-83f7-c7209d92b51d" __path="libs/foundry/records/crafting/globalparams/craftingglobalparams.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'OUTPUT_AMMO' => $outputItemPath,
                    'INPUT_MATERIAL' => $inputItemPath,
                ],
                'CraftingBlueprintRecord' => [
                    'BP_CRAFT_PHASE_TWO' => $blueprintPath,
                ],
            ],
            uuidToClassMap: [
                '8177489f-ed83-44ac-afd4-2b32a80fa0a6' => 'OUTPUT_AMMO',
                '4a9a0bce-0f8b-4688-8ddf-dbd57e59af01' => 'INPUT_MATERIAL',
                'd1da140e-b7ee-46ba-b76a-f5dd33c0348c' => 'BP_CRAFT_PHASE_TWO',
            ],
            classToUuidMap: [
                'OUTPUT_AMMO' => '8177489f-ed83-44ac-afd4-2b32a80fa0a6',
                'INPUT_MATERIAL' => '4a9a0bce-0f8b-4688-8ddf-dbd57e59af01',
                'BP_CRAFT_PHASE_TWO' => 'd1da140e-b7ee-46ba-b76a-f5dd33c0348c',
            ],
            uuidToPathMap: [
                '8177489f-ed83-44ac-afd4-2b32a80fa0a6' => $outputItemPath,
                '4a9a0bce-0f8b-4688-8ddf-dbd57e59af01' => $inputItemPath,
                'd1da140e-b7ee-46ba-b76a-f5dd33c0348c' => $blueprintPath,
            ],
        );
        $this->writeResourceTypeCache([
            '61189578-ed7a-4491-9774-37ae2f82b8b0' => <<<'XML'
            <ResourceType.Hephaestanite displayName="Hephaestanite" __type="ResourceType" __ref="61189578-ed7a-4491-9774-37ae2f82b8b0" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
              <densityType>
                <ResourceTypeDensity>
                  <densityUnit>
                    <GramsPerCubicCentimeter gramsPerCubicCentimeter="1" />
                  </densityUnit>
                </ResourceTypeDensity>
              </densityType>
            </ResourceType.Hephaestanite>
            XML,
        ]);
        $this->writeCraftingGameplayPropertyCache([
            'cfc129ce-488a-46f2-92f7-9272cd0cfdfb' => '<CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />',
        ]);

        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $tester = new CommandTester(new TestRealLoadBlueprintsCommand($this->tempDir));
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $index = $this->readJsonFile('blueprints.json');
        self::assertCount(1, $index);
        self::assertSame('8177489f-ed83-44ac-afd4-2b32a80fa0a6', $index[0]['output']['uuid']);
        self::assertSame('Hephaestanite', $index[0]['tiers'][0]['requirements']['children'][0]['children'][1]['name']);
        self::assertSame(
            'cfc129ce-488a-46f2-92f7-9272cd0cfdfb',
            $index[0]['tiers'][0]['requirements']['children'][0]['modifiers'][0]['property_uuid']
        );

        $blueprintFile = $this->readJsonFile('blueprints/bp_craft_phase_two.json');
        self::assertSame('output_ammo', $blueprintFile['Blueprint']['output']['class']);
        self::assertSame('Input Material', $blueprintFile['Blueprint']['tiers'][0]['requirements']['children'][0]['children'][0]['name']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}

final class TestRealLoadBlueprintsCommand extends LoadBlueprints
{
    public function __construct(private readonly string $scDataPath)
    {
        parent::__construct();
        $this->setName('load:blueprints');
    }

    protected function prepareServices(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void
    {
        (new ServiceFactory($this->scDataPath))->initialize();
    }
}
