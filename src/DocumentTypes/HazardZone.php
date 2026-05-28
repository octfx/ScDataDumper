<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Helper\Element;

/**
 * Represents a hazard zone entity spawned by items like plasma grenades.
 */
class HazardZone extends EntityClassDefinition
{
    /**
     * The raw HazardComponentParams element.
     */
    public function getHazardParams(): ?Element
    {
        $params = $this->get('Components/HazardComponentParams');

        return $params instanceof Element ? $params : null;
    }

    /**
     * Damage dealt per tick, summed across all damage types.
     */
    public function getDamagePerTick(): ?float
    {
        $params = $this->getHazardParams();

        if ($params === null) {
            return null;
        }

        $damageInfo = $params->get('damagePerHit/DamageInfo');

        if ($damageInfo === null) {
            return null;
        }

        $total = (float) ($damageInfo->get('@DamagePhysical') ?? 0)
            + (float) ($damageInfo->get('@DamageEnergy') ?? 0)
            + (float) ($damageInfo->get('@DamageDistortion') ?? 0)
            + (float) ($damageInfo->get('@DamageThermal') ?? 0)
            + (float) ($damageInfo->get('@DamageBiochemical') ?? 0)
            + (float) ($damageInfo->get('@DamageStun') ?? 0);

        return $total > 0 ? $total : null;
    }

    /**
     * The name of the highest damage type (e.g. "Thermal", "Physical").
     */
    public function getDamageType(): ?string
    {
        $params = $this->getHazardParams();

        if ($params === null) {
            return null;
        }

        $damageInfo = $params->get('damagePerHit/DamageInfo');

        if ($damageInfo === null) {
            return null;
        }

        $values = array_filter([
            'Physical' => (float) ($damageInfo->get('@DamagePhysical') ?? 0),
            'Energy' => (float) ($damageInfo->get('@DamageEnergy') ?? 0),
            'Distortion' => (float) ($damageInfo->get('@DamageDistortion') ?? 0),
            'Thermal' => (float) ($damageInfo->get('@DamageThermal') ?? 0),
            'Biochemical' => (float) ($damageInfo->get('@DamageBiochemical') ?? 0),
            'Stun' => (float) ($damageInfo->get('@DamageStun') ?? 0),
        ], static fn (float $v): bool => $v > 0);

        if ($values === []) {
            return null;
        }

        arsort($values, SORT_NUMERIC);

        return array_key_first($values);
    }

    /**
     * Seconds between damage ticks.
     */
    public function getDamagePeriod(): ?float
    {
        $params = $this->getHazardParams();

        if ($params === null) {
            return null;
        }

        $value = $params->get('@damagePeriod');

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Total potential damage over the hazard zone's full lifetime.
     *
     * Calculated as: damagePerTick * (duration / damagePeriod).
     * Returns null if any required component is missing or period is zero.
     */
    public function getTotalDamage(): ?int
    {
        $perTick = $this->getDamagePerTick();
        $period = $this->getDamagePeriod();
        $duration = $this->getDuration();

        if ($perTick === null || $period === null || $period <= 0 || $duration === null) {
            return null;
        }

        return (int) round($perTick * ($duration / $period));
    }

    /**
     * How long the hazard zone persists in seconds.
     */
    public function getDuration(): ?float
    {
        $delay = $this->get('Components/SInteractionStateMachineParams/StateTypes/SInteractionStateType/States/SInteractionState[@StateName="Default"]/StateAutoChange/SStateAutoChange@Delay');

        return is_numeric($delay) ? (float) $delay : null;
    }

    /**
     * Effective radius of the hazard zone in meters.
     */
    public function getRadius(): ?float
    {
        $params = $this->getHazardParams();

        if ($params === null) {
            return null;
        }

        $radius = $params->get('hazardAreaShape/SSphereHazardAreaShapeParams@radius');

        return is_numeric($radius) ? (float) $radius : null;
    }

    /**
     * Whether the hazard zone damage bypasses shields.
     */
    public function getIgnoreShields(): ?bool
    {
        $params = $this->getHazardParams();

        if ($params === null) {
            return null;
        }

        $value = $params->get('@ignoreShields');

        return $value !== null ? (bool) $value : null;
    }
}
