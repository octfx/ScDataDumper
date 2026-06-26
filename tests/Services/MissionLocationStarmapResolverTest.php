<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\MissionLocationStarmapResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers MissionLocationStarmapResolver, the component that decides which
 * StarMapObject uuid (if any) a MissionLocationTemplate maps to.
 *
 * The historical bug: resolveAll() grouped templates by EVERY generalTag and
 * propagated an already-resolved starmap uuid to every template sharing that
 * tag, mutating the map in place so the assignment cascaded. Generic tags
 * ("Surface", shared by 1200+ templates) then collapsed huge unrelated sets
 * onto whichever SMO one member happened to match by name (e.g. HDPC-Cassillo).
 */
final class MissionLocationStarmapResolverTest extends ScDataTestCase
{
    // StarMapObject uuids
    private const SMO_CASSILLO_UUID = '8cd86a33-a3b4-47ed-9e35-9351b84f108d';

    private const SMO_GRIMHEX_UUID = '10000000-0000-0000-0000-000000000001';

    // MissionLocationTemplate uuids
    private const CASSILLO_DC_TEMPLATE_UUID = '1ab32043-b2d1-4c2c-ba10-f5491ab17008';

    private const COLLECTOR_TEMPLATE_UUID = '4927865a-2efa-0359-1f98-8b4c8b88a5a9';

    private const MICROTECH_LOBBY_TEMPLATE_UUID = '20000000-0000-0000-0000-000000000001';

    private const GRIMHEX_OUTPOST_TEMPLATE_UUID = '30000000-0000-0000-0000-000000000001';

    // A generic tag shared by many unrelated templates (mirrors real "Surface" tag).
    private const SHARED_GENERIC_TAG = 'c70dbe51-3d1e-47b7-9795-290d3eba1728';

    private const ORPHAN_TEMPLATE_UUID = '40000000-0000-0000-0000-000000000001';

    public function test_display_name_match_resolves_to_starmap_uuid(): void
    {
        $resolver = $this->bootstrapAndResolve();

        // Cassillo DC template's displayName "@mission_location_stanton_759" -> "HDPC-Cassillo"
        // matches the StarMapObject name "@Stanton1_DistributionCenter_Hurston_02" -> "HDPC-Cassillo".
        self::assertSame(self::SMO_CASSILLO_UUID, $resolver->getStarmapUuid(self::CASSILLO_DC_TEMPLATE_UUID));
    }

    public function test_class_name_match_resolves_to_starmap_uuid(): void
    {
        $resolver = $this->bootstrapAndResolve();

        // Outpost template with className "GrimHEX" (after stripping "outpost_") matches SMO "GrimHEX".
        self::assertSame(self::SMO_GRIMHEX_UUID, $resolver->getStarmapUuid(self::GRIMHEX_OUTPOST_TEMPLATE_UUID));
    }

    /**
     * The core regression test: a generic tag shared between a name-resolved
     * template and unrelated templates must NOT propagate the starmap uuid.
     */
    public function test_generic_tag_does_not_cascade_starmap_uuid(): void
    {
        $resolver = $this->bootstrapAndResolve();

        // Collector + MicroTech lobby share only the generic SHARED_GENERIC_TAG with Cassillo.
        // They have no displayable name and no className match, so they must stay unresolved.
        self::assertNull($resolver->getStarmapUuid(self::COLLECTOR_TEMPLATE_UUID));
        self::assertNull($resolver->getStarmapUuid(self::MICROTECH_LOBBY_TEMPLATE_UUID));
    }

    public function test_template_with_no_signal_returns_null(): void
    {
        $resolver = $this->bootstrapAndResolve();

        // Orphan has no displayable name, no className match, no tags at all.
        self::assertNull($resolver->getStarmapUuid(self::ORPHAN_TEMPLATE_UUID));
    }

    public function test_unknown_template_uuid_returns_null(): void
    {
        $resolver = $this->bootstrapAndResolve();

        self::assertNull($resolver->getStarmapUuid('99999999-0000-0000-0000-000000000001'));
    }

    private function bootstrapAndResolve(): MissionLocationStarmapResolver
    {
        $this->writeFixtures();

        (new ServiceFactory($this->tempDir))->initialize();

        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        $locations = [];
        foreach ($lookup->getDocumentType('MissionLocationTemplate', MissionLocationTemplate::class) as $template) {
            $displayName = $template->getDisplayName();
            if ($displayName !== null) {
                $displayName = $localization->translateValue($displayName, true);
            }
            $locations[] = [
                'uuid' => $template->getUuid(),
                'className' => $template->getClassName(),
                'displayName' => $displayName,
                'generalTags' => $template->getGeneralTagReferences(),
            ];
        }

        $resolver = new MissionLocationStarmapResolver;
        $resolver->resolveAll($locations);

        return $resolver;
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

        // --- StarMapObjects ---
        $cassilloPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/stanton1_distributioncentre_hurston_cassillo.xml',
            sprintf(
                '<StarMapObject.Stanton1_DistributionCentre_Hurston_Cassillo name="@Stanton1_DistributionCenter_Hurston_02" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/stanton1_distributioncentre_hurston_cassillo.xml" />',
                self::SMO_CASSILLO_UUID,
            )
        );

