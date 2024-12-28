<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\RadarSystemSharedParams;
use RuntimeException;

final class RadarSystemService extends BaseService
{
    private array $radarSystems = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->radarSystems = $classes['RadarSystemSharedParams'];
    }

    public function iterator(): Generator
    {
        foreach ($this->radarSystems as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?RadarSystemSharedParams
    {
        if (! is_string($uuid)) {
            return null;
        }

        if (! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$uuid]);
    }

    public function load(string $filePath): ?RadarSystemSharedParams
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $radarSystem = new RadarSystemSharedParams;
        $radarSystem->load($filePath);
        $radarSystem->checkValidity();

        return $radarSystem;
    }
}
