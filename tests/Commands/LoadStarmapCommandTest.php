<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadStarmap;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use ZipArchive;

final class LoadStarmapCommandTest extends ScDataTestCase
{
    public function test_execute_writes_lazy_resolved_starmap_export(): void
    {
        $objectPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/test/test_object.xml',
            <<<'XML'
            <StarMapObject.TestObject name="@loc_starmap_name" description="@loc_starmap_desc" locationHierarchyTag="tag-location" jurisdiction="30000000-0000-0000-0000-000000000003" type="30000000-0000-0000-0000-000000000002" affiliation="30000000-0000-0000-0000-000000000005" navIcon="planet" respawnLocationType="station" isScannable="1" hideInStarmap="0" hideInWorld="0" hideWhenInAdoptionRadius="0" blockTravel="0" onlyShowWhenParentSelected="0" showOrbitLine="1" noAutoBodyRecovery="0" size="100" minimumDisplaySize="10" __type="StarMapObject" __ref="30000000-0000-0000-0000-000000000001" __path="libs/foundry/records/starmap/pu/system/test/test_object.xml">
              <radarProperties>
                <SSCRadarContactProperites contactType="30000000-0000-0000-0000-000000000004" />
              </radarProperties>
              <amenities>
                <Reference value="30000000-0000-0000-0000-000000000006" />
              </amenities>
            </StarMapObject.TestObject>
            XML
        );
        $typePath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/types/test_type.xml',
            <<<'XML'
            <StarMapObjectType.TestType name="@loc_type_name" classification="@loc_type_class" spawnNavPoints="1" validQuantumTravelDestination="1" __type="StarMapObjectType" __ref="30000000-0000-0000-0000-000000000002" __path="libs/foundry/records/starmap/types/test_type.xml" />
            XML
        );
        $jurisdictionPath = $this->writeFile(
            'Game2/libs/foundry/records/lawsystem/jurisdictions/test_jurisdiction.xml',
            <<<'XML'
            <Jurisdiction.TestJurisdiction name="@loc_jurisdiction_name" baseFine="500" maxStolenGoodsPossessionSCU="32" isPrison="0" __type="Jurisdiction" __ref="30000000-0000-0000-0000-000000000003" __path="libs/foundry/records/lawsystem/jurisdictions/test_jurisdiction.xml" />
            XML
        );
        $radarPath = $this->writeFile(
            'Game2/libs/foundry/records/radarsystem/test_radar_contact.xml',
            <<<'XML'
            <RadarContactTypeEntry.TestRadar name="@loc_radar_name" displayName="@loc_radar_display" tag="tag-radar" isObjectOfInterest="1" __type="RadarContactTypeEntry" __ref="30000000-0000-0000-0000-000000000004" __path="libs/foundry/records/radarsystem/test_radar_contact.xml" />
            XML
        );
        $factionPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/test_faction.xml',
            <<<'XML'
            <Faction.TestFaction displayName="@loc_faction_name" name="FactionFallback" __type="Faction" __ref="30000000-0000-0000-0000-000000000005" __path="libs/foundry/records/factions/test_faction.xml" />
            XML
        );
        $amenityPath = $this->writeFile(
            'Game2/libs/foundry/records/starmapamenitytypes/test_amenity.xml',
            <<<'XML'
            <StarMapAmenityTypeEntry.TestAmenity name="@loc_amenity_name" displayName="@loc_amenity_display" __type="StarMapAmenityTypeEntry" __ref="30000000-0000-0000-0000-000000000006" __path="libs/foundry/records/starmapamenitytypes/test_amenity.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'StarMapObject' => ['TestObject' => $objectPath],
                'StarMapObjectType' => ['TestType' => $typePath],
                'Jurisdiction' => ['TestJurisdiction' => $jurisdictionPath],
                'RadarContactTypeEntry' => ['TestRadar' => $radarPath],
                'Faction' => ['TestFaction' => $factionPath],
                'StarMapAmenityTypeEntry' => ['TestAmenity' => $amenityPath],
            ],
            uuidToClassMap: [
                '30000000-0000-0000-0000-000000000001' => 'TestObject',
                '30000000-0000-0000-0000-000000000002' => 'TestType',
                '30000000-0000-0000-0000-000000000003' => 'TestJurisdiction',
                '30000000-0000-0000-0000-000000000004' => 'TestRadar',
                '30000000-0000-0000-0000-000000000005' => 'TestFaction',
                '30000000-0000-0000-0000-000000000006' => 'TestAmenity',
            ],
            classToUuidMap: [
                'TestObject' => '30000000-0000-0000-0000-000000000001',
                'TestType' => '30000000-0000-0000-0000-000000000002',
                'TestJurisdiction' => '30000000-0000-0000-0000-000000000003',
                'TestRadar' => '30000000-0000-0000-0000-000000000004',
                'TestFaction' => '30000000-0000-0000-0000-000000000005',
                'TestAmenity' => '30000000-0000-0000-0000-000000000006',
            ],
            uuidToPathMap: [
                '30000000-0000-0000-0000-000000000001' => $objectPath,
                '30000000-0000-0000-0000-000000000002' => $typePath,
                '30000000-0000-0000-0000-000000000003' => $jurisdictionPath,
                '30000000-0000-0000-0000-000000000004' => $radarPath,
                '30000000-0000-0000-0000-000000000005' => $factionPath,
                '30000000-0000-0000-0000-000000000006' => $amenityPath,
            ],
        );
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'loc_starmap_name=Test Object',
                'loc_starmap_desc=Test Description',
                'loc_type_name=Planet',
                'loc_type_class=Navigation',
                'loc_jurisdiction_name=UEE',
                'loc_radar_name=Radar',
                'loc_radar_display=Radar Display',
                'loc_faction_name=UEE Navy',
                'loc_amenity_name=Refuel',
                'loc_amenity_display=Refuel Display',
            ])
        );
        $this->writeExtractedTagFiles([
            ['name' => 'tag-location', 'uuid' => 'tag-location'],
            ['name' => 'tag-radar', 'uuid' => 'tag-radar'],
        ]);

        $tester = new CommandTester(new LoadStarmap);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $export = $this->readJsonFile('starmap.json');

        self::assertCount(1, $export);
        self::assertSame('Test Object', $export[0]['Name']);
        self::assertSame('UEE', $export[0]['Jurisdiction']['Name']);
        self::assertSame('UEE Navy', $export[0]['Affiliation']['DisplayName']);
        self::assertSame('Radar Display', $export[0]['RadarContactType']['DisplayName']);
        self::assertSame('tag-radar', $export[0]['RadarContactType']['TagUUID']);
        self::assertSame('Refuel Display', $export[0]['Amenities'][0]['DisplayName']);
        self::assertSame('tag-location', $export[0]['LocationHierarchyTag']['Name']);
    }

    public function test_execute_writes_starmap_positions_export(): void
    {
        $planetPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/test_planet.xml',
            <<<'XML'
            <StarMapObject.TestPlanet name="Test Planet" __type="StarMapObject" __ref="31000000-0000-0000-0000-000000000001" __path="libs/foundry/records/starmap/pu/system/stanton/test_planet.xml" />
            XML
        );
        $moonPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/test_moon.xml',
            <<<'XML'
            <StarMapObject.TestMoon name="Test Moon" __type="StarMapObject" __ref="31000000-0000-0000-0000-000000000002" __path="libs/foundry/records/starmap/pu/system/stanton/test_moon.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'StarMapObject' => [
                    'TestPlanet' => $planetPath,
                    'TestMoon' => $moonPath,
                ],
            ],
            uuidToClassMap: [
                '31000000-0000-0000-0000-000000000001' => 'TestPlanet',
                '31000000-0000-0000-0000-000000000002' => 'TestMoon',
            ],
            classToUuidMap: [
                'TestPlanet' => '31000000-0000-0000-0000-000000000001',
                'TestMoon' => '31000000-0000-0000-0000-000000000002',
            ],
            uuidToPathMap: [
                '31000000-0000-0000-0000-000000000001' => $planetPath,
                '31000000-0000-0000-0000-000000000002' => $moonPath,
            ],
        );

        $this->writeSocpak(
            'Data/ObjectContainers/PU/system/stanton/stantonsystem.socpak',
            'stantonsystem.xml',
            <<<'XML'
            <ObjectContainer>
              <ChildObjectContainers>
                <Child pos="10,20,30" starMapRecord="31000000-0000-0000-0000-000000000001" entityName="planet_test">
                  <ChildObjectContainers>
                    <Child pos="1,2,3" starMapRecord="31000000-0000-0000-0000-000000000002" entityName="moon_test" />
                  </ChildObjectContainers>
                </Child>
              </ChildObjectContainers>
            </ObjectContainer>
            XML
        );

        $tester = new CommandTester(new LoadStarmap);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $positions = $this->readJsonFile('starmap_positions.json');

        self::assertCount(2, $positions['entities']);
        self::assertSame([], $positions['connections']);
        self::assertSame('Test Planet', $positions['entities'][0]['name']);
        self::assertSame([10, 20, 30], [
            $positions['entities'][0]['x'],
            $positions['entities'][0]['y'],
            $positions['entities'][0]['z'],
        ]);
        self::assertSame('Test Moon', $positions['entities'][1]['name']);
        self::assertSame([11, 22, 33], [
            $positions['entities'][1]['x'],
            $positions['entities'][1]['y'],
            $positions['entities'][1]['z'],
        ]);
    }

    public function test_execute_extracts_entities_from_external_sub_socpak(): void
    {
        $planetPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/test_planet.xml',
            <<<'XML'
            <StarMapObject.TestPlanet name="Test Planet" __type="StarMapObject" __ref="31000000-0000-0000-0000-000000000001" __path="libs/foundry/records/starmap/pu/system/stanton/test_planet.xml" />
            XML
        );
        $stationPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/test_station.xml',
            <<<'XML'
            <StarMapObject.TestStation name="Test Station" __type="StarMapObject" __ref="31000000-0000-0000-0000-000000000003" __path="libs/foundry/records/starmap/pu/system/stanton/test_station.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'StarMapObject' => [
                    'TestPlanet' => $planetPath,
                    'TestStation' => $stationPath,
                ],
            ],
            uuidToClassMap: [
                '31000000-0000-0000-0000-000000000001' => 'TestPlanet',
                '31000000-0000-0000-0000-000000000003' => 'TestStation',
            ],
            classToUuidMap: [
                'TestPlanet' => '31000000-0000-0000-0000-000000000001',
                'TestStation' => '31000000-0000-0000-0000-000000000003',
            ],
            uuidToPathMap: [
                '31000000-0000-0000-0000-000000000001' => $planetPath,
                '31000000-0000-0000-0000-000000000003' => $stationPath,
            ],
        );

        // System socpak references an external planet socpak
        $this->writeSocpak(
            'Data/ObjectContainers/PU/system/stanton/stantonsystem.socpak',
            'stantonsystem.xml',
            <<<'XML'
            <ObjectContainer>
              <ChildObjectContainers>
                <Child external="1" name="Data/objectcontainers/pu/system/stanton/stanton1.socpak" pos="1000,2000,3000" starMapRecord="31000000-0000-0000-0000-000000000001" entityName="planet_test">
                  <ChildObjectContainers />
                </Child>
              </ChildObjectContainers>
            </ObjectContainer>
            XML
        );

        // Planet sub-socpak contains a station with position relative to the planet
        $this->writeSocpak(
            'Data/ObjectContainers/PU/system/stanton/stanton1.socpak',
            'stanton1.xml',
            <<<'XML'
            <ObjectContainer>
              <ChildObjectContainers>
                <Child external="1" name="" pos="500,600,700" starMapRecord="31000000-0000-0000-0000-000000000003" entityName="station_test">
                  <ChildObjectContainers />
                </Child>
              </ChildObjectContainers>
            </ObjectContainer>
            XML
        );

        $tester = new CommandTester(new LoadStarmap);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $positions = $this->readJsonFile('starmap_positions.json');

        // Planet from inline system XML + Station from external planet socpak
        self::assertCount(2, $positions['entities']);

        // Build a name-keyed map for order-independent assertions
        $byName = [];
        foreach ($positions['entities'] as $entity) {
            $byName[$entity['name']] = $entity;
        }

        self::assertArrayHasKey('Test Planet', $byName);
        self::assertSame([1000, 2000, 3000], [
            $byName['Test Planet']['x'],
            $byName['Test Planet']['y'],
            $byName['Test Planet']['z'],
        ]);

        // Station: planet pos (1000,2000,3000) + station local pos (500,600,700)
        self::assertArrayHasKey('Test Station', $byName);
        self::assertSame([1500, 2600, 3700], [
            $byName['Test Station']['x'],
            $byName['Test Station']['y'],
            $byName['Test Station']['z'],
        ]);
    }

    private function writeSocpak(string $relativePath, string $entryName, string $xml): void
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.$relativePath;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            self::assertTrue(mkdir($directory, 0777, true));
        }

        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString($entryName, trim($xml).PHP_EOL));
        self::assertTrue($zip->close());
    }
}
