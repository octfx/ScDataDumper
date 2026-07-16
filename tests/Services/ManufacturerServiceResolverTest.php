<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers ManufacturerService::resolveCanonicalByNameOrCode against the real
 * import/wiki_manufacturers.json. The miss/unknown cases here exercise the
 * identical code path a missing data.json would (empty maps -> null),
 * proving the graceful-degradation contract.
 */
final class ManufacturerServiceResolverTest extends ScDataTestCase
{
    private ManufacturerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Shared static indices: reset between tests.
        ManufacturerService::resetDataOverrideCache();

        $this->writeCacheFiles();
        new ServiceFactory($this->tempDir)->initialize();
        $this->service = new ManufacturerService($this->tempDir);
    }

    public function test_resolves_known_display_name_to_canonical_code(): void
    {
        // No XML fixture in this suite -> uuid stays null (XML is the uuid source).
        // Code absent -> name path resolves.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $result = $this->service->resolveCanonicalByNameOrCode('Aegis Dynamics', null);

        self::assertSame(['code' => 'AEGS', 'name' => 'Aegis Dynamics', 'uuid' => null], $result);
    }

    public function test_curated_code_wins_over_conflicting_name(): void
    {
        // Game-data copy-paste bug: FSKI's XML Name token is @AEGS, so its display
        // name is "Aegis Dynamics". The curated code FSKI is identity and must win
        // -- data.json FSKI = "FireStorm Kinetics", a distinct manufacturer.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics', 'FSKI' => 'FireStorm Kinetics']);

        $result = $this->service->resolveCanonicalByNameOrCode('Aegis Dynamics', 'FSKI');

        self::assertSame(['code' => 'FSKI', 'name' => 'FireStorm Kinetics', 'uuid' => null], $result);
    }

    public function test_name_normalizes_ampersand_to_and(): void
    {
        // "&" vs "and" variant must still resolve.
        $this->useWikiManufacturers(['MISC' => 'Musashi Industrial and Starflight Concern']);

        $result = $this->service->resolveCanonicalByNameOrCode(
            'Musashi Industrial & Starflight Concern',
            'WRONG',
        );

        self::assertNotNull($result);
        self::assertSame('MISC', $result['code']);
    }

    public function test_falls_back_to_code_when_name_is_a_raw_localization_token(): void
    {
        // @-token names skip the name path; raw code is the forward lookup.
        $this->useWikiManufacturers(['RSI' => 'Roberts Space Industries']);

        $result = $this->service->resolveCanonicalByNameOrCode('@manufacturer_NameRSI', 'RSI');

        self::assertSame(['code' => 'RSI', 'name' => 'Roberts Space Industries', 'uuid' => null], $result);
    }

    public function test_falls_back_to_code_when_name_is_null(): void
    {
        // Nameless-XML case: only a code.
        $this->useWikiManufacturers(['DRAK' => 'Drake Interplanetary']);

        $result = $this->service->resolveCanonicalByNameOrCode(null, 'DRAK');

        self::assertSame(['code' => 'DRAK', 'name' => 'Drake Interplanetary', 'uuid' => null], $result);
    }

    public function test_returns_null_when_neither_name_nor_code_resolve(): void
    {
        // Also the missing-data.json behavior: every lookup misses, caller
        // keeps raw XML via null coalescing.
        $result = $this->service->resolveCanonicalByNameOrCode('Nonexistent Manufacturer', 'NOPE');

        self::assertNull($result);
    }

    public function test_falls_through_to_code_when_name_does_not_normalize_match(): void
    {
        // The XML display name is an abbreviation that doesn't normalize-match
        // the data.json name ("Banu" vs "Banu Souli"). The code is identity, so
        // the resolver falls through to the code lookup and returns the data.json
        // canonical name. This is the same path that fixes GRNP "GNP" ->
        // "Groupe Nouveau Paradigme".
        $this->useWikiManufacturers(['BANU' => 'Banu Souli']);

        $result = $this->service->resolveCanonicalByNameOrCode('Banu', 'BANU');

        self::assertSame(['code' => 'BANU', 'name' => 'Banu Souli', 'uuid' => null], $result);
    }

    public function test_returns_null_when_code_is_empty_and_name_unknown(): void
    {
        $result = $this->service->resolveCanonicalByNameOrCode('Unknown Brand', null);

        self::assertNull($result);
    }

    public function test_cache_load_runs_only_once_across_calls(): void
    {
        // $dataJsonLoaded must prevent a re-stat per call.
        $this->useWikiManufacturers([
            'ANVL' => 'Anvil Aerospace',
            'ORIG' => 'Origin Jumpworks',
            'CRUS' => 'Crusader Industries',
        ]);

        $this->service->resolveCanonicalByNameOrCode('Anvil', 'ANVL');
        $this->service->resolveCanonicalByNameOrCode('Origin Jumpworks', 'ORIG');
        $result = $this->service->resolveCanonicalByNameOrCode('Crusader Industries', 'CRUS');

        self::assertNotNull($result);
    }

    public function test_resolve_canonical_by_code_returns_name_for_known_code(): void
    {
        $this->useWikiManufacturers(['BANU' => 'Banu Souli']);

        $result = $this->service->resolveCanonicalByCode('BANU');

        self::assertSame(['code' => 'BANU', 'name' => 'Banu Souli', 'uuid' => null], $result);
    }

    public function test_resolve_canonical_by_code_returns_null_for_unknown_code(): void
    {
        // Unknown code must return null so the caller falls through to the
        // normal resolution path rather than emitting a half-applied {code, xmlName}.
        self::assertNull($this->service->resolveCanonicalByCode('NOPE'));
    }

    public function test_resolve_canonical_for_prefers_wiki_code(): void
    {
        // Wiki code wins even when the name would resolve to a different code.
        $this->useWikiManufacturers(['ANVL' => 'Anvil Aerospace', 'GRIN' => 'Greycat Industrial']);

        $result = $this->service->resolveCanonicalFor('Anvil Aerospace', 'ANVL', 'GRIN');

        self::assertSame(['code' => 'GRIN', 'name' => 'Greycat Industrial', 'uuid' => null], $result);
    }

    public function test_resolve_canonical_for_falls_through_when_wiki_code_unknown(): void
    {
        // Unknown wiki code must not short-circuit; the XML code is curated and
        // wins as identity.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $result = $this->service->resolveCanonicalFor('Aegis Dynamics', 'AEGS', 'NOPE');

        self::assertSame(['code' => 'AEGS', 'name' => 'Aegis Dynamics', 'uuid' => null], $result);
    }

    public function test_resolve_canonical_for_uses_code_resolution_with_curated_code(): void
    {
        // Curated XML code wins over a name that would resolve elsewhere.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $result = $this->service->resolveCanonicalFor('Aegis Dynamics', 'AEGS', null);

        self::assertSame(['code' => 'AEGS', 'name' => 'Aegis Dynamics', 'uuid' => null], $result);
    }

    // -- Canonical UUID (XML-derived, primary-preferred) --

    public function test_resolve_by_code_returns_primary_uuid_collapsing_aliases(): void
    {
        // Two XML records share Code=RSI. The primary (basename scitemmanufacturer.*)
        // wins; the alias uuid is never surfaced.
        $this->useWikiManufacturers(['RSI' => 'Roberts Space Industries']);

        $primaryPath = $this->writeManufacturerXml('scitemmanufacturer.rsi.xml', 'RSI', 'RSI', 'primary-rsi-uuid');
        $aliasPath = $this->writeManufacturerXml('rsi_2025.xml', 'RSI_2025', 'RSI', 'alias-rsi-uuid');

        $service = $this->bootServiceWithManufacturers([
            'primary-rsi-uuid' => $primaryPath,
            'alias-rsi-uuid' => $aliasPath,
        ]);

        $result = $service->resolveCanonicalByCode('RSI');

        self::assertSame('primary-rsi-uuid', $result['uuid']);
    }

    public function test_resolve_by_name_carries_canonical_uuid(): void
    {
        // data.json maps "Aegis Dynamics" -> AEGS; the AEGS XML fixture supplies uuid.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $path = $this->writeManufacturerXml('scitemmanufacturer.aegs.xml', 'AEGS', 'AEGS', 'aegs-primary-uuid');

        $service = $this->bootServiceWithManufacturers(['aegs-primary-uuid' => $path]);

        $result = $service->resolveCanonicalByNameOrCode('Aegis Dynamics', null);

        self::assertSame(['code' => 'AEGS', 'name' => 'Aegis Dynamics', 'uuid' => 'aegs-primary-uuid'], $result);
    }

    public function test_resolve_canonical_for_carries_uuid_for_wiki_code(): void
    {
        $this->useWikiManufacturers(['GRIN' => 'Greycat Industrial']);

        $path = $this->writeManufacturerXml('scitemmanufacturer.grin.xml', 'GRIN', 'GRIN', 'grin-primary-uuid');

        $service = $this->bootServiceWithManufacturers(['grin-primary-uuid' => $path]);

        $result = $service->resolveCanonicalFor('Anvil Aerospace', 'ANVL', 'GRIN');

        self::assertSame('grin-primary-uuid', $result['uuid']);
    }

    public function test_codeless_alias_token_uuid_matches_code_uuid(): void
    {
        // The token path (canonicalIndex) and the code path (codeToUuid) must agree
        // on the same primary uuid for one manufacturer identity.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $path = $this->writeManufacturerXml('scitemmanufacturer.aegs.xml', 'AEGS', 'AEGS', 'aegs-primary-uuid', '@manufacturer_NameAEGS');

        $service = $this->bootServiceWithManufacturers(['aegs-primary-uuid' => $path]);

        $byKey = $service->getCanonicalByNameKey('@manufacturer_NameAEGS');
        $byCode = $service->resolveCanonicalByCode('AEGS');

        self::assertSame('aegs-primary-uuid', $byKey['uuid']);
        self::assertSame('aegs-primary-uuid', $byCode['uuid']);
    }

    public function test_canonical_iterator_yields_one_primary_record_per_code(): void
    {
        // Two RSI records (primary + alias) + one AEGS. Aliases and dup codes
        // collapse; the export must see one primary row per code.
        $this->useWikiManufacturers(['RSI' => 'Roberts Space Industries', 'AEGS' => 'Aegis Dynamics']);

        $rsiPrimary = $this->writeManufacturerXml('scitemmanufacturer.rsi.xml', 'RSI', 'RSI', 'primary-rsi-uuid');
        $rsiAlias = $this->writeManufacturerXml('rsi_2025.xml', 'RSI_2025', 'RSI', 'alias-rsi-uuid');
        $aegs = $this->writeManufacturerXml('scitemmanufacturer.aegs.xml', 'AEGS', 'AEGS', 'aegs-uuid', '@manufacturer_NameAEGS');

        $service = $this->bootServiceWithManufacturers([
            'primary-rsi-uuid' => $rsiPrimary,
            'alias-rsi-uuid' => $rsiAlias,
            'aegs-uuid' => $aegs,
        ]);

        $codes = [];
        foreach ($service->canonicalIterator() as $mfr) {
            $codes[$mfr->getCode()] = $mfr->getUuid();
        }

        self::assertSame(['RSI' => 'primary-rsi-uuid', 'AEGS' => 'aegs-uuid'], $codes);
    }

    public function test_canonical_iterator_recovers_manufacturer_hidden_by_bugged_code(): void
    {
        // mrai.xml has Code=MIS (Musashi's code, a copy-paste bug) but name token
        // @manufacturer_NameMRAI (Mirai). MIS is not in data.json (MISC is), so
        // the token-suffix fallback derives identity MRAI and files the record
        // under Mirai instead of collapsing it into Musashi.
        $this->useWikiManufacturers([
            'MISC' => 'Musashi Industrial and Starflight Concern',
            'MRAI' => 'Mirai',
        ]);

        $misc = $this->writeManufacturerXml('scitemmanufacturer.misc.xml', 'MISC', 'MIS', 'misc-primary-uuid', '@manufacturer_NameMISC');
        $mrai = $this->writeManufacturerXml('mrai.xml', 'MIS', 'MIS', 'mrai-uuid', '@manufacturer_NameMRAI');

        $service = $this->bootServiceWithManufacturers([
            'misc-primary-uuid' => $misc,
            'mrai-uuid' => $mrai,
        ]);

        // Mirai: distinct from Musashi, recoverable via token-suffix identity.
        $mirai = $service->resolveCanonicalByCode('MRAI');
        self::assertNotNull($mirai);
        self::assertSame('Mirai', $mirai['name']);
        self::assertSame('mrai-uuid', $mirai['uuid']);

        // Musashi: filed under MISC (token-suffix identity), primary uuid preserved.
        $musashi = $service->resolveCanonicalByCode('MISC');
        self::assertNotNull($musashi);
        self::assertSame('misc-primary-uuid', $musashi['uuid']);
    }

    public function test_canonical_iterator_recovers_codeless_manufacturer_via_token_suffix(): void
    {
        // stor.xml has NO Code attribute but name token @manufacturer_NameSTOR,
        // and STOR is curated in data.json ("Stor*All"). The token-suffix identity
        // files the codeless record under STOR so it exports as its own row.
        $this->useWikiManufacturers(['STOR' => 'Stor*All']);

        $stor = $this->writeManufacturerXml('stor.xml', 'STOR', '', 'stor-uuid', '@manufacturer_NameSTOR');

        $service = $this->bootServiceWithManufacturers(['stor-uuid' => $stor]);

        $result = $service->resolveCanonicalByCode('STOR');
        self::assertNotNull($result);
        self::assertSame('Stor*All', $result['name']);
        self::assertSame('stor-uuid', $result['uuid']);
    }

    // -- resolveForEntity (the consumer-facing entry point) --

    public function test_resolve_for_entity_returns_null_when_entity_is_null_and_no_wiki_code(): void
    {
        // Neither XML entity nor wiki override: nothing to resolve.
        $service = $this->bootServiceWithManufacturers([]);

        self::assertNull($service->resolveForEntity(null, null));
    }

    public function test_resolve_for_entity_uses_wiki_code_when_entity_is_null(): void
    {
        // Stale source UUID: CIG ships paint/livery records (e.g. the ATLS
        // Foreman Livery) with a Manufacturer UUID that has no
        // scitemmanufacturer.*.xml, so getManufacturer() returns null. The wiki
        // override still carries the right code -- it must recover ARGO instead
        // of leaving the export on the Unknown Manufacturer fallback.
        $this->useWikiManufacturers(['ARGO' => 'Argo Astronautics']);

        $argo = $this->writeManufacturerXml('scitemmanufacturer.argo.xml', 'ARGO', 'ARGO', 'argo-xml-uuid', '@manufacturer_NameARGO');
        $service = $this->bootServiceWithManufacturers(['argo-xml-uuid' => $argo]);

        $result = $service->resolveForEntity(null, 'ARGO');

        self::assertSame('ARGO', $result['code']);
        self::assertSame('Argo Astronautics', $result['name']);
        self::assertSame('argo-xml-uuid', $result['uuid']);
    }

    public function test_resolve_for_entity_returns_null_when_entity_null_and_wiki_code_unknown(): void
    {
        // A wiki code that isn't curated must fall through to null so the caller
        // keeps the Unknown Manufacturer default rather than emitting a
        // half-applied {code, xmlName}.
        $service = $this->bootServiceWithManufacturers([]);

        self::assertNull($service->resolveForEntity(null, 'NOPE'));
    }

    public function test_resolve_for_entity_returns_canonical_code_name_uuid(): void
    {
        // Coded manufacturer: data.json canonicalizes code+name; uuid from XML.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $aegs = $this->writeManufacturerXml('scitemmanufacturer.aegs.xml', 'AEGS', 'AEGS', 'aegs-xml-uuid', '@manufacturer_NameAEGS');
        $service = $this->bootServiceWithManufacturers(['aegs-xml-uuid' => $aegs]);
        $mfr = $service->load($aegs);

        $result = $service->resolveForEntity($mfr, null);

        self::assertSame('AEGS', $result['code']);
        self::assertSame('Aegis Dynamics', $result['name']);
        self::assertSame('aegs-xml-uuid', $result['uuid']);
    }

    public function test_resolve_for_entity_resolves_codeless_alias_via_token(): void
    {
        // No Code attr, but @manufacturer_NameAEGS token -> resolves to AEGS.
        // A primary AEGS record exists, so the alias's uuid collapses to the
        // primary's -- the "one identity per manufacturer" guarantee.
        $this->useWikiManufacturers(['AEGS' => 'Aegis Dynamics']);

        $primary = $this->writeManufacturerXml('scitemmanufacturer.aegs.xml', 'AEGS', 'AEGS', 'aegs-xml-uuid', '@manufacturer_NameAEGS');
        $alias = $this->writeManufacturerXml('paintcolorlogo_aegs.xml', 'AEGS_LOGO', '', 'alias-uuid', '@manufacturer_NameAEGS');
        $service = $this->bootServiceWithManufacturers([
            'aegs-xml-uuid' => $primary,
            'alias-uuid' => $alias,
        ]);
        $mfr = $service->load($alias);

        $result = $service->resolveForEntity($mfr, null);

        self::assertSame('AEGS', $result['code']);
        self::assertSame('aegs-xml-uuid', $result['uuid']);
    }

    public function test_resolve_for_entity_wiki_code_wins(): void
    {
        $this->useWikiManufacturers(['GRIN' => 'Greycat Industrial']);

        $anvl = $this->writeManufacturerXml('scitemmanufacturer.anvl.xml', 'ANVL', 'ANVL', 'anvl-uuid', '@manufacturer_NameANVL');
        $service = $this->bootServiceWithManufacturers(['anvl-uuid' => $anvl]);
        $mfr = $service->load($anvl);

        // Wiki code GRIN overrides the XML code ANVL.
        $result = $service->resolveForEntity($mfr, 'GRIN');

        self::assertSame('GRIN', $result['code']);
        self::assertSame('Greycat Industrial', $result['name']);
    }

    public function test_resolve_for_entity_falls_back_to_xml_values_when_uncurated(): void
    {
        // Code not in data.json, name doesn't resolve -> raw XML values kept.
        $unknown = $this->writeManufacturerXml('scitemmanufacturer.xx.xml', 'XX', 'XX', 'xx-uuid', '@manufacturer_NameXX');
        $service = $this->bootServiceWithManufacturers(['xx-uuid' => $unknown]);
        $mfr = $service->load($unknown);

        $result = $service->resolveForEntity($mfr, null);

        self::assertSame('XX', $result['code']);
        self::assertSame('xx-uuid', $result['uuid']);
    }

    public function test_canonical_iterator_yields_manufacturers_with_non_manufacturer_name_tokens(): void
    {
        // Hangar/shop brands (Revel & York, Self-Land) carry code + uuid but use
        // @items_hangarName* / @item_NameShop_* tokens, not @manufacturer_*.
        // Identity is code-keyed and must not depend on the name token.
        $this->useWikiManufacturers(['REYO' => 'Revel & York', 'SELA' => 'SELF-LAND']);

        $reyo = $this->writeManufacturerXml('scitemmanufacturer.reyo.xml', 'REYO', 'REYO', 'reyo-uuid', '@items_hangarNameRevelYork');
        $sela = $this->writeManufacturerXml('scitemmanufacturer.sela.xml', 'SELA', 'SELA', 'sela-uuid', '@items_hangarNameSelfLand');

        $service = $this->bootServiceWithManufacturers([
            'reyo-uuid' => $reyo,
            'sela-uuid' => $sela,
        ]);

        $codes = [];
        foreach ($service->canonicalIterator() as $mfr) {
            $codes[$mfr->getCode()] = $mfr->getUuid();
        }

        self::assertSame(['REYO' => 'reyo-uuid', 'SELA' => 'sela-uuid'], $codes);
        // And code->uuid resolution works for them too.
        self::assertSame('reyo-uuid', $service->resolveCanonicalByCode('REYO')['uuid'] ?? null);
    }

    // -- Code aliases (manual map for prefix/singular-plural misses) --

    public function test_code_alias_relabels_code_and_name_but_preserves_xml_uuid(): void
    {
        // BEH XML name "Behring" doesn't normalize-match data.json "Behring Applied
        // Technology". The alias BEH -> BEHR relabels code+name to canonical, but
        // the uuid comes from BEH's XML record (BEHR has no XML).
        $this->useWikiManufacturers(['BEHR' => 'Behring Applied Technology']);

        $beh = $this->writeManufacturerXml('scitemmanufacturer.behr.xml', 'BEH', 'BEH', 'beh-xml-uuid', '@manufacturer_NameBEH');

        $service = $this->bootServiceWithManufacturers(['beh-xml-uuid' => $beh]);

        $result = $service->resolveCanonicalByNameOrCode('Behring', 'BEH');

        self::assertSame('BEHR', $result['code']);
        self::assertSame('Behring Applied Technology', $result['name']);
        self::assertSame('beh-xml-uuid', $result['uuid']);
    }

    public function test_resolve_canonical_by_code_honors_alias(): void
    {
        $this->useWikiManufacturers(['VNCL' => 'Vanduul Clans']);

        $vnc = $this->writeManufacturerXml('scitemmanufacturer.vncl.xml', 'VNC', 'VNC', 'vnc-xml-uuid', '@manufacturer_NameVNC');

        $service = $this->bootServiceWithManufacturers(['vnc-xml-uuid' => $vnc]);

        $result = $service->resolveCanonicalByCode('VNC');

        self::assertSame('VNCL', $result['code']);
        self::assertSame('Vanduul Clans', $result['name']);
        self::assertSame('vnc-xml-uuid', $result['uuid']);
    }

    public function test_code_alias_does_not_apply_for_same_code_variant_names(): void
    {
        // BANU is NOT in CODE_ALIASES (it's a same-code variant-name case, not a
        // cross-code alias). The name "Banu" misses, so the resolver falls through
        // to the BANU code lookup and returns "Banu Souli" from data.json. The
        // cross-code alias machinery is not involved.
        $this->useWikiManufacturers(['BANU' => 'Banu Souli']);

        $banu = $this->writeManufacturerXml('scitemmanufacturer.banu.xml', 'BANU', 'BANU', 'banu-xml-uuid');

        $service = $this->bootServiceWithManufacturers(['banu-xml-uuid' => $banu]);

        $result = $service->resolveCanonicalByNameOrCode('Banu', 'BANU');

        self::assertSame('BANU', $result['code']);
        self::assertSame('Banu Souli', $result['name']);
        self::assertSame('banu-xml-uuid', $result['uuid']);
    }

    private function writeManufacturerXml(
        string $fileName,
        string $className,
        string $code,
        string $uuid,
        string $nameKey = '@manufacturer_NameRSI',
    ): string {
        return $this->writeFile(
            "records/scitemmanufacturer/{$fileName}",
            <<<XML
            <SCItemManufacturer.{$className} Code="{$code}" __type="SCItemManufacturer" __ref="{$uuid}" __path="libs/foundry/records/scitemmanufacturer/{$fileName}">
                <Localization Name="{$nameKey}" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" __type="SCItemLocalization">
                    <displayFeatures __type="SCExtendedLocalizationLevelParams" />
                </Localization>
            </SCItemManufacturer.{$className}>
            XML
        );
    }

    /**
     * @param  array<string, string>  $uuidToPathMap
     */
    private function bootServiceWithManufacturers(array $uuidToPathMap): ManufacturerService
    {
        $this->writeCacheFiles(uuidToPathMap: $uuidToPathMap);

        $service = new ManufacturerService($this->tempDir);
        $service->initialize();

        return $service;
    }
}
