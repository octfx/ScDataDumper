<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MountingPositionBuilder
{
    // Countermeasure launchers stay in the regular loadout, not positions or payload.
    private const TYPES = [
        'weapongun' => 'Fixed', 'weaponmount' => 'Fixed',
        'turret' => 'Turret', 'turretbase' => 'Turret', 'utilityturret' => 'Turret',
        'missilelauncher' => 'Rack', 'groundvehiclemissilelauncher' => 'Rack', 'bomblauncher' => 'Rack', 'missile' => 'Missile',
        'weaponmining' => 'Tool', 'miningarm' => 'Tool', 'toolarm' => 'Tool',
        'tractorbeam' => 'Tool', 'salvagehead' => 'Tool', 'towingbeam' => 'Tool',
        'emp' => 'Device', 'salvagefieldemitter' => 'Tool',
    ];

    public function __construct(
        private readonly WeaponDpsAggregator $weaponStats = new WeaponDpsAggregator(new StandardisedPartWalker),
    ) {}

    /**
     * @param  array  $operatingStations  Verified stations by snapshot port ID
     * @param  array<string, list<array<string, mixed>>>  $legacyBuckets  Exported legacy turret
     *                                                                    summaries (MannedTurrets, RemoteTurrets,
     *                                                                    PdcTurrets) in export order
     */
    public function build(array &$loadout, array $sourcePorts, array $operatingStations, array $legacyBuckets = []): array
    {
        $positions = [];
        $issues = [];
        $statSources = [];
        $legacyIdentities = [];

        foreach ($legacyBuckets as $turrets) {
            foreach ($turrets as $turret) {
                $legacyIdentities[$this->legacyIdentity($turret['HardpointName'] ?? null, $turret['PartName'] ?? null, $turret['TurretClassName'] ?? null)] = true;
            }
        }

        foreach ($loadout as $i => $entry) {
            $port = $sourcePorts[$i];
            $this->indexStatSources($entry, $port, $statSources, true);
            $this->collect($entry, $port, $positions, $legacyIdentities);
        }

        $byName = [];

        foreach ($positions as $position) {
            $byName[$position['HardpointName']][] = $position['PortId'];
        }

        foreach ($positions as &$position) {
            $name = $position['HardpointName'];
            $id = $position['PortId'];

            if ($position['Occupied'] && isset($operatingStations[$id])) {
                $position['OperatingStations'] = $operatingStations[$id];
            }

            if (isset($position['IsPilotSlaveable'])
                && (count($byName[$name]) !== 1 || (isset($statSources[$name]) && $statSources[$name] !== [$id]))) {
                $issues[$name] = ['Reason' => 'AmbiguousSlaveability', 'HardpointName' => $name,
                    'PortIds' => array_values(array_unique([...$byName[$name], ...($statSources[$name] ?? [])]))];
                unset($position['IsPilotSlaveable']);
            }
        }

        unset($position);

        $this->attributeLegacyBuckets($positions, $loadout, $legacyBuckets, $issues);

        $this->annotate($loadout, $positions);

        return [array_values($positions), array_values($issues)];
    }

    private function collect(array $entry, array $port, array &$positions, array $legacyIdentities): void
    {
        $item = $port['InstalledItem']['stdItem'] ?? [];
        $name = $entry['HardpointName'] ?? null;
        $type = strtolower($entry['Type'] ?? '');

        if ($type === 'quantuminterdictiongenerator' || str_starts_with($type, 'quantuminterdictiongenerator.')) {
            return;
        }

        $mount = $this->mountType($type);
        // Hull extensions host their own ports rather than carrying those assemblies as payload.
        $extendsHull = in_array($type, ['attachedpart.undefined', 'container.cargo', 'module.undefined'], true);

        if ($extendsHull) {
            $children = array_values($item['Ports'] ?? []);

            foreach ($entry['Loadout'] ?? [] as $i => $child) {
                $this->collect($child, $children[$i], $positions, $legacyIdentities);
            }
        }

        if ($mount === null && ! array_any($port['Types'] ?? [], fn ($type) => $this->mountType($type) !== null)) {
            return;
        }

        // Locked empty slots can be inherited from another variant. Keep established legacy coverage.
        if (! isset($port['InstalledItem']) && ($port['Uneditable'] ?? false)
            && ! isset($legacyIdentities[$this->legacyIdentity($name, $entry['PartName'] ?? null, $entry['ClassName'] ?? null)])) {
            return;
        }

        $payload = $extendsHull ? [] : $this->payload($entry, $port);
        $role = match ($port['Category'] ?? null) {
            'Manned turrets', 'Mining turrets' => 'Manned',
            'Remote turrets' => 'Remote',
            'PDC turrets' => 'Pdc',
            'Autonomous turrets' => 'Autonomous',
            default => null,
        };

        if ($mount === 'Turret') {
            $assembly = isset($entry['UUID']) ? ServiceFactory::getItemService()->getByReference($entry['UUID']) : null;

            if ($assembly?->hasDoorParameters()) {
                $mount = null;
                $role = null;
            } elseif ($type === 'turret.ballturret' && $assembly !== null && ! $assembly->hasTurretParameters()) {
                // Ball-turret attachment compatibility is also used by fixed adapters and caps.
                $mount = $assembly->hasFixedWeaponMountGeometry() ? 'Fixed' : null;
                $role = null;
            }
        }

        if ($mount === 'Turret') {
            $role ??= match ($type) {
                'utilityturret.mannedturret', 'turretbase.mannedturret' => 'Manned',
                'turretbase.remoteturret', 'turret.utility', 'utilityturret.remoteturret' => 'Remote',
                'turret.pdcturret' => 'Pdc',
                default => null,
            };

            if ($role === null) {
                if ($type === 'turret.gunturret' && (new PortClassifierService)->isGimbalAdapter($port, $item)) {
                    $mount = 'Gimbal';
                } elseif ($assembly !== null && ! $assembly->hasIntegratedSeat() && $assembly->hasRemoteTurretArticulation()) {
                    $role = 'Remote';
                } elseif ($type === 'turret.gunturret') {
                    if ($port['HasTurretArticulation'] ?? false) {
                        $role = ($port['Category'] ?? null) === 'Utility turrets' ? 'Remote' : null;
                    } else {
                        $mount = null;
                    }
                }
            }
        }

        if ($mount !== null && $role !== null) {
            $mount = 'Turret';
        } else {
            $role = null;
        }

        $id = $entry['PortId'];
        $positions[$id] = [
            'PortId' => $id,
            'HardpointName' => $name,
            'MountType' => $mount,
            'TurretRole' => $role,
            'MinSize' => $entry['MinSize'],
            'MaxSize' => $entry['MaxSize'],
            'Occupied' => isset($port['InstalledItem']),
            'InstalledSize' => $item['Size'] ?? null,
            'IsPilotSlaveable' => $entry['IsPilotSlaveable'] ?? null,
            'Payload' => $payload,
        ];

        $totals = [];

        foreach (array_column($payload, 'CombatStats', 'PortId') as $stats) {
            foreach ($stats as $metric => $value) {
                $totals[$metric] = ($totals[$metric] ?? 0) + $value;
            }
        }

        if ($totals !== []) {
            $positions[$id]['CombatStats'] = $totals;
        }
    }

    private function indexStatSources(array $entry, array $port, array &$ids, bool $isHullPort): void
    {
        $installed = $port['InstalledItem'] ?? null;
        $type = (new ItemTypeResolver)->resolveSemanticType($installed);
        // Match the aggregator's hull-port context; nested items do not get their parent's category.
        if (WeaponDpsAggregator::isTurretBaseType($type, $isHullPort ? $port : ($port['Port'] ?? null))) {
            $ids[$entry['HardpointName'] ?? null][] = $entry['PortId'];
        }

        $children = array_values($installed['stdItem']['Ports'] ?? []);

        foreach ($entry['Loadout'] ?? [] as $i => $child) {
            $this->indexStatSources($child, $children[$i], $ids, false);
        }
    }

    /**
     * Carry legacy turret bucket membership onto the linked mounting position without
     * touching the current classification. Attribution uses the multi-field identity
     * (hardpoint name, part name, installed class name); collisions or misses become
     * UnresolvedLegacyTurret diagnostics instead of a guess.
     *
     * @param  array<string, array<string, mixed>>  $positions
     * @param  array<string, list<array<string, mixed>>>  $legacyBuckets
     * @param  array<string, array<string, mixed>>  $issues
     */
    private function attributeLegacyBuckets(array &$positions, array $loadout, array $legacyBuckets, array &$issues): void
    {
        if ($legacyBuckets === []) {
            return;
        }

        $entries = [];
        $this->indexEntries($loadout, $entries);

        $identities = [];

        foreach ($positions as $position) {
            $entry = $entries[$position['PortId']] ?? [];
            $identities[$this->legacyIdentity($position['HardpointName'], $entry['PartName'] ?? null, $entry['ClassName'] ?? null)][] = $position['PortId'];
        }

        $claims = [];

        foreach ($legacyBuckets as $bucket => $turrets) {
            foreach ($turrets as $ordinal => $turret) {
                $name = $turret['HardpointName'] ?? null;
                $part = $turret['PartName'] ?? null;
                $class = $turret['TurretClassName'] ?? null;
                $matches = $identities[$this->legacyIdentity($name, $part, $class)] ?? [];

                if (count($matches) !== 1 || (($part === null || $part === '') && ($class === null || $class === ''))) {
                    $issues["legacy.{$bucket}.{$ordinal}"] = [
                        'Reason' => 'UnresolvedLegacyTurret',
                        'Bucket' => $bucket,
                        'Ordinal' => $ordinal,
                        'HardpointName' => $name,
                        'PortIds' => $matches,
                    ];

                    continue;
                }

                $claims[$matches[0]][] = [$bucket, $ordinal, $turret];
            }
        }

        foreach ($claims as $portId => $claimed) {
            if (count($claimed) > 1) {
                foreach ($claimed as [$bucket, $ordinal, $turret]) {
                    $issues["legacy.{$bucket}.{$ordinal}"] = [
                        'Reason' => 'UnresolvedLegacyTurret',
                        'Bucket' => $bucket,
                        'Ordinal' => $ordinal,
                        'HardpointName' => $turret['HardpointName'] ?? null,
                        'PortIds' => [$portId],
                    ];
                }

                continue;
            }

            [$bucket, $ordinal, $turret] = $claimed[0];
            $positions[$portId]['LegacyBucket'] = $bucket;
            $positions[$portId]['LegacyOrdinal'] = $ordinal;

            if (($turret['Size'] ?? 0) !== $this->derivableSize($entries[$portId])) {
                $positions[$portId]['LegacySize'] = (int) ($turret['Size'] ?? 0);
            }
        }
    }

    private function indexEntries(array $loadout, array &$entries): void
    {
        foreach ($loadout as $entry) {
            $entries[$entry['PortId']] = $entry;

            if (isset($entry['Loadout'])) {
                $this->indexEntries($entry['Loadout'], $entries);
            }
        }
    }

    private function derivableSize(array $entry): int
    {
        foreach ([$entry['InstalledSize'] ?? null, $entry['MaxSize'] ?? null, $entry['MinSize'] ?? null] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    private function legacyIdentity(?string $hardpoint, ?string $part, ?string $class): string
    {
        return serialize([$hardpoint, $part, $class]);
    }

    private function annotate(array &$loadout, array $positions): void
    {
        foreach ($loadout as &$entry) {
            if (isset($positions[$entry['PortId']])) {
                $entry += array_intersect_key($positions[$entry['PortId']], array_flip(['MountType', 'TurretRole']));
            }

            if (isset($entry['Loadout'])) {
                $this->annotate($entry['Loadout'], $positions);
            }
        }
    }

    private function mountType(?string $type): ?string
    {
        return self::TYPES[strtolower(explode('.', $type ?? '', 2)[0])] ?? null;
    }

    private function payload(array $entry, array $port): array
    {
        $payload = [];
        $item = $port['InstalledItem']['stdItem'] ?? [];
        $type = $entry['Type'] ?? '';

        if (strtolower($type) === 'bomb.utility' || in_array($this->mountType($type), ['Fixed', 'Missile', 'Tool', 'Device'], true)) {
            $payload[] = [
                ...array_intersect_key($entry, array_flip(['PortId', 'HardpointName', 'UUID', 'ClassName', 'Name', 'Type'])),
                'Size' => $item['Size'] ?? null,
            ];
            // The paired source port carries the same installation as this exported PortId, independent of the stat walk's path.
            $stats = $this->weaponStats->installedCombatStats($port['InstalledItem'] ?? []);

            if ($stats !== []) {
                $payload[0]['CombatStats'] = $stats;
            }
        }

        $children = array_values($item['Ports'] ?? []);

        foreach ($entry['Loadout'] ?? [] as $i => $child) {
            $payload = [...$payload, ...$this->payload($child, $children[$i])];
        }

        return $payload;
    }
}
