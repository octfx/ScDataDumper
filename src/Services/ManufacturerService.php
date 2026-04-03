<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;

final class ManufacturerService extends BaseService
{
    private array $manufacturerPaths;

    public function count(): int
    {
        return count($this->manufacturerPaths);
    }

    public function initialize(): void
    {
        // Only keep real SCItemManufacturer documents and ignore other files that happen to live in the folder
        $this->manufacturerPaths = array_filter(
            self::$uuidToPathMap,
            static function (string $path): bool {
                if (str_contains($path, 'scitemmanufacturer') !== true) {
                    return false;
                }

                $handle = @fopen($path, 'rb');
                if (! $handle) {
                    return false;
                }

                $firstLine = fgets($handle) ?: '';
                fclose($handle);

                return (bool) preg_match('/^<SCItemManufacturer\\./', ltrim($firstLine));
            }
        );
    }

    public function iterator(): Generator
    {
        foreach ($this->manufacturerPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?SCItemManufacturer
    {
        $path = $this->resolvePathByReference(is_string($uuid) ? $uuid : null);

        if ($path === null || ! in_array($path, $this->manufacturerPaths, true)) {
            return null;
        }

        return $this->load($path);
    }

    public function load(string $filePath): ?SCItemManufacturer
    {
        return $this->loadDocument($filePath, SCItemManufacturer::class);
    }
}
