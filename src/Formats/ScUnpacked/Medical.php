<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

/**
 * Format class for medical consumables (MedPens, medical items)
 *
 * **Medical Item Types:**
 * - MedPens (Hemozal, SuperCoagulant, Adrenaline, etc.)
 * - Medical packs
 * - Medical injections
 *
 * **Calculation Inheritance:**
 * All calculations are inherited from Food.php:
 * - calculateNutrition(): BloodDrugLevel calculation
 * - calculateHealth(): Health restoration calculation
 * - calculateBuffs(): Positive effects
 * - calculateDebuffs(): Negative effects
 * - calculateMedicalEffects(): Medical-specific effects
 *
 * **Example Items:**
 * - MedPen (Hemozal): 112.5 health, 30 BloodDrugLevel
 * - MedPen (SuperCoagulant): 240 health, 30 BloodDrugLevel
 * - MedPen (Adrenaline): Buffs, no direct health
 */
final class Medical extends Food
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        if (! $this->isMedicalSubtype()) {
            return null;
        }

        return $this->buildConsumableData();
    }
}
