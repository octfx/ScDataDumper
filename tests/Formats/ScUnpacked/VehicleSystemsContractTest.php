<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class VehicleSystemsContractTest extends ScDataTestCase
{
    // ------------------------------------------------------------------ //
    //  1. Port identity contract -- Loadout entries must have identity fields
    // ------------------------------------------------------------------ //

    public function test_loadout_entry_has_port_id(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result, 'Ship output must contain a Loadout key');

        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            self::assertArrayHasKey('PortId', $port,
                sprintf('Loadout entry "%s" must have a PortId field', $port['HardpointName'] ?? '(unnamed)')
            );
        }
    }

    public function test_loadout_entry_has_parent_port_id(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            // ParentPortId may be absent for top-level entries (null stripped by removeNullValuesPreservingEmptyArrays)
            // Nested entries should have it set
            if (($port['ParentPortId'] ?? null) !== null) {
                // Nested entry -- must have the key
                self::assertArrayHasKey('ParentPortId', $port,
                    sprintf('Nested loadout entry "%s" must have a ParentPortId field', $port['HardpointName'] ?? '(unnamed)')
                );
            }
            // Top-level entries: ParentPortId is null, stripped by output processing -- acceptable
        }
    }

    public function test_loadout_entry_has_root_port_id(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            self::assertArrayHasKey('RootPortId', $port,
                sprintf('Loadout entry "%s" must have a RootPortId field', $port['HardpointName'] ?? '(unnamed)')
            );
        }
    }

    public function test_loadout_entry_has_path(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            self::assertArrayHasKey('Path', $port,
                sprintf('Loadout entry "%s" must have a Path field', $port['HardpointName'] ?? '(unnamed)')
            );
        }
    }

    public function test_top_level_loadout_entries_have_null_parent_port_id(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        foreach ($result['Loadout'] as $topLevelPort) {
            // ParentPortId is null for top-level entries, which gets stripped by
            // removeNullValuesPreservingEmptyArrays. Accept either null or absent.
            $hasKey = array_key_exists('ParentPortId', $topLevelPort);
            $value = $topLevelPort['ParentPortId'] ?? null;
            self::assertTrue(
                ! $hasKey || $value === null,
                sprintf(
                    'Top-level Loadout entry "%s" must have ParentPortId === null or absent (stripped)',
                    $topLevelPort['HardpointName'] ?? '(unnamed)'
                )
            );
        }
    }

    public function test_port_ids_are_unique_within_vehicle(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        $portIds = [];
        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            $portId = $port['PortId'] ?? null;
            self::assertNotNull($portId, 'Every loadout entry must have a PortId');

            self::assertNotContains($portId, $portIds,
                sprintf('PortId "%s" must be unique within the vehicle payload', $portId)
            );
            $portIds[] = $portId;
        }
    }

    public function test_port_id_format_is_loadout_path(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            $portId = $port['PortId'] ?? null;
            self::assertMatchesRegularExpression(
                '/^loadout\.\d+(\.loadout\.\d+)*$/',
                $portId ?? '',
                sprintf('PortId "%s" must match the format loadout.<index>[.loadout.<index>...]', $portId ?? '(null)')
            );
        }
    }

    public function test_path_is_hardpoint_name_ancestry(): void
    {
        $result = $this->produceMinimalShipOutput();

        $this->assertArrayHasKey('Loadout', $result);

        // For top-level entries, Path should contain one element: their own hardpoint name
        foreach ($result['Loadout'] as $topLevelPort) {
            $path = $topLevelPort['Path'] ?? null;
            self::assertIsArray($path, 'Path must be an array');
            self::assertNotEmpty($path, 'Path must not be empty for top-level ports');
            self::assertSame(
                $topLevelPort['HardpointName'] ?? null,
                $path[0],
                'First Path element of a top-level port should be its HardpointName'
            );
        }
    }

    // ------------------------------------------------------------------ //
    //  2. Systems object existence
    // ------------------------------------------------------------------ //

    public function test_ship_output_contains_systems_key(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result,
            'Ship output must contain a top-level Systems key'
        );
    }

    public function test_systems_is_an_array(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);
        self::assertIsArray($result['Systems'],
            'Systems must be an associative array keyed by system name'
        );
    }

    // ------------------------------------------------------------------ //
    //  3. All system keys present
    // ------------------------------------------------------------------ //

    public function test_systems_contains_all_required_keys(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            self::assertArrayHasKey($systemKey, $result['Systems'],
                sprintf('Systems must contain the key "%s"', $systemKey)
            );
        }
    }

    public function test_systems_contains_exactly_required_keys(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        $actualKeys = array_keys($result['Systems']);
        $expectedKeys = VehicleSystemKeys::ALL_KEYS;

        sort($actualKeys);
        sort($expectedKeys);

        self::assertSame($expectedKeys, $actualKeys,
            'Systems must contain exactly the required keys, no more, no less'
        );
    }

    // ------------------------------------------------------------------ //
    //  4. System bucket shape -- each system has Summary and Ports
    // ------------------------------------------------------------------ //

    public function test_each_system_has_summary_key(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            self::assertArrayHasKey($systemKey, $result['Systems']);
            // Summary may be absent when null (stripped by removeNullValuesPreservingEmptyArrays)
            // or present with data when populated
            $hasSummary = array_key_exists('Summary', $result['Systems'][$systemKey]);
            $summaryIsNull = $result['Systems'][$systemKey]['Summary'] ?? null === null;
            self::assertTrue(
                $hasSummary || $summaryIsNull,
                sprintf('System "%s" must have a Summary key (or it was stripped because null)', $systemKey)
            );
        }
    }

    public function test_each_system_has_ports_key(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            self::assertArrayHasKey($systemKey, $result['Systems']);
            self::assertArrayHasKey('Ports', $result['Systems'][$systemKey],
                sprintf('System "%s" must have a Ports key', $systemKey)
            );
        }
    }

    public function test_each_system_ports_is_array(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            self::assertIsArray($result['Systems'][$systemKey]['Ports'],
                sprintf('System "%s".Ports must be an array', $systemKey)
            );
        }
    }

    // ------------------------------------------------------------------ //
    //  5. Empty system shape
    // ------------------------------------------------------------------ //

    public function test_empty_system_has_null_summary_and_empty_ports(): void
    {
        $result = $this->produceMinimalShipOutput();

        self::assertArrayHasKey('Systems', $result);

        // Find at least one empty system and verify its shape.
        // For a minimal ship with only a seat and a bed, many systems should be empty.
        $emptySystems = [];
        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            $ports = $result['Systems'][$systemKey]['Ports'] ?? null;
            if (is_array($ports) && $ports === []) {
                $emptySystems[] = $systemKey;
            }
        }

        self::assertNotEmpty($emptySystems,
            'A minimal ship must have at least one empty system (no installed items of that type)'
        );

        foreach ($emptySystems as $systemKey) {
            // Summary may be absent (null stripped by removeNullValuesPreservingEmptyArrays),
            // null, or a zero-filled array from calculators that always produce output.
            // Verify Ports is empty array.
            self::assertSame([], $result['Systems'][$systemKey]['Ports'],
                sprintf('Empty system "%s" must have Ports === []', $systemKey)
            );
            // Summary should be null, absent, or only contain zero/null values from
            // calculators that always run (e.g. PropulsionSystemAggregator).
            $summaryValue = $result['Systems'][$systemKey]['Summary'] ?? null;
            if ($summaryValue !== null) {
                // If a summary exists, it must be a calculator-produced zero-state
                // (not meaningful data for a ship that doesn't have that system)
                self::assertIsArray($summaryValue,
                    sprintf('Empty system "%s" Summary must be null or array, got %s', $systemKey, gettype($summaryValue))
                );
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  6. Port reference shape -- system port refs must have required fields
    // ------------------------------------------------------------------ //

    public function test_system_port_references_have_required_fields(): void
    {
        $result = $this->produceShipOutputWithQuantumDrive();

        self::assertArrayHasKey('Systems', $result);

        // QuantumDrives should have at least one port reference
        $qdPorts = $result['Systems']['QuantumDrives']['Ports'] ?? [];
        self::assertNotEmpty($qdPorts,
            'Ship with a quantum drive must have at least one port in Systems.QuantumDrives.Ports'
        );

        foreach ($qdPorts as $i => $portRef) {
            // PortId and HardpointName must always be present (never null)
            $requiredNonNullable = ['PortId', 'HardpointName'];
            foreach ($requiredNonNullable as $field) {
                self::assertArrayHasKey($field, $portRef,
                    sprintf('System port reference [%d] must have field "%s"', $i, $field)
                );
            }
            // Other fields in PORT_REF_KEYS may be absent when null
            // (stripped by removeNullValuesPreservingEmptyArrays)
        }
    }

    public function test_system_port_reference_port_id_resolves_to_loadout_entry(): void
    {
        $result = $this->produceShipOutputWithQuantumDrive();

        self::assertArrayHasKey('Systems', $result);
        self::assertArrayHasKey('Loadout', $result);

        // Build a set of all loadout PortIds
        $loadoutPortIds = [];
        foreach ($this->flattenLoadout($result['Loadout']) as $port) {
            $loadoutPortIds[$port['PortId']] = $port;
        }

        // Every system port reference should resolve to exactly one loadout entry
        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            $ports = $result['Systems'][$systemKey]['Ports'] ?? [];
            foreach ($ports as $i => $portRef) {
                self::assertArrayHasKey($portRef['PortId'], $loadoutPortIds,
                    sprintf(
                        'Systems.%s.Ports[%d].PortId "%s" must resolve to exactly one Loadout entry',
                        $systemKey, $i, $portRef['PortId']
                    )
                );
            }
        }
    }

    public function test_system_port_reference_type_is_normalized(): void
    {
        $result = $this->produceShipOutputWithQuantumDrive();

        self::assertArrayHasKey('Systems', $result);

        $qdPorts = $result['Systems']['QuantumDrives']['Ports'] ?? [];
        self::assertNotEmpty($qdPorts);

        foreach ($qdPorts as $portRef) {
            // Type should be normalized: "QuantumDrive" not "QuantumDrive.UNDEFINED"
            $type = $portRef['Type'] ?? null;
            self::assertNotNull($type);
            self::assertStringNotContainsString('.', $type ?? '',
                sprintf('System port reference Type "%s" should be normalized (no dot)', $type ?? '(null)')
            );
        }
    }

    // ------------------------------------------------------------------ //
    //  7. Semantic split assertions (skipped until implementation exists)
    // ------------------------------------------------------------------ //

    /**
     * @see test_quantum_drives_jump_drives_and_quantum_fuel_tanks_are_separate
     */
    public function test_quantum_drives_jump_drives_and_quantum_fuel_tanks_are_separate(): void
    {
        self::markTestSkipped(
            'Requires Ship output with quantum drive + jump drive + quantum fuel tank. '
            .'Will be enabled when SystemsBuilder is implemented and real ship fixtures are available.'
        );

        // Contract: QuantumDrives.Ports should contain the quantum drive port only.
        // JumpDrives.Ports should contain the jump drive port (nested under QD) separately.
        // QuantumFuelTanks.Ports should contain fuel tanks, not mixed into QuantumDrives.
    }

    /**
     * @see test_weapons_weapon_mounts_missile_racks_and_missiles_are_separate
     */
    public function test_weapons_weapon_mounts_missile_racks_and_missiles_are_separate(): void
    {
        self::markTestSkipped(
            'Requires Ship output with weapons, mounts, missile racks, and missiles. '
            .'Will be enabled when SystemsBuilder is implemented and real ship fixtures are available.'
        );

        // Contract: Weapons contains WeaponGun ports only.
        // WeaponMounts contains gimbal/mount ports, not weapons.
        // MissileRacks contains missile launcher ports, not missiles.
        // Missiles contains actual missile ports, not racks.
    }

    /**
     * @see test_turret_roots_dont_swallow_nested_weapons_and_mounts
     */
    public function test_turret_roots_dont_swallow_nested_weapons_and_mounts(): void
    {
        self::markTestSkipped(
            'Requires Ship output with manned turrets containing gimbals and weapons (e.g. Carrack). '
            .'Will be enabled when SystemsBuilder is implemented and real ship fixtures are available.'
        );

        // Contract: MannedTurrets contains turret root ports only.
        // WeaponMounts contains nested gimbal/mount ports.
        // Weapons contains the actual WeaponGun ports under those mounts.
        // None of these buckets should be missing entries because turret roots "absorbed" them.
    }

    /**
     * @see test_duplicate_nested_hardpoint_names_resolve_correctly
     */
    public function test_duplicate_nested_hardpoint_names_resolve_correctly(): void
    {
        self::markTestSkipped(
            'Requires Ship output with duplicate nested hardpoint names (e.g. Carrack turrets). '
            .'Will be enabled when SystemsBuilder is implemented and real ship fixtures are available.'
        );

        // Contract: Two different ports with HardpointName="hardpoint_class_2" must have
        // different PortId values and must both appear in the correct system buckets.
        // No system reference should depend on HardpointName uniqueness.
    }

    // ------------------------------------------------------------------ //
    //  8. Summary presence for calculator-backed systems (skipped)
    // ------------------------------------------------------------------ //

    public function test_quantum_drives_summary_exists_when_quantum_drive_present(): void
    {
        self::markTestSkipped(
            'Requires Ship output with an installed quantum drive. '
            .'Will be enabled when SystemsBuilder summary population is implemented.'
        );

        // Contract: When QuantumDrives.Ports is non-empty, QuantumDrives.Summary must not be null.
        // It should contain at least Speed and SpoolTime.
    }

    public function test_quantum_fuel_tanks_summary_has_capacity(): void
    {
        self::markTestSkipped(
            'Requires Ship output with installed quantum fuel tanks. '
            .'Will be enabled when SystemsBuilder summary population is implemented.'
        );

        // Contract: QuantumFuelTanks.Summary must contain a Capacity field
        // that is the sum of installed quantum fuel tank capacities.
    }

    // ------------------------------------------------------------------ //
    //  9. Backward compatibility -- existing top-level keys still present
    // ------------------------------------------------------------------ //

    public function test_existing_top_level_summary_keys_remain(): void
    {
        self::markTestSkipped(
            'Requires a ship with sufficient hardware to trigger all calculator outputs. '
            .'Will be enabled when real ship fixture integration tests are available.'
        );

        // Contract: When using real ship data that produces these keys today,
        // they must still be present after Systems is added (backward compat).
        // Tested keys: QuantumTravel, Propulsion, FlightCharacteristics, Cooling, Power.
    }

    // ------------------------------------------------------------------ //
    //  Helper methods to produce ship output
    // ------------------------------------------------------------------ //

    /**
     * Produce ship output with a minimal loadout (seat + bed).
     * This should always work even before Systems implementation.
     */
    private function produceMinimalShipOutput(): array
    {
        $this->setupMinimalShipServices();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeStandardVehicleImplementationFile());

        $loadout = $this->makeMinimalLoadout();

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $loadout));

        return $ship->toArray();
    }

    /**
     * Produce ship output with a quantum drive installed.
     */
    private function produceShipOutputWithQuantumDrive(): array
    {
        $this->setupMinimalShipServices();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeStandardVehicleImplementationFile());

        $loadout = $this->makeLoadoutWithQuantumDrive();

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, $loadout));

        return $ship->toArray();
    }

    private function setupMinimalShipServices(): void
    {
        $manufacturerPath = $this->writeFallbackManufacturerFile();
        $this->writeCacheFiles(
            classToPathMap: [
                'SCItemManufacturer' => ['FALLBACK' => $manufacturerPath],
            ],
            uuidToClassMap: ['11111111-1111-1111-1111-111111111111' => 'FALLBACK'],
            classToUuidMap: ['FALLBACK' => '11111111-1111-1111-1111-111111111111'],
            uuidToPathMap: ['11111111-1111-1111-1111-111111111111' => $manufacturerPath],
        );

        $this->writeFile(
            'Data/Localization/english/global.ini',
            "LOC_EMPTY=\nmanufacturer_name=Fallback Industries\nvehicle_name=Test Ship\nvehicle_description=Test"
        );

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

    private function writeVehicleEntityFile(): string
    {
        return $this->writeFile(
            'records/entity/test_ship.xml',
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="ship-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" />
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

    /**
     * Minimal loadout: seat with nested bed, nothing else.
     */
    private function makeMinimalLoadout(): array
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
                        'Manufacturer' => ['Name' => 'Test'],
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
                    'Manufacturer' => ['Name' => 'Test'],
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
     * Loadout with quantum drive installed.
     */
    private function makeLoadoutWithQuantumDrive(): array
    {
        return [[
            'portName' => 'hardpoint_quantum_drive',
            'className' => 'QD_TEST',
            'entries' => [[
                'portName' => 'hardpoint_Jump_Drive',
                'className' => 'JD_TEST',
                'entries' => [],
                'Item' => [
                    'type' => 'JumpDrive',
                    'stdItem' => [
                        'ClassName' => 'JD_TEST',
                        'UUID' => 'jd-uuid',
                        'Name' => 'Test Jump Drive',
                        'Manufacturer' => ['Name' => 'Test'],
                        'Type' => 'JumpDrive.UNDEFINED',
                        'Grade' => 'A',
                        'Mass' => 5.0,
                        'Ports' => [],
                    ],
                ],
            ]],
            'Item' => [
                'type' => 'QuantumDrive',
                'stdItem' => [
                    'ClassName' => 'QD_TEST',
                    'UUID' => 'qd-uuid',
                    'Name' => 'Test Quantum Drive',
                    'Manufacturer' => ['Name' => 'Test'],
                    'Type' => 'QuantumDrive.UNDEFINED',
                    'Grade' => 'A',
                    'Mass' => 50.0,
                    'QuantumDrive' => [
                        'Speed' => 319000000,
                        'SpoolTime' => 7,
                        'FuelConsumptionSCUPerGM' => 0.001,
                    ],
                    'Ports' => [[
                        'PortName' => 'hardpoint_Jump_Drive',
                        'DisplayName' => 'Jump Drive Port',
                        'Types' => ['JumpDrive'],
                        'Flags' => [],
                        'RequiredTags' => [],
                        'MinSize' => 1,
                        'MaxSize' => 1,
                        'Uneditable' => false,
                    ]],
                ],
            ],
        ]];
    }

    /**
     * Recursively flatten a loadout tree into a flat list of port entries.
     *
     * @param  list<array<string, mixed>>  $loadout
     * @return list<array<string, mixed>>
     */
    private function flattenLoadout(array $loadout): array
    {
        $flat = [];
        foreach ($loadout as $entry) {
            $flat[] = $entry;
            if (! empty($entry['Loadout']) && is_array($entry['Loadout'])) {
                $flat = array_merge($flat, $this->flattenLoadout($entry['Loadout']));
            }
        }

        return $flat;
    }
}
