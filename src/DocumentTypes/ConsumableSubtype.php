<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

/**
 * Represents a ConsumableSubtype definition from the cache
 * Contains nutrition values, buff/debuff effects, and durations
 */
final class ConsumableSubtype
{
    // Buff types that are positive effects
    private const array BUFFS = [
        'Energizing',
        'CognitiveBoost',
        'Hypertrophic',
        'HypoMetabolic',
        'StunRecoveryMask',
        'MoveSpeedMask',
        'WeaponSwayMask',
        'ADSEnterMask',
        'StaminaRegenMask',
        'StaminaPoolMask',
        'ImpactResistanceKnockdownMask',
        'ImpactResistanceStaggerMask',
        'ImpactResistanceTwitchMask',
        'ImpactResistanceFlinchMask',
    ];

    // Buff types that are negative effects
    private const array DEBUFFS = [
        'CognitiveImpair',
        'Dehydrating',
        'HyperMetabolic',
    ];

    // Medical effect categories
    private const array MEDICAL_PAIN_MASKING = [
        'HurtLocomotionMask',
        'PainGruntMask',
        'ArmsLockMask',
        'TraversalLockMask',
        'TraversalLockProneMask',
    ];

    private const array MEDICAL_COMBAT_BUFFS = [
        'StunRecoveryMask',
        'MoveSpeedMask',
        'WeaponSwayMask',
        'ADSEnterMask',
    ];

    private const array MEDICAL_STAMINA_EFFECTS = [
        'StaminaRegenMask',
        'StaminaPoolMask',
    ];

    private const array MEDICAL_IMPACT_RESISTANCE = [
        'ImpactResistanceKnockdownMask',
        'ImpactResistanceStaggerMask',
        'ImpactResistanceTwitchMask',
        'ImpactResistanceFlinchMask',
    ];

    public function __construct(
        private readonly string $uuid,
        private readonly array $data
    ) {}

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getTypeName(): string
    {
        return $this->data['typeName'] ?? '';
    }

    public function getConsumableName(): string
    {
        return $this->data['consumableName'] ?? '';
    }

    /**
     * Get all stat modifications (Hunger, Thirst, BloodDrugLevel)
     *
     * @return array{Hunger: ?float, Thirst: ?float, BloodDrugLevel: ?float}
     */
    public function getStatModifications(): array
    {
        $stats = [
            'Hunger' => null,
            'Thirst' => null,
            'BloodDrugLevel' => null,
        ];

        foreach ($this->data['effects'] ?? [] as $effect) {
            if ($effect['type'] === 'ModifyActorStatus') {
                $statType = $effect['statType'];

                if (array_key_exists($statType, $stats)) {
                    $stats[$statType] = $effect['statPointChange'];
                }
            }
        }

        return $stats;
    }

    /**
     * Get all buff effects (positive) with durations
     *
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    public function getBuffs(): ?array
    {
        $buffs = [];

        foreach ($this->data['effects'] ?? [] as $effect) {
            if ($effect['type'] === 'AddBuffEffect') {
                $buffType = $effect['buffType'];

                if (in_array($buffType, self::BUFFS, true)) {
                    $buffs[] = [
                        'Type' => $buffType,
                        'Duration' => $effect['duration'] ?? null,
                    ];
                }
            }
        }

        return empty($buffs) ? null : $buffs;
    }

    /**
     * Get all debuff effects (negative) with durations
     *
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    public function getDebuffs(): ?array
    {
        $debuffs = [];

        foreach ($this->data['effects'] ?? [] as $effect) {
            if ($effect['type'] === 'AddBuffEffect') {
                $buffType = $effect['buffType'];

                if (in_array($buffType, self::DEBUFFS, true)) {
                    $debuffs[] = [
                        'Type' => $buffType,
                        'Duration' => $effect['duration'] ?? null,
                    ];
                }
            }
        }

        return empty($debuffs) ? null : $debuffs;
    }

    /**
     * Get medical effects categorized by type
     *
     * @return array{
     *     PainMasking: string[],
     *     CombatBuffs: string[],
     *     StaminaEffects: string[],
     *     ImpactResistance: string[]
     * }|null
     */
    public function getMedicalEffects(): ?array
    {
        $medical = [
            'PainMasking' => [],
            'CombatBuffs' => [],
            'StaminaEffects' => [],
            'ImpactResistance' => [],
        ];

        foreach ($this->data['effects'] ?? [] as $effect) {
            if ($effect['type'] === 'AddBuffEffect') {
                $buffType = $effect['buffType'];

                if (in_array($buffType, self::MEDICAL_PAIN_MASKING, true)) {
                    $medical['PainMasking'][] = $buffType;
                }

                if (in_array($buffType, self::MEDICAL_COMBAT_BUFFS, true)) {
                    $medical['CombatBuffs'][] = $buffType;
                }

                if (in_array($buffType, self::MEDICAL_STAMINA_EFFECTS, true)) {
                    $medical['StaminaEffects'][] = $buffType;
                }

                if (in_array($buffType, self::MEDICAL_IMPACT_RESISTANCE, true)) {
                    $medical['ImpactResistance'][] = $buffType;
                }
            }
        }

        $hasEffects = ! empty($medical['PainMasking']) ||
                     ! empty($medical['CombatBuffs']) ||
                     ! empty($medical['StaminaEffects']) ||
                     ! empty($medical['ImpactResistance']);

        if (! $hasEffects) {
            return null;
        }

        return array_filter($medical, fn ($effects) => ! empty($effects));
    }

    /**
     * Get all effects (raw data)
     */
    public function getEffects(): array
    {
        return $this->data['effects'] ?? [];
    }
}
