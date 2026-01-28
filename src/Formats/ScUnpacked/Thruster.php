<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Thruster extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemThrusterParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attributes = $this->get()?->attributesToArray([
            'nozzleAnimation',
            'thrusterAnimDriver',
        ]);

        $thruster = $this->get();

        $legacyBurnRate = $thruster?->get('@fuelBurnRatePer10KNewton');
        $resourceBurnRate = $thruster?->get('fuelBurnRatePer10KNewtonRN/SStandardResourceUnit@standardResourceUnits');

        $burnRatePerMnLegacy = $legacyBurnRate !== null ? (float) $legacyBurnRate * 100 : null;
        $burnRatePerMnStandardResource = $resourceBurnRate !== null ? (float) $resourceBurnRate * 100 : null;
        $burnRatePerMnMicroUnits = $resourceBurnRate !== null ? (float) $resourceBurnRate * 100 * 1_000_000 : null;

        $burnRatePerMn = $burnRatePerMnStandardResource ?? $burnRatePerMnLegacy;

        $data = $attributes ? $this->transformArrayKeysToPascalCase($attributes) : [];
        $data['BurnRatePerMN'] = $burnRatePerMn;
        $data['BurnRatePerMNStandardResourceUnits'] = $burnRatePerMnStandardResource;
        $data['BurnRatePerMNMicroUnits'] = $burnRatePerMnMicroUnits;

        return $data === [] ? null : $data;
    }
}
