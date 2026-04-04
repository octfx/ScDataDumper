<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\Mining\MineableService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use RuntimeException;

final class MineableServiceTest extends ScDataTestCase
{
    public function test_resolves_rows_by_uuid_using_exact_match(): void
    {
        $this->writeFile(
            'mineables/mineables.json',
            <<<'JSON'
            [
              {
                "uuid": "20000000-0000-0000-0000-000000000001",
                "key": "SampleMineable",
                "composition": {
                  "parts": []
                }
              }
            ]
            JSON
        );

        $service = new MineableService($this->tempDir);

        self::assertSame(1, $service->count());
        self::assertTrue($service->has('20000000-0000-0000-0000-000000000001'));
        self::assertTrue($service->has('20000000-0000-0000-0000-000000000001'));
        self::assertSame('SampleMineable', $service->getByReference('20000000-0000-0000-0000-000000000001')['key']);
        self::assertSame('SampleMineable', $service->getByReference('20000000-0000-0000-0000-000000000001')['key']);
        self::assertNull($service->getByReference(' 20000000-0000-0000-0000-000000000001 '));
        self::assertNull($service->getByReference('20000000-0000-0000-0000-000000000001 '));
        self::assertNull($service->getByReference('20000000-0000-0000-0000-000000000099'));
        self::assertCount(1, $service->getAll());
    }

    public function test_factory_resolves_mineables_from_json_export_root(): void
    {
        $exportsDir = $this->tempDir.DIRECTORY_SEPARATOR.'exports';
        $this->writeFile(
            'exports/mineables/mineables.json',
            <<<'JSON'
            [
              {
                "uuid": "20000000-0000-0000-0000-000000000001",
                "key": "SampleMineable",
                "composition": {
                  "parts": []
                }
              }
            ]
            JSON
        );

        (new ServiceFactory($this->tempDir, $exportsDir))->initialize();

        $service = ServiceFactory::getMineableService();

        self::assertSame('SampleMineable', $service->getByReference('20000000-0000-0000-0000-000000000001')['key']);
    }

    public function test_factory_exposes_mineable_service(): void
    {
        $this->writeFile(
            'mineables/mineables.json',
            <<<'JSON'
            [
              {
                "uuid": "20000000-0000-0000-0000-000000000001",
                "key": "SampleMineable",
                "composition": {
                  "parts": []
                }
              }
            ]
            JSON
        );

        (new ServiceFactory($this->tempDir))->initialize();

        $service = ServiceFactory::getMineableService();

        self::assertSame('SampleMineable', $service->getByReference('20000000-0000-0000-0000-000000000001')['key']);
    }

    public function test_throws_clear_error_when_index_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mineable index is missing');
        $this->expectExceptionMessage('load:mineables');

        new MineableService($this->tempDir);
    }

    public function test_throws_clear_error_for_invalid_json(): void
    {
        $this->writeFile('mineables/mineables.json', '{invalid json}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains invalid JSON');

        new MineableService($this->tempDir);
    }

    public function test_throws_clear_error_for_rows_without_uuid(): void
    {
        $this->writeFile(
            'mineables/mineables.json',
            <<<'JSON'
            [
              {
                "key": "BrokenMineable"
              }
            ]
            JSON
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a valid uuid');

        new MineableService($this->tempDir);
    }
}
