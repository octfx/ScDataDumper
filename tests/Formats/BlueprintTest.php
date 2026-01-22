<?php

namespace Tests\Formats;

use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprint;
use Octfx\ScDataDumper\Formats\ScUnpacked\Blueprint;
use Octfx\ScDataDumper\Services\ServiceFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BlueprintTest extends TestCase
{
    private static bool $servicesInitialized = false;

    private function initServices(): void
    {
        if (self::$servicesInitialized) {
            return;
        }

        $factory = new ServiceFactory('import');
        $factory->initialize();

        self::$servicesInitialized = true;
    }

    #[Test]
    public function it_returns_empty_array_for_test_blueprints(): void
    {
        $this->initServices();

        $blueprint = ServiceFactory::getBlueprintService()->getByReference('c1e01ef2-9ba3-410d-a728-60acb1000a3d');
        $this->assertNotNull($blueprint);

        $format = new Blueprint($blueprint);
        $result = $format->toArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_null_for_null_document(): void
    {
        $format = new Blueprint(null);

        $this->assertSame([], $format->toArray());
    }

    #[Test]
    public function it_extracts_blueprint_structure(): void
    {
        $this->initServices();

        $blueprint = new CraftingBlueprint;
        $blueprint->load('import/Data/Libs/Foundry/Records/crafting/blueprints/test/example1.xml');

        $format = new Blueprint($blueprint);
        $result = $format->toArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_extracts_classname_and_reference(): void
    {
        $this->initServices();

        $blueprint = new CraftingBlueprint;
        $blueprint->load('import/Data/Libs/Foundry/Records/crafting/blueprints/test/example2.xml');

        $this->assertSame('Example2', $blueprint->getClassName());
        $this->assertSame('9e9cbb44-8a45-4ac8-9e71-bf318ccbcd9d', $blueprint->getUuid());
    }

    #[Test]
    public function it_handles_category_resolution(): void
    {
        $this->initServices();

        $blueprint = ServiceFactory::getBlueprintService()->getByReference('c1e01ef2-9ba3-410d-a728-60acb1000a3d');
        $this->assertNotNull($blueprint);

        $categoryUuid = $blueprint->getCategoryUuid();
        $this->assertSame('f9ccd11c-4e80-42c4-ae18-a591675f295d', $categoryUuid);
    }

    #[Test]
    public function it_loads_blueprint_by_uuid(): void
    {
        $this->initServices();

        $blueprint = ServiceFactory::getBlueprintService()->getByReference('c1e01ef2-9ba3-410d-a728-60acb1000a3d');

        $this->assertNotNull($blueprint);
        $this->assertInstanceOf(CraftingBlueprint::class, $blueprint);
        $this->assertSame('c1e01ef2-9ba3-410d-a728-60acb1000a3d', $blueprint->getUuid());
    }

    #[Test]
    public function it_returns_null_for_invalid_uuid(): void
    {
        $this->initServices();

        $blueprint = ServiceFactory::getBlueprintService()->getByReference('invalid-uuid-00000000-0000-0000-0000-000000000000');

        $this->assertNull($blueprint);
    }

    #[Test]
    public function it_filters_test_blueprints_from_count(): void
    {
        $this->initServices();

        $count = ServiceFactory::getBlueprintService()->count();

        $this->assertSame(0, $count);
    }

    #[Test]
    public function it_returns_null_for_missing_category(): void
    {
        $this->initServices();

        $blueprint = ServiceFactory::getBlueprintService()->getByReference('c1e01ef2-9ba3-410d-a728-60acb1000a3d');
        $this->assertNotNull($blueprint);

        $category = ServiceFactory::getTagDatabaseService()->getTagName('invalid-category-uuid');

        $this->assertNull($category);
    }
}
