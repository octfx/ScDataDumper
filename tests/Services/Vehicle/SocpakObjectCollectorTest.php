<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;
use Octfx\ScDataDumper\Services\Vehicle\SocpakObjectCollector;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use RuntimeException;
use ZipArchive;

final class SocpakObjectCollectorTest extends ScDataTestCase
{
    public function test_collects_source_a_and_source_b_occurrences_in_order_and_emits_per_section(): void
    {
        $crewSocpak = $this->writeSocpak(
            'Data/ObjectContainers/Ships/Test/crew_quarters.socpak',
            <<<'XML'
            <Objects>
              <Layer name="CrewLayer">
                <Object type="Bed_Test" name="Bed_Instance" />
                <Object type="PersonalStorage_Test" name="Storage_Instance" />
              </Layer>
            </Objects>
            XML,
        );
        $moduleSocpak = $this->writeSocpak(
            'Data/ObjectContainers/Ships/Test/module_room.socpak',
            <<<'XML'
            <Objects>
              <Object type="Weapon_Rack_Test" name="Rack_Instance" />
            </Objects>
            XML,
        );

        $vehicle = $this->loadVehicleDefinition(<<<'XML'
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="uuid-ship" __path="entities/spaceships/test_ship.xml">
              <Components>
                <VehicleComponentParams>
                  <objectContainers>
                    <SVehicleObjectContainerParams fileName="ObjectContainers/Ships/Test/crew_quarters.socpak" boneName="crew_a" />
                    <SVehicleObjectContainerParams fileName="ObjectContainers/Ships/Test/crew_quarters.socpak" boneName="crew_b" />
                    <SVehicleObjectContainerParams boneName="missing_file" />
                    <SVehicleObjectContainerParams fileName="ObjectContainers/Ships/Test/module_room.socpak" boneName="module_a" />
                  </objectContainers>
                </VehicleComponentParams>
              </Components>
            </VehicleDefinition.TEST_SHIP>
            XML);

        $loadout = [[
            'portName' => 'module_loadout',
            'ItemRaw' => [
                'Components' => [
                    'SObjectContainerComponentParams' => [
                        'objectContainer' => 'ObjectContainers/Ships/Test/module_room.socpak',
                    ],
                ],
            ],
            'entries' => [[
                'portName' => 'nested_crew',
                'ItemRaw' => [
                    'Components' => [
                        'SObjectContainerComponentParams' => [
                            'objectContainer' => 'ObjectContainers/Ships/Test/crew_quarters.socpak',
                        ],
                    ],
                ],
                'entries' => [],
            ]],
        ]];

        $objects = (new SocpakObjectCollector(new SocpakReader($this->tempDir)))->collectAll($vehicle, $loadout);

        self::assertSame([
            ['Bed_Test', 'Bed_Instance', 'crew_a', 'CrewLayer', $crewSocpak],
            ['PersonalStorage_Test', 'Storage_Instance', 'crew_a', 'CrewLayer', $crewSocpak],
            ['Bed_Test', 'Bed_Instance', 'crew_b', 'CrewLayer', $crewSocpak],
            ['PersonalStorage_Test', 'Storage_Instance', 'crew_b', 'CrewLayer', $crewSocpak],
            ['Weapon_Rack_Test', 'Rack_Instance', 'module_a', null, $moduleSocpak],
            ['Weapon_Rack_Test', 'Rack_Instance', 'module_loadout', null, $moduleSocpak],
            ['Bed_Test', 'Bed_Instance', 'nested_crew', 'CrewLayer', $crewSocpak],
            ['PersonalStorage_Test', 'Storage_Instance', 'nested_crew', 'CrewLayer', $crewSocpak],
        ], array_map(
            static fn ($object): array => [
                $object->className,
                $object->instanceName,
                $object->section,
                $object->layer,
                $object->socpakPath,
            ],
            $objects,
        ));
    }

    private function loadVehicleDefinition(string $xml): VehicleDefinition
    {
        $path = $this->writeFile('Data/Libs/Foundry/Records/entities/spaceships/test_ship.xml', $xml);
        $vehicle = new VehicleDefinition;
        $vehicle->load($path);

        return $vehicle;
    }

    private function writeSocpak(string $relativePath, string $editorXml): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.$relativePath;
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(sprintf('Failed to create socpak: %s', $path));
        }

        $zip->addFromString('test_editor.xml', trim($editorXml));
        $zip->close();

        return str_replace('\\', '/', $path);
    }
}
