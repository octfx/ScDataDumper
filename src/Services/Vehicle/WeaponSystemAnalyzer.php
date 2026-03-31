<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Formats\ScUnpacked\ItemPort;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

/**
 * Analyze weapon systems and turret configurations
 *
 * Calculates weapon fittings, identifies turret types (gimbals, ball turrets, etc.),
 * and analyzes weapon mounting configurations.
 */
final class WeaponSystemAnalyzer implements VehicleDataCalculator
{
    private readonly ItemTypeResolver $itemTypeResolver;

    public function __construct(?ItemTypeResolver $itemTypeResolver = null)
    {
        $this->itemTypeResolver = $itemTypeResolver ?? new ItemTypeResolver;
    }

    /**
     * Analyze turrets and return weapon fitting data
     *
     * @param  Collection  $turrets  Collection of turret ports
     * @return array Array of weapon fitting data
     */
    public function analyzeTurrets(Collection $turrets): array
    {
        return $turrets
            ->filter(fn ($turret) => is_array($turret) && isset($turret['Port']) && is_array($turret['Port']))
            ->map(fn ($turret) => $this->calculateWeaponFitting($turret['Port'], $turret))
            ->values()
            ->toArray();
    }

    /**
     * Calculate weapon fitting for a port
     *
     * @param  array  $port  Port data
     * @return array Weapon fitting configuration
     */
    public function calculateWeaponFitting(array $port, ?array $part = null): array
    {
        $summary = $this->buildTurretSummary($port, $part);

        if ($this->isWeaponMounting($port)) {
            return [
                ...$summary,
                'Gimballed' => $this->isGimbal($port),
                'Turret' => $this->isTurret($port),
            ];
        }

        return [
            ...$summary,
            'Fixed' => true,
            'WeaponSizes' => $summary['WeaponSizes'] ?? [$summary['Size']],
            'PayloadSizes' => $summary['PayloadSizes'] ?? [$summary['Size']],
        ];
    }

    /**
     * Check if port is a weapon mounting (turret or gimbal)
     */
    private function isWeaponMounting(array $port): bool
    {
        return (! empty($port['Uneditable']) || ! $this->acceptsWeapon($port))
            && ($this->isTurret($port) || $this->isGimbal($port));
    }

    /**
     * Check if port has a gimbal mount
     */
    private function isGimbal(array $port): bool
    {
        $type = $this->resolveInstalledItemType($port);

        return is_string($type) && strcasecmp($type, 'Turret.GunTurret') === 0;
    }

    /**
     * Check if port has a turret mount
     */
    private function isTurret(array $port): bool
    {
        $type = $this->resolveInstalledItemType($port);

        return is_string($type)
            && (
                str_starts_with($type, 'TurretBase.')
                || (str_starts_with($type, 'Turret.') && strcasecmp($type, 'Turret.GunTurret') !== 0)
            );
    }

    /**
     * Check if port accepts weapon types
     */
    private function acceptsWeapon(array $port): bool
    {
        if (! isset($port['Types']) || ! is_array($port['Types'])) {
            return false;
        }

        return ItemPort::accepts($port, 'WeaponGun')
            || ItemPort::accepts($port, 'WeaponMining.Gun');
    }

    private function buildTurretSummary(array $port, ?array $part = null): array
    {
        $mounts = $this->listTurretMounts($port);
        $summary = [
            'PartName' => $part['Name'] ?? null,
            'HardpointName' => $port['PortName'] ?? null,
            'DisplayName' => $this->translate($part['DisplayName'] ?? $port['DisplayName'] ?? null),
            'Size' => $this->resolvePortSize($port),
            'TurretClassName' => $this->resolveInstalledItemClassName($port),
            'TurretType' => $this->resolveInstalledItemType($port),
        ];

        if ($mounts === []) {
            return $summary;
        }

        $weaponSizes = $this->extractMountSizes($mounts, 'WeaponSizes');
        $payloadSizes = $this->extractMountSizes($mounts, 'PayloadSizes');
        $payloadTypes = $this->extractMountStrings($mounts, 'PayloadTypes');

        $summary['MountCount'] = count($mounts);
        $summary['Mounts'] = $mounts;

        if ($weaponSizes !== []) {
            $summary['WeaponSizes'] = $weaponSizes;
        }

        if ($payloadSizes !== []) {
            $summary['PayloadSizes'] = $payloadSizes;
        }

        if ($payloadTypes !== []) {
            $summary['PayloadTypes'] = $payloadTypes;
        }

        return $summary;
    }

    private function listTurretMounts(array $port): array
    {
        $mounts = [];

        foreach ($this->extractInstalledPorts($port) as $subPort) {
            $mount = $this->buildMountSummary($subPort);
            if ($mount !== null) {
                $mounts[] = $mount;
            }
        }

        return $mounts;
    }

    private function buildMountSummary(array $port): ?array
    {
        $payloads = $this->collectPayloadDescriptors($port);
        if ($payloads === []) {
            return null;
        }

        $mount = [
            'HardpointName' => $port['PortName'] ?? null,
            'DisplayName' => $this->translate($port['DisplayName'] ?? null),
            'Size' => $this->resolvePortSize($port),
            'MinSize' => $this->normalizeSize($port['MinSize'] ?? null),
            'MaxSize' => $this->normalizeSize($port['MaxSize'] ?? null),
            'Types' => $this->extractTypes($port),
            'MountClassName' => $this->resolveInstalledItemClassName($port),
            'MountType' => $this->resolveInstalledItemType($port),
            'PayloadTypes' => $this->uniqueStrings(array_map(
                static fn ($payload) => $payload['Type'] ?? null,
                $payloads
            )),
            'PayloadClassNames' => $this->uniqueStrings(array_map(
                static fn ($payload) => $payload['ClassName'] ?? null,
                $payloads
            )),
            'PayloadSizes' => array_map(
                fn ($payload) => $this->normalizeSize($payload['Size'] ?? null) ?? 0,
                $payloads
            ),
        ];

        $weaponPayloads = array_filter(
            $payloads,
            fn ($payload) => $this->isOffensivePayloadType($payload['Type'] ?? null)
        );

        if ($weaponPayloads !== []) {
            $mount['WeaponSizes'] = array_map(
                fn ($payload) => $this->normalizeSize($payload['Size'] ?? null) ?? 0,
                $weaponPayloads
            );
        }

        return $mount;
    }

