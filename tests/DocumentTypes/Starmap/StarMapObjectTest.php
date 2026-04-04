<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Starmap;

use Octfx\ScDataDumper\DocumentTypes\Faction\Faction;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class StarMapObjectTest extends ScDataTestCase
{
    private const OBJECT_UUID = '10000000-0000-0000-0000-000000000001';

    private const TYPE_UUID = '10000000-0000-0000-0000-000000000002';

    private const JURISDICTION_UUID = '10000000-0000-0000-0000-000000000003';

    private const RADAR_UUID = '10000000-0000-0000-0000-000000000004';

    private const AFFILIATION_UUID = '10000000-0000-0000-0000-000000000005';

    private const AMENITY_UUID = '10000000-0000-0000-0000-000000000006';

    protected function setUp(): void
    {
        parent::setUp();

        $objectPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/test/test_object.xml',
            sprintf(
                '<StarMapObject.TestObject name="@loc_starmap_name" description="@loc_starmap_desc" jurisdiction="%2$s" type="%3$s" affiliation="%4$s" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/system/test/test_object.xml"><radarProperties><SSCRadarContactProperites contactType="%5$s" /></radarProperties><amenities><Reference value="%6$s" /></amenities></StarMapObject.TestObject>',
                self::OBJECT_UUID,
                self::JURISDICTION_UUID,
                self::TYPE_UUID,
                self::AFFILIATION_UUID,
                self::RADAR_UUID,
                self::AMENITY_UUID,
            )
        );
        $typePath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/types/test_type.xml',
            sprintf(
                '<StarMapObjectType.TestType name="@loc_type_name" classification="@loc_type_class" spawnNavPoints="1" validQuantumTravelDestination="1" __type="StarMapObjectType" __ref="%1$s" __path="libs/foundry/records/starmap/types/test_type.xml" />',
                self::TYPE_UUID,
            )
        );
        $jurisdictionPath = $this->writeFile(
            'Game2/libs/foundry/records/lawsystem/jurisdictions/test_jurisdiction.xml',
            sprintf(
                '<Jurisdiction.TestJurisdiction name="@loc_jurisdiction_name" baseFine="500" maxStolenGoodsPossessionSCU="32" isPrison="0" __type="Jurisdiction" __ref="%1$s" __path="libs/foundry/records/lawsystem/jurisdictions/test_jurisdiction.xml" />',
                self::JURISDICTION_UUID,
            )
        );
        $radarPath = $this->writeFile(
            'Game2/libs/foundry/records/radarsystem/test_radar_contact.xml',
            sprintf(
                '<RadarContactTypeEntry.TestRadar name="@loc_radar_name" displayName="@loc_radar_display" tag="tag-radar" isObjectOfInterest="1" __type="RadarContactTypeEntry" __ref="%1$s" __path="libs/foundry/records/radarsystem/test_radar_contact.xml" />',
                self::RADAR_UUID,
            )
        );
        $factionPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/test_faction.xml',
            sprintf(
                '<Faction.TestFaction displayName="@loc_faction_name" name="FactionFallback" __type="Faction" __ref="%1$s" __path="libs/foundry/records/factions/test_faction.xml" />',
                self::AFFILIATION_UUID,
            )
        );
        $amenityPath = $this->writeFile(
            'Game2/libs/foundry/records/starmapamenitytypes/test_amenity.xml',
            sprintf(
                '<StarMapAmenityTypeEntry.TestAmenity name="@loc_amenity_name" displayName="@loc_amenity_display" __type="StarMapAmenityTypeEntry" __ref="%1$s" __path="libs/foundry/records/starmapamenitytypes/test_amenity.xml" />',
                self::AMENITY_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'StarMapObject' => [
                    'TestObject' => $objectPath,
                ],
                'StarMapObjectType' => [
                    'TestType' => $typePath,
                ],
                'Jurisdiction' => [
                    'TestJurisdiction' => $jurisdictionPath,
                ],
                'RadarContactTypeEntry' => [
                    'TestRadar' => $radarPath,
                ],
                'Faction' => [
                    'TestFaction' => $factionPath,
                ],
                'StarMapAmenityTypeEntry' => [
                    'TestAmenity' => $amenityPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::OBJECT_UUID) => 'TestObject',
                strtolower(self::TYPE_UUID) => 'TestType',
                strtolower(self::JURISDICTION_UUID) => 'TestJurisdiction',
                strtolower(self::RADAR_UUID) => 'TestRadar',
                strtolower(self::AFFILIATION_UUID) => 'TestFaction',
                strtolower(self::AMENITY_UUID) => 'TestAmenity',
            ],
            classToUuidMap: [
                'TestObject' => strtolower(self::OBJECT_UUID),
                'TestType' => strtolower(self::TYPE_UUID),
                'TestJurisdiction' => strtolower(self::JURISDICTION_UUID),
                'TestRadar' => strtolower(self::RADAR_UUID),
                'TestFaction' => strtolower(self::AFFILIATION_UUID),
                'TestAmenity' => strtolower(self::AMENITY_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::OBJECT_UUID) => $objectPath,
                strtolower(self::TYPE_UUID) => $typePath,
                strtolower(self::JURISDICTION_UUID) => $jurisdictionPath,
                strtolower(self::RADAR_UUID) => $radarPath,
                strtolower(self::AFFILIATION_UUID) => $factionPath,
                strtolower(self::AMENITY_UUID) => $amenityPath,
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
            ['name' => 'tag-radar', 'uuid' => 'tag-radar'],
        ]);

        (new ServiceFactory($this->tempDir))->initialize();
    }

    public function test_resolves_linked_documents_when_reference_hydration_is_disabled(): void
    {
        $document = (new StarMapObject)
            ->setReferenceHydrationEnabled(false);
        $document->load($this->tempDir.'/Game2/libs/foundry/records/starmap/pu/system/test/test_object.xml');

        self::assertSame(self::JURISDICTION_UUID, $document->getJurisdiction()?->getUuid());
        self::assertSame(self::TYPE_UUID, $document->getTypeDocument()?->getUuid());
        self::assertSame(self::RADAR_UUID, $document->getRadarContactType()?->getUuid());
        self::assertInstanceOf(Faction::class, $document->getAffiliation());
        self::assertSame(self::AFFILIATION_UUID, $document->getAffiliation()?->getUuid());
        self::assertCount(1, $document->getAmenities());
        self::assertSame(self::AMENITY_UUID, $document->getAmenities()[0]->getUuid());
        self::assertSame([self::AMENITY_UUID], $document->getAmenityReferences());
    }
}
