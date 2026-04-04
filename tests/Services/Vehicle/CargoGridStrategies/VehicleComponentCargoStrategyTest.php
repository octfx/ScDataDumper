<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\VehicleComponentCargoStrategy;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

final class VehicleComponentCargoStrategyTest extends ScDataTestCase
{
    public function test_resolve_uses_typed_inventory_container_helper(): void
    {
        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/test_grid.xml',
            <<<'XML'
            <InventoryContainer.TEST_GRID __type="InventoryContainer" __ref="uuid-grid" __path="libs/foundry/records/inventorycontainers/test_grid.xml">
              <interiorDimensions x="1.953125" y="1" z="1" />
              <inventoryType>
                <InventoryOpenContainerType isExternalContainer="1">
                  <minPermittedItemSize x="0.1" y="0.1" z="0.1" />
                  <maxPermittedItemSize x="10" y="10" z="10" />
                </InventoryOpenContainerType>
              </inventoryType>
            </InventoryContainer.TEST_GRID>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_vehicle.xml',
            <<<'XML'
            <VehicleDefinition.TEST_VEHICLE __type="VehicleDefinition" __ref="uuid-vehicle" __path="libs/foundry/records/entities/vehicles/test_vehicle.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Vehicle" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <VehicleComponentParams inventoryContainerParams="uuid-grid" />
              </Components>
            </VehicleDefinition.TEST_VEHICLE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_VEHICLE' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_GRID' => $containerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-vehicle' => 'TEST_VEHICLE',
                'uuid-grid' => 'TEST_GRID',
            ],
            classToUuidMap: [
                'TEST_VEHICLE' => 'uuid-vehicle',
                'TEST_GRID' => 'uuid-grid',
            ],
            uuidToPathMap: [
                'uuid-vehicle' => $vehiclePath,
                'uuid-grid' => $containerPath,
            ],
        );

        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $result = new CargoGridResult;
        (new VehicleComponentCargoStrategy)->resolve(new VehicleWrapper(null, $vehicle, []), $result);

        self::assertSame(1.0, $result->totalCapacity);
        self::assertSame(['uuid-grid'], $result->existingGridUuids);
        self::assertCount(1, $result->fallbackContainers);
        self::assertSame('TEST_GRID', $result->fallbackContainers[0]->getClassName());
    }
}