    private function collectPayloadDescriptors(array $port): array
    {
        $payloads = [];

        foreach ($this->extractInstalledPorts($port) as $childPort) {
            $payloads = [...$payloads, ...$this->collectPayloadDescriptors($childPort)];
        }

        if ($payloads !== []) {
            return $payloads;
        }

        $payloadType = $this->resolvePayloadType($port);
        if ($payloadType === null) {
            return [];
        }

        return [[
            'Type' => $payloadType,
            'ClassName' => $this->resolveInstalledItemClassName($port),
            'Size' => $this->resolvePortSize($port),
        ]];
    }

    private function resolvePayloadType(array $port): ?string
    {
        $installedType = $this->resolveInstalledItemType($port);
        if ($this->isPrimaryPayloadType($installedType)) {
            return $installedType;
        }

        foreach ($this->extractTypes($port) as $type) {
            if ($this->isPrimaryPayloadType($type)) {
                return $type;
            }
        }

        return null;
    }

    private function isPrimaryPayloadType(?string $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        return str_starts_with($type, 'WeaponGun')
            || str_starts_with($type, 'WeaponMining')
            || str_starts_with($type, 'MissileLauncher')
            || str_starts_with($type, 'GroundVehicleMissileLauncher')
            || str_starts_with($type, 'TractorBeam')
            || strcasecmp($type, 'SalvageHead') === 0
            || strcasecmp($type, 'TowingBeam') === 0;
    }

    private function isOffensivePayloadType(?string $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        return str_starts_with($type, 'WeaponGun')
            || str_starts_with($type, 'WeaponMining')
            || str_starts_with($type, 'MissileLauncher')
            || str_starts_with($type, 'GroundVehicleMissileLauncher');
    }

    private function extractInstalledPorts(array $port): array
    {
        $ports = $port['InstalledItem']['stdItem']['Ports']
            ?? $port['InstalledItem']['Ports']
            ?? [];

        if (! is_array($ports)) {
            return [];
        }

        return array_values(array_filter($ports, 'is_array'));
    }

    private function extractTypes(array $port): array
    {
        $types = $port['Types'] ?? [];

        if (! is_array($types)) {
            return [];
        }

        return array_values(array_filter($types, 'is_string'));
    }

    private function extractMountSizes(array $mounts, string $key): array
    {
        $sizes = [];

        foreach ($mounts as $mount) {
            if (! isset($mount[$key]) || ! is_array($mount[$key])) {
                continue;
            }

            foreach ($mount[$key] as $size) {
                $normalizedSize = $this->normalizeSize($size);
                if ($normalizedSize !== null) {
                    $sizes[] = $normalizedSize;
                }
            }
        }

        return $sizes;
    }

    private function extractMountStrings(array $mounts, string $key): array
    {
        $values = [];

        foreach ($mounts as $mount) {
            if (! isset($mount[$key]) || ! is_array($mount[$key])) {
                continue;
            }

            foreach ($mount[$key] as $value) {
                if (is_string($value) && $value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $this->uniqueStrings($values);
    }

    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_filter(
            $values,
            static fn ($value) => is_string($value) && $value !== ''
        )));
    }

    private function resolvePortSize(array $port): int
    {
        $candidates = [
            $port['InstalledItem']['stdItem']['Size'] ?? null,
            $port['InstalledItem']['Size'] ?? null,
            $port['Size'] ?? null,
            $port['MaxSize'] ?? null,
            $port['MinSize'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $size = $this->normalizeSize($candidate);
            if ($size !== null && $size > 0) {
                return $size;
            }
        }

        return 0;
    }

    private function normalizeSize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function resolveInstalledItemType(array $port): ?string
    {
        $installedItem = $port['InstalledItem'] ?? null;
        if (! is_array($installedItem)) {
            return null;
        }

        return $this->itemTypeResolver->resolveSemanticType($installedItem);
    }

    private function resolveInstalledItemClassName(array $port): ?string
    {
        $installedItem = $port['InstalledItem'] ?? null;
        if (! is_array($installedItem)) {
            return null;
        }

        $className = $installedItem['stdItem']['ClassName'] ?? $installedItem['ClassName'] ?? null;

        return is_string($className) && $className !== '' ? $className : null;
    }

    private function translate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! str_starts_with($value, '@')) {
            return $value;
        }

        try {
            return ServiceFactory::getLocalizationService()->getTranslation($value);
        } catch (RuntimeException) {
            return $value;
        }
    }

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $mannedTurrets = $context->portSummary['mannedTurrets'] ?? collect([]);
        $pdcTurrets = $context->portSummary['pdcTurrets'] ?? collect([]);
        $remoteTurrets = $context->portSummary['remoteTurrets'] ?? collect([]);

        return [
            'MannedTurrets' => $this->analyzeTurrets($mannedTurrets),
            'PdcTurrets' => $this->analyzeTurrets($pdcTurrets),
            'RemoteTurrets' => $this->analyzeTurrets($remoteTurrets),
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }
}
