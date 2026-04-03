<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\ConsumableSubtype;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ConsumableSubtypeTest extends ScDataTestCase
{
    public function test_parses_effects_from_extracted_xml(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/entities/consumables/subtypes/test_consumable.xml',
            <<<'XML'
            <ConsumableSubtype.TestConsumable
              __type="ConsumableSubtype"
              __ref="11111111-2222-3333-4444-555555555555"
              __path="libs/foundry/records/entities/consumables/subtypes/test_consumable.xml"
              typeName="Medical"
              consumableName="Test Stim">
              <effectsPerMicroSCU>
                <ConsumableEffectModifyActorStatus statType="Hunger" statPointChange="1.5" statCooldownChange="0.25" />
                <ConsumableEffectModifyActorStatus statType="BodyRadiation" statPointChange="-0.5" />
                <ConsumableEffectModifyActorStatus statType="Stun" statPointChange="2.25" />
                <ConsumableEffectHealth healthChange="3.75" />
                <ConsumableEffectHealth healthChange="1.25" />
                <ConsumableEffectAddBuffEffect buffType="Energizing">
                  <BuffDurationOverride durationOverride="45" />
                </ConsumableEffectAddBuffEffect>
                <ConsumableEffectAddBuffEffect buffType="Dehydrating">
                  <BuffDurationOverride durationOverride="60" />
                </ConsumableEffectAddBuffEffect>
                <ConsumableEffectAddBuffEffect buffType="MoveSpeedMask" />
                <ConsumableEffectAddBuffEffect buffType="ImpactResistanceKnockdownMask" />
              </effectsPerMicroSCU>
            </ConsumableSubtype.TestConsumable>
            XML
        );

        $document = new ConsumableSubtype;
        $document->load($path);

        self::assertSame('11111111-2222-3333-4444-555555555555', $document->getUuid());
        self::assertSame('Medical', $document->getTypeName());
        self::assertSame('Test Stim', $document->getConsumableName());
        self::assertSame(
            [
                'Hunger' => 1.5,
                'Thirst' => null,
                'BloodDrugLevel' => null,
                'BodyRadiation' => -0.5,
                'Stun' => 2.25,
            ],
            $document->getStatModifications()
        );
        self::assertSame(5.0, $document->getHealthChangePerMicroScu());
        self::assertSame(
            [
                ['Type' => 'Energizing', 'Duration' => 45],
                ['Type' => 'MoveSpeedMask', 'Duration' => null],
                ['Type' => 'ImpactResistanceKnockdownMask', 'Duration' => null],
            ],
            $document->getBuffs()
        );
        self::assertSame(
            [
                ['Type' => 'Dehydrating', 'Duration' => 60],
            ],
            $document->getDebuffs()
        );
        self::assertSame(
            [
                'CombatBuffs' => ['MoveSpeedMask'],
                'ImpactResistance' => ['ImpactResistanceKnockdownMask'],
            ],
            $document->getMedicalEffects()
        );
        self::assertCount(9, $document->getEffects());
    }
}
