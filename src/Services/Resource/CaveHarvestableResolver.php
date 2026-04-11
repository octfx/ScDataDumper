<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableMultiConfigRecord;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CaveHarvestableResolver
{
    /** @var array<string, array<string, array<string, list<string>>>>|null */
    private ?array $caveMappings = null;

    /**
     * @return array{locations: list<array{className: string, system: string, caveType: string, occupancy: string}>, caveType: string|null, occupancy: string|null, system: string|null}
     * @throws JsonException
     */
    public function resolveCaveLocations(SubHarvestableMultiConfigRecord $config): array
    {
        $className = $config->getClassName();
        $parsed = $this->parseConfigName($className);

        if ($parsed === null) {
            return ['locations' => [], 'caveType' => null, 'occupancy' => null, 'system' => null];
        }

        $mappings = $this->loadCaveMappings();

        $locations = [];
        $systems = $parsed['system'] !== null
            ? [$parsed['system']]
            : array_keys($mappings);

        foreach ($systems as $system) {
            $systemData = $mappings[$system] ?? [];
            $typeData = $systemData[$parsed['caveType']] ?? [];
            $classNames = $typeData[$parsed['occupancy']] ?? [];

            foreach ($classNames as $cn) {
                $locations[] = [
                    'className' => $cn,
                    'system' => $system,
                    'caveType' => $parsed['caveType'],
                    'occupancy' => $parsed['occupancy'],
                ];
            }
        }

        return [
            'locations' => $locations,
            'caveType' => $parsed['caveType'],
            'occupancy' => $parsed['occupancy'],
            'system' => $parsed['system'],
        ];
    }

    /**
     * @return array{caveType: string, occupancy: string, system: string|null}|null
     */
    private function parseConfigName(string $className): ?array
    {
        if (! preg_match('/^Loot_Caves_(Occupied|Unoccupied)_(Rock|Sand|Acidic)(?:_(Stanton|Pyro))?$/i', $className, $matches)) {
            return null;
        }

        return [
            'occupancy' => strtolower($matches[1]),
            'caveType' => strtolower($matches[2]),
            'system' => isset($matches[3]) && $matches[3] !== '' ? ucfirst(strtolower($matches[3])) : null,
        ];
    }

    /**
     * @return array<string, array<string, array<string, list<string>>>>
     *
     * @throws JsonException
     */
    private function loadCaveMappings(): array
    {
        if ($this->caveMappings !== null) {
            return $this->caveMappings;
        }

        $path = ServiceFactory::getActiveScDataPath();
        if ($path === null) {
            return $this->caveMappings = [];
        }

        $mappingFile = $path.DIRECTORY_SEPARATOR.'cave_mappings.json';
        if (! file_exists($mappingFile)) {
            return $this->caveMappings = [];
        }

        $content = file_get_contents($mappingFile);
        if ($content === false) {
            return $this->caveMappings = [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            return $this->caveMappings = [];
        }

        return $this->caveMappings = $data;
    }
}
