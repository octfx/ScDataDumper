<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;
use RuntimeException;

final class ManufacturerService extends BaseService
{
    private array $manufacturerPaths;

    public function count(): int
    {
        return count($this->manufacturerPaths);
    }

    public function initialize(): void
    {
        $this->manufacturerPaths = array_filter(self::$uuidToPathMap, static fn (string $path) => str_contains($path, 'scitemmanufacturer') === true);
    }

    public function iterator(): Generator
    {
        foreach ($this->manufacturerPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?SCItemManufacturer
    {
        if (! is_string($uuid)) {
            return null;
        }

        if (! isset($this->manufacturerPaths[$uuid])) {
            return null;
        }

        return $this->load($this->manufacturerPaths[$uuid]);
    }

    public function load(string $filePath): ?SCItemManufacturer
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $manufacturer = new SCItemManufacturer;
        $manufacturer->load($filePath);
        $manufacturer->checkValidity();

        return $manufacturer;
    }
}
