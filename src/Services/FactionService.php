<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\Faction;
use RuntimeException;

final class FactionService extends BaseService
{
    private array $factionPaths;

    public function initialize(): void
    {
        $this->factionPaths = array_filter(self::$uuidToPathMap, static fn (string $path) => str_contains($path, 'factions'.DIRECTORY_SEPARATOR) === true);
    }

    public function count(): int
    {
        return count($this->factionPaths);
    }

    public function iterator(): Generator
    {
        foreach ($this->factionPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?Faction
    {
        if (! is_string($uuid)) {
            return null;
        }

        if (! isset($this->manufacturerPaths[$uuid])) {
            return null;
        }

        return $this->load($this->factionPaths[$uuid]);
    }

    public function load(string $filePath): ?Faction
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $faction = new Faction;
        $faction->load($filePath);

        return $faction;
    }
}
