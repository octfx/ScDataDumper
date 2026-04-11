<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use JsonException;
use Octfx\ScDataDumper\Services\BaseService;
use Octfx\ScDataDumper\Services\CacheService;
use Octfx\ScDataDumper\Services\ItemService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use XMLReader;

final class CacheServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sc-data-dumper-cache-test-'.str_replace('.', '', uniqid('', true));
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

    /**
     * @throws JsonException
     */
    public function test_hyphenated_class_names_are_preserved_in_generated_cache_maps(): void
    {
        $className = 'COMP_BEHR_S01_CSR-RP';
        $uuid = 'uuid-hyphen-class-1';
        $entityPath = $this->writeXmlFile(
            'records/entity/hyphenated-item.xml',
            <<<'XML'
                <EntityClassDefinition.COMP_BEHR_S01_CSR-RP __type="EntityClassDefinition" __ref="uuid-hyphen-class-1" __path="libs/foundry/records/entityclassdefinition/comp_behr_s01_csr-rp.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="Weapon" SubType="LaserRepeater"/>
                        </SAttachableComponentParams>
                    </Components>
                </EntityClassDefinition.COMP_BEHR_S01_CSR-RP>
                XML
        );

        $this->generateCacheFiles();

        $classToPathMap = $this->readCacheFile('classToPathMap');
        $classToTypeMap = $this->readCacheFile('classToTypeMap');
        $entityMetadataMap = $this->readCacheFile('entityMetadataMap');
        $classToUuidMap = $this->readCacheFile('classToUuidMap');
        $uuidToClassMap = $this->readCacheFile('uuidToClassMap');
        $uuidToPathMap = $this->readCacheFile('uuidToPathMap');

        $this->assertSame($entityPath, $classToPathMap['EntityClassDefinition'][$className] ?? null);
        $this->assertSame('Weapon', $classToTypeMap['EntityClassDefinition.'.$className] ?? null);
        $this->assertSame([
            'uuid' => $uuid,
            'path' => $entityPath,
            'type' => 'Weapon',
            'sub_type' => 'LaserRepeater',
        ], $entityMetadataMap[$className] ?? null);
        $this->assertSame($uuid, $classToUuidMap[$className] ?? null);
        $this->assertSame($className, $uuidToClassMap[$uuid] ?? null);
        $this->assertSame($entityPath, $uuidToPathMap[$uuid] ?? null);
    }

    /**
     * @throws JsonException
     */
    public function test_item_service_resolves_hyphenated_class_name_to_expected_entity_and_path(): void
    {
        $className = 'COMP_BEHR_S01_CSR-RP';
        $uuid = 'uuid-hyphen-class-lookup';
        $declaredPath = 'libs/foundry/records/entityclassdefinition/comp_behr_s01_csr-rp.xml';
        $entityPath = $this->writeXmlFile(
            'records/entity/hyphenated-lookup.xml',
            <<<'XML'
                <EntityClassDefinition.COMP_BEHR_S01_CSR-RP __type="EntityClassDefinition" __ref="uuid-hyphen-class-lookup" __path="libs/foundry/records/entityclassdefinition/comp_behr_s01_csr-rp.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="Weapon" SubType="LaserRepeater"/>
                        </SAttachableComponentParams>
                    </Components>
                </EntityClassDefinition.COMP_BEHR_S01_CSR-RP>
                XML
        );

        $this->generateCacheFiles();

        $service = new ItemService($this->tempDir);
        $service->initialize();

        $resolvedEntity = $service->getByClassName($className);
        $this->assertNotNull($resolvedEntity);
        $this->assertSame($uuid, $resolvedEntity->getUuid());
        $this->assertSame($declaredPath, $resolvedEntity->getPath());
        $this->assertSame($uuid, $service->getUuidByClassName($className));

        $entityPathsProperty = (new ReflectionClass($service))->getProperty('entityPaths');
        $entityPaths = $entityPathsProperty->getValue($service);
        $this->assertSame($entityPath, $entityPaths[$className] ?? null);
    }

    /**
     * @throws JsonException
     */
    public function test_no_dot_roots_use_path_stem_and_real_file_basename_fallback(): void
    {
        $withPathUuid = 'uuid-no-dot-with-path';
        $fallbackUuid = 'uuid-no-dot-fallback';
        $withPathFile = $this->writeXmlFile(
            'records/no-dot/with-path.xml',
            <<<'XML'
                <InventoryContainer __type="InventoryContainer" __ref="uuid-no-dot-with-path" __path="libs/foundry/records/inventorycontainer/alpha-grid-item.xml">
                    <Components/>
                </InventoryContainer>
                XML
        );
        $fallbackFile = $this->writeXmlFile(
            'records/no-dot/fallback-item.xml',
            <<<'XML'
                <InventoryContainer __type="InventoryContainer" __ref="uuid-no-dot-fallback">
                    <Components/>
                </InventoryContainer>
                XML
        );

        $this->generateCacheFiles();

        $classToPathMap = $this->readCacheFile('classToPathMap');
        $classToTypeMap = $this->readCacheFile('classToTypeMap');
        $classToUuidMap = $this->readCacheFile('classToUuidMap');
        $uuidToClassMap = $this->readCacheFile('uuidToClassMap');
        $uuidToPathMap = $this->readCacheFile('uuidToPathMap');

        $this->assertSame($withPathFile, $classToPathMap['InventoryContainer']['alpha-grid-item'] ?? null);
        $this->assertSame($fallbackFile, $classToPathMap['InventoryContainer']['fallback-item'] ?? null);
        $this->assertSame('InventoryContainer', $classToTypeMap['InventoryContainer'] ?? null);
        $this->assertSame($withPathUuid, $classToUuidMap['alpha-grid-item'] ?? null);
        $this->assertSame($fallbackUuid, $classToUuidMap['fallback-item'] ?? null);
        $this->assertSame('alpha-grid-item', $uuidToClassMap[$withPathUuid] ?? null);
        $this->assertSame('fallback-item', $uuidToClassMap[$fallbackUuid] ?? null);
        $this->assertSame($withPathFile, $uuidToPathMap[$withPathUuid] ?? null);
        $this->assertSame($fallbackFile, $uuidToPathMap[$fallbackUuid] ?? null);
    }

    /**
     * @throws JsonException
     */
    public function test_no_dot_synthetic_collisions_warn_and_keep_last_write(): void
    {
        $firstUuid = 'uuid-collision-first';
        $secondUuid = 'uuid-collision-second';
        $firstPath = $this->writeXmlFile(
            'records/collision/first.xml',
            <<<'XML'
                <InventoryContainer __type="InventoryContainer" __ref="uuid-collision-first" __path="libs/foundry/records/inventorycontainer/collision-grid.xml">
                    <Components/>
                </InventoryContainer>
                XML
        );
        $secondPath = $this->writeXmlFile(
            'records/collision/second.xml',
            <<<'XML'
                <InventoryContainer __type="InventoryContainer" __ref="uuid-collision-second" __path="libs/foundry/records/inventorycontainer/collision-grid.xml">
                    <Components/>
                </InventoryContainer>
                XML
        );

        $output = new BufferedOutput;
        $this->generateCacheFiles($output);
        $consoleOutput = $output->fetch();

        $expectedLastPath = $this->resolveLastEncounteredNoDotPath('InventoryContainer', 'collision-grid');
        $this->assertNotNull($expectedLastPath);

        $pathToUuid = [
            $firstPath => $firstUuid,
            $secondPath => $secondUuid,
        ];
        $expectedLastUuid = $pathToUuid[$expectedLastPath];

        $classToPathMap = $this->readCacheFile('classToPathMap');
        $classToUuidMap = $this->readCacheFile('classToUuidMap');

        $this->assertSame($expectedLastPath, $classToPathMap['InventoryContainer']['collision-grid'] ?? null);
        $this->assertSame($expectedLastUuid, $classToUuidMap['collision-grid'] ?? null);
        $this->assertStringContainsString('Synthetic no-dot class collision for', $consoleOutput);
        $this->assertStringContainsString('InventoryContainer.collision-grid', $consoleOutput);
    }

    /**
     * @throws JsonException
     */
    public function test_entity_metadata_map_only_contains_entity_records(): void
    {
        $entityClassName = 'COMP_MISC_MINER';
        $entityUuid = 'uuid-entity-metadata-1';
        $entityPath = $this->writeXmlFile(
            'records/entity/mineable.xml',
            <<<'XML'
                <EntityClassDefinition.COMP_MISC_MINER __type="EntityClassDefinition" __ref="uuid-entity-metadata-1" __path="libs/foundry/records/entityclassdefinition/comp_misc_miner.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="Misc" SubType="Mineable"/>
                        </SAttachableComponentParams>
                    </Components>
                </EntityClassDefinition.COMP_MISC_MINER>
                XML
        );

        $this->writeXmlFile(
            'records/resource/resource.xml',
            <<<'XML'
                <ResourceType.Carinite __type="ResourceType" __ref="uuid-resource-metadata-1" __path="libs/foundry/records/resourcetypedatabase/carinite.xml" />
                XML
        );

        $this->generateCacheFiles();

        $entityMetadataMap = $this->readCacheFile('entityMetadataMap');

        $this->assertSame([
            'uuid' => $entityUuid,
            'path' => $entityPath,
            'type' => 'Misc',
            'sub_type' => 'Mineable',
        ], $entityMetadataMap[$entityClassName] ?? null);
        $this->assertCount(1, $entityMetadataMap);
    }

    private function writeXmlFile(string $relativePath, string $contents): string
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

    private function generateCacheFiles(?BufferedOutput $output = null): void
    {
        $output ??= new BufferedOutput;

        $io = new SymfonyStyle(new ArrayInput([]), $output);
        $io->progressStart();
        $service = new CacheService($this->tempDir, $io);
        $service->makeCacheFiles();
        $io->progressFinish();
    }

    /**
     * @return array<mixed>
     *
     * @throws JsonException
     */
    private function readCacheFile(string $cacheName): array
    {
        $cachePath = sprintf('%s%s%s-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, $cacheName, PHP_OS_FAMILY);
        $contents = file_get_contents($cachePath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read cache file: %s', $cachePath));
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function resolveLastEncounteredNoDotPath(string $docType, string $className): ?string
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tempDir));
        $lastPath = null;

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'xml') {
                continue;
            }

            $filePath = $file->getRealPath();
            if (! is_string($filePath)) {
                continue;
            }

            $root = $this->readRootElementMetadata($filePath);
            if ($root === null) {
                continue;
            }

            if (str_contains($root['name'], '.') || $root['name'] !== $docType) {
                continue;
            }

            $resolvedClassName = $this->resolveSyntheticClassName($root['path'], $filePath);
            if ($resolvedClassName === $className) {
                $lastPath = str_replace('\\', '/', $filePath);
            }
        }

        return $lastPath;
    }

    /**
     * @return array{name: string, path: string}|null
     */
    private function readRootElementMetadata(string $filePath): ?array
    {
        $reader = new XMLReader;
        if (! $reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                return [
                    'name' => $reader->name,
                    'path' => (string) ($reader->getAttribute('__path') ?? ''),
                ];
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    private function resolveSyntheticClassName(string $rootPath, string $filePath): string
    {
        $sourcePath = $rootPath !== '' ? $rootPath : $filePath;
        $normalizedPath = str_replace('\\', '/', $sourcePath);

        return pathinfo(basename($normalizedPath), PATHINFO_FILENAME);
    }

    private function resetServiceState(): void
    {
        $baseService = new ReflectionClass(BaseService::class);
        foreach (['uuidToPathMap', 'uuidToClassMap', 'classToUuidMap', 'classToPathMap', 'entityMetadataMap'] as $propertyName) {
            $property = $baseService->getProperty($propertyName);
            $property->setValue(null, []);
        }
        $baseService->getProperty('entityMetadataMapLoaded')->setValue(null, false);

        $itemService = new ReflectionClass(ItemService::class);
        $documentCache = $itemService->getProperty('documentCache');
        $documentCache->setValue(null, []);
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
