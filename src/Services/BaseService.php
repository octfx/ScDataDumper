<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
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

        if (empty(self::$classToUuidMap)) {
            self::$classToUuidMap = json_decode(file_get_contents($this->classToUuidMapPath), true, 512, JSON_THROW_ON_ERROR);
        }
    }

    abstract public function initialize(): void;

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
