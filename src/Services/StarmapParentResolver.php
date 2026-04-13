<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

final class StarmapParentResolver
{
    /** @var array<string, string>|null */
    private ?array $parentMappings = null;

    /** @var array<string, string>|null */
    private ?array $classToUuidMap = null;

    public function __construct(
        private readonly string $scDataPath,
    ) {}

    public function resolveParentUuid(string $className): ?string
    {
        $parentClassName = $this->getParentMappings()[$className] ?? null;
        if ($parentClassName === null) {
            return null;
        }

        return $this->getClassToUuidMap()[$parentClassName] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function getParentMappings(): array
    {
        if ($this->parentMappings !== null) {
            return $this->parentMappings;
        }

        $path = $this->scDataPath.DIRECTORY_SEPARATOR.'socpak_parent_mappings.json';
        if (! file_exists($path)) {
            return $this->parentMappings = [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return $this->parentMappings = [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $this->parentMappings = is_array($data) ? $data : [];
    }

    /**
     * @return array<string, string>
     */
    private function getClassToUuidMap(): array
    {
        if ($this->classToUuidMap !== null) {
            return $this->classToUuidMap;
        }

        $path = $this->scDataPath.DIRECTORY_SEPARATOR.sprintf('classToUuidMap-%s.json', PHP_OS_FAMILY);
        if (! file_exists($path)) {
            return $this->classToUuidMap = [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return $this->classToUuidMap = [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $this->classToUuidMap = is_array($data) ? $data : [];
    }
}
