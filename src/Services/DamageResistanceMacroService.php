<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\DamageResistanceMacro;
use RuntimeException;

final class DamageResistanceMacroService extends BaseService
{
    private array $resistanceMacros = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->resistanceMacros = $classes['DamageResistanceMacro'];
    }

    public function iterator(): Generator
    {
        foreach ($this->resistanceMacros as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?DamageResistanceMacro
    {
        if (! is_string($uuid)) {
            return null;
        }

        if (! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$uuid]);
    }

    public function load(string $filePath): ?DamageResistanceMacro
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $resistanceMacro = new DamageResistanceMacro;
        $resistanceMacro->load($filePath);
        $resistanceMacro->checkValidity();

        return $resistanceMacro;
    }
}