        $grimhexPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/grimhex.xml',
            sprintf(
                '<StarMapObject.GrimHEX name="Grim HEX" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/grimhex.xml" />',
                self::SMO_GRIMHEX_UUID,
            )
        );

        // --- MissionLocationTemplates ---
        // Cassillo DC: displayName matches Cassillo SMO name; also carries the shared generic tag.
        $cassilloTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/distributioncentres/dc_stan_hurston_s1_cassillo.xml',
            sprintf(
                '<MissionLocationTemplate.DC_Stan_Hurston_S1_Cassillo __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/distributioncentres/dc_stan_hurston_s1_cassillo.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags><stringVariants><variants><MissionStringVariant tag="0130de18-97e2-4d4c-9bb1-3e7ef5fd0490" string="@mission_location_stanton_759" /></variants></stringVariants></locationData></MissionLocationTemplate.DC_Stan_Hurston_S1_Cassillo>',
                self::CASSILLO_DC_TEMPLATE_UUID,
                self::SHARED_GENERIC_TAG,
            )
        );

        // Collector: shares only the generic tag; no name, className matches no SMO.
        $collectorTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/system/asteroidbase/thecollectorsasteriod.xml',
            sprintf(
                '<MissionLocationTemplate.TheCollectorsAsteriod __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/system/asteroidbase/thecollectorsasteriod.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags></locationData></MissionLocationTemplate.TheCollectorsAsteriod>',
                self::COLLECTOR_TEMPLATE_UUID,
                self::SHARED_GENERIC_TAG,
            )
        );

        // MicroTech lobby: another victim of the cascade; shares only the generic tag.
        $microtechTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/microtech/dc_stan_microtech_s4_lobby.xml',
            sprintf(
                '<MissionLocationTemplate.DC_Stan_microTech_S4_Lobby __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/microtech/dc_stan_microtech_s4_lobby.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags></locationData></MissionLocationTemplate.DC_Stan_microTech_S4_Lobby>',
                self::MICROTECH_LOBBY_TEMPLATE_UUID,
                self::SHARED_GENERIC_TAG,
            )
        );

        // GrimHEX outpost: className "GrimHEX" matches the SMO via path 3.
        $grimhexTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/outposts/outpost_grimhex.xml',
            sprintf(
                '<MissionLocationTemplate.outpost_GrimHEX __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/outposts/outpost_grimhex.xml"><locationData disabled="0"><generalTags><tags><Reference value="%2$s" /></tags></generalTags></locationData></MissionLocationTemplate.outpost_GrimHEX>',
                self::GRIMHEX_OUTPOST_TEMPLATE_UUID,
                self::SHARED_GENERIC_TAG,
            )
        );

        // Orphan: no name, no className match, no tags.
        $orphanTemplatePath = $this->writeFile(
            'Game2/libs/foundry/records/missiondata/pu_locations/templates/orphan.xml',
            sprintf(
                '<MissionLocationTemplate.Some_Orphan __type="MissionLocationTemplate" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_locations/templates/orphan.xml"><locationData disabled="0" /></MissionLocationTemplate.Some_Orphan>',
                self::ORPHAN_TEMPLATE_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'StarMapObject' => [
                    'Stanton1_DistributionCentre_Hurston_Cassillo' => $cassilloPath,
                    'GrimHEX' => $grimhexPath,
                ],
                'MissionLocationTemplate' => [
                    'DC_Stan_Hurston_S1_Cassillo' => $cassilloTemplatePath,
                    'TheCollectorsAsteriod' => $collectorTemplatePath,
                    'DC_Stan_microTech_S4_Lobby' => $microtechTemplatePath,
                    'outpost_GrimHEX' => $grimhexTemplatePath,
                    'Some_Orphan' => $orphanTemplatePath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::SMO_CASSILLO_UUID) => 'Stanton1_DistributionCentre_Hurston_Cassillo',
                strtolower(self::SMO_GRIMHEX_UUID) => 'GrimHEX',
            ],
            classToUuidMap: [
                'Stanton1_DistributionCentre_Hurston_Cassillo' => strtolower(self::SMO_CASSILLO_UUID),
                'GrimHEX' => strtolower(self::SMO_GRIMHEX_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::SMO_CASSILLO_UUID) => $cassilloPath,
                strtolower(self::SMO_GRIMHEX_UUID) => $grimhexPath,
            ],
        );

        // LocalizationService + TagDatabaseService must be bootable; seed minimal caches.
        $localization = new LocalizationService($this->tempDir);
        $localization->initialize();
    }
}
