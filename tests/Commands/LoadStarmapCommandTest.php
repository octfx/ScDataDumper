<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadStarmap;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

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
        self::assertSame('Test Object', $export[0]['name']);
        self::assertSame('UEE', $export[0]['jurisdiction']['name']);
        self::assertSame('UEE Navy', $export[0]['affiliation']['displayName']);
        self::assertSame('Radar Display', $export[0]['radarContactType']['displayName']);
        self::assertSame('tag-radar', $export[0]['radarContactType']['tagUuid']);
        self::assertSame('Refuel Display', $export[0]['amenities'][0]['displayName']);
        self::assertSame('tag-location', $export[0]['locationHierarchyTag']['name']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
