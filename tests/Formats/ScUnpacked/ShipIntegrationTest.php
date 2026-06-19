<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ShipIntegrationTest extends ScDataTestCase
{
    private const MANUFACTURER_UUID = '11111111-1111-1111-1111-111111111111';

    /**
     * @throws JsonException
     */
    public function test_to_array_uses_manufacturer_fallback_and_exports_nested_loadout_shape(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertSame(self::MANUFACTURER_UUID, $result['Manufacturer']['UUID']);
        self::assertSame('FALL', $result['Manufacturer']['Code']);
        self::assertSame('Fallback Industries', $result['Manufacturer']['Name']);
        self::assertSame("Manufacturer: Fallback Industries\nFocus: Cargo\n\nFast hauler.", $result['Description']);
        self::assertSame(['Manufacturer' => 'Fallback Industries', 'Focus' => 'Cargo'], $result['DescriptionData']);
        self::assertSame('Fast hauler.', $result['DescriptionText']);
        self::assertSame('Combat', $result['Career']);
        self::assertSame('Light Fighter', $result['Role']);

        self::assertSame(100.0, $result['Mass']);
        self::assertSame(35.0, $result['MassLoadout']);
        self::assertSame(135.0, $result['MassTotal']);
        self::assertSame(1, $result['Seating']['CrewStations']);
        self::assertSame(1, $result['Seating']['TotalBeds']);
        self::assertArrayNotHasKey('MedicalBeds', $result['Seating']);

        self::assertArrayHasKey('Loadout', $result);
        self::assertCount(1, $result['Loadout']);

        $seatEntry = $result['Loadout'][0];
        self::assertSame('seat_mount', $seatEntry['HardpointName']);
        self::assertSame('Seat.Pilot', $seatEntry['Type']);
        self::assertTrue($seatEntry['Editable']);
        self::assertFalse($seatEntry['EditableChildren']);
        self::assertArrayHasKey('Loadout', $seatEntry);
        self::assertCount(1, $seatEntry['Loadout']);

        $bedEntry = $seatEntry['Loadout'][0];
        self::assertSame('BedPort', $bedEntry['HardpointName']);
        self::assertSame('Bed.Captain', $bedEntry['Type']);
        self::assertFalse($bedEntry['Editable']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_strips_trailing_newline_from_localized_vehicle_name(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertSame('Argo CSV-SM', $result['Name']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_keeps_shield_system_summary_in_parity_with_top_level_shields_total(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFileWithShieldPool());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeShieldVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeShieldLoadout()));
        $result = $ship->toArray();

        self::assertSame(
            $result['ShieldsTotal']['Resistance'],
            $result['Systems']['Shields']['Summary']['Resistance'],
        );
        self::assertSame(
            $result['ShieldsTotal']['Absorption'],
            $result['Systems']['Shields']['Summary']['Absorption'],
        );
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_uses_attachdef_localization_for_actor_vehicles_without_vehicle_component_params(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeBanuManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath, extraManufacturers: [
            '22222222-2222-2222-2222-222222222222' => [
                'class' => 'BANU',
                'path' => $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'scitemmanufacturer'.DIRECTORY_SEPARATOR.'banu.xml',
            ],
        ]);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeActorVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertSame('Argo ATLS IKTI', $result['Name']);
        self::assertSame(
            "Manufacturer: Banu\nFocus: Combat\n\nHandcrafted by Wikelo.",
            $result['Description']
        );
        self::assertSame(['Manufacturer' => 'Banu', 'Focus' => 'Combat'], $result['DescriptionData']);
        self::assertSame('Handcrafted by Wikelo.', $result['DescriptionText']);
        self::assertSame('22222222-2222-2222-2222-222222222222', $result['Manufacturer']['UUID']);
        self::assertSame('BANU', $result['Manufacturer']['Code']);
        // XML name "Banu" is the abbreviation; data.json canonical "Banu Souli"
        // wins via code fallback (code is identity).
        self::assertSame('Banu Souli', $result['Manufacturer']['Name']);
        self::assertSame('', $result['Career']);
        self::assertSame('', $result['Role']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_applies_wiki_manufacturer_code_to_fix_variant_name(): void
    {
        // Vehicle UUID 4a7a4b9d.. is curated in wiki_vehicles.json as BANU.
        // The XML manufacturer name is "Banu" (truncated variant), which misses
        // the name->code reverse map. The wiki code forward-resolves to the
        // canonical "Banu Souli" -- code is identity, name follows.
        $manufacturerPath = $this->writeBanuManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath, extraManufacturers: [
            '22222222-2222-2222-2222-222222222222' => [
                'class' => 'BANU',
                'path' => $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'scitemmanufacturer'.DIRECTORY_SEPARATOR.'banu.xml',
            ],
        ]);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeCuratedBanuVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertSame('BANU', $result['Manufacturer']['Code']);
        self::assertSame('Banu Souli', $result['Manufacturer']['Name']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_uses_resolved_cargo_grid_container_capacity_when_loadout_items_only_have_container_refs(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $containerPath = $this->writeInventoryContainerFile();
        $this->writeShipTestCacheFiles($manufacturerPath, $containerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeCargoGridLoadout()));
        $result = $ship->toArray();

        self::assertSame(18.0, $result['Cargo']);
        self::assertCount(1, $result['CargoGrids']);
        self::assertSame(18.0, $result['CargoGrids'][0]['SCU']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_prefers_loadout_inline_cargo_capacity_over_vehicle_inventory_fallback(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $vehicleInventoryPath = $this->writeInventoryContainerFile(
            fileName: 'ship_inventory.xml',
            className: 'TEST_SHIP_INVENTORY',
            reference: 'ship-inventory-container-uuid',
            dimensions: ['x' => 3.125, 'y' => 2.5, 'z' => 2.0]
        );
        $this->writeCacheFilesWithInventoryContainers($manufacturerPath, [
            'TEST_SHIP_INVENTORY' => [
                'uuid' => 'ship-inventory-container-uuid',
                'path' => $vehicleInventoryPath,
            ],
        ]);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile('ship-inventory-container-uuid'));

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeInlineCargoGridLoadout()));
        $result = $ship->toArray();

        self::assertSame(224.0, $result['Cargo']);
        self::assertCount(1, $result['CargoGrids']);
        self::assertSame(224.0, $result['CargoGrids'][0]['SCU']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_prefers_resolved_template_named_cargo_grids_over_vehicle_inventory_fallback(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $cargoGridPath = $this->writeInventoryContainerFile(
            fileName: 'cargo_grid_template.xml',
            className: 'TEST_CARGO_GRID_TEMPLATE',
            reference: 'cargo-grid-template-uuid',
            dimensions: ['x' => 2.5, 'y' => 20.0, 'z' => 2.5]
        );
        $vehicleInventoryPath = $this->writeInventoryContainerFile(
            fileName: 'ship_inventory.xml',
            className: 'TEST_SHIP_INVENTORY',
            reference: 'ship-inventory-container-uuid',
            dimensions: ['x' => 3.125, 'y' => 2.5, 'z' => 2.0]
        );
        $this->writeCacheFilesWithInventoryContainers($manufacturerPath, [
            'TEST_CARGO_GRID_TEMPLATE' => [
                'uuid' => 'cargo-grid-template-uuid',
                'path' => $cargoGridPath,
            ],
            'TEST_SHIP_INVENTORY' => [
                'uuid' => 'ship-inventory-container-uuid',
                'path' => $vehicleInventoryPath,
            ],
        ]);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile('ship-inventory-container-uuid'));

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $loadout = [
            [
                'portName' => 'hardpoint_cargogrid_left',
                'className' => 'REAL_CARGO_GRID_LEFT',
                'entries' => [],
                'ItemRaw' => [
                    'className' => 'REAL_CARGO_GRID_LEFT',
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'CargoGrid',
                            ],
                        ],
                        'SCItemInventoryContainerComponentParams' => [
                            'containerParams' => 'cargo-grid-template-uuid',
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'hardpoint_cargogrid_right',
                'className' => 'REAL_CARGO_GRID_RIGHT',
                'entries' => [],
                'ItemRaw' => [
                    'className' => 'REAL_CARGO_GRID_RIGHT',
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'CargoGrid',
                            ],
                        ],
                        'SCItemInventoryContainerComponentParams' => [
                            'containerParams' => 'cargo-grid-template-uuid',
                        ],
                    ],
                ],
            ],
        ];

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $loadout));
        $result = $ship->toArray();

        self::assertSame(128.0, $result['Cargo']);
        self::assertCount(2, $result['CargoGrids']);
        self::assertSame('TEST_CARGO_GRID_TEMPLATE', $result['CargoGrids'][0]['Class']);
        self::assertSame(64.0, $result['CargoGrids'][0]['SCU']);
    }

    public function test_to_array_exports_vehicle_level_port_tags(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFileWithPortTags('AEGS_Avenger_Base'));

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertArrayHasKey('PortTags', $result);
        self::assertSame(['AEGS_Avenger_Base'], $result['PortTags']);
    }

    public function test_to_array_exports_empty_port_tags_when_vehicle_has_none(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertArrayHasKey('PortTags', $result);
        self::assertSame([], $result['PortTags']);
    }

    public function test_to_array_exports_multiple_space_separated_port_tags(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeShipTestCacheFiles($manufacturerPath);
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFileWithPortTags('ANVL_Hurricane DRAK_Cutlass_Base ANVL_Gladiator'));

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $this->makeLoadout()));
        $result = $ship->toArray();

        self::assertArrayHasKey('PortTags', $result);
        self::assertSame(['ANVL_Hurricane', 'DRAK_Cutlass_Base', 'ANVL_Gladiator'], $result['PortTags']);
    }

    private function configureServiceFactory(): void
    {
        $this->writeLocalizationFile();

        $inventoryContainerService = new InventoryContainerService($this->tempDir);
        $inventoryContainerService->initialize();

        $manufacturerService = new ManufacturerService($this->tempDir);
        $manufacturerService->initialize();

        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $localizationService = new LocalizationService($this->tempDir);
        $localizationService->initialize();

        $this->setPrivateProperty(ServiceFactory::class, 'initialized', true);
        $this->setPrivateProperty(ServiceFactory::class, 'activeScDataPath', $this->tempDir);
        $this->setPrivateProperty(ServiceFactory::class, 'services', [
            'InventoryContainerService' => $inventoryContainerService,
            'ManufacturerService' => $manufacturerService,
            'ItemService' => $itemService,
            'LocalizationService' => $localizationService,
        ]);
    }

    private function writeManufacturerFile(): string
    {
        return $this->writeFallbackManufacturerFile();
    }

    private function writeBanuManufacturerFile(): string
    {
        return $this->writeFallbackManufacturerFile(
            code: 'BANU',
            uuid: '22222222-2222-2222-2222-222222222222',
            className: 'BANU',
            fileName: 'banu.xml',
            nameKey: '@manufacturer_name_banu',
        );
    }

    private function writeVehicleEntityFile(?string $inventoryContainerRef = null): string
    {
        $inventoryContainerAttribute = $inventoryContainerRef !== null
            ? sprintf(' inventoryContainerParams="%s"', $inventoryContainerRef)
            : '';

        return $this->writeFile(
            'records/entity/test_ship.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="ship-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" vehicleCareer="@vehicle_career" vehicleRole="@vehicle_role"{$inventoryContainerAttribute} />
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="11111111-1111-1111-1111-111111111111" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.TEST_SHIP>
            XML
        );
    }

    private function writeVehicleEntityFileWithShieldPool(int $maxShieldCount = 1): string
    {
        return $this->writeFile(
            'records/entity/test_ship.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="ship-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" vehicleCareer="@vehicle_career" vehicleRole="@vehicle_role" />
                    <SItemPortContainerComponentParams>
                        <resourceNetworkPowerPools>
                            <itemPools>
                                <Pool itemType="Shield" maxItemCount="{$maxShieldCount}" />
                            </itemPools>
                        </resourceNetworkPowerPools>
                    </SItemPortContainerComponentParams>
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="11111111-1111-1111-1111-111111111111" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.TEST_SHIP>
            XML
        );
    }

    private function writeActorVehicleEntityFile(): string
    {
        return $this->writeFile(
            'records/entity/actor_ship.xml',
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.ACTOR_SHIP __type="EntityClassDefinition" __ref="actor-ship-uuid" __path="libs/foundry/records/entityclassdefinition/actor_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="NOITEM_Vehicle" SubType="Vehicle_PowerSuit" Size="1" Grade="1" Manufacturer="22222222-2222-2222-2222-222222222222">
                            <Localization Name="@actor_vehicle_name" ShortName="@LOC_EMPTY" Description="@actor_vehicle_description" />
                        </AttachDef>
                    </SAttachableComponentParams>
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams name="@actor_vehicle_name" manufacturer="11111111-1111-1111-1111-111111111111" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.ACTOR_SHIP>
            XML
        );
    }

    /** Vehicle entity with a UUID curated in wiki_vehicles.json as BANU. */
    private function writeCuratedBanuVehicleEntityFile(): string
    {
        return $this->writeFile(
            'records/entity/curated_banu_ship.xml',
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.CURATED_BANU_SHIP __type="EntityClassDefinition" __ref="4a7a4b9d-ae3c-4375-af63-4100f831f43c" __path="libs/foundry/records/entityclassdefinition/curated_banu_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" Grade="1" Manufacturer="22222222-2222-2222-2222-222222222222">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@LOC_EMPTY" vehicleCareer="@LOC_EMPTY" vehicleRole="@LOC_EMPTY" />
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="22222222-2222-2222-2222-222222222222" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.CURATED_BANU_SHIP>
            XML
        );
    }

    private function writeVehicleImplementationFile(): string
    {
        return $this->writeStandardVehicleImplementationFile();
    }

    private function writeShieldVehicleImplementationFile(): string
    {
        return $this->writeFile(
            'records/vehicles/test_ship_impl.xml',
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Vehicle.TEST_SHIP_IMPL>
                <Parts>
                    <Part name="shield_mount" mass="100" damageMax="500">
                        <ItemPort maxSize="2" minSize="1">
                            <Types>
                                <Type type="Shield" />
                            </Types>
                        </ItemPort>
                    </Part>
                </Parts>
            </Vehicle.TEST_SHIP_IMPL>
            XML
        );
    }

    private function writeVehicleImplementationFileWithPortTags(string $portTags): string
    {
        return $this->writeFile(
            'records/vehicles/test_ship_impl.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Vehicle.TEST_SHIP_IMPL itemPortTags="{$portTags}">
                <Parts>
                    <Part name="seat_mount" mass="100" damageMax="500">
                        <ItemPort maxSize="1" minSize="1">
                            <Types>
                                <Type type="Seat" subtypes="Pilot" />
                            </Types>
                        </ItemPort>
                    </Part>
                </Parts>
            </Vehicle.TEST_SHIP_IMPL>
            XML
        );
    }

    private function writeInventoryContainerFile(
        string $fileName = 'cargo_grid.xml',
        string $className = 'TEST_CARGO_GRID',
        string $reference = 'cargo-grid-container-uuid',
        array $dimensions = ['x' => 3.75, 'y' => 2.5, 'z' => 3.75]
    ): string {
        $normalizedPath = sprintf('libs/foundry/records/inventorycontainers/%s', $fileName);

        return $this->writeFile(
            "records/inventorycontainers/{$fileName}",
            <<<XML
            <InventoryContainer.{$className} __type="InventoryContainer" __ref="{$reference}" __path="{$normalizedPath}">
                <interiorDimensions x="{$dimensions['x']}" y="{$dimensions['y']}" z="{$dimensions['z']}" />
                <inventoryType>
                    <InventoryOpenContainerType isExternalContainer="0">
                        <minPermittedItemSize x="1.25" y="1.25" z="1.25" />
                        <maxPermittedItemSize x="2.5" y="2.5" z="2.5" />
                    </InventoryOpenContainerType>
                </inventoryType>
            </InventoryContainer.{$className}>
            XML
        );
    }

    private function writeLocalizationFile(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            "manufacturer_name=Fallback Industries\nmanufacturer_name_banu=Banu\nvehicle_name=Argo CSV-SM\\n\nvehicle_description=Manufacturer: Fallback Industries\\nFocus: Cargo\\n\\nFast hauler.\nvehicle_career=Combat\nvehicle_role=Light Fighter\nactor_vehicle_name=Argo ATLS IKTI\nactor_vehicle_description=Manufacturer: Banu\\nFocus: Combat\\n\\nHandcrafted by Wikelo.\nLOC_EMPTY="
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeLoadout(): array
    {
        return [[
            'portName' => 'seat_mount',
            'className' => 'SEAT_TEST',
            'entries' => [[
                'portName' => 'BedPort',
                'className' => 'BED_TEST',
                'entries' => [],
                'Item' => [
                    'type' => 'Bed',
                    'stdItem' => [
                        'ClassName' => 'BED_TEST',
                        'UUID' => 'bed-uuid',
                        'Name' => 'Captain Bed',
                        'Manufacturer' => ['Name' => 'Fallback Industries'],
                        'Type' => 'Bed.Captain',
                        'Grade' => 'A',
                        'Mass' => 10.0,
                        'Ports' => [],
                    ],
                ],
            ]],
            'Item' => [
                'type' => 'Seat',
                'stdItem' => [
                    'ClassName' => 'SEAT_TEST',
                    'UUID' => 'seat-uuid',
                    'Name' => 'Pilot Seat',
                    'Manufacturer' => ['Name' => 'Fallback Industries'],
                    'Type' => 'Seat.Pilot',
                    'Grade' => 'A',
                    'Mass' => 25.0,
                    'Ports' => [[
                        'PortName' => 'BedPort',
                        'DisplayName' => 'Bed Port',
                        'Types' => ['Bed.Captain'],
                        'Flags' => [],
                        'RequiredTags' => [],
                        'MinSize' => 1,
                        'MaxSize' => 1,
                        'Uneditable' => true,
                    ]],
                ],
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeShieldLoadout(): array
    {
        return [[
            'portName' => 'shield_mount',
            'className' => 'SHIELD_TEST',
            'entries' => [],
            'Item' => [
                'type' => 'Shield',
                'stdItem' => [
                    'ClassName' => 'SHIELD_TEST',
                    'UUID' => 'shield-uuid',
                    'Name' => 'Test Shield',
                    'Manufacturer' => ['Name' => 'Fallback Industries'],
                    'Type' => 'Shield.UNDEFINED',
                    'Grade' => 'A',
                    'Mass' => 10.0,
                    'Shield' => [
                        'MaxShieldHealth' => 1000.0,
                        'MaxShieldRegen' => 100.0,
                        'Resistance' => [
                            'Physical' => ['Minimum' => 0.1, 'Maximum' => 0.2],
                            'Energy' => ['Minimum' => 0.3, 'Maximum' => 0.4],
                        ],
                        'Absorption' => [
                            'Physical' => ['Minimum' => 0.5, 'Maximum' => 0.6],
                            'Energy' => ['Minimum' => 0.7, 'Maximum' => 0.8],
                        ],
                    ],
                    'Ports' => [],
                ],
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeCargoGridLoadout(): array
    {
        return [[
            'portName' => 'hardpoint_cargogrid_main',
            'className' => 'TEST_CARGO_GRID_ITEM',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'TEST_CARGO_GRID_ITEM',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'cargo-grid-container-uuid',
                    ],
                ],
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeInlineCargoGridLoadout(): array
    {
        return [[
            'portName' => 'hardpoint_cargogrid_main',
            'className' => 'INLINE_CARGO_GRID_ITEM',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'INLINE_CARGO_GRID_ITEM',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'inventoryContainer' => [
                            'interiorDimensions' => [
                                'x' => 35.0,
                                'y' => 2.5,
                                'z' => 5.0,
                            ],
                            'inventoryType' => [
                                'InventoryOpenContainerType' => [
                                    'isExternalContainer' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]];
    }

    /**
     * @throws JsonException
     */
    private function writeShipTestCacheFiles(string $manufacturerPath, ?string $inventoryContainerPath = null, array $extraManufacturers = []): void
    {
        $inventoryContainers = $inventoryContainerPath !== null ? [
            'TEST_CARGO_GRID' => [
                'uuid' => 'cargo-grid-container-uuid',
                'path' => $inventoryContainerPath,
            ],
        ] : [];

        $this->writeCacheFilesWithInventoryContainers($manufacturerPath, $inventoryContainers, $extraManufacturers);
    }

    /**
     * @param  array<string, array{uuid: string, path: string}>  $inventoryContainers
     *
     * @throws JsonException
     */
    private function writeCacheFilesWithInventoryContainers(string $manufacturerPath, array $inventoryContainers = [], array $extraManufacturers = []): void
    {
        $uuidToClassMap = [self::MANUFACTURER_UUID => 'FALLBACK'];
        $classToUuidMap = ['FALLBACK' => self::MANUFACTURER_UUID];
        $uuidToPathMap = [self::MANUFACTURER_UUID => $manufacturerPath];
        $inventoryContainerPaths = [];
        $manufacturerPaths = ['FALLBACK' => $manufacturerPath];

        foreach ($inventoryContainers as $class => $config) {
            $uuidToClassMap[$config['uuid']] = $class;
            $classToUuidMap[$class] = $config['uuid'];
            $uuidToPathMap[$config['uuid']] = $config['path'];
            $inventoryContainerPaths[$class] = $config['path'];
        }

        foreach ($extraManufacturers as $uuid => $config) {
            $uuidToClassMap[$uuid] = $config['class'];
            $classToUuidMap[$config['class']] = $uuid;
            $uuidToPathMap[$uuid] = $config['path'];
            $manufacturerPaths[$config['class']] = $config['path'];
        }

        $this->writeCacheFiles(
            classToPathMap: [
                'InventoryContainer' => $inventoryContainerPaths,
                'SCItemManufacturer' => $manufacturerPaths,
            ],
            uuidToClassMap: $uuidToClassMap,
            classToUuidMap: $classToUuidMap,
            uuidToPathMap: $uuidToPathMap,
        );
    }
}
