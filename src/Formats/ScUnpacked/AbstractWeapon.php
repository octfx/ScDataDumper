<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Exception;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Shared weapon logic (damage, modes, magazine, etc.) for both personal and vehicle weapons.
 */
abstract class AbstractWeapon extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemWeaponComponentParams';

    protected function buildBaseWeaponArray(): array
    {
        $weapon = $this->get();

        $ammunition = new Ammunition($this->item);
        $ammunitionArray = $ammunition->toArray();

        $out = [
            'Size' => $this->item->get('Components/SAttachableComponentParams/AttachDef@Size'),
            'WeaponType' => $this->item->get('Components/SAttachableComponentParams/AttachDef@Type'),
            'WeaponClass' => $this->item->get('Components/SAttachableComponentParams/AttachDef@SubType'),
            'EffectiveRange' => $this->resolveEffectiveRange($weapon, $ammunitionArray),
            'RateOfFire' => null,
            'Capacity' => is_array($ammunitionArray) ? ($ammunitionArray['Capacity'] ?? null) : null,
            // 'Magazine' => $this->buildMagazine(), // Deprecated: Use stdItem.Magazine
            // 'Ammunition' => $ammunitionArray, // Deprecated: Use stdItem.Ammunition
            'Attachments' => $this->buildAttachments(),
            'Modes' => [],
            'Consumption' => new WeaponConsumption($weapon)->toArray(),
            'Knife' => new MeleeWeapon($this->item),
        ];

        foreach ($weapon->get('/fireActions')?->children() as $action) {
            $mode = new WeaponMode($action);
            if (! $mode->canTransform()) {
                continue;
            }

            $mode = $mode->toArray();

            $launchParams = $this->extractLauncher($action);
            $chargeContext = $this->extractChargeContext($action);
            $damageMultiplier = $this->extractDamageMultiplier($launchParams, $chargeContext['Modifier']);

            $pellets = (float) ($mode['PelletsPerShot'] ?? 0);
            $damage = $this->calculateDamage($ammunitionArray, $pellets, $damageMultiplier);

            $rpm = $this->calculateEffectiveRpm($mode['RoundsPerMinute'] ?? null, $action, $chargeContext['Modifier']);
            if ($rpm !== null) {
                $mode['RoundsPerMinute'] = $this->roundStat($rpm);
            }

            $mode['DamagePerShot'] = $this->roundStat($damage['Total'], 3);
            $mode['DamagePerSecond'] = $this->roundStat($this->calculateDamagePerSecond($damage['Total'], $mode['RoundsPerMinute'] ?? null));
            $mode['Alpha'] = $this->roundStat($damage['Total']);
            $mode['Dps'] = $mode['DamagePerSecond'];

            $capacity = $out['Capacity'] ?? ($ammunitionArray['Capacity'] ?? null);
            if ($capacity !== null) {
                $mode['MaxDamagePerMagazine'] = $this->roundStat($damage['Total'] * (float) $capacity);
            }

            foreach ($this->buildDamagePerSecondByType($damage['ByType'], $mode['RoundsPerMinute'] ?? null) as $type => $value) {
                $mode['Dps'.$type] = $this->roundStat($value);
            }

            foreach ($damage['ByType'] as $type => $value) {
                $mode['Alpha'.$type] = $this->roundStat($value, 3);
            }

            $spread = $this->extractSpread($action);
            if ($spread !== []) {
                $mode['Spread'] = $spread;
                $mode['AdsSpread'] = $this->extractAdsSpread($weapon, $spread);
            }

            $spin = $this->extractSpinTimes($action);
            if ($spin !== []) {
                $mode['BarrelSpinTime'] = $spin;
            }

            if ($chargeContext['Timings'] !== []) {
                $mode['Charge'] = $chargeContext['Timings'];
            }

            if ($chargeContext['Modifier'] !== []) {
                $mode['ChargeModifier'] = $chargeContext['Modifier'];
            }

            $out['RateOfFire'] ??= $mode['RoundsPerMinute'] ?? null;

            $out['Modes'][] = $mode;
        }

        $consumption = $out['Consumption'];
        if (empty($out['Capacity']) && ! empty($consumption['RequestedAmmoLoad']) && ! empty($consumption['CostPerBullet'])) {
            $out['Capacity'] = (int) floor($consumption['RequestedAmmoLoad'] / $consumption['CostPerBullet']);
        }

        return $this->removeNullValues($out);
    }

    private function extractLauncher(Element $action): ?Element
    {
        return match ($action->nodeName) {
            'SWeaponActionFireChargedParams' => $action->get('weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher')
                ?? $action->get('weaponAction/SWeaponActionFireBurstParams/launchParams/SProjectileLauncher'),
            default => $action->get('launchParams/SProjectileLauncher'),
        };
    }

    private function extractSpread(Element $action): array
    {
        $launcher = $this->extractLauncher($action);

        if (! $launcher instanceof Element) {
            return [];
        }

        $spread = [
            'Min' => $launcher->get('spreadParams@min'),
            'Max' => $launcher->get('spreadParams@max'),
            'FirstAttack' => $launcher->get('spreadParams@firstAttack'),
            'Attack' => $launcher->get('spreadParams@attack'),
            'Decay' => $launcher->get('spreadParams@decay'),
        ];

        return $this->removeNullValues($spread);
    }

    private function extractAdsSpread(Element $weapon, array $baseSpread): array
    {
        $modifier = $weapon->get('aimAction/SWeaponActionAimSimpleParams/aimModifier/SWeaponModifierParams/weaponStats/spreadModifier');

        if (! $modifier instanceof Element || $baseSpread === []) {
            return $baseSpread;
        }

        $apply = static function (?float $value, ?float $multiplier, ?float $additive = 0.0): ?float {
            if ($value === null && $additive === null) {
                return null;
            }

            $multiplier ??= 1.0;
            $additive ??= 0.0;

            if ($value === null) {
                return $additive === 0.0 ? null : $additive;
            }

            return ($value * $multiplier) + $additive;
        };

        $ads = [
            'Min' => $apply($baseSpread['Min'] ?? null, $modifier->get('minMultiplier'), $modifier->get('additiveModifier')),
            'Max' => $apply($baseSpread['Max'] ?? null, $modifier->get('maxMultiplier'), $modifier->get('additiveModifier')),
            'FirstAttack' => $apply($baseSpread['FirstAttack'] ?? null, $modifier->get('firstAttackMultiplier'), $modifier->get('additiveModifier')),
            'Attack' => $apply($baseSpread['Attack'] ?? null, $modifier->get('attackMultiplier'), $modifier->get('additiveModifier')),
            'Decay' => $apply($baseSpread['Decay'] ?? null, $modifier->get('decayMultiplier')),
        ];

        return $this->removeNullValues($ads);
    }

    private function extractSpinTimes(Element $action): array
    {
        if ($action->nodeName !== 'SWeaponActionFireRapidParams') {
            return [];
        }

        $up = $action->get('spinUpTime');
        $down = $action->get('spinDownTime');

        $spin = [
            'Up' => $up,
            'Down' => $down,
        ];

        return $this->removeNullValues($spin);
    }

    private function extractChargeContext(Element $action): array
    {
        if ($action->nodeName !== 'SWeaponActionFireChargedParams') {
            return ['Timings' => [], 'Modifier' => []];
        }

        $modifier = $action->get('/maxChargeModifier');

        return [
            'Timings' => $this->removeNullValues([
                'ChargeTime' => $action->get('chargeTime'),
                'OverchargeTime' => $action->get('overchargeTime'),
                'OverchargedTime' => $action->get('overchargedTime'),
                'CooldownTime' => $action->get('cooldownTime'),
            ]),
            'Modifier' => $this->removeNullValues([
                'Damage' => $modifier?->get('damageMultiplier') ?? 1.0,
                'FireRate' => $modifier?->get('fireRateMultiplier') ?? 1.0,
                'AmmoSpeed' => $modifier?->get('projectileSpeedMultiplier'),
                'AmmoCost' => $modifier?->get('ammoCost'),
                'AmmoCostMultiplier' => $modifier?->get('ammoCostMultiplier'),
            ]),
        ];
    }

    private function extractDamageMultiplier(?Element $launcher, array $chargeModifier): float
    {
        $base = $launcher?->get('damageMultiplier') ?? 1.0;
        $charge = $chargeModifier['Damage'] ?? 1.0;

        return (float) $base * (float) $charge;
    }

    protected function calculateDamage(?array $ammunition, float $pellets, float $damageMultiplier): array
    {
        $ammo = is_array($ammunition) ? $ammunition : [];
        $impact = $ammo['ImpactDamage'] ?? [];
        $detonation = $ammo['DetonationDamage'] ?? [];

        $byType = [];
        $total = 0.0;

        foreach (self::$resistanceKeys as $key) {
            $value = (($impact[$key] ?? 0) + ($detonation[$key] ?? 0)) * $pellets * $damageMultiplier;
            $byType[$key] = $value;
            $total += $value;
        }

        return ['Total' => $total, 'ByType' => $byType];
    }

    protected function calculateDamagePerSecond(float $damagePerShot, ?float $rpm): float
    {
        if ($rpm === null) {
            return 0.0;
        }

        return $damagePerShot * ($rpm / 60.0);
    }

    private function buildDamagePerSecondByType(array $damageByType, ?float $rpm): array
    {
        $result = [];

        if ($rpm === null) {
            foreach (self::$resistanceKeys as $key) {
                $result[$key] = 0.0;
            }

            return $result;
        }

        foreach (self::$resistanceKeys as $key) {
            $result[$key] = ($damageByType[$key] ?? 0) * ($rpm / 60.0);
        }

        return $result;
    }

    private function calculateEffectiveRpm(?float $rpm, Element $action, array $chargeModifier): ?float
    {
        if ($rpm === null) {
            return null;
        }

        if ($action->nodeName === 'SWeaponActionFireChargedParams') {
            $chargeTime = (float) ($action->get('chargeTime') ?? 0);
            $cooldown = (float) ($action->get('cooldownTime') ?? 0);

            $timedCycle = $chargeTime + $cooldown;
            if ($timedCycle > 0) {
                $rpmFromTiming = 60.0 / $timedCycle;

                return $rpmFromTiming * (float) ($chargeModifier['FireRate'] ?? 1.0);
            }
        }

        $rpm *= (float) ($chargeModifier['FireRate'] ?? 1.0);

        if ($rpm === 0.0) {
            return null;
        }

        $cycleTime = 60.0 / $rpm;

        return $cycleTime > 0 ? 60.0 / $cycleTime : null;
    }

    protected function roundStat(?float $value, int $precision = 1): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value, $precision);
    }

    private function resolveEffectiveRange(Element $weapon, ?array $ammunition): ?float
    {
        $range = $weapon->get('effectiveRange');

        return $range ?? $ammunition['Range'] ?? null;
    }

    private function buildMagazine(): array
    {
        $magazine = $this->item->get('Components/SCItemWeaponComponentParams/Magazine/Components/SAmmoContainerComponentParams');

        if ($magazine === null) {
            return [];
        }

        return [
            'UUID' => $magazine->get('ammoParamsRecord'),
            'Type' => $this->item->get('Components/SCItemWeaponComponentParams/Magazine/Components/SAttachableComponentParams/AttachDef/SubType'),
            'InitialAmmoCount' => $magazine->get('initialAmmoCount'),
            'MaxAmmoCount' => $magazine->get('maxAmmoCount'),
            'MaxRestockCount' => $magazine->get('maxRestockCount'),
        ];
    }

    private function buildAttachments(): array
    {
        $entries = $this->item->get('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams/entries');

        if ($entries === null) {
            return [];
        }

        $attachments = [];
        $itemService = ServiceFactory::getItemService();

        foreach ($entries->children() as $entry) {
            $portName = $entry->get('@itemPortName');
            $className = $entry->get('@entityClassName');

            if (empty($portName)) {
                continue;
            }

            $uuid = null;
            if (! empty($className)) {
                try {
                    $item = $itemService->getByClassName($className);
                    $uuid = $item?->getUuid();
                } catch (Exception $e) {
                    // Item not found or error loading - leave UUID as null
                }
            }

            $attachments[] = array_filter([
                'UUID' => $uuid,
                'Port' => $portName,
                'ClassName' => $className ?: null,
            ]);
        }

        return $attachments;
    }
}
