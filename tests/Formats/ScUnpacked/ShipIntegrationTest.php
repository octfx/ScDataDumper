<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\BaseService;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

final class ShipIntegrationTest extends TestCase
{
    private const MANUFACTURER_UUID = '11111111-1111-1111-1111-111111111111';

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sc-data-dumper-ship-integration-'.str_replace('.', '', uniqid('', true));
        if (! mkdir($this->tempDir, 0777, true) && ! is_dir($this->tempDir)) {
            throw new RuntimeException(sprintf('Failed to create test directory: %s', $this->tempDir));
        }

        $this->resetServiceState();
    }

    protected function tearDown(): void
    {
        $this->resetServiceState();
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_uses_manufacturer_fallback_and_exports_nested_loadout_shape(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeCacheFiles($manufacturerPath);
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
        self::assertSame(1, $result['Seats']);
        self::assertSame(1, $result['Beds']);

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
        $this->writeCacheFiles($manufacturerPath);
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
    public function test_to_array_uses_attachdef_localization_for_actor_vehicles_without_vehicle_component_params(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeBanuManufacturerFile();
        $this->writeCacheFiles($manufacturerPath, extraManufacturers: [
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
        self::assertSame('Banu', $result['Manufacturer']['Name']);
        self::assertSame('', $result['Career']);
        self::assertSame('', $result['Role']);
    }

    /**
     * @throws JsonException
     */
    public function test_to_array_uses_resolved_cargo_grid_container_capacity_when_loadout_items_only_have_container_refs(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $containerPath = $this->writeInventoryContainerFile();
        $this->writeCacheFiles($manufacturerPath, $containerPath);
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

    /**
     * @throws JsonException
     */
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

        $factory = new ReflectionClass(ServiceFactory::class);
        $initializedProperty = $factory->getProperty('initialized');
        $initializedProperty->setValue(null, true);

        $servicesProperty = $factory->getProperty('services');
        $servicesProperty->setValue(null, [
            'InventoryContainerService' => $inventoryContainerService,
            'ManufacturerService' => $manufacturerService,
            'ItemService' => $itemService,
            'LocalizationService' => $localizationService,
        ]);
    }

    private function writeManufacturerFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'scitemmanufacturer'.DIRECTORY_SEPARATOR.'fallback.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $xml = <<<XML
            <SCItemManufacturer.FALLBACK Code="FALL" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/fallback.xml">
                <Localization Name="@manufacturer_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" __type="SCItemLocalization">
                    <displayFeatures __type="SCExtendedLocalizationLevelParams" />
                </Localization>
            </SCItemManufacturer.FALLBACK>
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        $resolvedPath = realpath($path);
        if (! is_string($resolvedPath)) {
            throw new RuntimeException(sprintf('Failed to resolve path: %s', $path));
        }

        return str_replace('\\', '/', $resolvedPath);
    }

    private function writeBanuManufacturerFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'scitemmanufacturer'.DIRECTORY_SEPARATOR.'banu.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $xml = <<<XML
            <SCItemManufacturer.BANU Code="BANU" __type="SCItemManufacturer" __ref="22222222-2222-2222-2222-222222222222" __path="libs/foundry/records/scitemmanufacturer/banu.xml">
                <Localization Name="@manufacturer_name_banu" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" __type="SCItemLocalization">
                    <displayFeatures __type="SCExtendedLocalizationLevelParams" />
                </Localization>
            </SCItemManufacturer.BANU>
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        $resolvedPath = realpath($path);
        if (! is_string($resolvedPath)) {
            throw new RuntimeException(sprintf('Failed to resolve path: %s', $path));
        }

        return str_replace('\\', '/', $resolvedPath);
    }

    private function writeVehicleEntityFile(?string $inventoryContainerRef = null): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'entity'.DIRECTORY_SEPARATOR.'test_ship.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $inventoryContainerAttribute = $inventoryContainerRef !== null
            ? sprintf(' inventoryContainerParams="%s"', $inventoryContainerRef)
            : '';

        $xml = <<<XML
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
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        return $path;
    }

    private function writeActorVehicleEntityFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'entity'.DIRECTORY_SEPARATOR.'actor_ship.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $xml = <<<XML
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
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        return $path;
    }

    private function writeVehicleImplementationFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'vehicles'.DIRECTORY_SEPARATOR.'test_ship_impl.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Vehicle.TEST_SHIP_IMPL>
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
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        return $path;
    }

    private function writeInventoryContainerFile(
        string $fileName = 'cargo_grid.xml',
        string $className = 'TEST_CARGO_GRID',
        string $reference = 'cargo-grid-container-uuid',
        array $dimensions = ['x' => 3.75, 'y' => 2.5, 'z' => 3.75]
    ): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'inventorycontainers'.DIRECTORY_SEPARATOR.$fileName;
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $normalizedPath = sprintf('libs/foundry/records/inventorycontainers/%s', $fileName);

        $xml = <<<XML
            <InventoryContainer.{$className} __type="InventoryContainer" __ref="{$reference}" __path="{$normalizedPath}">
                <interiorDimensions x="{$dimensions['x']}" y="{$dimensions['y']}" z="{$dimensions['z']}" />
                <inventoryType>
                    <InventoryOpenContainerType isExternalContainer="0">
                        <minPermittedItemSize x="1.25" y="1.25" z="1.25" />
                        <maxPermittedItemSize x="2.5" y="2.5" z="2.5" />
                    </InventoryOpenContainerType>
                </inventoryType>
            </InventoryContainer.{$className}>
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        return $path;
    }

    private function writeLocalizationFile(): void
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'Localization'.DIRECTORY_SEPARATOR.'english';
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $path));
        }

        file_put_contents(
            $path.DIRECTORY_SEPARATOR.'global.ini',
            "manufacturer_name=Fallback Industries\nmanufacturer_name_banu=Banu\nvehicle_name=Argo CSV-SM\\n\nvehicle_description=Manufacturer: Fallback Industries\\nFocus: Cargo\\n\\nFast hauler.\nvehicle_career=Combat\nvehicle_role=Light Fighter\nactor_vehicle_name=Argo ATLS IKTI\nactor_vehicle_description=Manufacturer: Banu\\nFocus: Combat\\n\\nHandcrafted by Wikelo.\nLOC_EMPTY=\n"
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
    private function writeCacheFiles(string $manufacturerPath, ?string $inventoryContainerPath = null, array $extraManufacturers = []): void
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
     * @throws JsonException
     */
    private function writeCacheFilesWithInventoryContainers(string $manufacturerPath, array $inventoryContainers = [], array $extraManufacturers = []): void
    {
        $uuidToClassMap = [
            self::MANUFACTURER_UUID => 'FALLBACK',
        ];
        $classToUuidMap = [
            'FALLBACK' => self::MANUFACTURER_UUID,
        ];
        $uuidToPathMap = [
            self::MANUFACTURER_UUID => $manufacturerPath,
        ];
        $inventoryContainerPaths = [];
        $manufacturerPaths = [
            'FALLBACK' => $manufacturerPath,
        ];

        foreach ($inventoryContainers as $class => $config) {
            $uuidToClassMap[$config['uuid']] = $class;
            $classToUuidMap[$class] = $config['uuid'];
            $uuidToPathMap[$config['uuid']] = $config['path'];
            $inventoryContainerPaths[$class] = $config['path'];
        }

        foreach ($extraManufacturers as $uuid => $config) {
            $class = $config['class'];
            $path = $config['path'];

            $uuidToClassMap[$uuid] = $class;
            $classToUuidMap[$class] = $uuid;
            $uuidToPathMap[$uuid] = $path;
            $manufacturerPaths[$class] = $path;
        }

        $this->writeCacheFile('classToTypeMap', []);
        $this->writeCacheFile('classToPathMap', [
            'EntityClassDefinition' => [],
            'InventoryContainer' => $inventoryContainerPaths,
            'CargoGrid' => [],
            'SCItemManufacturer' => $manufacturerPaths,
        ]);
        $this->writeCacheFile('uuidToClassMap', $uuidToClassMap);
        $this->writeCacheFile('classToUuidMap', $classToUuidMap);
        $this->writeCacheFile('uuidToPathMap', $uuidToPathMap);
    }

    /**
     * @throws JsonException
     */
    private function writeCacheFile(string $name, array $payload): void
    {
        $path = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resetServiceState(): void
    {
        $baseService = new ReflectionClass(BaseService::class);
        foreach (['uuidToPathMap', 'uuidToClassMap', 'classToUuidMap'] as $propertyName) {
            $property = $baseService->getProperty($propertyName);
            $property->setValue(null, []);
        }

        $itemService = new ReflectionClass(ItemService::class);
        $documentCache = $itemService->getProperty('documentCache');
        $documentCache->setValue(null, []);

        $factory = new ReflectionClass(ServiceFactory::class);
        $factory->getProperty('initialized')->setValue(null, false);
        $factory->getProperty('services')->setValue(null, []);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
