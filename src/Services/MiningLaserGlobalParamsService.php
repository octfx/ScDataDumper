<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\MiningLaserGlobalParams;
use RuntimeException;

final class MiningLaserGlobalParamsService extends BaseService
{
    private array $globalParams = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->globalParams = $classes['MiningLaserGlobalParams'] ?? [];
    }

    public function iterator(): Generator
    {
        foreach ($this->globalParams as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $uuid): ?MiningLaserGlobalParams
    {
        if ($uuid === null || ! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$uuid]);
    }

    public function load(string $filePath): ?MiningLaserGlobalParams
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $params = new MiningLaserGlobalParams;
        $params->load($filePath);
        $params->checkValidity();

        return $params;
    }
}
