<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Aggregates resource data for vehicles
 *
 * Calculates shields, distortion pool, ammunition capacity, weapon storage slots, and loadout mass.
 * Also extracts weapon/suit storage from interior socpak entities that are not part of the vehicle XML.
 */
final readonly class ResourceAggregator implements VehicleDataCalculator
{
    private ItemTypeResolver $itemTypeResolver;

    public function __construct(
        private StandardisedPartWalker $walker,
        ?ItemTypeResolver $itemTypeResolver = null,
        private ?SocpakStorageExtractor $socpakStorageExtractor = null,
    ) {
        $this->itemTypeResolver = $itemTypeResolver ?? new ItemTypeResolver;
    }

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
        $shieldRegenRaw = 0.0;
        $shieldRegenMinPower = 0.0;
        $activeShieldPowerMaxTotal = 0.0;
        $installedShieldPowerMaxTotal = 0.0;
        $activeShieldCount = 0;
        $maxActiveShields = $this->normalizeShieldPoolMaxCount($context->entity?->getShieldPoolMaxCount());
        $distortionPool = 0.0;
        $missileCount = 0.0;
        $missileRackAmmo = 0.0;

        $weaponLockers = 0;
        $weaponSlotsTotal = 0;
        $weaponSlotsRifle = 0;
        $weaponSlotsPistol = 0;

        /** @var array<int, array{name: string|null, class_name: string|null, port: string|null, slots_total: int, slots_rifle: int, slots_pistol: int}> */
        $weaponStorageByLocker = [];

        $suitLockers = 0;
        $suitSlotsTotal = 0;

        /** @var array<int, array{name: string|null, class_name: string|null, port: string|null, slots_total: int}> */
        $suitStorageByLocker = [];

        foreach ($this->walker->walkItems($loadout) as $entry) {
            $item = $entry['Item'];
            $stdItem = Arr::get($item, 'stdItem') ?? Arr::get($item, 'StdItem');

            $loadoutMass += Arr::get($stdItem, 'Mass', 0.0);

            // Shields: denominator uses all installed shield generator max power;
            // HP/regen/contribution use only active shield generators.
            if (Arr::has($stdItem ?? [], 'Shield')) {
                $shieldPowerMaximum = $this->deriveShieldPowerMaximum($stdItem);
                $installedShieldPowerMaxTotal += $shieldPowerMaximum;

                if ($activeShieldCount < $maxActiveShields) {
                    $activeShieldCount++;
                    $shieldHp += (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldHealth', 0.0);
                    [$itemShieldRegenRaw, $itemShieldRegenMinPower] = $this->deriveShieldRegenValues($stdItem);
                    $shieldRegenRaw += $itemShieldRegenRaw;
                    $shieldRegenMinPower += $itemShieldRegenMinPower;
                    $activeShieldPowerMaxTotal += $shieldPowerMaximum;
                }
            }

            // Distortion pool
            $distortionPool += (float) Arr::get($stdItem ?? [], 'Distortion.Maximum', 0.0);

            // Ammunition / missiles
            $type = $this->itemTypeResolver->resolveSemanticType($item);
            $typeLower = is_string($type) ? strtolower($type) : null;

            if (is_string($typeLower) && str_starts_with($typeLower, 'missile')) {
                $missileCount++;
            }

            // Missile rack capacity
            if (is_string($typeLower) && str_starts_with($typeLower, 'missilelauncher')) {
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

            // Suit storage / lockers (class names: Locker_Suit_*, *_suit_locker_*, Useable_suit_locker_*)
            $isSuitLocker = str_contains($className, 'locker_suit') || str_contains($className, 'suit_locker');

            $lockerSuitSlots = 0;

            if (! $isSuitLocker) {
                foreach ($ports as $port) {
                    $types = $port['Types'] ?? [];
                    if ($this->isSuitStoragePort($types)) {
                        $isSuitLocker = true;
                        break;
                    }
                }
            }

            if ($isSuitLocker) {
                foreach ($ports as $port) {
                    $types = $port['Types'] ?? [];
                    if ($this->isSuitStoragePort($types)) {
                        $suitSlotsTotal++;
                        $lockerSuitSlots++;
                    }
                }

                $suitLockers++;

                $suitStorageByLocker[] = [
                    'name' => Arr::get($stdItem ?? [], 'Name')
                        ?? Arr::get($item, 'name')
                            ?? Arr::get($item, 'Name'),
                    'class_name' => $className !== '' ? $className : null,
                    'port' => is_string($entry['portName'] ?? null) ? $entry['portName'] : null,
                    'slots_total' => $lockerSuitSlots,
                ];
            }
        }

        // Post-process: find weapon/suit storage in interior socpak entities
        // that are not represented as parts in the vehicle XML.
        $weaponStorage = [
            'lockers' => $weaponLockers > 0 ? $weaponLockers : null,
            'slots_total' => $weaponSlotsTotal > 0 ? $weaponSlotsTotal : null,
            'slots_rifle' => $weaponSlotsRifle > 0 ? $weaponSlotsRifle : null,
            'slots_pistol' => $weaponSlotsPistol > 0 ? $weaponSlotsPistol : null,
            'by_locker' => $weaponStorageByLocker !== [] ? $weaponStorageByLocker : null,
        ];

        $suitStorage = [
            'lockers' => $suitLockers > 0 ? $suitLockers : null,
            'slots_total' => $suitSlotsTotal > 0 ? $suitSlotsTotal : null,
            'by_locker' => $suitStorageByLocker !== [] ? $suitStorageByLocker : null,
        ];

        if ($this->socpakStorageExtractor !== null && $context->entity !== null) {
            $socpakStorage = $this->socpakStorageExtractor->extractStorage($context->entity);

            if ($weaponStorage['slots_total'] === null && $socpakStorage['weapon_racks'] !== []) {
                $totalSocpakSlots = 0;
                $totalSocpakRifle = 0;
                $totalSocpakPistol = 0;
                $socpakByLocker = [];

                foreach ($socpakStorage['weapon_racks'] as $rack) {
                    $totalSocpakSlots += $rack['slots_total'];
                    $totalSocpakRifle += $rack['slots_rifle'];
                    $totalSocpakPistol += $rack['slots_pistol'];

                    $socpakByLocker[] = [
                        'name' => 'Weapon Rack',
                        'class_name' => $rack['class_name'],
                        'port' => '<interior>',
                        'slots_total' => $rack['slots_total'],
                        'slots_rifle' => $rack['slots_rifle'],
                        'slots_pistol' => $rack['slots_pistol'],
                    ];
                }

                $weaponStorage = [
                    'lockers' => count($socpakStorage['weapon_racks']),
                    'slots_total' => $totalSocpakSlots,
                    'slots_rifle' => $totalSocpakRifle,
                    'slots_pistol' => $totalSocpakPistol,
                    'by_locker' => $socpakByLocker,
                ];
            }

            if ($suitStorage['slots_total'] === null && $socpakStorage['suit_lockers'] !== []) {
                $totalSocpakSuitSlots = 0;
                $socpakSuitByLocker = [];

                foreach ($socpakStorage['suit_lockers'] as $locker) {
                    $totalSocpakSuitSlots += $locker['slots_total'];

                    $socpakSuitByLocker[] = [
                        'name' => 'Suit Locker',
                        'class_name' => $locker['class_name'],
                        'port' => '<interior>',
                        'slots_total' => $locker['slots_total'],
                    ];
                }

                $suitStorage = [
                    'lockers' => count($socpakStorage['suit_lockers']),
                    'slots_total' => $totalSocpakSuitSlots,
                    'by_locker' => $socpakSuitByLocker,
                ];
            }
        }

        $shieldRegenEffective = $this->calculateTheoreticalShieldRegen(
            $shieldRegenRaw,
            $activeShieldPowerMaxTotal,
            $installedShieldPowerMaxTotal,
        );
        $shieldRegenerationTime = ($shieldHp > 0.0 && $shieldRegenEffective > 0.0)
            ? $shieldHp / $shieldRegenEffective
            : null;

        return [
            'mass_loadout' => round($loadoutMass),

            'shields_total' => [
                'hp' => $shieldHp > 0 ? $shieldHp : null,
                'regen' => $shieldRegenEffective > 0 ? round($shieldRegenEffective, 2) : null,
                'regeneration_time' => $shieldRegenerationTime !== null ? round($shieldRegenerationTime, 2) : null,
                'regen_raw' => $shieldRegenRaw > 0 ? $shieldRegenRaw : null,
                'regen_min_power' => $shieldRegenMinPower > 0 ? $shieldRegenMinPower : null,
            ],

            'distortion' => [
                'pool' => $distortionPool > 0 ? $distortionPool : null,
            ],

            'ammo' => [
                'missiles' => $missileCount > 0 ? $missileCount : null,
                'missile_rack_capacity' => $missileRackAmmo > 0 ? $missileRackAmmo : null,
            ],

            'weapon_storage' => $weaponStorage,
            'suit_storage' => $suitStorage,
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }

    /**
     * Normalize vehicle shield pool max count.
     *
     * Resource-network dynamic pools use -1 to mean unlimited, not zero.
     */
    private function normalizeShieldPoolMaxCount(?int $maxCount): int
    {
        if ($maxCount === null || $maxCount < 0) {
            return PHP_INT_MAX;
        }

        return $maxCount;
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

    /**
     * Detect whether a port accepts suit/armor items (Char_Armor_* or Suit.* types).
     */
    private function isSuitStoragePort(array $types): bool
    {
        return array_any($types, function ($type) {
            $lower = strtolower((string) $type);

            return str_starts_with($lower, 'char_armor')
                || str_starts_with($lower, 'suit.');
        });
    }

    /**
     * Derive per-item shield regeneration from resource-network conversion deltas.
     *
     * Fallback behavior:
     * - missing conversion data => fallback to Shield.MaxShieldRegen
     * - partial conversion data (e.g. missing GeneratedRate) => fallback to Shield.MaxShieldRegen
     *
     * @param  array<string, mixed>|null  $stdItem
     * @return array{0: float, 1: float}
     */
    private function deriveShieldRegenValues(?array $stdItem): array
    {
        $maxShieldRegen = (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldRegen', 0.0);
        $state = $this->selectResourceNetworkState($stdItem);

        if ($state === null) {
            return [$maxShieldRegen, $maxShieldRegen];
        }

        $deltas = $this->normalizeArrayEntries($state['Deltas'] ?? $state['deltas'] ?? []);
        if ($deltas === []) {
            return [$maxShieldRegen, $maxShieldRegen];
        }

        $regenRaw = 0.0;
        $regenMinPower = 0.0;
        $foundConversion = false;

        foreach ($deltas as $delta) {
            $deltaType = (string) ($delta['Type'] ?? $delta['type'] ?? '');
            $generatedResource = (string) ($delta['GeneratedResource'] ?? $delta['generatedResource'] ?? '');

            if (strcasecmp($deltaType, 'Conversion') !== 0 || strcasecmp($generatedResource, 'Shield') !== 0) {
                continue;
            }

            $foundConversion = true;
            $rawGeneratedRate = $delta['GeneratedRate'] ?? $delta['generatedRate'] ?? null;
            if (! is_numeric($rawGeneratedRate)) {
                return [$maxShieldRegen, $maxShieldRegen];
            }

            $generatedRate = (float) $rawGeneratedRate;
            $rawMinimumFraction = $delta['MinimumFraction'] ?? $delta['minimumFraction'] ?? 1.0;
            $minimumFraction = is_numeric($rawMinimumFraction) ? (float) $rawMinimumFraction : 1.0;

            $regenRaw += $generatedRate;
            $regenMinPower += $generatedRate * $minimumFraction;
        }

        if (! $foundConversion) {
            return [$maxShieldRegen, $maxShieldRegen];
        }

        return [$regenRaw, $regenMinPower];
    }

    /**
     * Calculate theoretical max shield regen by assigning active shields their high-power state.
     */
    private function calculateTheoreticalShieldRegen(float $regenRaw, float $shieldPowerMaxTotal, mixed $totalPowerPool): float
    {
        if ($regenRaw <= 0.0) {
            return 0.0;
        }

        if (! is_numeric($totalPowerPool) || (float) $totalPowerPool <= 0.0 || $shieldPowerMaxTotal <= 0.0) {
            return $regenRaw;
        }

        return $regenRaw * ($shieldPowerMaxTotal / (float) $totalPowerPool);
    }

    /**
     * Derive the maximum power segments a shield can accept at the high-power state.
     *
     * @param  array<string, mixed>|null  $stdItem
     */
    private function deriveShieldPowerMaximum(?array $stdItem): float
    {
        $powerMaximum = Arr::get($stdItem ?? [], 'ResourceNetwork.Usage.Power.Maximum');

        return is_numeric($powerMaximum) ? (float) $powerMaximum : 0.0;
    }

    /**
     * Pick Online state if available; otherwise fallback to the first available state.
     *
     * @param  array<string, mixed>|null  $stdItem
     * @return array<string, mixed>|null
     */
    private function selectResourceNetworkState(?array $stdItem): ?array
    {
        $states = $this->normalizeArrayEntries(Arr::get($stdItem ?? [], 'ResourceNetwork.States', []));
        if ($states === []) {
            return null;
        }

        foreach ($states as $state) {
            $stateName = (string) ($state['Name'] ?? $state['name'] ?? '');
            if (strcasecmp($stateName, 'Online') === 0) {
                return $state;
            }
        }

        return $states[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeArrayEntries(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        if (Arr::has($value, 'Name') || Arr::has($value, 'Deltas') || Arr::has($value, 'Type')) {
            return [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private function getEmptyResources(): array
    {
        return [
            'mass_loadout' => 0.0,
            'shields_total' => [
                'hp' => null,
                'regen' => null,
                'regeneration_time' => null,
                'regen_raw' => null,
                'regen_min_power' => null,
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
            'suit_storage' => [
                'lockers' => null,
                'slots_total' => null,
                'by_locker' => null,
            ],
        ];
    }
}
