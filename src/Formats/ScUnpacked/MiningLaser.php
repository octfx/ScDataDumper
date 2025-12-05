<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;

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

        $descriptionData = ItemDescriptionParser::parse(
            $attachDef->get('Localization/English@Description', ''),
            [
                'Item Type' => 'item_type',
                'Optimal Range' => 'optimal_range',
                'Maximum Range' => 'maximum_range',
                'Power Transfer' => 'power_transfer',
                'Collection Throughput' => 'collection_throughput',
                'Extraction Throughput' => 'extraction_throughput',
                'All Charge Rates' => 'all_charge_rates',
                'Collection Point Radius' => 'collection_point_radius',
                'Instability' => 'instability',
                'Module Slots' => 'module_slots',
                'Module' => 'module',
                'Optimal Charge Rate' => 'optimal_charge_rate',
                'Optimal Charge Window' => 'optimal_charge_window',
                'Overcharge Rate' => 'overcharge_rate',
                'Resistance' => 'resistance',
                'Shatter Damage' => 'shatter_damage',
                'Throttle Responsiveness Delay' => 'throttle_responsiveness_delay',
                'Throttle Speed' => 'throttle_speed',
            ]
        );

        $laserParams = $this->get();
        $weapon = $this->item->get('Components/SCItemWeaponComponentParams');
        $fireActions = $weapon?->get('/fireActions');

        [$fractureAction, $extractionAction] = $this->identifyActions($fireActions);

        $powerTransfer = $this->extractDamagePerSecond($fractureAction);
        $extractionThroughput = $this->extractThroughput($extractionAction);

        $data = [
            'PowerTransfer' => $powerTransfer,
            'OptimalRange' => $fractureAction?->get('@fullDamageRange'),
            'MaximumRange' => $fractureAction?->get('@zeroDamageRange'),
            'ExtractionThroughput' => $extractionThroughput,
            'ModuleSlots' => $this->countModulePorts(),
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
            foreach ($port->get('Types')?->children() ?? [] as $type) {
                if ($type->get('@Type') === 'MiningModifier') {
                    $count++;
                    break;
                }
            }
        }

        return $count === 0 ? null : $count;
    }
}
