<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\VehicleService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use ReflectionClass;

final class VehicleServiceTest extends ScDataTestCase
{
    public function test_load_builds_vehicle_wrapper_with_manual_loadout(): void
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
        $vehicleEntityPath = $this->writeVehicleDefinitionFile(
            'test_ship',
            'ship-wrapper-uuid',
            'ships/test_ship_impl.xml',
            [
                ['portName' => 'seat_mount', 'className' => 'SEAT_TEST'],
            ]
        );
        $implementationPath = $this->writeVehicleImplementationFile(
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

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SEAT_TEST' => $seatPath,
                    'BED_DEFAULT' => $bedPath,
                    'TEST_SHIP' => $vehicleEntityPath,
                ],
                'InventoryContainer' => [],
                'CargoGrid' => [],
            ],
            uuidToClassMap: [
                'seat-test-uuid' => 'SEAT_TEST',
                'bed-default-uuid' => 'BED_DEFAULT',
                'ship-wrapper-uuid' => 'TEST_SHIP',
            ],
            classToUuidMap: [
                'SEAT_TEST' => 'seat-test-uuid',
                'BED_DEFAULT' => 'bed-default-uuid',
                'TEST_SHIP' => 'ship-wrapper-uuid',
            ],
            uuidToPathMap: [
                'seat-test-uuid' => $seatPath,
                'bed-default-uuid' => $bedPath,
                'ship-wrapper-uuid' => $vehicleEntityPath,
            ],
        );
        $this->bootstrapItemFormattingServices();

        $service = new VehicleService($this->tempDir);
        $this->setImplementations($service, [
            'test_ship_impl.xml' => $implementationPath,
        ]);

        $wrapper = $service->load($vehicleEntityPath);

        self::assertInstanceOf(VehicleWrapper::class, $wrapper);
        self::assertSame('seat_mount', $wrapper->vehicle?->get('Parts/Part@name'));
        self::assertCount(1, $wrapper->loadout);
        self::assertSame('SEAT_TEST', $wrapper->loadout[0]['Item']['stdItem']['ClassName']);
        self::assertSame(
            'BED_DEFAULT',
            $wrapper->loadout[0]['Item']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName'] ?? null
        );
    }

    public function test_load_applies_patch_files_and_inline_modification_elements(): void
    {
        $vehicleEntityPath = $this->writeVehicleDefinitionFile(
            'modified_ship',
            'modified-ship-uuid',
            'ships/modified_ship_impl.xml',
            [],
            'damaged'
        );
        $implementationPath = $this->writeVehicleImplementationFile(
            'Data/Scripts/Entities/Vehicles/Implementations/Xml/modified_ship_impl.xml',
            <<<'XML'
            <Vehicle.MODIFIED_SHIP_IMPL>
                <Modifications>
                    <Modification name="damaged" patchFile="parts/wing_replaced">
                        <Elems>
                            <Elem idRef="target-wing" name="mass" value="42" />
                        </Elems>
                    </Modification>
                </Modifications>
                <Parts>
                    <Part id="target-wing" name="wing_original" mass="10">
                        <ItemPort display_name="Wing Mount" minSize="1" maxSize="1">
                            <Types>
                                <Type type="WeaponGun" />
                            </Types>
                        </ItemPort>
                    </Part>
                </Parts>
            </Vehicle.MODIFIED_SHIP_IMPL>
            XML
        );
        $this->writeVehicleImplementationFile(
            'Data/Scripts/Entities/Vehicles/Implementations/Xml/Modifications/wing_replaced.xml',
            <<<'XML'
            <Patch>
                <Part id="target-wing" name="wing_replaced" mass="2">
                    <ItemPort display_name="Wing Mount" minSize="1" maxSize="1">
                        <Types>
                            <Type type="WeaponGun" />
                        </Types>
                    </ItemPort>
                </Part>
            </Patch>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'MODIFIED_SHIP' => $vehicleEntityPath,
                ],
                'InventoryContainer' => [],
                'CargoGrid' => [],
            ],
            uuidToClassMap: [
                'modified-ship-uuid' => 'MODIFIED_SHIP',
            ],
            classToUuidMap: [
                'MODIFIED_SHIP' => 'modified-ship-uuid',
            ],
            uuidToPathMap: [
                'modified-ship-uuid' => $vehicleEntityPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $service = new VehicleService($this->tempDir);
        $this->setImplementations($service, [
            'modified_ship_impl.xml' => $implementationPath,
        ]);

        $wrapper = $service->load($vehicleEntityPath);

        self::assertInstanceOf(VehicleWrapper::class, $wrapper);
        self::assertSame('wing_replaced', $wrapper->vehicle?->get('Parts/Part@name'));
        self::assertSame(42.0, $wrapper->vehicle?->get('Parts/Part@mass'));
        self::assertSame([], $wrapper->loadout);
    }

    public function test_initialize_does_not_require_entity_metadata_cache(): void
    {
        $this->writeCacheFiles();
        unlink(sprintf('%s%sentityMetadataMap-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, PHP_OS_FAMILY));
        $this->writeVehicleImplementationFile(
            'Data/Scripts/Entities/Vehicles/Implementations/Xml/.gitkeep',
            '<placeholder />'
        );

        $service = new VehicleService($this->tempDir);
        $this->setImplementations($service, []);
        $service->initialize();

        self::assertSame(0, $service->count());
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
        array $manualLoadout,
        ?string $modification = null
    ): string {
        $modificationAttribute = $modification !== null ? sprintf(' modification="%s"', $modification) : '';
        $loadoutXml = $manualLoadout === []
            ? ''
            : sprintf(
                '<SEntityComponentDefaultLoadoutParams><loadout><SItemPortLoadoutManualParams><entries>%s</entries></SItemPortLoadoutManualParams></loadout></SEntityComponentDefaultLoadoutParams>',
                $this->renderLoadoutEntries($manualLoadout)
            );

        return $this->writeFile(
            sprintf('entities/spaceships/%s.xml', $className),
            sprintf(
                '<?xml version="1.0" encoding="UTF-8"?><VehicleDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="entities/spaceships/%3$s.xml"><Components><SAttachableComponentParams><AttachDef Type="Vehicle" SubType="Ship" /></SAttachableComponentParams><VehicleComponentParams vehicleDefinition="%4$s"%5$s><English vehicleName="" vehicleDescription="" vehicleCareer="" vehicleRole="" /></VehicleComponentParams>%6$s</Components></VehicleDefinition.%1$s>',
                strtoupper($className),
                $uuid,
                strtolower($className),
                $implementation,
                $modificationAttribute,
                $loadoutXml
            )
        );
    }

    private function writeVehicleImplementationFile(string $relativePath, string $xml): string
    {
        return $this->writeFile($relativePath, $xml);
    }

    /**
     * @param  array<string, string>  $implementations
     */
    private function setImplementations(VehicleService $service, array $implementations): void
    {
        $reflection = new ReflectionClass($service);
        $reflection->getProperty('implementations')->setValue($service, $implementations);
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

            if (($entry['classReference'] ?? null) !== null) {
                $attributes[] = sprintf('entityClassReference="%s"', $entry['classReference']);
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
}
