<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadBlueprints;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadBlueprintsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_scunpacked_payload_by_default(): void
    {
        $this->writeCacheFiles();
        new ServiceFactory($this->tempDir)->initialize();

        $command = new TestLoadBlueprintsCommand([
            [
                'className' => 'BP_CRAFT_TEST_AMMO',
                'formatted' => [
                    'uuid' => 'blueprint-uuid',
                    'key' => 'BP_CRAFT_TEST_AMMO',
                    'availability' => [
                        'default' => false,
                        'reward_pools' => [],
                    ],
                    'tiers' => [
                        [
                            'tier_index' => 0,
                            'requirements' => [
                                'kind' => 'root',
                                'children' => [
                                    [
                                        'kind' => 'group',
                                        'children' => [
                                            [
                                                'kind' => 'group',
                                                'required_count' => 2,
                                                'children' => [
                                                    [
                                                        'kind' => 'material',
                                                        'uuid' => 'resource-uuid',
                                                        'modifiers' => [
                                                            [
                                                                'property_uuid' => 'property-uuid',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'rawBlueprint' => [
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ],
                'defaultJson' => json_encode([
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $index = $this->readJsonFile('blueprints.json');
        self::assertCount(1, $index);
        self::assertSame('BP_CRAFT_TEST_AMMO', $index[0]['key']);
        self::assertSame('root', $index[0]['tiers'][0]['requirements']['kind']);
        self::assertSame(2, $index[0]['tiers'][0]['requirements']['children'][0]['children'][0]['required_count']);
        self::assertSame(
            'property-uuid',
            $index[0]['tiers'][0]['requirements']['children'][0]['children'][0]['children'][0]['modifiers'][0]['property_uuid']
        );
        self::assertArrayNotHasKey('schema_version', $index[0]);

        $blueprintFile = $this->readJsonFile('blueprints/bp_craft_test_ammo.json');
        self::assertSame('ammo-category', $blueprintFile['Raw']['Blueprint']['blueprint']['CraftingBlueprint']['category']);
        self::assertSame('BP_CRAFT_TEST_AMMO', $blueprintFile['Blueprint']['key']);
    }

    public function test_execute_accepts_scunpacked_flag_as_a_noop(): void
    {
        $this->writeCacheFiles();
        (new ServiceFactory($this->tempDir))->initialize();

        $records = [
            [
                'className' => 'BP_CRAFT_TEST_AMMO',
                'formatted' => [
                    'uuid' => 'blueprint-uuid',
                    'key' => 'BP_CRAFT_TEST_AMMO',
                    'availability' => [
                        'default' => true,
                        'reward_pools' => [
                            [
                                'uuid' => 'reward-uuid',
                                'key' => 'REWARD_POOL',
                            ],
                        ],
                    ],
                    'tiers' => [
                        [
                            'tier_index' => 0,
                            'requirements' => [
                                'kind' => 'root',
                            ],
                        ],
                    ],
                ],
                'rawBlueprint' => [
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ],
                'defaultJson' => json_encode([
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ];

        $defaultOutputDir = $this->tempDir.DIRECTORY_SEPARATOR.'default';
        mkdir($defaultOutputDir, 0777, true);
        $defaultTester = new CommandTester(new TestLoadBlueprintsCommand($records));
        $defaultExitCode = $defaultTester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $defaultOutputDir,
        ]);

        self::assertSame(0, $defaultExitCode);
        $defaultContents = file_get_contents($defaultOutputDir.DIRECTORY_SEPARATOR.'blueprints/bp_craft_test_ammo.json');
        self::assertNotFalse($defaultContents);

        $flaggedOutputDir = $this->tempDir.DIRECTORY_SEPARATOR.'flagged';
        mkdir($flaggedOutputDir, 0777, true);
        $flaggedTester = new CommandTester(new TestLoadBlueprintsCommand($records));
        $flaggedExitCode = $flaggedTester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $flaggedOutputDir,
            '--scUnpackedFormat' => true,
        ]);

        self::assertSame(0, $flaggedExitCode);
        $flaggedContents = file_get_contents($flaggedOutputDir.DIRECTORY_SEPARATOR.'blueprints/bp_craft_test_ammo.json');
        self::assertNotFalse($flaggedContents);

        self::assertSame($defaultContents, $flaggedContents);
    }

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
        self::assertSame('8177489f-ed83-44ac-afd4-2b32a80fa0a6', $index[0]['Output']['UUID']);
        self::assertSame('Hephaestanite', $index[0]['Tiers'][0]['Requirements']['Children'][0]['Children'][1]['Name']);
        self::assertSame(
            'cfc129ce-488a-46f2-92f7-9272cd0cfdfb',
            $index[0]['Tiers'][0]['Requirements']['Children'][0]['Modifiers'][0]['UUID']
        );

        $blueprintFile = $this->readJsonFile('blueprints/bp_craft_phase_two.json');
        self::assertSame('output_ammo', $blueprintFile['Blueprint']['Output']['Class']);
        self::assertSame('Input Material', $blueprintFile['Blueprint']['Tiers'][0]['Requirements']['Children'][0]['Children'][0]['Name']);
    }
}

final class TestLoadBlueprintsCommand extends LoadBlueprints
{
    /**
     * @param  array<int, array{className: string, formatted: array, rawBlueprint: array, defaultJson: string}>  $records
     */
    public function __construct(private readonly array $records)
    {
        parent::__construct();
        $this->setName('load:blueprints');
    }

    protected function prepareServices(InputInterface $input, OutputInterface $output): void {}

    protected function getBlueprintExportCount(): int
    {
        return count($this->records);
    }

    protected function iterateBlueprintExports(?string $nameFilter): iterable
    {
        foreach ($this->records as $record) {
            if ($nameFilter !== null && ! str_contains(strtolower($record['className']), $nameFilter)) {
                continue;
            }

            yield $record;
        }
    }
}

final class TestRealLoadBlueprintsCommand extends LoadBlueprints
{
    public function __construct(private readonly string $scDataPath)
    {
        parent::__construct();
        $this->setName('load:blueprints');
    }

    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        new ServiceFactory($this->scDataPath)->initialize();
    }
}
