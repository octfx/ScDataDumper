<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use RuntimeException;

abstract class BaseService
{
    protected readonly string $classToTypeMapPath;

    protected readonly string $classToPathMapPath;

    protected readonly string $uuidToClassMapPath;

    protected readonly string $uuidToPathMapPath;

    protected readonly string $classToUuidMapPath;

    /**
     * Maps UUID to a file Path
     *
     * @var array|mixed
     */
    protected static array $uuidToPathMap = [];

    /**
     * Maps UUID to an entity class
     *
     * @var array|mixed
     */
    protected static array $uuidToClassMap = [];

    /**
     * Maps class name to UUID
     *
     * @var array|mixed
     */
    protected static array $classToUuidMap = [];

    /**
     * Maps class name to path
     *
     * @var array|mixed
     */
    protected static array $classToPathMap = [];

    /**
     * @throws JsonException
     */
    public function __construct(protected readonly string $scDataDir)
    {
        $this->classToTypeMapPath = $this->makePath(sprintf('classToTypeMap-%s.json', PHP_OS_FAMILY));
        $this->classToPathMapPath = $this->makePath(sprintf('classToPathMap-%s.json', PHP_OS_FAMILY));
        $this->uuidToClassMapPath = $this->makePath(sprintf('uuidToClassMap-%s.json', PHP_OS_FAMILY));
        $this->uuidToPathMapPath = $this->makePath(sprintf('uuidToPathMap-%s.json', PHP_OS_FAMILY));
        $this->classToUuidMapPath = $this->makePath(sprintf('classToUuidMap-%s.json', PHP_OS_FAMILY));

        foreach ([
            $this->classToTypeMapPath,
            $this->classToPathMapPath,
            $this->uuidToClassMapPath,
            $this->uuidToPathMapPath,
            $this->classToUuidMapPath,
        ] as $file) {
            if (! file_exists($file)) {
                throw new RuntimeException(sprintf(
                    'Did not find required file %s. Does it exist in folder %s?',
                    $file,
                    $this->scDataDir
                ));
            }
        }

        if (empty(self::$uuidToPathMap)) {
            self::$uuidToPathMap = json_decode(file_get_contents($this->uuidToPathMapPath), true, 512, JSON_THROW_ON_ERROR);
        }

        if (empty(self::$uuidToClassMap)) {
            self::$uuidToClassMap = json_decode(file_get_contents($this->uuidToClassMapPath), true, 512, JSON_THROW_ON_ERROR);
        }

        if (empty(self::$classToPathMap)) {
            self::$classToPathMap = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);
        }

        if (empty(self::$classToUuidMap)) {
            $rawClassToUuidMap = json_decode(file_get_contents($this->classToUuidMapPath), true, 512, JSON_THROW_ON_ERROR);

            // json_decode casts numeric-string object keys to ints; re-stringify to keep class names usable
            self::$classToUuidMap = [];
            foreach ($rawClassToUuidMap as $className => $uuid) {
                self::$classToUuidMap[(string) $className] = $uuid;
            }
        }

        $this->validateCachePaths();
    }

    /**
     * Validate that cache paths use normalized forward slashes.
     * Detects cache files generated on different platforms before the fix.
     *
     * @throws RuntimeException if cache contains Windows-style paths
     */
    private function validateCachePaths(): void
    {
        $samplePaths = array_slice(self::$uuidToPathMap, 0, 10);

        foreach ($samplePaths as $path) {
            if (str_contains($path, '\\')) {
                throw new RuntimeException(
                    'Cache files contain Windows-style paths with backslashes. '.
                    'Please regenerate cache files.'
                );
            }
        }
    }

    abstract public function initialize(): void;

    protected function normalizeReference(?string $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $normalizedReference = strtolower(trim($reference));

        return $normalizedReference === '' ? null : $normalizedReference;
    }

    protected function resolvePathByReference(?string $reference): ?string
    {
        $normalizedReference = $this->normalizeReference($reference);

        if ($normalizedReference === null) {
            return null;
        }

        return self::$uuidToPathMap[$normalizedReference] ?? null;
    }

    protected function normalizePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', $path));
    }

    protected function pathMatches(string $path, array $pathNeedles): bool
    {
        $normalizedPath = $this->normalizePath($path);

        return array_any($pathNeedles, fn ($needle) => str_contains($normalizedPath, strtolower($needle)));
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T> $class
     * @return T
     */
    protected function loadDocument(string $filePath, string $class, bool $checkValidity = true): RootDocument
    {
        if (empty($filePath) || ! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $document = new $class;
        $document->load($filePath);

        if ($checkValidity) {
            $document->checkValidity();
        }

        return $document;
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T> $class
     * @return Generator<int, T, mixed, void>
     */
    protected function iterateDocumentType(string $mapKey, string $class): Generator
    {
        foreach (self::$classToPathMap[$mapKey] ?? [] as $path) {
            yield $this->loadDocument($path, $class);
        }
    }

    public static function resetSharedState(): void
    {
        self::$uuidToPathMap = [];
        self::$uuidToClassMap = [];
        self::$classToUuidMap = [];
        self::$classToPathMap = [];
    }

    private function makePath(string $fileName): string
    {
        return sprintf(
            '%s%s%s',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            $fileName
        );
    }
}
