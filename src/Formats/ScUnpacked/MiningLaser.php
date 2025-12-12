<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Arr;

final class MiningLaser extends BaseFormat
{
    protected ?string $elementKey = 'Components/SEntityComponentMiningLaserParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attachDef = $this->item->getAttachDef();

        if ($attachDef === null) {
            return null;
        }

        $laserParams = $this->get();
        $weapon = $this->item->get('Components/SCItemWeaponComponentParams');
        $fireActions = $weapon?->get('/fireActions');

        [$fractureAction, $extractionAction] = $this->identifyActions($fireActions);

        $powerTransfer = $this->extractDamagePerSecond($fractureAction);

        $extractionThroughput = $this->extractThroughput($extractionAction);

        $laserParamsData = $laserParams?->get('/MiningLaserGlobalParams')?->attributesToArray(['__ref', '__path'],pascalCase: true);

        $throttleMinimum = $laserParams?->get('@throttleMinimum');
        $throttleHoldAccFactor = Arr::get($laserParamsData, 'ThrottleHoldAccFactor');

        if ($throttleMinimum !== null && $throttleHoldAccFactor !== null) {
            $minFactor = min($throttleMinimum, $throttleHoldAccFactor);
        } elseif ($throttleMinimum !== null) {
            $minFactor = $throttleMinimum;
        } elseif ($throttleHoldAccFactor !== null) {
            $minFactor = $throttleHoldAccFactor;
        } else {
            $minFactor = null;
        }

        $minPowerTransfer = $powerTransfer !== null && $minFactor !== null
            ? $minFactor * $powerTransfer
            : null;

        $data = [
            'PowerTransfer' => $powerTransfer,
            'MinPowerTransfer' => $minPowerTransfer,
            'OptimalRange' => $fractureAction?->get('@fullDamageRange'),
            'MaximumRange' => $fractureAction?->get('@zeroDamageRange'),
            'ExtractionThroughput' => $extractionThroughput,
            'ModuleSlots' => $this->countModulePorts(),
            'UsesPowerThrottle' => $laserParams?->get('@usesPowerThrottle'),
            'GlobalParams' => $laserParamsData,
            'Modifiers' => [
                'AllChargeRates' => $laserParams?->get('filterParams/filterModifier/FloatModifierMultiplicative@value'),
                'CollectionPointRadius' => $this->extractCollectionRadius($extractionAction ?? $fractureAction),
                'Instability' => $laserParams?->get('miningLaserModifiers/laserInstability/FloatModifierMultiplicative@value'),
                'Module' => $data['module'] ?? null,
                'OptimalChargeRate' => $laserParams?->get('miningLaserModifiers/optimalChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'OptimalChargeWindow' => $laserParams?->get('miningLaserModifiers/optimalChargeWindowSizeModifier/FloatModifierMultiplicative@value'),
                'OverchargeRate' => $laserParams?->get('miningLaserModifiers/catastrophicChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'Resistance' => $laserParams?->get('miningLaserModifiers/resistanceModifier/FloatModifierMultiplicative@value'),
                'ShatterDamage' => $laserParams?->get('miningLaserModifiers/shatterdamageModifier/FloatModifierMultiplicative@value'),
                'ClusterFactor' => $laserParams?->get('miningLaserModifiers/clusterFactorModifier/FloatModifierMultiplicative@value'),
                'ThrottleResponsivenessDelay' => $laserParams?->get('@throttleMinimum'),
                'ThrottleSpeed' => $laserParams?->get('@throttleLerpSpeed'),
            ],
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'WeaponMining'
            && $this->has($this->elementKey);
    }

    /**
     * @return array{0: Element|null, 1: Element|null}
     */
    private function identifyActions(?Element $fireActions): array
    {
        $fracture = $extraction = null;

        foreach ($fireActions?->children() ?? [] as $action) {
            if ($action->nodeName === 'SWeaponActionFireBeamParams') {
                $hitType = $action->get('@hitType');

                if ($hitType === 'Extraction') {
                    $extraction ??= $action;

                    continue;
                }

                $fracture ??= $action;

                continue;
            }

            if ($action->nodeName === 'SWeaponActionGatheringBeamParams') {
                $extraction ??= $action;
            }
        }

        return [$fracture, $extraction];
    }

    private function extractDamagePerSecond(?Element $action): ?float
    {
        if (! $action instanceof Element) {
            return null;
        }

        $damageInfo = $action->get('damagePerSecond/DamageInfo');

        if (! $damageInfo instanceof Element) {
            return null;
        }

        $total = 0.0;

        foreach (self::$resistanceKeys as $key) {
            $value = $damageInfo->get('Damage'.$key);

            if ($value !== null) {
                $total += (float) $value;
            }
        }

        return $total === 0.0 ? null : $total;
    }

    private function extractThroughput(?Element $action): ?float
    {
        if (! $action instanceof Element) {
            return null;
        }

        if ($action->nodeName === 'SWeaponActionGatheringBeamParams') {
            return $action->get('collectionRate');
        }

        return $this->extractDamagePerSecond($action);
    }

    private function extractCollectionRadius(?Element $action): ?float
    {
        if (! $action instanceof Element) {
            return null;
        }

        if ($action->nodeName === 'SWeaponActionGatheringBeamParams') {
            return $action->get('beamRadius');
        }

        return $action->get('@hitRadius');
    }

    private function countModulePorts(): ?int
    {
        $ports = $this->item->get('Components/SItemPortContainerComponentParams/Ports');

        if (! $ports instanceof Element) {
            return null;
        }

        $count = 0;

        foreach ($ports->children() as $port) {
            $typeNodes = $port->get('/CompatibleTypes')?->children();
            if ($typeNodes === null || $typeNodes === []) {
                $typeNodes = $port->get('/Types')?->children() ?? [];
            }

            foreach ($typeNodes as $type) {
                $major = $type->get('@CompatibleType') ?? $type->get('@Type') ?? $type->get('@type');

                if ($major !== null && strcasecmp($major, 'MiningModifier') === 0) {
                    $count++;
                    break;
                }
            }
        }

        return $count === 0 ? null : $count;
    }
}
