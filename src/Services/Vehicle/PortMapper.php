<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item as ScUnpackedItem;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Maps port information to standardized format
 */
final class PortMapper
{
    private array $itemCache = [];

    /**
     * Map port data to standardized format
     *
     * @param  array  $portInfo  Port information (name, min, max, types)
     * @param  array|null  $loadout  Loadout entry for this port
     * @param  array  $childPorts  Child ports array
     * @return array Mapped port data
     */
    public function mapPort(array $portInfo, ?array $loadout, array $childPorts): array
    {
        $equippedItem = $this->mapEquippedItem($loadout);

        $mapped = [
            'name' => $portInfo['name'] ?? null,
            'position' => $this->inferPortPosition($portInfo['name'] ?? ''),
            'sizes' => [
                'min' => $portInfo['min'] ?? null,
                'max' => $portInfo['max'] ?? null,
            ],
            'class_name' => $loadout['className'] ?? null,
            'health' => Arr::get($loadout, 'Item.stdItem.Durability.Health'),
            'compatible_types' => $portInfo['types'] ?? [],
        ];

        if ($equippedItem) {
            $mapped['equipped_item'] = $equippedItem;
        }

        if (! empty($childPorts)) {
            $mapped['ports'] = $childPorts;
        }

        if (! empty($loadout['entries'])) {
            $mapped['entries'] = array_map(static fn ($entry) => Arr::get($entry, 'Item'), $loadout['entries']);
        }

        return $mapped;
    }

    /**
     * Map equipped item to standardized format
     */
    public function mapEquippedItem(?array $loadout): ?array
    {
        if (! $loadout) {
            return null;
        }

        $cacheKey = $loadout['classReference'] ?? null;

        if ($cacheKey === '00000000-0000-0000-0000-000000000000') {
            $cacheKey = null;
        }

        if ($cacheKey !== null && array_key_exists($cacheKey, $this->itemCache)) {
            return $this->itemCache[$cacheKey];
        }

        $itemService = ServiceFactory::getItemService();
        $entity = null;

        $classReference = $loadout['classReference'] ?? null;
        if (! empty($classReference) && $classReference !== '00000000-0000-0000-0000-000000000000') {
            $entity = $itemService->getByReference($classReference);
        }

        if ($entity === null) {
            $className = $loadout['className'] ?? null;
            if (! empty($className)) {
                $entity = $itemService->getByClassName($className);
            }
        }

        if ($entity === null) {
            return null;
        }

        $mapped = new ScUnpackedItem($entity)->toArray();

        if ($cacheKey !== null) {
            $this->itemCache[$cacheKey] = $mapped;
        }

        return $mapped;
    }

    /**
     * Infer port position from name
     */
    private function inferPortPosition(string $name): ?string
    {
        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'left') => 'left',
            str_contains($name, 'right') => 'right',
            str_contains($name, 'front') => 'front',
            str_contains($name, 'rear') => 'rear',
            str_contains($name, 'tail') => 'tail',
            default => null,
        };
    }
}
