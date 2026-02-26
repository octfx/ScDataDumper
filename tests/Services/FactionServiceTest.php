<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Faction;
use Octfx\ScDataDumper\Services\BaseService;
use Octfx\ScDataDumper\Services\FactionService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

final class FactionServiceTest extends TestCase
{
    private const FACTION_UUID = 'FACTION-UUID-ABC-123';

    private string $tempDir;

    private string $factionPath;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sc-data-dumper-faction-service-test-'.str_replace('.', '', uniqid('', true));
        if (! mkdir($this->tempDir, 0777, true) && ! is_dir($this->tempDir)) {
            throw new RuntimeException(sprintf('Failed to create test directory: %s', $this->tempDir));
        }

        $this->resetServiceState();
        $this->factionPath = $this->writeFactionFile();
        $this->writeCacheFiles();
    }

    protected function tearDown(): void
    {
        $this->resetServiceState();
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function test_get_by_reference_returns_faction_for_exact_uuid_hit(): void
    {
        $service = new FactionService($this->tempDir);
        $service->initialize();

        $faction = $service->getByReference(self::FACTION_UUID);

        $this->assertInstanceOf(Faction::class, $faction);
        $this->assertSame(self::FACTION_UUID, $faction?->getUuid());
        $this->assertSame('libs/foundry/records/factions/test-faction.xml', $faction?->getPath());
    }

    /**
     * @throws JsonException
     */
    public function test_get_by_reference_returns_null_for_non_exact_uuid_miss(): void
    {
        $service = new FactionService($this->tempDir);
        $service->initialize();

        $this->assertNull($service->getByReference(strtolower(self::FACTION_UUID)));
    }

    private function writeFactionFile(): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'factions'.DIRECTORY_SEPARATOR.'test-faction.xml';
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create test directory: %s', $directory));
        }

        $xml = <<<XML
            <Faction.TestFaction __type="Faction" __ref="FACTION-UUID-ABC-123" __path="libs/foundry/records/factions/test-faction.xml">
                <DisplayName value="Test Faction"/>
            </Faction.TestFaction>
            XML;

        file_put_contents($path, trim($xml).PHP_EOL);

        $resolvedPath = realpath($path);
        if (! is_string($resolvedPath)) {
            throw new RuntimeException(sprintf('Failed to resolve path: %s', $path));
        }

        return str_replace('\\', '/', $resolvedPath);
    }

    /**
     * @throws JsonException
     */
    private function writeCacheFiles(): void
    {
        $this->writeCacheFile('classToTypeMap', []);
        $this->writeCacheFile('classToPathMap', []);
        $this->writeCacheFile('uuidToClassMap', []);
        $this->writeCacheFile('classToUuidMap', []);
        $this->writeCacheFile('uuidToPathMap', [
            self::FACTION_UUID => $this->factionPath,
        ]);
    }

    /**
     * @throws JsonException
     */
    private function writeCacheFile(string $name, array $payload): void
    {
        $path = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resetServiceState(): void
    {
        $baseService = new ReflectionClass(BaseService::class);
        foreach (['uuidToPathMap', 'uuidToClassMap', 'classToUuidMap'] as $propertyName) {
            $property = $baseService->getProperty($propertyName);
            $property->setValue(null, []);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
