<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Fixtures;

use JsonException;
use Octfx\ScDataDumper\Services\BaseService;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\TagDatabaseService;
use Octfx\ScDataDumper\Services\VehicleService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

abstract class ScDataTestCase extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sc-data-dumper-test-'.str_replace('.', '', uniqid('', true));
        if (! mkdir($this->tempDir, 0777, true) && ! is_dir($this->tempDir)) {
            throw new RuntimeException(sprintf('Failed to create test directory: %s', $this->tempDir));
        }

        $this->resetServiceState();
    }

    protected function tearDown(): void
    {
        $this->resetServiceState();
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    protected function writeFile(string $relativePath, string $contents): string
    {
        $fullPath = $this->tempDir.DIRECTORY_SEPARATOR.$relativePath;
        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        file_put_contents($fullPath, trim($contents).PHP_EOL);

        $realPath = realpath($fullPath);
        if (! is_string($realPath)) {
            throw new RuntimeException(sprintf('Failed to resolve real path for: %s', $fullPath));
        }

        return str_replace('\\', '/', $realPath);
    }

    /**
     * @param  array<string, mixed>  $classToTypeMap
     * @param  array<string, array<string, string>>  $classToPathMap
     * @param  array<string, string>  $uuidToClassMap
     * @param  array<string, string>  $classToUuidMap
     * @param  array<string, string>  $uuidToPathMap
     *
     * @throws JsonException
     */
    protected function writeCacheFiles(
        array $classToTypeMap = [],
        array $classToPathMap = [],
        array $uuidToClassMap = [],
        array $classToUuidMap = [],
        array $uuidToPathMap = []
    ): void {
        $this->writeCacheFile('classToTypeMap', $classToTypeMap);
        $this->writeCacheFile('classToPathMap', array_replace_recursive([
            'EntityClassDefinition' => [],
            'InventoryContainer' => [],
            'CargoGrid' => [],
        ], $classToPathMap));
        $this->writeCacheFile('uuidToClassMap', $uuidToClassMap);
        $this->writeCacheFile('classToUuidMap', $classToUuidMap);
        $this->writeCacheFile('uuidToPathMap', $uuidToPathMap);
    }

    /**
     * @param  array<string, string>  $resourceTypes
     *
     * @throws JsonException
     */
    protected function writeResourceTypeCache(array $resourceTypes): void
    {
        $this->writeCacheFile('resource-type-cache', $resourceTypes);
    }

    /**
     * @param  array<string, string>  $properties
     *
     * @throws JsonException
     */
    protected function writeCraftingGameplayPropertyCache(array $properties): void
    {
        $this->writeCacheFile('crafting-gameplay-property-cache', $properties);
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<int, array{name: string, uuid: string}>  $tags
     *
     * @throws JsonException
     */
    protected function initializeMinimalItemServices(
        array $translations = ['LOC_EMPTY' => ''],
        array $tags = []
    ): void {
        $localizationLines = [];
        foreach ($translations as $key => $value) {
            $localizationLines[] = sprintf('%s=%s', $key, $value);
        }

        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, $localizationLines)
        );

        $tagNodes = array_map(
            static fn (array $tag): string => sprintf(
                '<Tag.%s tagName="%s" __ref="%s" />',
                strtoupper(str_replace('-', '_', $tag['name'])),
                $tag['name'],
                $tag['uuid']
            ),
            $tags
        );

        $this->writeFile('Data/Game2.xml', "<GameData>\n    ".implode("\n    ", $tagNodes)."\n</GameData>");

        $inventoryContainerService = new InventoryContainerService($this->tempDir);
        $inventoryContainerService->initialize();

        $manufacturerService = new ManufacturerService($this->tempDir);
        $manufacturerService->initialize();

        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $localizationService = new LocalizationService($this->tempDir);
        $localizationService->initialize();

        $tagDatabaseService = new TagDatabaseService($this->tempDir);
        $tagDatabaseService->initialize();

        $factory = new ReflectionClass(ServiceFactory::class);
        $factory->getProperty('initialized')->setValue(null, true);
        $factory->getProperty('activeScDataPath')->setValue(null, $this->tempDir);
        $factory->getProperty('services')->setValue(null, [
            'InventoryContainerService' => $inventoryContainerService,
            'ManufacturerService' => $manufacturerService,
            'ItemService' => $itemService,
            'LocalizationService' => $localizationService,
            'TagDatabaseService' => $tagDatabaseService,
            'ItemClassifierService' => new ItemClassifierService,
        ]);
    }

    protected function bootstrapItemFormattingServices(): void
    {
        $this->initializeMinimalItemServices();
    }

    /**
     * @throws JsonException
     */
    protected function initializeBlueprintDefinitionServices(): void
    {
        (new ServiceFactory($this->tempDir))->initialize();
    }

    private function writeCacheFile(string $name, array $payload): void
    {
        $path = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resetServiceState(): void
    {
        $baseService = new ReflectionClass(BaseService::class);
        foreach (['uuidToPathMap', 'uuidToClassMap', 'classToUuidMap'] as $propertyName) {
            $baseService->getProperty($propertyName)->setValue(null, []);
        }

        $itemService = new ReflectionClass(ItemService::class);
        $itemService->getProperty('documentCache')->setValue(null, []);

        $vehicleService = new ReflectionClass(VehicleService::class);
        $vehicleService->getProperty('documentCache')->setValue(null, []);

        $factory = new ReflectionClass(ServiceFactory::class);
        $factory->getProperty('initialized')->setValue(null, false);
        $factory->getProperty('activeScDataPath')->setValue(null, null);
        $factory->getProperty('services')->setValue(null, []);
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
