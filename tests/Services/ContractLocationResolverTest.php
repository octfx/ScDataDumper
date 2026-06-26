<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\ContractLocationResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers ContractLocationResolver output shape.
 *
 * The primary `uuid` of a resolved location MUST be the stable MissionLocationTemplate
 * identity (always the template), with the resolved StarMapObject parent exposed
 * separately as `starmap_uuid` when available. Mixing the two in one field (starmap
 * parent when resolved, template uuid as fallback) made the field heterogeneous and
 * broke downstream joins that key on it.
 */
final class ContractLocationResolverTest extends ScDataTestCase
{
    private const SMO_CASSILLO_UUID = '8cd86a33-a3b4-47ed-9e35-9351b84f108d';

    private const CASSILLO_DC_TEMPLATE_UUID = '1ab32043-b2d1-4c2c-ba10-f5491ab17008';

    private const COLLECTOR_TEMPLATE_UUID = '4927865a-2efa-0359-1f98-8b4c8b88a5a9';

    // Tag shared by both templates; the search term uses it as its positive tag.
    private const DESTINATION_TAG = '5fa026c4-21cc-46e5-801d-ca4e4c9b5fcf';

    public function test_primary_uuid_is_template_identity_not_starmap_parent(): void
    {
        $results = $this->bootstrapAndResolve();

        $byTemplate = [];
        foreach ($results as $row) {
            $byTemplate[$row['location_template_uuid']] = $row;
        }

        // Cassillo DC resolves to the Cassillo StarMapObject via display name, but its
        // primary uuid must stay the template identity, with the starmap parent separate.
        $cassillo = $byTemplate[self::CASSILLO_DC_TEMPLATE_UUID];
        self::assertSame(self::CASSILLO_DC_TEMPLATE_UUID, $cassillo['uuid']);
        self::assertSame(self::SMO_CASSILLO_UUID, $cassillo['starmap_uuid']);
        self::assertSame(self::CASSILLO_DC_TEMPLATE_UUID, $cassillo['location_template_uuid']);
    }

    public function test_starmap_uuid_absent_when_template_does_not_resolve(): void
    {
        $results = $this->bootstrapAndResolve();

        $byTemplate = [];
        foreach ($results as $row) {
            $byTemplate[$row['location_template_uuid']] = $row;
        }

        // Collector has no displayable name and no className match -> no starmap parent.
        // Its primary uuid is its template identity, and starmap_uuid is absent (not null)
        // so the field is omitted from the final output rather than emitted as null.
        $collector = $byTemplate[self::COLLECTOR_TEMPLATE_UUID];
        self::assertSame(self::COLLECTOR_TEMPLATE_UUID, $collector['uuid']);
        self::assertArrayNotHasKey('starmap_uuid', $collector);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bootstrapAndResolve(): array
    {
        $this->writeFixtures();
        (new ServiceFactory($this->tempDir))->initialize();

        $resolver = new ContractLocationResolver;

        return $resolver->resolveLocations(
            [['positiveTags' => [self::DESTINATION_TAG], 'negativeTags' => []]],
            [],
        );
    }

    private function writeFixtures(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'Stanton1_DistributionCenter_Hurston_02=HDPC-Cassillo',
                'mission_location_stanton_759=HDPC-Cassillo',
            ])
        );

        // Tag the search term references; needed so expandTagsWithAncestors resolves it.
        $tagPath = $this->writeFile(
            'Game2/libs/foundry/records/tagdatabase/destination_tag.xml',
            sprintf(
                '<Tag.DestinationTag tagName="Destination" __type="Tag" __ref="%1$s" __path="libs/foundry/records/tagdatabase/destination_tag.xml" />',
                self::DESTINATION_TAG,
            )
        );

        // StarMapObject Cassillo.
        $cassilloSmoPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/stanton1_distributioncentre_hurston_cassillo.xml',
            sprintf(
                '<StarMapObject.Stanton1_DistributionCentre_Hurston_Cassillo name="@Stanton1_DistributionCenter_Hurston_02" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/stanton1_distributioncentre_hurston_cassillo.xml" />',
                self::SMO_CASSILLO_UUID,
            )
        );

        // Cassillo DC template: display name resolves to Cassillo; carries the search tag.
        $cassilloTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/distributioncentres/dc_stan_hurston_s1_cassillo.xml',
            sprintf(
                '<MissionLocationTemplate.DC_Stan_Hurston_S1_Cassillo __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/distributioncentres/dc_stan_hurston_s1_cassillo.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags><stringVariants><variants><MissionStringVariant tag="0130de18-97e2-4d4c-9bb1-3e7ef5fd0490" string="@mission_location_stanton_759" /></variants></stringVariants></locationData></MissionLocationTemplate.DC_Stan_Hurston_S1_Cassillo>',
                self::CASSILLO_DC_TEMPLATE_UUID,
                self::DESTINATION_TAG,
            )
        );

        // Collector template: carries the search tag but has no resolvable name.
        $collectorTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/system/asteroidbase/thecollectorsasteriod.xml',
            sprintf(
                '<MissionLocationTemplate.TheCollectorsAsteriod __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/system/asteroidbase/thecollectorsasteriod.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags></locationData></MissionLocationTemplate.TheCollectorsAsteriod>',
                self::COLLECTOR_TEMPLATE_UUID,
                self::DESTINATION_TAG,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'Tag' => ['DestinationTag' => $tagPath],
                'StarMapObject' => [
                    'Stanton1_DistributionCentre_Hurston_Cassillo' => $cassilloSmoPath,
                ],
                'MissionLocationTemplate' => [
                    'DC_Stan_Hurston_S1_Cassillo' => $cassilloTemplatePath,
                    'TheCollectorsAsteriod' => $collectorTemplatePath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::SMO_CASSILLO_UUID) => 'Stanton1_DistributionCentre_Hurston_Cassillo',
            ],
            classToUuidMap: [
                'Stanton1_DistributionCentre_Hurston_Cassillo' => strtolower(self::SMO_CASSILLO_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::SMO_CASSILLO_UUID) => $cassilloSmoPath,
            ],
        );
    }
}
