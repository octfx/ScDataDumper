<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\ConsumableSubtype;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityDistributionRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityLocationOverrideRecord;
use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class FoundryLookupServiceTest extends ScDataTestCase
{
    private const RAW_ORE_UUID = '11111111-1111-1111-1111-111111111111';

    private const REFINED_ORE_UUID = '22222222-2222-2222-2222-222222222222';

    private const MISSION_TYPE_UUID = '33333333-3333-3333-3333-333333333333';

    private const GENERIC_RECORD_UUID = '44444444-4444-4444-4444-444444444444';

    private const CONSUMABLE_SUBTYPE_UUID = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

    private const CRAFTING_RESOURCE_UUID = '66666666-6666-6666-6666-666666666666';

    private const QUALITY_DISTRIBUTION_UUID = '77777777-7777-7777-7777-777777777777';

    private const QUALITY_LOCATION_OVERRIDE_UUID = '88888888-8888-8888-8888-888888888888';

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeCacheFiles();
        $this->writeResourceTypeCache([
            self::RAW_ORE_UUID => sprintf(
                '<ResourceType.RawOre displayName="@items_commodities_rawore" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />',
                self::RAW_ORE_UUID,
            ),
            self::REFINED_ORE_UUID => sprintf(
                '<ResourceType.RefinedOre displayName="@items_commodities_refinedore" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />',
                self::REFINED_ORE_UUID,
            ),
        ]);

        $this->writeFoundryRecord(
            self::MISSION_TYPE_UUID,
            'records/missiontype',
            sprintf(
                '<FoundryRecord.PickupMission __type="FoundryRecord" __ref="%1$s" __path="libs/foundry/records/missiontype/pickupmission.xml" />',
                self::MISSION_TYPE_UUID,
            )
        );

        $this->writeFoundryRecord(
            self::GENERIC_RECORD_UUID,
            'records/misc',
            sprintf(
                '<FoundryRecord.GenericRecord __type="FoundryRecord" __ref="%1$s" __path="libs/foundry/records/misc/genericrecord.xml" />',
                self::GENERIC_RECORD_UUID,
            )
        );

        $this->writeFoundryRecord(
            self::CONSUMABLE_SUBTYPE_UUID,
            'records/entities/consumables/subtypes',
            sprintf(
                '<ConsumableSubtype.TestConsumable __type="ConsumableSubtype" __ref="%1$s" __path="libs/foundry/records/entities/consumables/subtypes/test_consumable.xml" typeName="Food" consumableName="Fruit Bar"><effectsPerMicroSCU><ConsumableEffectModifyActorStatus statType="Hunger" statPointChange="4.5" /></effectsPerMicroSCU></ConsumableSubtype.TestConsumable>',
                self::CONSUMABLE_SUBTYPE_UUID,
            )
        );
    }

    public function test_count_document_type_counts_entries_from_class_to_path_map(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        self::assertSame(2, $service->countDocumentType('ResourceType'));
    }

    public function test_get_document_type_yields_typed_documents(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        $documents = iterator_to_array($service->getDocumentType('ResourceType', ResourceType::class));

        self::assertCount(2, $documents);
        self::assertContainsOnlyInstancesOf(ResourceType::class, $documents);
        self::assertSame(
            [self::RAW_ORE_UUID, self::REFINED_ORE_UUID],
            array_map(static fn (ResourceType $document): string => $document->getUuid(), $documents)
        );
    }

    public function test_get_resource_type_by_reference_returns_typed_document(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        $resourceType = $service->getResourceTypeByReference(self::RAW_ORE_UUID);

        self::assertInstanceOf(ResourceType::class, $resourceType);
        self::assertSame('RawOre', $resourceType?->getClassName());
    }

    public function test_resource_type_loading_without_crafting_references_does_not_require_service_factory(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        $documents = iterator_to_array($service->getDocumentType('ResourceType', ResourceType::class));
        $resourceType = $service->getResourceTypeByReference(self::RAW_ORE_UUID);

        self::assertCount(2, $documents);
        self::assertInstanceOf(ResourceType::class, $resourceType);
        self::assertNull($resourceType?->getQualityDistribution());
        self::assertNull($resourceType?->getQualityLocationOverride());
    }

    public function test_resource_type_with_crafting_references_hydrates_children_when_service_factory_is_initialized(): void
    {
        $this->writeResourceTypeCache([
            self::CRAFTING_RESOURCE_UUID => sprintf(
                <<<'XML'
                <ResourceType.CraftingOre displayName="@items_commodities_craftingore" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <properties>
                    <ResourceTypeCraftingData>
                      <qualityDistribution>
                        <CraftingQualityDistribution_RecordRef qualityDistributionRecord="%2$s" />
                      </qualityDistribution>
                      <qualityLocationOverride>
                        <CraftingQualityLocationOverride_RecordRef locationOverrideRecord="%3$s" />
                      </qualityLocationOverride>
                    </ResourceTypeCraftingData>
                  </properties>
                </ResourceType.CraftingOre>
                XML,
                self::CRAFTING_RESOURCE_UUID,
                self::QUALITY_DISTRIBUTION_UUID,
                self::QUALITY_LOCATION_OVERRIDE_UUID,
            ),
        ]);
        $this->writeFoundryRecord(
            self::QUALITY_DISTRIBUTION_UUID,
            'records/crafting/qualitydistribution',
            sprintf(
                <<<'XML'
                <CraftingQualityDistributionRecord.Default __type="CraftingQualityDistributionRecord" __ref="%1$s" __path="libs/foundry/records/crafting/qualitydistribution/%1$s.xml">
                  <qualityDistribution>
                    <CraftingQualityDistributionNormal min="100" max="900" mean="500" stddev="100" />
                  </qualityDistribution>
                </CraftingQualityDistributionRecord.Default>
                XML,
                self::QUALITY_DISTRIBUTION_UUID,
            )
        );
        $this->writeFoundryRecord(
            self::QUALITY_LOCATION_OVERRIDE_UUID,
            'records/crafting/qualitydistribution',
            sprintf(
                <<<'XML'
                <CraftingQualityLocationOverrideRecord.Default __type="CraftingQualityLocationOverrideRecord" __ref="%1$s" __path="libs/foundry/records/crafting/qualitydistribution/%1$s.xml">
                  <locationOverride>
                    <CraftingQualityLocationOverride>
                      <locationOverrideList />
                    </CraftingQualityLocationOverride>
                  </locationOverride>
                </CraftingQualityLocationOverrideRecord.Default>
                XML,
                self::QUALITY_LOCATION_OVERRIDE_UUID,
            )
        );

        (new ServiceFactory($this->tempDir))->initialize();

        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();
        $resourceType = $service->getResourceTypeByReference(self::CRAFTING_RESOURCE_UUID);

        self::assertInstanceOf(ResourceType::class, $resourceType);
        self::assertSame(self::QUALITY_DISTRIBUTION_UUID, $resourceType?->getQualityDistributionReference());
        self::assertSame(self::QUALITY_LOCATION_OVERRIDE_UUID, $resourceType?->getQualityLocationOverrideReference());
        self::assertInstanceOf(CraftingQualityDistributionRecord::class, $resourceType?->getQualityDistribution());
        self::assertInstanceOf(CraftingQualityLocationOverrideRecord::class, $resourceType?->getQualityLocationOverride());
    }

    public function test_filtered_lookup_returns_null_for_mismatched_path(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        self::assertNull($service->getMissionTypeByReference(self::GENERIC_RECORD_UUID));
    }

    public function test_filtered_lookup_returns_foundry_record_for_matching_path(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        $record = $service->getMissionTypeByReference(self::MISSION_TYPE_UUID);

        self::assertInstanceOf(FoundryRecord::class, $record);
        self::assertSame('PickupMission', $record?->getClassName());
    }

    public function test_get_consumable_subtype_by_reference_uses_exact_uuid_match_and_reuses_cached_instance(): void
    {
        $service = new FoundryLookupService($this->tempDir);
        $service->initialize();

        $mismatchedDocument = $service->getConsumableSubtypeByReference(strtoupper(self::CONSUMABLE_SUBTYPE_UUID));
        $document = $service->getConsumableSubtypeByReference(self::CONSUMABLE_SUBTYPE_UUID);
        $secondDocument = $service->getConsumableSubtypeByReference(self::CONSUMABLE_SUBTYPE_UUID);
        $cachedDocument = $service->getConsumableSubtypeByReference(self::CONSUMABLE_SUBTYPE_UUID);

        self::assertNull($mismatchedDocument);
        self::assertInstanceOf(ConsumableSubtype::class, $document);
        self::assertNotSame($document, $secondDocument);
        self::assertSame($secondDocument, $cachedDocument);
        self::assertSame('Fruit Bar', $document?->getConsumableName());
        self::assertNull($service->getConsumableSubtypeByReference('00000000-0000-0000-0000-000000000000'));
    }

    private function writeFoundryRecord(string $uuid, string $relativeDirectory, string $xml): void
    {
        $normalizedUuid = strtolower($uuid);
        $path = $this->writeFile(sprintf('Game2/libs/foundry/%s/%s.xml', $relativeDirectory, $normalizedUuid), $xml);

        $this->mergeCacheFile('uuidToPathMap', [$normalizedUuid => $path]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function mergeCacheFile(string $name, array $values): void
    {
        $path = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY);
        $current = file_exists($path)
            ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            : [];

        file_put_contents($path, json_encode(array_replace($current, $values), JSON_THROW_ON_ERROR));
    }
}
