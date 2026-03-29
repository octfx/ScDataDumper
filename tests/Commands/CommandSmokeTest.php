<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use JsonException;
use Octfx\ScDataDumper\Commands\GenerateCache;
use Octfx\ScDataDumper\Commands\LoadBlueprints;
use Octfx\ScDataDumper\Commands\LoadData;
use Octfx\ScDataDumper\Commands\LoadFactions;
use Octfx\ScDataDumper\Commands\LoadItems;
use Octfx\ScDataDumper\Commands\LoadManufacturers;
use Octfx\ScDataDumper\Commands\LoadTags;
use Octfx\ScDataDumper\Commands\LoadTranslations;
use Octfx\ScDataDumper\Commands\LoadVehicles;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandSmokeTest extends ScDataTestCase
{
    public function test_load_items_exports_index_and_item_files(): void
    {
        $this->seedCommandFixtureData(includeVehicle: false);

        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.'out-items';
        $tester = new CommandTester(new LoadItems);

        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $outputPath,
            '--overwrite' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(2, $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'items.json'));
        self::assertSame([], $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'fps-items.json'));
        self::assertSame([], $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'ship-items.json'));
        self::assertArrayHasKey('Components', $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.'seat_test.json'));
    }

    public function test_load_vehicles_exports_index_and_raw_payloads(): void
    {
        $this->seedCommandFixtureData(includeVehicle: true);

        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.'out-vehicles';
        $tester = new CommandTester(new LoadVehicles);

        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $outputPath,
            '--overwrite' => true,
            '--with-raw' => true,
            '--filter' => 'test_ship',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $ships = $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'ships.json');
        self::assertCount(1, $ships);
        self::assertSame('TEST_SHIP', $ships[0]['ClassName'] ?? null);

        $shipPayload = $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'ships'.DIRECTORY_SEPARATOR.'test_ship.json');
        self::assertSame('TEST_SHIP', $shipPayload['ClassName'] ?? null);

        $rawPayload = $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'ships'.DIRECTORY_SEPARATOR.'test_ship-raw.json');
        self::assertSame('TEST_SHIP', $rawPayload['ScVehicle']['ClassName'] ?? null);
        self::assertCount(1, $rawPayload['Loadout'] ?? []);
    }

    public function test_load_blueprints_exports_index_and_scunpacked_payloads(): void
    {
        $this->seedCommandFixtureData(includeVehicle: false);

        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.'out-blueprints';
        $tester = new CommandTester(new LoadBlueprints);

        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $outputPath,
            '--overwrite' => true,
            '--scUnpackedFormat' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $blueprints = $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'blueprints.json');
        self::assertCount(1, $blueprints);
        self::assertSame('BP_CRAFT_TEST_AMMO', $blueprints[0]['key'] ?? null);

        $payload = $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'blueprints'.DIRECTORY_SEPARATOR.'bp_craft_test_ammo.json');
        self::assertSame('BP_CRAFT_TEST_AMMO', $payload['Blueprint']['key'] ?? null);
        self::assertSame('blueprint-uuid', $payload['Raw']['Blueprint']['__ref'] ?? null);
    }

    public function test_generate_cache_with_blueprint_support_writes_blueprint_related_caches(): void
    {
        $this->seedCommandFixtureData(includeVehicle: false);

        @unlink($this->tempDir.DIRECTORY_SEPARATOR.sprintf('resource-type-cache-%s.json', PHP_OS_FAMILY));
        @unlink($this->tempDir.DIRECTORY_SEPARATOR.sprintf('crafting-gameplay-property-cache-%s.json', PHP_OS_FAMILY));

        $tester = new CommandTester(new GenerateCache);
        $exitCode = $tester->execute([
            'path' => $this->tempDir,
            '--overwrite' => true,
            '--with-blueprint-support' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($this->tempDir.DIRECTORY_SEPARATOR.sprintf('resource-type-cache-%s.json', PHP_OS_FAMILY));
        self::assertFileExists($this->tempDir.DIRECTORY_SEPARATOR.sprintf('crafting-gameplay-property-cache-%s.json', PHP_OS_FAMILY));
    }

    public function test_load_data_runs_real_subcommands_and_writes_core_outputs(): void
    {
        $this->seedCommandFixtureData(includeVehicle: true);

        $application = new Application;
        $application->addCommand(new LoadData);
        $application->addCommand(new GenerateCache);
        $application->addCommand(new LoadItems);
        $application->addCommand(new LoadBlueprints);
        $application->addCommand(new LoadVehicles);
        $application->addCommand(new LoadFactions);
        $application->addCommand(new LoadManufacturers);
        $application->addCommand(new LoadTranslations);
        $application->addCommand(new LoadTags);

        $tester = new CommandTester($application->find('load:data'));
        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.'out-all';

        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $outputPath,
            '--overwrite' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'items.json');
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'blueprints.json');
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'ships.json');
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'manufacturers.json');
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'labels.json');
        self::assertFileExists($outputPath.DIRECTORY_SEPARATOR.'tags.json');
        self::assertSame([], $this->decodeJson($outputPath.DIRECTORY_SEPARATOR.'manufacturers.json'));
    }

    /**
     * @throws JsonException
     */
    private function seedCommandFixtureData(bool $includeVehicle): void
    {
        $seatPath = $this->writeItemEntityFile(
            'SEAT_TEST',
            'seat-test-uuid',
            'Seat',
            'Pilot',
            20,
            [
                ['name' => 'BedPort', 'displayName' => 'Bed Port', 'types' => ['Bed.Captain']],
            ],
            [
                ['portName' => 'BedPort', 'className' => 'BED_DEFAULT'],
            ]
        );
        $bedPath = $this->writeItemEntityFile('BED_DEFAULT', 'bed-default-uuid', 'Bed', 'Captain', 8);

        $classToPathMap = [
            'EntityClassDefinition' => [
                'SEAT_TEST' => $seatPath,
                'BED_DEFAULT' => $bedPath,
            ],
            'InventoryContainer' => [],
            'CargoGrid' => [],
            'RadarSystemSharedParams' => [],
            'DamageResistanceMacro' => [],
            'MeleeCombatConfig' => [],
            'MiningLaserGlobalParams' => [],
            'AmmoParams' => [],
        ];
        $uuidToClassMap = [
            'seat-test-uuid' => 'SEAT_TEST',
            'bed-default-uuid' => 'BED_DEFAULT',
        ];
        $classToUuidMap = [
            'SEAT_TEST' => 'seat-test-uuid',
            'BED_DEFAULT' => 'bed-default-uuid',
        ];
        $uuidToPathMap = [
            'seat-test-uuid' => $seatPath,
            'bed-default-uuid' => $bedPath,
        ];

        if ($includeVehicle) {
            $vehicleEntityPath = $this->writeVehicleDefinitionFile(
                'test_ship',
                'ship-wrapper-uuid',
                'ships/test_ship_impl.xml',
                [
                    ['portName' => 'seat_mount', 'className' => 'SEAT_TEST'],
                ]
            );
            $this->writeVehicleImplementationFile(
                'Data/Scripts/Entities/Vehicles/Implementations/Xml/test_ship_impl.xml',
                <<<'XML'
                <Vehicle.TEST_SHIP_IMPL>
                    <Parts>
                        <Part name="seat_mount" mass="100">
                            <ItemPort display_name="Seat Mount" minSize="1" maxSize="1">
                                <Types>
                                    <Type type="Seat" subtypes="Pilot" />
                                </Types>
                            </ItemPort>
                        </Part>
                    </Parts>
                </Vehicle.TEST_SHIP_IMPL>
                XML
            );

            $classToPathMap['EntityClassDefinition']['TEST_SHIP'] = $vehicleEntityPath;
            $uuidToClassMap['ship-wrapper-uuid'] = 'TEST_SHIP';
            $classToUuidMap['TEST_SHIP'] = 'ship-wrapper-uuid';
            $uuidToPathMap['ship-wrapper-uuid'] = $vehicleEntityPath;
        } else {
            $this->writeFile('Data/Scripts/Entities/Vehicles/Implementations/Xml/.keep', '');
        }

        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");
        $this->writeFile('Data/Game2.xml', <<<'XML'
            <GameData>
              <ResourceType.TestResource displayName="Test Resource" __type="ResourceType" __ref="resource-uuid" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
            </GameData>
            XML
        );
        $this->writeFile('Data/Scripts/Loadouts/.keep', '');
        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/stattypes/materialstatdatabase.xml',
            '<materialstatdatabase><statTypes /></materialstatdatabase>'
        );
        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/globalparams/craftingglobalparams.xml',
            <<<'XML'
            <CraftingGlobalParams.CraftingGlobalParams __type="CraftingGlobalParams" __ref="globalparams-uuid" __path="libs/foundry/records/crafting/globalparams/craftingglobalparams.xml">
              <defaultBlueprintSelection>
                <DefaultBlueprintSelection_Whitelist>
                  <blueprintRecords>
                    <Reference value="blueprint-uuid" />
                  </blueprintRecords>
                </DefaultBlueprintSelection_Whitelist>
              </defaultBlueprintSelection>
            </CraftingGlobalParams.CraftingGlobalParams>
            XML
        );
        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprintrewards/blueprintmissionpools/test_reward_pool.xml',
            <<<'XML'
            <BlueprintPoolRecord.TEST_REWARD_POOL __type="BlueprintPoolRecord" __ref="reward-pool-uuid" __path="libs/foundry/records/crafting/blueprintrewards/blueprintmissionpools/test_reward_pool.xml">
              <blueprintRewards>
                <BlueprintReward weight="1" blueprintRecord="blueprint-uuid" />
              </blueprintRewards>
            </BlueprintPoolRecord.TEST_REWARD_POOL>
            XML
        );
        $blueprintPath = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_test_ammo.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_TEST_AMMO __type="CraftingBlueprintRecord" __ref="blueprint-uuid" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_test_ammo.xml">
              <blueprint>
                <CraftingBlueprint category="blueprint-category" blueprintName="Test Ammo Blueprint">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="bed-default-uuid" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned seconds="10" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="1">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="FRAME" displayName="Frame" />
                                      <options>
                                        <CraftingCost_Resource resource="resource-uuid" minQuality="0">
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.5" />
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
            </CraftingBlueprintRecord.BP_CRAFT_TEST_AMMO>
            XML
        );

        $classToPathMap['CraftingBlueprintRecord'] = [
            'BP_CRAFT_TEST_AMMO' => $blueprintPath,
        ];
        $uuidToClassMap['blueprint-uuid'] = 'BP_CRAFT_TEST_AMMO';
        $classToUuidMap['BP_CRAFT_TEST_AMMO'] = 'blueprint-uuid';
        $uuidToPathMap['blueprint-uuid'] = $blueprintPath;

        $this->writeCacheFiles(
            classToTypeMap: [],
            classToPathMap: $classToPathMap,
            uuidToClassMap: $uuidToClassMap,
            classToUuidMap: $classToUuidMap,
            uuidToPathMap: $uuidToPathMap,
        );

        $this->writeFile(sprintf('consumable-subType-cache-%s.json', PHP_OS_FAMILY), '{}');
        $this->writeFile(sprintf('tagdatabase-cache-%s.json', PHP_OS_FAMILY), '{}');
        $this->writeFile(sprintf('materialstat-cache-%s.json', PHP_OS_FAMILY), '{}');
    }

    /**
     * @param  array<int, array<string, mixed>>  $ports
     * @param  array<int, array<string, mixed>>  $defaultLoadout
     */
    private function writeItemEntityFile(
        string $className,
        string $uuid,
        string $type,
        ?string $subType,
        float $mass,
        array $ports = [],
        array $defaultLoadout = []
    ): string {
        $subTypeAttribute = $subType !== null ? sprintf(' SubType="%s"', $subType) : '';

        $portsXml = '';
        if ($ports !== []) {
            $portsXml = sprintf(
                '<SItemPortContainerComponentParams><Ports>%s</Ports></SItemPortContainerComponentParams>',
                implode('', array_map(fn (array $port): string => $this->renderPortDefinition($port), $ports))
            );
        }

        $defaultLoadoutXml = '';
        if ($defaultLoadout !== []) {
            $defaultLoadoutXml = sprintf(
                '<SEntityComponentDefaultLoadoutParams><loadout><SItemPortLoadoutManualParams><entries>%s</entries></SItemPortLoadoutManualParams></loadout></SEntityComponentDefaultLoadoutParams>',
                $this->renderLoadoutEntries($defaultLoadout)
            );
        }

        return $this->writeFile(
            sprintf('records/entity/%s.xml', strtolower($className)),
            sprintf(
                '<?xml version="1.0" encoding="UTF-8"?><EntityClassDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="libs/foundry/records/entityclassdefinition/%3$s.xml"><Components><SAttachableComponentParams><AttachDef Type="%4$s"%5$s Size="1" Grade="A"><Localization><English Name="%1$s" Description="" /></Localization></AttachDef></SAttachableComponentParams><SEntityPhysicsControllerParams><PhysType><SEntityRigidPhysicsControllerParams Mass="%6$s" /></PhysType></SEntityPhysicsControllerParams>%7$s%8$s</Components></EntityClassDefinition.%1$s>',
                $className,
                $uuid,
                strtolower($className),
                $type,
                $subTypeAttribute,
                $mass,
                $portsXml,
                $defaultLoadoutXml
            )
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualLoadout
     */
    private function writeVehicleDefinitionFile(
        string $className,
        string $uuid,
        string $implementation,
        array $manualLoadout
    ): string {
        $loadoutXml = $manualLoadout === []
            ? ''
            : sprintf(
                '<SEntityComponentDefaultLoadoutParams><loadout><SItemPortLoadoutManualParams><entries>%s</entries></SItemPortLoadoutManualParams></loadout></SEntityComponentDefaultLoadoutParams>',
                $this->renderLoadoutEntries($manualLoadout)
            );

        return $this->writeFile(
            sprintf('entities/spaceships/%s.xml', $className),
            sprintf(
                '<?xml version="1.0" encoding="UTF-8"?><VehicleDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="entities/spaceships/%3$s.xml"><Components><SAttachableComponentParams><AttachDef Type="Vehicle" SubType="Ship" /></SAttachableComponentParams><VehicleComponentParams vehicleDefinition="%4$s" vehicleName="Smoke Test Ship" vehicleDescription="" vehicleCareer="" vehicleRole=""><English vehicleName="Smoke Test Ship" vehicleDescription="" vehicleCareer="" vehicleRole="" /></VehicleComponentParams>%5$s</Components></VehicleDefinition.%1$s>',
                strtoupper($className),
                $uuid,
                strtolower($className),
                $implementation,
                $loadoutXml
            )
        );
    }

    private function writeVehicleImplementationFile(string $relativePath, string $xml): string
    {
        return $this->writeFile($relativePath, $xml);
    }

    /**
     * @param  array<string, mixed>  $port
     */
    private function renderPortDefinition(array $port): string
    {
        return sprintf(
            '<SItemPortDef Name="%s" DisplayName="%s" MinSize="1" MaxSize="1"><Types>%s</Types></SItemPortDef>',
            $port['name'],
            $port['displayName'],
            implode('', array_map(fn (string $type): string => $this->renderTypeDefinition($type), $port['types'] ?? []))
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function renderLoadoutEntries(array $entries): string
    {
        $xml = '';

        foreach ($entries as $entry) {
            $nestedEntries = $entry['entries'] ?? [];
            $nestedLoadout = $nestedEntries === []
                ? ''
                : sprintf(
                    '<loadout><SItemPortLoadoutManualParams><entries>%s</entries></SItemPortLoadoutManualParams></loadout>',
                    $this->renderLoadoutEntries($nestedEntries)
                );

            $attributes = [
                sprintf('itemPortName="%s"', $entry['portName']),
            ];

            if (($entry['className'] ?? null) !== null) {
                $attributes[] = sprintf('entityClassName="%s"', $entry['className']);
            }

            $xml .= sprintf(
                '<SItemPortLoadoutEntryParams %s>%s</SItemPortLoadoutEntryParams>',
                implode(' ', $attributes),
                $nestedLoadout
            );
        }

        return $xml;
    }

    private function renderTypeDefinition(string $type): string
    {
        [$major, $minor] = array_pad(explode('.', $type, 2), 2, null);

        if ($minor === null) {
            return sprintf('<Type Type="%s" />', $major);
        }

        return sprintf('<Type Type="%s" SubTypes="%s" />', $major, $minor);
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, sprintf('Failed to read %s', $path));

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
