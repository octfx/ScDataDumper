<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Maps port information to standardized format
 */
final class PortMapper
{
    public function __construct(
        private ?ItemSignatureCalculator $signatureCalculator = null,
    ) {
        $this->signatureCalculator ??= new ItemSignatureCalculator;
    }

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
        $equippedItem = $this->mapEquippedItem($loadout['Item'] ?? null);

        $mapped = [
            'name' => $portInfo['name'] ?? null,
            'position' => $this->inferPortPosition($portInfo['name'] ?? ''),
            'sizes' => [
                'min' => $portInfo['min'] ?? null,
                'max' => $portInfo['max'] ?? null,
            ],
            'class_name' => $loadout['className'] ?? null,
            'health' => Arr::get($loadout, 'Item.Components.SHealthComponentParams.Health'),
            'compatible_types' => $portInfo['types'] ?? [],
        ];

        if ($equippedItem) {
            $mapped['equipped_item'] = $equippedItem;
        }

        if (! empty($childPorts)) {
            $mapped['ports'] = $childPorts;
        }

        return $mapped;
    }

    /**
     * Map equipped item to standardized format
     */
    public function mapEquippedItem(?array $item): ?array
    {
        if (! $item) {
            return null;
        }

        $name = Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Localization.English.Name')
            ?? Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Localization.Name')
            ?? Arr::get($item, 'className')
            ?? Arr::get($item, 'ClassName');

        $manufacturerName = Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Manufacturer.Localization.English.Name')
            ?? Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Manufacturer.Localization.Name')
            ?? Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Manufacturer.Name');

        $manufacturerCode = Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Manufacturer.Code');

        [$powerConsumption, $coolingConsumption, $emEmission, $irEmission] = $this->computeResourceStats($item);

        return [
            'uuid' => $item['__ref'] ?? null,
            'name' => $name,
            'class_name' => $item['className'] ?? $item['ClassName'] ?? null,
            'size' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Size'),
            'mass' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Mass'),
            'grade' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Grade'),
            'class' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Class'),
            'manufacturer' => ($manufacturerName || $manufacturerCode) ? [
                'name' => $manufacturerName,
                'code' => $manufacturerCode,
            ] : null,
            'type' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Type') ?? Arr::get($item, 'Type'),
            'sub_type' => Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.SubType'),
            'power_consumption' => $powerConsumption,
            'cooling_consumption' => $coolingConsumption,
            'em_emission' => $emEmission,
            'ir_emission' => $irEmission,
        ];
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

    /**
     * Compute per-item resource stats (power, coolant consumption and EM emission).
     */
    private function computeResourceStats(array $item): array
    {
        $power = 0.0;
        $coolant = 0.0;

        $resource = Arr::get($item, 'Components.ItemResourceComponentParams');
        if (! $resource || ! is_array($resource)) {
            return [null, null, null, null];
        }

        $state = $this->extractSingleState($resource['states'] ?? null);
        if (! $state) {
            return [null, null, null, null];
        }

        $deltas = $this->normalizeDeltas($state['deltas'] ?? null);
        $signatures = $this->signatureCalculator->calculate(
            $item['Components'] ?? [],
            $state,
            $deltas,
            Arr::get($item, 'portName'),
            Arr::get($item, 'Components.EntityComponentPowerConnection.PowerBase')
        );

        foreach ($deltas as $delta) {
            if (! is_array($delta)) {
                continue;
            }

            if (isset($delta['ItemResourceDeltaConsumption'])) {
                $consumption = $delta['ItemResourceDeltaConsumption']['consumption'] ?? [];
                $res = $consumption['resource'] ?? null;
                $rate = $this->extractRate($consumption['resourceAmountPerSecond'] ?? []);
                if ($res === 'Power') {
                    $power += $rate;
                }
                if ($res === 'Coolant') {
                    $coolant += $rate;
                }
            }

            if (isset($delta['ItemResourceDeltaConversion'])) {
                $consumption = $delta['ItemResourceDeltaConversion']['consumption'] ?? [];
                $res = $consumption['resource'] ?? null;
                $rate = $this->extractRate($consumption['resourceAmountPerSecond'] ?? []);
                if ($res === 'Power') {
                    $power += $rate;
                }
                if ($res === 'Coolant') {
                    $coolant += $rate;
                }
            }
        }

        return [
            $power > 0 ? $power : null,
            $coolant > 0 ? $coolant : null,
            $signatures['em_scaled'] > 0 ? $signatures['em_scaled'] : null,
            $signatures['ir_total'] > 0 ? $signatures['ir_total'] : null,
        ];
    }

    private function extractSingleState(mixed $states): ?array
    {
        if (is_array($states) && array_key_exists('ItemResourceState', $states)) {
            return $states['ItemResourceState'];
        }

        if (is_array($states) && isset($states[0]) && is_array($states[0])) {
            return $states[0];
        }

        return null;
    }

    private function extractRate(array $resourceAmountPerSecond): float
    {
        if ($resourceAmountPerSecond === []) {
            return 0.0;
        }

        $first = reset($resourceAmountPerSecond);
        if (! is_array($first)) {
            return 0.0;
        }

        $value = reset($first);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Ensure deltas are always an array of associative arrays.
     */
    private function normalizeDeltas(mixed $deltas): array
    {
        if ($deltas === null) {
            return [];
        }

        if (is_array($deltas) && array_is_list($deltas)) {
            return $deltas;
        }

        return [is_array($deltas) ? $deltas : []];
    }
}
