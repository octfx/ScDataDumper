<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Format class for food consumables (non-medical)
 *
 * **Calculation Overview:**
 * - Nutrition (Hunger/Thirst/BloodDrugLevel): statPointChange × ratio × volume
 * - Health: healthChange × ratio × volume
 * - Buffs/Debuffs: Collected from ConsumableSubtype effects
 * - Medical Effects: Categorized buffs for medical use cases
 *
 * **BloodDrugLevel vs Food:**
 * - Food returns nutrition data including BloodDrugLevel
 * - Medical items have separate handling via Medical.php
 * - BloodDrugLevel represents drug dosage (limits medical consumable usage)
 *
 * **Example Item: MedPen (Hemozal)**
 * - Volume: 30 µSCU
 * - Coagulant subtype: healthChange=3.75, BloodDrugLevel=1
 * - Result: 112.5 health, 30 BloodDrugLevel
 */
class Food extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemConsumableParams';

    protected const array MEDICAL_SUBTYPES = ['medical', 'medpack'];

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        if ($this->isMedicalSubtype()) {
            return null;
        }

        return $this->buildConsumableData();
    }

    protected function buildConsumableData(): array
    {
        /** @var Element $consumable */
        $consumable = $this->get();

        // Get consumable subtypes and calculate nutrition/effects
        $defaultContents = $this->parseDefaultContents($consumable);
        $volume = $this->parseConsumableVolume($consumable);

        $nutrition = $this->calculateNutrition($defaultContents, $volume);
        $health = $this->calculateHealth($defaultContents, $volume);
        $buffs = $this->calculateBuffs($defaultContents);
        $debuffs = $this->calculateDebuffs($defaultContents);
        $medicalEffects = $this->calculateMedicalEffects($defaultContents);

        $containerType = $consumable->get('@containerTypeTag', '');
        if ($containerType === '') {
            $containerType = null;
        }

        return $this->removeNullValues([
            'Nutrition' => $nutrition,
            'Health' => $health,
            'Buffs' => $buffs,
            'Debuffs' => $debuffs,
            'MedicalEffects' => $medicalEffects,

            'Container' => [
                'Type' => $containerType,
                'Closed' => (bool) $consumable->get('@containerClosed', false),
                'CanBeReclosed' => (bool) $consumable->get('@canBeReclosed', false),
                'DiscardWhenConsumed' => (bool) $consumable->get('@discardWhenConsumed', false),
            ],

            'Consumption' => [
                'Volume' => $volume,
                'OneShotConsume' => (bool) $consumable->get('@oneShotConsume', false),
            ],
        ]);
    }

    /**
     * Calculate nutrition values from consumable subtypes
     *
     * **Calculation Formula:**
     * - PerMicroSCU = statPointChange × ratio
     * - Total = PerMicroSCU × volume
     *
     * **BloodDrugLevel Example (MedPen - Hemozal):**
     * - ConsumableSubtype UUID: 2e3fc0d3-be97-4c57-972e-526872e4bd56 (Coagulant)
     * - Cache data: {"type": "ModifyActorStatus", "statType": "BloodDrugLevel", "statPointChange": 1}
     * - Volume: 30 µSCU (from XML: <SMicroCargoUnit microSCU="30" />)
     * - Ratio: 1.0 (from XML: <ConsumableContent ratio="1" />)
     * - Calculation: 1.0 × 1.0 = 1.0 (PerMicroSCU)
     * - Calculation: 1.0 × 30 = 30.0 (Total BloodDrugLevel)
     *
     * BloodDrugLevel represents drug dosage that limits how much medical consumable
     * can be used before experiencing negative effects.
     *
     * @param  array  $defaultContents  Array of {uuid, ratio} - Each element references a ConsumableSubtype
     * @param  int  $volume  Volume in microSCU from <consumableVolume/SMicroCargoUnit/@microSCU>
     * @return array{Hunger: array|null, Thirst: array|null, BloodDrugLevel: array|null}|null
     */
    protected function calculateNutrition(array $defaultContents, int $volume): ?array
    {
        $hungerTotal = 0.0;
        $hungerPerMicroSCU = null;
        $thirstTotal = 0.0;
        $thirstPerMicroSCU = null;
        $bloodDrugLevelTotal = 0.0;
        $bloodDrugLevelPerMicroSCU = null;

        $consumableService = ServiceFactory::getConsumableSubtypeService();

        foreach ($defaultContents as $content) {
            $subtype = $consumableService->getByUuid($content['uuid']);

            if ($subtype === null) {
                continue;
            }

            $stats = $subtype->getStatModifications();
            $ratio = $content['ratio'];

            if ($stats['Hunger'] !== null) {
                $hungerPerMicroSCU = $stats['Hunger'] * $ratio;
                $hungerTotal += $hungerPerMicroSCU * $volume;
            }

            if ($stats['Thirst'] !== null) {
                $thirstPerMicroSCU = $stats['Thirst'] * $ratio;
                $thirstTotal += $thirstPerMicroSCU * $volume;
            }

            if ($stats['BloodDrugLevel'] !== null) {
                $bloodDrugLevelPerMicroSCU = $stats['BloodDrugLevel'] * $ratio;
                $bloodDrugLevelTotal += $bloodDrugLevelPerMicroSCU * $volume;
            }
        }

        $nutrition = [
            'Hunger' => $hungerPerMicroSCU !== null ? [
                'Total' => round($hungerTotal, 2),
                'PerMicroSCU' => round($hungerPerMicroSCU, 4),
            ] : null,
            'Thirst' => $thirstPerMicroSCU !== null ? [
                'Total' => round($thirstTotal, 2),
                'PerMicroSCU' => round($thirstPerMicroSCU, 4),
            ] : null,
            'BloodDrugLevel' => $bloodDrugLevelPerMicroSCU !== null ? [
                'Total' => round($bloodDrugLevelTotal, 2),
                'PerMicroSCU' => round($bloodDrugLevelPerMicroSCU, 4),
            ] : null,
        ];

        if ($nutrition['Hunger'] === null && $nutrition['Thirst'] === null && $nutrition['BloodDrugLevel'] === null) {
            return null;
        }

        return $nutrition;
    }

    /**
     * Calculate health change values from consumable subtypes
     *
     * **Calculation Formula:**
     * - PerMicroSCU = healthChange × ratio
     * - Total = PerMicroSCU × volume
     *
     * **Health Restoration Example (MedPen - Hemozal):**
     * - ConsumableSubtype UUID: 2e3fc0d3-be97-4c57-972e-526872e4bd56 (Coagulant)
     * - Cache data: {"type": "Health", "healthChange": 3.75}
     * - Volume: 30 µSCU (from XML: <SMicroCargoUnit microSCU="30" />)
     * - Ratio: 1.0 (from XML: <ConsumableContent ratio="1" />)
     * - Calculation: 3.75 × 1.0 = 3.75 (PerMicroSCU)
     * - Calculation: 3.75 × 30 = 112.5 (Total Health Restored)
     *
     * **SuperCoagulant Comparison:**
     * - Cache data: {"type": "Health", "healthChange": 8.0}
     * - Calculation: 8.0 × 1.0 = 8.0 (PerMicroSCU)
     * - Calculation: 8.0 × 30 = 240.0 (Total Health Restored)
     * - SuperCoagulant restores 2.13× more health for same BloodDrugLevel cost
     *
     * Health restoration is calculated independently of BloodDrugLevel - they're separate effects
     * from the same ConsumableSubtype.
     *
     * @param  array  $defaultContents  Array of {uuid, ratio} - Each element references a ConsumableSubtype
     * @param  int  $volume  Volume in microSCU from <consumableVolume/SMicroCargoUnit/@microSCU>
     * @return array{Total: float, PerMicroSCU: float}|null
     */
    protected function calculateHealth(array $defaultContents, int $volume): ?array
    {
        $healthTotal = 0.0;
        $healthPerMicroSCU = 0.0;
        $hasHealth = false;

        $consumableService = ServiceFactory::getConsumableSubtypeService();

        foreach ($defaultContents as $content) {
            $subtype = $consumableService->getByUuid($content['uuid']);

            if ($subtype === null) {
                continue;
            }

            $healthChange = $subtype->getHealthChangePerMicroScu();

            if ($healthChange !== null) {
                $perMicroScu = $healthChange * $content['ratio'];
                $healthPerMicroSCU += $perMicroScu;
                $healthTotal += $perMicroScu * $volume;
                $hasHealth = true;
            }
        }

        if (! $hasHealth) {
            return null;
        }

        return [
            'Total' => round($healthTotal, 2),
            'PerMicroSCU' => round($healthPerMicroSCU, 4),
        ];
    }

    /**
     * Calculate debuff effects from consumable subtypes
     *
     * Debuffs are negative effects like CognitiveImpair, Dehydrating, HyperMetabolic, Atrophic.
     * Retrieved from ConsumableSubtype cache where type = "AddBuffEffect" and
     * buffType is in DEBUFFS constant array.
     *
     * **Data Flow:**
     * 1. XML: <ConsumableContent consumableSubtype="UUID" />
     * 2. Cache: Lookup UUID → get ConsumableSubtype
     * 3. Effects: Get debuffs from ConsumableSubtype
     * 4. Merge: Combine debuffs from all defaultContents
     *
     * @param  array  $defaultContents  Array of {uuid, ratio} - Each element references a ConsumableSubtype
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    protected function calculateBuffs(array $defaultContents): ?array
    {
        $buffs = [];
        $consumableService = ServiceFactory::getConsumableSubtypeService();

        foreach ($defaultContents as $content) {
            $subtype = $consumableService->getByUuid($content['uuid']);

            if ($subtype === null) {
                continue;
            }

            $subtypeBuffs = $subtype->getBuffs();

            if ($subtypeBuffs !== null) {
                $buffs = array_merge($buffs, $subtypeBuffs);
            }
        }

        return empty($buffs) ? null : $buffs;
    }

    /**
     * Calculate buff effects from consumable subtypes
     *
     * Buffs are positive effects like Energizing, CognitiveBoost, Hypertrophic, etc.
     * Retrieved from ConsumableSubtype cache where type = "AddBuffEffect" and
     * buffType is in BUFFS constant array.
     *
     * **Data Flow:**
     * 1. XML: <ConsumableContent consumableSubtype="UUID" />
     * 2. Cache: Lookup UUID → get ConsumableSubtype
     * 3. Effects: Get buffs from ConsumableSubtype
     * 4. Merge: Combine buffs from all defaultContents
     *
     * @param  array  $defaultContents  Array of {uuid, ratio} - Each element references a ConsumableSubtype
     * @return array<array{Type: string, Duration: ?int}>|null
     */
    protected function calculateDebuffs(array $defaultContents): ?array
    {
        $debuffs = [];
        $consumableService = ServiceFactory::getConsumableSubtypeService();

        foreach ($defaultContents as $content) {
            $subtype = $consumableService->getByUuid($content['uuid']);

            if ($subtype === null) {
                continue;
            }

            $subtypeDebuffs = $subtype->getDebuffs();

            if ($subtypeDebuffs !== null) {
                $debuffs = array_merge($debuffs, $subtypeDebuffs);
            }
        }

        return empty($debuffs) ? null : $debuffs;
    }

    /**
     * Calculate medical effects from consumable subtypes
     *
     * Medical effects are specialized buff categories used by medical consumables.
     * Categories include:
     * - PainMasking: HurtLocomotionMask, PainGruntMask, ArmsLockMask, etc.
     * - CombatBuffs: StunRecoveryMask, MoveSpeedMask, WeaponSwayMask, ADSEnterMask
     * - StaminaEffects: StaminaRegenMask, StaminaPoolMask
     * - ImpactResistance: ImpactResistanceKnockdownMask, StaggerMask, TwitchMask, FlinchMask
     *
     * **Data Flow:**
     * 1. XML: <ConsumableContent consumableSubtype="UUID" />
     * 2. Cache: Lookup UUID → get ConsumableSubtype
     * 3. Effects: Get medical effects from ConsumableSubtype
     * 4. Merge: Combine effects from all defaultContents by category
     * 5. Unique: Remove duplicate buff types within each category
     *
     * @param  array  $defaultContents  Array of {uuid, ratio} - Each element references a ConsumableSubtype
     * @return array{
     *     PainMasking: string[],
     *     CombatBuffs: string[],
     *     StaminaEffects: string[],
     *     ImpactResistance: string[]
     * }|null
     */
    protected function calculateMedicalEffects(array $defaultContents): ?array
    {
        $medical = [
            'PainMasking' => [],
            'CombatBuffs' => [],
            'StaminaEffects' => [],
            'ImpactResistance' => [],
        ];

        $consumableService = ServiceFactory::getConsumableSubtypeService();

        foreach ($defaultContents as $content) {
            $subtype = $consumableService->getByUuid($content['uuid']);

            if ($subtype === null) {
                continue;
            }

            $subtypeMedical = $subtype->getMedicalEffects();

            if ($subtypeMedical !== null) {
                foreach ($subtypeMedical as $category => $effects) {
                    $medical[$category] = array_unique(array_merge($medical[$category], $effects));
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
     * Parse defaultContents to extract consumable subtype UUIDs and ratios
     *
     * **XML Structure:**
     * ```xml
     * <defaultContents>
     *   <ConsumableContent consumableSubtype="UUID" ratio="1.0" />
     *   <ConsumableContent consumableSubtype="UUID" ratio="0.5" />
     * </defaultContents>
     * ```
     *
     * The ratio allows mixing multiple consumable subtypes in a single item.
     * For example, a medicate-all pen might contain multiple subtypes with different ratios.
     *
     * **Data Flow:**
     * 1. Get <defaultContents> node from SCItemConsumableParams
     * 2. Iterate through <ConsumableContent> children
     * 3. Extract @consumableSubtype (UUID) and @ratio values
     * 4. Return array of {uuid, ratio} pairs
     *
     * @return array<array{uuid: string, ratio: float}>
     */
    protected function parseDefaultContents(Element $consumable): array
    {
        $contents = [];
        $defaultContentsNode = $consumable->get('defaultContents');

        if (! $defaultContentsNode instanceof Element) {
            return [];
        }

        foreach ($defaultContentsNode->children() as $contentNode) {
            if ($contentNode->nodeName === 'ConsumableContent') {
                $uuid = $contentNode->get('@consumableSubtype', '');
                $ratio = (float) $contentNode->get('@ratio', 1.0);

                if ($uuid !== '') {
                    $contents[] = [
                        'uuid' => $uuid,
                        'ratio' => $ratio,
                    ];
                }
            }
        }

        return $contents;
    }

    /**
     * Parse consumableVolume to get microSCU value
     *
     * **XML Structure:**
     * ```xml
     * <SCItemConsumableParams>
     *   <consumableVolume>
     *     <SMicroCargoUnit microSCU="30" />
     *   </consumableVolume>
     * </SCItemConsumableParams>
     * ```
     *
     * This volume is used in all nutrition and health calculations to determine
     * the total effect of consuming the entire item.
     *
     * **Example Values:**
     * - MedPen (Hemozal): 30 µSCU → 112.5 total health
     * - Simple fruit: 1 µSCU → small nutrition values
     * - Canned food: 500-1000 µSCU → larger nutrition values
     */
    protected function parseConsumableVolume(Element $consumable): int
    {
        $volumeNode = $consumable->get('consumableVolume');

        if (! $volumeNode instanceof Element) {
            return 0;
        }

        $microSCUNode = $volumeNode->get('SMicroCargoUnit');

        if (! $microSCUNode instanceof Element) {
            return 0;
        }

        return (int) $microSCUNode->get('@microSCU', 0);
    }

    protected function isMedicalSubtype(): bool
    {
        $subType = (string) $this->get('Components/SAttachableComponentParams/AttachDef@SubType', '');

        if ($subType === '') {
            return false;
        }

        return in_array(strtolower($subType), self::MEDICAL_SUBTYPES, true);
    }
}
