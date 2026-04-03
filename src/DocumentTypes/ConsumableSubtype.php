<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use DOMElement;

/**
 * Represents an extracted ConsumableSubtype document.
 */
final class ConsumableSubtype extends RootDocument
{
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
        'RadiationAntidote',
        'ImpactResistanceKnockdownMask',
        'ImpactResistanceStaggerMask',
        'ImpactResistanceTwitchMask',
        'ImpactResistanceFlinchMask',
    ];

    private const array DEBUFFS = [
        'CognitiveImpair',
        'Dehydrating',
        'HyperMetabolic',
        'Atrophic',
    ];

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

    public function getTypeName(): string
    {
        return $this->documentElement?->getAttribute('typeName') ?? '';
    }

    public function getConsumableName(): string
    {
        return $this->documentElement?->getAttribute('consumableName') ?? '';
    }

    /**
     * @return array{Hunger: ?float, Thirst: ?float, BloodDrugLevel: ?float, BodyRadiation: ?float, Stun: ?float}
     */
    public function getStatModifications(): array
    {
        $stats = [
            'Hunger' => null,
            'Thirst' => null,
            'BloodDrugLevel' => null,
            'BodyRadiation' => null,
            'Stun' => null,
        ];

        foreach ($this->getEffects() as $effect) {
            if (($effect['type'] ?? null) !== 'ModifyActorStatus') {
                continue;
            }

            $statType = $effect['statType'] ?? null;
            if (is_string($statType) && array_key_exists($statType, $stats)) {
                $stats[$statType] = (float) ($effect['statPointChange'] ?? 0.0);
            }
        }

        return $stats;
    }

    public function getHealthChangePerMicroScu(): ?float
    {
        $total = 0.0;
        $hasHealth = false;

        foreach ($this->getEffects() as $effect) {
            if (($effect['type'] ?? null) !== 'Health') {
                continue;
            }

            $total += (float) ($effect['healthChange'] ?? 0.0);
            $hasHealth = true;
        }

        return $hasHealth ? $total : null;
    }

    /**
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    public function getBuffs(): ?array
    {
        $buffs = [];

        foreach ($this->getEffects() as $effect) {
            if (($effect['type'] ?? null) !== 'AddBuffEffect') {
                continue;
            }

            $buffType = $effect['buffType'] ?? null;
            if (! is_string($buffType) || ! in_array($buffType, self::BUFFS, true)) {
                continue;
            }

            $buffs[] = [
                'Type' => $buffType,
                'Duration' => $effect['duration'] ?? null,
            ];
        }

        return $buffs === [] ? null : $buffs;
    }

    /**
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    public function getDebuffs(): ?array
    {
        $debuffs = [];

        foreach ($this->getEffects() as $effect) {
            if (($effect['type'] ?? null) !== 'AddBuffEffect') {
                continue;
            }

            $buffType = $effect['buffType'] ?? null;
            if (! is_string($buffType) || ! in_array($buffType, self::DEBUFFS, true)) {
                continue;
            }

            $debuffs[] = [
                'Type' => $buffType,
                'Duration' => $effect['duration'] ?? null,
            ];
        }

        return $debuffs === [] ? null : $debuffs;
    }

    /**
     * @return array{
     *     PainMasking?: string[],
     *     CombatBuffs?: string[],
     *     StaminaEffects?: string[],
     *     ImpactResistance?: string[]
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

        foreach ($this->getEffects() as $effect) {
            if (($effect['type'] ?? null) !== 'AddBuffEffect') {
                continue;
            }

            $buffType = $effect['buffType'] ?? null;
            if (! is_string($buffType) || $buffType === '') {
                continue;
            }

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

        $hasEffects = ! empty($medical['PainMasking'])
            || ! empty($medical['CombatBuffs'])
            || ! empty($medical['StaminaEffects'])
            || ! empty($medical['ImpactResistance']);

        if (! $hasEffects) {
            return null;
        }

        return array_filter($medical, static fn (array $effects): bool => $effects !== []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEffects(): array
    {
        $effects = [];

        foreach ($this->getEffectElements() as $effectElement) {
            $effect = $this->parseEffect($effectElement);
            if ($effect !== null) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }

    /**
     * @return list<DOMElement>
     */
    private function getEffectElements(): array
    {
        $containers = $this->documentElement?->getElementsByTagName('effectsPerMicroSCU');
        $container = $containers?->item(0);

        if (! $container instanceof DOMElement) {
            return [];
        }

        $effects = [];

        foreach ($container->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $effects[] = $node;
            }
        }

        return $effects;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseEffect(DOMElement $effectElement): ?array
    {
        $effectType = $effectElement->getAttribute('__polymorphicType');
        if ($effectType === '') {
            $effectType = $effectElement->nodeName;
        }

        return match ($effectType) {
            'ConsumableEffectModifyActorStatus' => [
                'type' => 'ModifyActorStatus',
                'statType' => $effectElement->getAttribute('statType'),
                'statPointChange' => (float) $effectElement->getAttribute('statPointChange'),
                'statCooldownChange' => (float) ($effectElement->getAttribute('statCooldownChange') ?: 0),
            ],
            'ConsumableEffectHealth' => [
                'type' => 'Health',
                'healthChange' => (float) $effectElement->getAttribute('healthChange'),
            ],
            'ConsumableEffectAddBuffEffect' => [
                'type' => 'AddBuffEffect',
                'buffType' => $effectElement->getAttribute('buffType'),
                'duration' => $this->extractBuffDuration($effectElement),
            ],
            default => null,
        };
    }

    private function extractBuffDuration(DOMElement $effectElement): ?int
    {
        $durationNodes = $effectElement->getElementsByTagName('BuffDurationOverride');
        $durationNode = $durationNodes->item(0);

        if (! $durationNode instanceof DOMElement) {
            return null;
        }

        $duration = $durationNode->getAttribute('durationOverride');

        return $duration === '' ? null : (int) $duration;
    }
}
