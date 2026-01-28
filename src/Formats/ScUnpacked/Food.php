<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

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
     * @param  array  $defaultContents  Array of {uuid, ratio}
     * @param  int  $volume  Volume in microSCU
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
     * @param  array  $defaultContents  Array of {uuid, ratio}
     * @param  int  $volume  Volume in microSCU
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
     * Calculate buff effects from consumable subtypes
     *
     * @param  array  $defaultContents  Array of {uuid, ratio}
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
     * Calculate debuff effects from consumable subtypes
     *
     * @param  array  $defaultContents  Array of {uuid, ratio}
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
     * @param  array  $defaultContents  Array of {uuid, ratio}
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
