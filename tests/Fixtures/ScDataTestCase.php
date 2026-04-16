<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Fixtures;

use JsonException;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\TagDatabaseService;
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

        $mappingFile = $this->tempDir.DIRECTORY_SEPARATOR.'socpak_mappings.json';
        if (file_exists($mappingFile)) {
            @unlink($mappingFile);
        }

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
     * @param  array<string, array{uuid: string, path: string, type: string, sub_type: ?string}>  $entityMetadataMap
     *
     * @throws JsonException
     */
    protected function writeCacheFiles(
        array $classToTypeMap = [],
        array $classToPathMap = [],
        array $uuidToClassMap = [],
        array $classToUuidMap = [],
        array $uuidToPathMap = [],
        array $entityMetadataMap = []
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
        $this->writeCacheFile('entityMetadataMap', $entityMetadataMap);
    }

    /**
     * @param  array<string, string>  $resourceTypes
     *
     * @throws JsonException
     */
    protected function writeResourceTypeCache(array $resourceTypes): void
    {
        $this->writeCacheFile('resource-type-cache', $resourceTypes);
        $this->writeExtractedResourceTypeFiles($resourceTypes);
    }

    /**
     * @param  array<string, string>  $resourceTypes
     *
     * @throws JsonException
     */
    protected function writeExtractedResourceTypeFiles(array $resourceTypes): void
    {
        $resourceTypePathMap = [];
        $uuidToPathMap = $this->readCacheFileOrEmpty('uuidToPathMap');
        $uuidToClassMap = $this->readCacheFileOrEmpty('uuidToClassMap');
        $classToUuidMap = $this->readCacheFileOrEmpty('classToUuidMap');

        foreach ($resourceTypes as $uuid => $xml) {
            $normalizedUuid = strtolower($uuid);
            $path = $this->writeFile(
                sprintf('Game2/libs/foundry/records/resourcetypedatabase/%s.xml', $normalizedUuid),
                $xml
            );
            $className = $this->extractDocumentClassName($xml) ?? $normalizedUuid;

            $resourceTypePathMap[$uuid] = $path;
            $uuidToPathMap[$normalizedUuid] = $path;
            $uuidToClassMap[$normalizedUuid] = $className;
            $classToUuidMap[$className] = $normalizedUuid;
        }

        $classToPathMapPath = sprintf(
            '%s%sclassToPathMap-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );

        $classToPathMap = file_exists($classToPathMapPath)
            ? json_decode(file_get_contents($classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $classToPathMap['ResourceType'] = array_replace($classToPathMap['ResourceType'] ?? [], $resourceTypePathMap);

        $this->writeCacheFile('classToPathMap', $classToPathMap);
        $this->writeCacheFile('uuidToPathMap', $uuidToPathMap);
        $this->writeCacheFile('uuidToClassMap', $uuidToClassMap);
        $this->writeCacheFile('classToUuidMap', $classToUuidMap);
    }

    /**
     * @param  array<string, string>  $elements  Map of UUID => XML content
     *
     * @throws JsonException
     */
    protected function writeMineableElementCache(array $elements): void
    {
        $pathMap = [];
        $uuidToPathMap = $this->readCacheFileOrEmpty('uuidToPathMap');
        $uuidToClassMap = $this->readCacheFileOrEmpty('uuidToClassMap');
        $classToUuidMap = $this->readCacheFileOrEmpty('classToUuidMap');

        foreach ($elements as $uuid => $xml) {
            $normalizedUuid = strtolower($uuid);
            $path = $this->writeFile(
                sprintf('Game2/libs/foundry/records/mining/mineableelements/%s.xml', $normalizedUuid),
                $xml
            );
            $className = $this->extractDocumentClassName($xml) ?? $normalizedUuid;

            $pathMap[$className] = $path;
            $uuidToPathMap[$normalizedUuid] = $path;
            $uuidToClassMap[$normalizedUuid] = $className;
            $classToUuidMap[$className] = $normalizedUuid;
        }

        $classToPathMapPath = sprintf(
            '%s%sclassToPathMap-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );

        $classToPathMap = file_exists($classToPathMapPath)
            ? json_decode(file_get_contents($classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $classToPathMap['MineableElement'] = array_replace($classToPathMap['MineableElement'] ?? [], $pathMap);

        $this->writeCacheFile('classToPathMap', $classToPathMap);
        $this->writeCacheFile('uuidToPathMap', $uuidToPathMap);
        $this->writeCacheFile('uuidToClassMap', $uuidToClassMap);
        $this->writeCacheFile('classToUuidMap', $classToUuidMap);
    }

    /**
     * @param  array<string, string>  $properties
     *
     * @throws JsonException
     */
    protected function writeCraftingGameplayPropertyCache(array $properties): void
    {
        $this->writeCacheFile('crafting-gameplay-property-cache', $properties);
        $this->writeExtractedCraftingGameplayPropertyFiles($properties);
    }

    /**
     * @param  array<string, string>  $properties
     *
     * @throws JsonException
     */
    protected function writeExtractedCraftingGameplayPropertyFiles(array $properties): void
    {
        $propertyPathMap = [];
        $uuidToPathMap = $this->readCacheFileOrEmpty('uuidToPathMap');
        $uuidToClassMap = $this->readCacheFileOrEmpty('uuidToClassMap');
        $classToUuidMap = $this->readCacheFileOrEmpty('classToUuidMap');

        foreach ($properties as $uuid => $xml) {
            $normalizedUuid = strtolower($uuid);
            $path = $this->writeFile(
                sprintf('Game2/libs/foundry/records/crafting/craftedproperties/%s.xml', $normalizedUuid),
                $xml
            );
            $className = $this->extractDocumentClassName($xml) ?? $normalizedUuid;

            $propertyPathMap[$uuid] = $path;
            $uuidToPathMap[$normalizedUuid] = $path;
            $uuidToClassMap[$normalizedUuid] = $className;
            $classToUuidMap[$className] = $normalizedUuid;
        }

        $classToPathMapPath = sprintf(
            '%s%sclassToPathMap-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );

        $classToPathMap = file_exists($classToPathMapPath)
            ? json_decode(file_get_contents($classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $classToPathMap['CraftingGameplayPropertyDef'] = array_replace(
            $classToPathMap['CraftingGameplayPropertyDef'] ?? [],
            $propertyPathMap
        );

        $this->writeCacheFile('classToPathMap', $classToPathMap);
        $this->writeCacheFile('uuidToPathMap', $uuidToPathMap);
        $this->writeCacheFile('uuidToClassMap', $uuidToClassMap);
        $this->writeCacheFile('classToUuidMap', $classToUuidMap);
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<int, array{name: string, uuid: string, legacyGUID?: string}>  $tags
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

        $this->writeExtractedTagFiles($tags);

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
     * @param  array<int, array{name: string, uuid: string, legacyGUID?: string}>  $tags
     *
     * @throws JsonException
     */
    protected function writeExtractedTagFiles(array $tags): void
    {
        $tagPathMap = [];

        foreach ($tags as $tag) {
            $uuid = $tag['uuid'];
            $legacyGUID = $tag['legacyGUID'] ?? '4294967295';
            $path = $this->writeFile(
                sprintf('Game2/libs/foundry/records/tagdatabase/%s.xml', $uuid),
                sprintf(
                    '<Tag.%1$s tagName="%2$s" legacyGUID="%3$s" __type="Tag" __ref="%4$s" __path="libs/foundry/records/tagdatabase/tagdatabase.tagdatabase.xml" />',
                    $uuid,
                    $tag['name'],
                    $legacyGUID,
                    $uuid
                )
            );

            $tagPathMap[$uuid] = $path;
        }

        $classToPathMapPath = sprintf(
            '%s%sclassToPathMap-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );

        $classToPathMap = file_exists($classToPathMapPath)
            ? json_decode(file_get_contents($classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $classToPathMap['Tag'] = array_replace($classToPathMap['Tag'] ?? [], $tagPathMap);

        $this->writeCacheFile('classToPathMap', $classToPathMap);
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

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readCacheFileOrEmpty(string $name): array
    {
        $path = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY);

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function extractDocumentClassName(string $xml): ?string
    {
        if (! preg_match('/<([A-Za-z0-9_]+\.[^ \/>\t\r\n]+)/', $xml, $matches)) {
            return null;
        }

        $parts = explode('.', $matches[1], 2);

        return $parts[1] ?? null;
    }

    private function resetServiceState(): void
    {
        ServiceFactory::reset();
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
