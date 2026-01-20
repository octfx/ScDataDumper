<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Aggregates resource data for vehicles
 *
 * Calculates shields, distortion pool, ammunition capacity, weapon storage slots, and loadout mass.
 */
final readonly class ResourceAggregator implements VehicleDataCalculator
{
    public function __construct(
        private StandardisedPartWalker $walker,
    ) {}

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $loadout = $context->standardisedParts;

        if (empty($loadout)) {
            return $this->getEmptyResources();
        }

        $loadoutMass = 0.0;
        $shieldHp = 0.0;
        $shieldRegen = 0.0;
        $distortionPool = 0.0;
        $missileCount = 0.0;
        $missileRackAmmo = 0.0;

        $weaponLockers = 0;
        $weaponSlotsTotal = 0;
        $weaponSlotsRifle = 0;
        $weaponSlotsPistol = 0;

        /** @var array<int, array{name: string|null, class_name: string|null, port: string|null, slots_total: int, slots_rifle: int, slots_pistol: int}> */
        $weaponStorageByLocker = [];

        foreach ($this->walker->walkItems($loadout) as $entry) {
            $item = $entry['Item'];
            $stdItem = Arr::get($item, 'stdItem') ?? Arr::get($item, 'StdItem');

            $loadoutMass += Arr::get($stdItem, 'Mass', 0.0);

            // Shields
            $shieldHp += (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldHealth', 0.0);
            $shieldRegen += (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldRegen', 0.0);

            // Distortion pool
            $distortionPool += (float) Arr::get($stdItem ?? [], 'Distortion.Maximum', 0.0);

            // Ammunition / missiles
            $type = Arr::get($stdItem ?? [], 'Type', Arr::get($item, 'Type'));
            if (is_string($type) && str_starts_with(strtolower($type), 'missile')) {
                $missileCount++;
            }

            // Missile rack capacity
            if (is_string($type) && str_starts_with(strtolower($type), 'missilelauncher')) {
                $missileRackAmmo += (float) Arr::get($stdItem ?? [], 'MissileRack.Count', 0.0);
            }

            // Weapon racks / lockers (capacity overview)
            $ports = $this->extractPorts($item);
            $className = strtolower((string) Arr::get($stdItem ?? [], 'ClassName', Arr::get($item, 'ClassName', Arr::get($item, 'className', ''))));

            $isWeaponLocker = str_contains($className, 'weapon_rack') || str_contains($className, 'weapon_locker');

            $lockerSlotsTotal = 0;
            $lockerSlotsRifle = 0;
            $lockerSlotsPistol = 0;

            foreach ($ports as $port) {
                $types = $port['Types'] ?? [];
                if ($this->isWeaponPersonalPort($types)) {
                    $isWeaponLocker = true;

                    $maxSize = $port['MaxSize'] ?? $port['Size'] ?? null;

                    $weaponSlotsTotal++;
                    $lockerSlotsTotal++;

                    if ($maxSize !== null && (float) $maxSize <= 2) {
                        $weaponSlotsPistol++;
                        $lockerSlotsPistol++;
                    } else {
                        $weaponSlotsRifle++;
                        $lockerSlotsRifle++;
                    }
                }
            }

            if ($isWeaponLocker) {
                $weaponLockers++;

                $weaponStorageByLocker[] = [
                    'name' => Arr::get($stdItem ?? [], 'Name')
                        ?? Arr::get($item, 'name')
                            ?? Arr::get($item, 'Name'),
                    'class_name' => $className !== '' ? $className : null,
                    'port' => is_string($entry['portName'] ?? null) ? $entry['portName'] : null,
                    'slots_total' => $lockerSlotsTotal,
                    'slots_rifle' => $lockerSlotsRifle,
                    'slots_pistol' => $lockerSlotsPistol,
                ];
            }
        }

        return [
            'mass_loadout' => round($loadoutMass),

            'shields_total' => [
                'hp' => $shieldHp > 0 ? $shieldHp : null,
                // TODO: Magic number
                'regen' => $shieldRegen > 0 ? round($shieldRegen * 0.66) : null,
            ],

            'distortion' => [
                'pool' => $distortionPool > 0 ? $distortionPool : null,
            ],

            'ammo' => [
                'missiles' => $missileCount > 0 ? $missileCount : null,
                'missile_rack_capacity' => $missileRackAmmo > 0 ? $missileRackAmmo : null,
            ],

            'weapon_storage' => [
                'lockers' => $weaponLockers > 0 ? $weaponLockers : null,
                'slots_total' => $weaponSlotsTotal > 0 ? $weaponSlotsTotal : null,
                'slots_rifle' => $weaponSlotsRifle > 0 ? $weaponSlotsRifle : null,
                'slots_pistol' => $weaponSlotsPistol > 0 ? $weaponSlotsPistol : null,
                'by_locker' => $weaponStorageByLocker !== [] ? $weaponStorageByLocker : null,
            ],
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }

    /**
     * Normalize port definitions from either stdItem or raw Components.
     */
    private function extractPorts(?array $item = null): array
    {
        $ports = Arr::get($item, 'stdItem.Ports', []);
        if (is_array($ports) && $ports !== []) {
            return $ports;
        }

        $rawPorts = Arr::get($item, 'Components.SItemPortContainerComponentParams.Ports', []);
        if ($rawPorts === [] || ! is_array($rawPorts)) {
            return [];
        }

        $rawPorts = $rawPorts['SItemPortDef'] ?? $rawPorts;

        if (! is_array($rawPorts)) {
            return [];
        }

        if (! array_is_list($rawPorts)) {
            $rawPorts = [$rawPorts];
        }

        return array_map(function (array $port): array {
            $types = $this->extractPortTypes($port);

            return [
                'PortName' => $port['Name'] ?? $port['@Name'] ?? null,
                'MaxSize' => isset($port['MaxSize']) ? (float) $port['MaxSize'] : (isset($port['@MaxSize']) ? (float) $port['@MaxSize'] : null),
                'Size' => isset($port['Size']) ? (float) $port['Size'] : (isset($port['@Size']) ? (float) $port['@Size'] : null),
                'Types' => $types,
            ];
        }, $rawPorts);
    }

    /**
     * Extract a flat list of type strings (e.g. "WeaponPersonal.Small") from a raw port definition.
     */
    private function extractPortTypes(array $port): array
    {
        $types = [];
        $rawTypes = Arr::get($port, 'Types.SItemPortDefTypes', []);
        if ($rawTypes === [] || $rawTypes === null) {
            return $types;
        }

        if (! array_is_list($rawTypes)) {
            $rawTypes = [$rawTypes];
        }

        foreach ($rawTypes as $rawType) {
            $major = $rawType['Type'] ?? $rawType['@Type'] ?? $rawType['@type'] ?? null;
            if ($major === null) {
                continue;
            }

            $subTypes = [];
            $rawSubTypes = $rawType['SubTypes'] ?? $rawType['@SubTypes'] ?? $rawType['@subtypes'] ?? null;

            if (is_array($rawSubTypes)) {
                $subTypeEntries = $rawSubTypes['SItemPortDefType'] ?? $rawSubTypes;
                if (! array_is_list($subTypeEntries)) {
                    $subTypeEntries = [$subTypeEntries];
                }

                foreach ($subTypeEntries as $subEntry) {
                    if (is_array($subEntry)) {
                        $value = $subEntry['value'] ?? $subEntry['@value'] ?? null;
                        if (! empty($value)) {
                            $subTypes[] = $value;
                        }
                    } elseif (is_string($subEntry) && $subEntry !== '') {
                        $subTypes[] = $subEntry;
                    }
                }
            } elseif (is_string($rawSubTypes) && $rawSubTypes !== '') {
                $subTypes = array_filter(array_map('trim', explode(',', $rawSubTypes)));
            }

            if ($subTypes === []) {
                $types[] = $major;
            } else {
                foreach ($subTypes as $sub) {
                    $types[] = $major.'.'.$sub;
                }
            }
        }

        return $types;
    }

    /**
     * Detect whether a port accepts personal weapons (any subtype).
     */
    private function isWeaponPersonalPort(array $types): bool
    {
        return array_any($types, fn ($type) => str_starts_with(strtolower((string) $type), 'weaponpersonal'));
    }

    private function getEmptyResources(): array
    {
        return [
            'mass_loadout' => 0.0,
            'shields_total' => [
                'hp' => null,
                'regen' => null,
            ],
            'distortion' => [
                'pool' => null,
            ],
            'ammo' => [
                'missiles' => null,
                'missile_rack_capacity' => null,
            ],
            'weapon_storage' => [
                'lockers' => null,
                'slots_total' => null,
                'slots_rifle' => null,
                'slots_pistol' => null,
                'by_locker' => null,
            ],
        ];
    }
}
