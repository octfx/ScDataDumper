<?php

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\Definitions\MeleeCombatConfig;
use RuntimeException;

final class MeleeCombatConfigService extends BaseService
{
    private array $meleeCombatConfig = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->meleeCombatConfig = $classes['MeleeCombatConfig'];
    }

    public function iterator(): Generator
    {
        foreach ($this->meleeCombatConfig as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?MeleeCombatConfig
    {
        if (! is_string($uuid)) {
            return null;
        }

        if (! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$uuid]);
    }

    public function load(string $filePath): ?MeleeCombatConfig
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $meleeCombatConfig = simplexml_load_string(file_get_contents($filePath), MeleeCombatConfig::class, LIBXML_NOCDATA | LIBXML_NOBLANKS);

        if ($meleeCombatConfig === false || ! is_object($meleeCombatConfig)) {
            throw new RuntimeException(sprintf('Cannot parse XML %s', $filePath));
        }

        $meleeCombatConfig->checkValidity();

        return $meleeCombatConfig;
    }
}
