<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\ResourceTypeService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ResourceTypeServiceTest extends ScDataTestCase
{
    private const GOLD_UUID = '21825507-7923-4683-9bf3-9cfe316940e3';

    private const HEPHAESTANITE_UUID = '61189578-ed7a-4491-9774-37ae2f82b8b0';

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeCacheFiles();
        $this->writeExtractedResourceTypeFiles([
            self::GOLD_UUID => <<<'XML'
            <ResourceType.Gold displayName="@items_commodities_gold" description="@items_commodities_gold_desc" defaultThumbnailPath="UI/SharedAssets/commodityLogos/Inv_Icon_Gold.tif" rttThumbnailEntityClass="2c2b877d-0ae6-4139-8244-363b4daf365e" validateDefaultCargoBox="1" __type="ResourceType" __ref="21825507-7923-4683-9bf3-9cfe316940e3" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
              <densityType>
                <ResourceTypeDensity>
                  <densityUnit>
                    <GramsPerCubicCentimeter gramsPerCubicCentimeter="1" />
                  </densityUnit>
                </ResourceTypeDensity>
              </densityType>
            </ResourceType.Gold>
            XML,
            self::HEPHAESTANITE_UUID => <<<'XML'
            <ResourceType.Hephaestanite displayName="@items_commodities_hephaestanite" description="@items_commodities_hephaestanite_desc" defaultThumbnailPath="UI/SharedAssets/commodityLogos/Inv_Icon_Hephaestanite.tif" rttThumbnailEntityClass="1dcc30b8-d97b-446e-8735-ee7810fe2777" validateDefaultCargoBox="1" __type="ResourceType" __ref="61189578-ed7a-4491-9774-37ae2f82b8b0" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
              <densityType>
                <ResourceTypeDensity>
                  <densityUnit>
                    <GramsPerCubicCentimeter gramsPerCubicCentimeter="1" />
                  </densityUnit>
                </ResourceTypeDensity>
              </densityType>
            </ResourceType.Hephaestanite>
            XML,
        ]);
    }

    public function test_get_by_reference_returns_resource_type_document_from_extracted_file(): void
    {
        $service = new ResourceTypeService($this->tempDir);
        $service->initialize();

        $resourceType = $service->getByReference(self::GOLD_UUID);

        self::assertInstanceOf(ResourceType::class, $resourceType);
        self::assertSame(self::GOLD_UUID, $resourceType?->getUuid());
        self::assertSame('Gold', $resourceType?->getClassName());
        self::assertSame('@items_commodities_gold', $resourceType?->get('@displayName'));
    }

    public function test_iterator_skips_malformed_resource_type_documents(): void
    {
        $invalidUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->writeExtractedResourceTypeFiles([
            $invalidUuid => '<ResourceType.Broken',
        ]);

        $service = new ResourceTypeService($this->tempDir);
        $service->initialize();

        self::assertNull($service->getByReference($invalidUuid));
        self::assertCount(2, iterator_to_array($service->iterator()));
    }

    public function test_initialize_fails_when_resource_type_paths_are_missing(): void
    {
        $this->writeCacheFiles(classToPathMap: []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing resource type paths');

        $service = new ResourceTypeService($this->tempDir);
        $service->initialize();
    }
}
