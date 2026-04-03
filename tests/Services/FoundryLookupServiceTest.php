<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class FoundryLookupServiceTest extends ScDataTestCase
{
    private const RAW_ORE_UUID = '11111111-1111-1111-1111-111111111111';

    private const REFINED_ORE_UUID = '22222222-2222-2222-2222-222222222222';

    private const MISSION_TYPE_UUID = '33333333-3333-3333-3333-333333333333';

    private const GENERIC_RECORD_UUID = '44444444-4444-4444-4444-444444444444';

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
