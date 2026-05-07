<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\InventoryContainerResolver;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class InventoryContainerResolverTest extends ScDataTestCase
{
    public function test_vehicle_container_uuid_dedup_prevents_double_counting(): void
    {
        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/personal/test_ps.xml',
            <<<'XML'
            <InventoryContainer.TEST_PERSONAL_STORAGE __type="InventoryContainer" __ref="uuid-ps-container" __path="libs/foundry/records/inventorycontainers/personal/test_ps.xml">
              <interiorDimensions x="0.5" y="0.5" z="0.5" />
              <inventoryType>
                <InventoryClosedContainerType>
                  <capacity>
                    <SStandardCargoUnit standardCargoUnits="0.5" />
                  </capacity>
                </InventoryClosedContainerType>
              </inventoryType>
            </InventoryContainer.TEST_PERSONAL_STORAGE>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_dedup_vehicle.xml',
            <<<'XML'
            <VehicleDefinition.TEST_DEDUP_VEHICLE __type="VehicleDefinition" __ref="uuid-dedup-vehicle" __path="libs/foundry/records/entities/vehicles/test_dedup_vehicle.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Dedup Vehicle" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SCItemInventoryContainerComponentParams containerParams="uuid-ps-container" />
              </Components>
            </VehicleDefinition.TEST_DEDUP_VEHICLE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_DEDUP_VEHICLE' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_PERSONAL_STORAGE' => $containerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-dedup-vehicle' => 'TEST_DEDUP_VEHICLE',
                'uuid-ps-container' => 'TEST_PERSONAL_STORAGE',
            ],
            classToUuidMap: [
                'TEST_DEDUP_VEHICLE' => 'uuid-dedup-vehicle',
                'TEST_PERSONAL_STORAGE' => 'uuid-ps-container',
            ],
            uuidToPathMap: [
                'uuid-dedup-vehicle' => $vehiclePath,
                'uuid-ps-container' => $containerPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        // Loadout item whose container resolves to the same UUID as the vehicle's
        // own inventory container — simulates the Gladius SeatAccess scenario.
        $loadout = [[
            'portName' => 'hardpoint_seat_access',
            'className' => 'TEST_DEDUP_VEHICLE',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'SeatAccess_DuplicateRef',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'UNDEFINED',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'uuid-ps-container',
                    ],
                ],
            ],
        ]];

        // No extractor injected — the resolver lazily creates one that finds no socpak files in the test temp dir
        $resolver = new InventoryContainerResolver;

        $result = $resolver->resolveInventoryContainers(
            new VehicleWrapper(null, $vehicle, $loadout),
        );

        // The container should appear exactly once, sourced from 'vehicle'
        self::assertCount(1, $result->containers);
        self::assertSame('vehicle', $result->containers->first()['source']);
        self::assertSame('uuid-ps-container', $result->containers->first()['uuid']);
    }

    public function test_loadout_container_not_deduped_when_vehicle_has_no_container(): void
    {
        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/personal/test_standalone.xml',
            <<<'XML'
            <InventoryContainer.TEST_STANDALONE_PS __type="InventoryContainer" __ref="uuid-standalone-ps" __path="libs/foundry/records/inventorycontainers/personal/test_standalone.xml">
              <interiorDimensions x="0.5" y="0.5" z="0.5" />
              <inventoryType>
                <InventoryClosedContainerType>
                  <capacity>
                    <SStandardCargoUnit standardCargoUnits="1.0" />
                  </capacity>
                </InventoryClosedContainerType>
              </inventoryType>
            </InventoryContainer.TEST_STANDALONE_PS>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_no_container_vehicle.xml',
            <<<'XML'
            <VehicleDefinition.TEST_NO_CONTAINER_VEHICLE __type="VehicleDefinition" __ref="uuid-no-container-vehicle" __path="libs/foundry/records/entities/vehicles/test_no_container_vehicle.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test No Container Vehicle" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </VehicleDefinition.TEST_NO_CONTAINER_VEHICLE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_NO_CONTAINER_VEHICLE' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_STANDALONE_PS' => $containerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-no-container-vehicle' => 'TEST_NO_CONTAINER_VEHICLE',
                'uuid-standalone-ps' => 'TEST_STANDALONE_PS',
            ],
            classToUuidMap: [
                'TEST_NO_CONTAINER_VEHICLE' => 'uuid-no-container-vehicle',
                'TEST_STANDALONE_PS' => 'uuid-standalone-ps',
            ],
            uuidToPathMap: [
                'uuid-no-container-vehicle' => $vehiclePath,
                'uuid-standalone-ps' => $containerPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        // Vehicle has no container; loadout item provides one — should be included.
        $loadout = [[
            'portName' => 'hardpoint_storage',
            'className' => 'SomeItem',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'LoadoutStorageItem',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'UNDEFINED',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'uuid-standalone-ps',
                    ],
                ],
            ],
        ]];

        // No extractor injected — the resolver lazily creates one that finds no socpak files in the test temp dir
        $resolver = new InventoryContainerResolver;

        $result = $resolver->resolveInventoryContainers(
            new VehicleWrapper(null, $vehicle, $loadout),
        );

        self::assertCount(1, $result->containers);
        self::assertSame('loadout', $result->containers->first()['source']);
        self::assertSame('uuid-standalone-ps', $result->containers->first()['uuid']);
    }

    public function test_different_containers_both_appear(): void
    {
        $vehicleContainerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/personal/test_vehicle_ps.xml',
            <<<'XML'
            <InventoryContainer.TEST_VEHICLE_PS __type="InventoryContainer" __ref="uuid-vehicle-ps" __path="libs/foundry/records/inventorycontainers/personal/test_vehicle_ps.xml">
              <interiorDimensions x="0.5" y="0.5" z="0.5" />
              <inventoryType>
                <InventoryClosedContainerType>
                  <capacity>
                    <SStandardCargoUnit standardCargoUnits="0.5" />
                  </capacity>
                </InventoryClosedContainerType>
              </inventoryType>
            </InventoryContainer.TEST_VEHICLE_PS>
            XML
        );
        $loadoutContainerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/personal/test_loadout_ps.xml',
            <<<'XML'
            <InventoryContainer.TEST_LOADOUT_PS __type="InventoryContainer" __ref="uuid-loadout-ps" __path="libs/foundry/records/inventorycontainers/personal/test_loadout_ps.xml">
              <interiorDimensions x="0.5" y="0.5" z="0.5" />
              <inventoryType>
                <InventoryClosedContainerType>
                  <capacity>
                    <SStandardCargoUnit standardCargoUnits="1.0" />
                  </capacity>
                </InventoryClosedContainerType>
              </inventoryType>
            </InventoryContainer.TEST_LOADOUT_PS>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_two_container_vehicle.xml',
            <<<'XML'
            <VehicleDefinition.TEST_TWO_CONTAINER_VEHICLE __type="VehicleDefinition" __ref="uuid-two-container-vehicle" __path="libs/foundry/records/entities/vehicles/test_two_container_vehicle.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Two Container Vehicle" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SCItemInventoryContainerComponentParams containerParams="uuid-vehicle-ps" />
              </Components>
            </VehicleDefinition.TEST_TWO_CONTAINER_VEHICLE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_TWO_CONTAINER_VEHICLE' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_VEHICLE_PS' => $vehicleContainerPath,
                    'TEST_LOADOUT_PS' => $loadoutContainerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-two-container-vehicle' => 'TEST_TWO_CONTAINER_VEHICLE',
                'uuid-vehicle-ps' => 'TEST_VEHICLE_PS',
                'uuid-loadout-ps' => 'TEST_LOADOUT_PS',
            ],
            classToUuidMap: [
                'TEST_TWO_CONTAINER_VEHICLE' => 'uuid-two-container-vehicle',
                'TEST_VEHICLE_PS' => 'uuid-vehicle-ps',
                'TEST_LOADOUT_PS' => 'uuid-loadout-ps',
            ],
            uuidToPathMap: [
                'uuid-two-container-vehicle' => $vehiclePath,
                'uuid-vehicle-ps' => $vehicleContainerPath,
                'uuid-loadout-ps' => $loadoutContainerPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $loadout = [[
            'portName' => 'hardpoint_storage',
            'className' => 'SomeItem',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'LoadoutStorageItem',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'UNDEFINED',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'uuid-loadout-ps',
                    ],
                ],
            ],
        ]];

        // No extractor injected — the resolver lazily creates one that finds no socpak files in the test temp dir
        $resolver = new InventoryContainerResolver;

        $result = $resolver->resolveInventoryContainers(
            new VehicleWrapper(null, $vehicle, $loadout),
        );

        // Both containers should appear with correct sources
        self::assertCount(2, $result->containers);
        $sources = $result->containers->pluck('source')->sort()->values()->all();
        self::assertEqualsCanonicalizing(['loadout', 'vehicle'], $sources);

        $uuids = $result->containers->pluck('uuid')->all();
        self::assertEqualsCanonicalizing(['uuid-vehicle-ps', 'uuid-loadout-ps'], $uuids);
    }
}
