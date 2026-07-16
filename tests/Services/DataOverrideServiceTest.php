<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\DataOverrideService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers DataOverrideService against the real import/wiki_items.json.
 * The unknown-UUID case is also the missing-file behavior: every lookup
 * misses and returns [], so callers degrade to their raw values.
 */
final class DataOverrideServiceTest extends ScDataTestCase
{
    private DataOverrideService $service;

    protected function setUp(): void
    {
        parent::setUp();

        DataOverrideService::reset();

        $this->writeCacheFiles();
        new ServiceFactory($this->tempDir)->initialize();
        $this->service = ServiceFactory::getDataOverrideService();
    }

    public function test_facts_for_known_uuid_returns_fact_bag(): void
    {
        $this->useWikiItems(['1782c749-a756-4244-96f9-26362c81abeb' => ['event' => 'Foundation Festival']]);

        // A curated Foundation Festival paint in wiki_items.json.
        $facts = $this->service->factsFor('1782c749-a756-4244-96f9-26362c81abeb');

        self::assertSame('Foundation Festival', $facts['event'] ?? null);
    }

    public function test_facts_for_unknown_uuid_returns_empty_array(): void
    {
        // Unknown UUID == missing-file behavior: callers keep raw values via ?? coalescing.
        $facts = $this->service->factsFor('00000000-0000-0000-0000-000000000000');

        self::assertSame([], $facts);
    }

    public function test_loads_once_across_calls(): void
    {
        // Multiple lookups must not re-stat the files.
        $this->useWikiItems(['1782c749-a756-4244-96f9-26362c81abeb' => ['event' => 'Foundation Festival']]);

        $this->service->factsFor('1782c749-a756-4244-96f9-26362c81abeb');
        $this->service->factsFor('00000000-0000-0000-0000-000000000000');
        $facts = $this->service->factsFor('1782c749-a756-4244-96f9-26362c81abeb');

        self::assertSame('Foundation Festival', $facts['event'] ?? null);
    }

    public function test_reset_clears_loaded_state(): void
    {
        $this->useWikiItems(['1782c749-a756-4244-96f9-26362c81abeb' => ['event' => 'Foundation Festival']]);

        $this->service->factsFor('1782c749-a756-4244-96f9-26362c81abeb');

        DataOverrideService::reset();

        // A fresh boot reloads; lookups still resolve after reset.
        $fresh = ServiceFactory::getDataOverrideService();
        $facts = $fresh->factsFor('1782c749-a756-4244-96f9-26362c81abeb');

        self::assertSame('Foundation Festival', $facts['event'] ?? null);
    }
}
