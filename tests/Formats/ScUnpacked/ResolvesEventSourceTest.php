<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\ResolvesEventSource;
use Octfx\ScDataDumper\Services\DataOverrideService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use ReflectionMethod;

/**
 * ResolvesEventSource: wiki overrides are authoritative; heuristics fill the
 * gaps. Validates the migration from event_sources.csv to DataOverrideService.
 */
final class ResolvesEventSourceTest extends ScDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DataOverrideService::reset();
        EventSourceStub::resetEventSourceCache();

        $this->writeCacheFiles();
        new ServiceFactory($this->tempDir)->initialize();
    }

    public function test_curated_uuid_returns_wiki_event(): void
    {
        // 1782c749.. is curated as Foundation Festival in wiki_items.json.
        $this->useWikiItems(['1782c749-a756-4244-96f9-26362c81abeb' => ['event' => 'Foundation Festival']]);

        $result = $this->resolve('Paint_Anything', [], '1782c749-a756-4244-96f9-26362c81abeb');

        self::assertSame(['Foundation Festival'], $result);
    }

    public function test_wiki_suppresses_heuristic_for_curated_uuid(): void
    {
        // Unity paints carry _invictus (heuristic would say Invictus Launch
        // Week) but are curated Foundation Festival. Wiki must win.
        $this->useWikiItems(['1782c749-a756-4244-96f9-26362c81abeb' => ['event' => 'Foundation Festival']]);

        $result = $this->resolve(
            'Paint_100i_Invictus_Unity_Metal_Teal',
            [],
            '1782c749-a756-4244-96f9-26362c81abeb',
        );

        self::assertSame(['Foundation Festival'], $result);
        self::assertNotContains('Invictus Launch Week', $result);
    }

    public function test_heuristic_fires_for_uncurated_uuid(): void
    {
        // UUID not in wiki_items.json: heuristic rules take over.
        $result = $this->resolve('Paint_Razor_Invictus', [], '99999999-9999-9999-9999-999999999999');

        self::assertContains('Invictus Launch Week', $result);
    }

    public function test_returns_empty_when_uncurated_and_no_rule_matches(): void
    {
        $result = $this->resolve('Paint_Bland_Default', [], '99999999-9999-9999-9999-999999999999');

        self::assertSame([], $result);
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function resolve(string $className, array $tags, ?string $uuid): array
    {
        $method = new ReflectionMethod(EventSourceStub::class, 'resolveEventSource');

        return $method->invoke(null, $className, $tags, $uuid);
    }
}

final class EventSourceStub
{
    use ResolvesEventSource;
}
