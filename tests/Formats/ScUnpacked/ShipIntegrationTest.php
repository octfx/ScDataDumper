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

    private function writeVehicleEntityFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'entity'.DIRECTORY_SEPARATOR.'test_ship.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="ship-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" manufacturer="00000000-0000-0000-0000-000000000000" />
                    </SAttachableComponentParams>
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

    private function writeLocalizationFile(): void
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'Localization'.DIRECTORY_SEPARATOR.'english';
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $path));
        }

        file_put_contents($path.DIRECTORY_SEPARATOR.'global.ini', "manufacturer_name=Fallback Industries\nLOC_EMPTY=\n");
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
     * @throws JsonException
     */
    private function writeCacheFiles(string $manufacturerPath): void
    {
        $this->writeCacheFile('classToTypeMap', []);
        $this->writeCacheFile('classToPathMap', [
            'EntityClassDefinition' => [],
            'InventoryContainer' => [],
            'CargoGrid' => [],
        ]);
        $this->writeCacheFile('uuidToClassMap', [
            self::MANUFACTURER_UUID => 'FALLBACK',
        ]);
        $this->writeCacheFile('classToUuidMap', [
            'FALLBACK' => self::MANUFACTURER_UUID,
        ]);
        $this->writeCacheFile('uuidToPathMap', [
            self::MANUFACTURER_UUID => $manufacturerPath,
        ]);
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
