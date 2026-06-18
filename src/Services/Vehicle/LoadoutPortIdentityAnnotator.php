<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Recursively annotates every Loadout entry with identity metadata:
 *
 *   PortId       - "loadout.<idx>[.loadout.<idx>...]"
 *   ParentPortId - parent's PortId (null for top-level)
 *   RootPortId   - top-level ancestor's PortId (== PortId for top-level)
 *   Path         - HardpointName values from root to this port
 */
final class LoadoutPortIdentityAnnotator
{
    /** @return list<array<string, mixed>> */
    public function annotate(array $loadout): array
    {
        return $this->annotatePorts($loadout);
    }

    /**
     * @param  list<array<string, mixed>>  $ports
     * @param  string  $base  Current PortId prefix (e.g. "loadout" or "loadout.0")
     * @param  string|null  $parentId  Parent's PortId, or null for top-level
     * @param  string|null  $rootId  Root ancestor's PortId, or null for top-level
     * @param  list<string|null>  $path  Hardpoint name ancestry from root
     * @return list<array<string, mixed>>
     */
    private function annotatePorts(
        array $ports,
        string $base = 'loadout',
        ?string $parentId = null,
        ?string $rootId = null,
        array $path = [],
    ): array {
        $result = [];

        foreach ($ports as $index => $port) {
            $portId = $base === 'loadout'
                ? "loadout.{$index}"
                : "{$base}.loadout.{$index}";

            $currentRootId = $rootId ?? $portId;
            $currentPath = array_merge($path, [$port['HardpointName'] ?? null]);

            $annotated = $port;
            $annotated['PortId'] = $portId;
            $annotated['ParentPortId'] = $parentId;
            $annotated['RootPortId'] = $currentRootId;
            $annotated['Path'] = $currentPath;

            if (isset($annotated['Loadout']) && is_array($annotated['Loadout']) && ! empty($annotated['Loadout'])) {
                $annotated['Loadout'] = $this->annotatePorts(
                    $annotated['Loadout'],
                    $portId,
                    $portId,
                    $currentRootId,
                    $currentPath,
                );
            }

            $result[] = $annotated;
        }

        return $result;
    }
}
