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
                  <BuffValueOverride valueOverride="1.5" />
                </ConsumableEffectAddBuffEffect>
                <ConsumableEffectAddBuffEffect buffType="Dehydrating">
                  <BuffDurationOverride durationOverride="60" />
                </ConsumableEffectAddBuffEffect>
                <ConsumableEffectAddBuffEffect buffType="MoveSpeedMask" />
                <ConsumableEffectAddBuffEffect buffType="ImpactResistanceKnockdownMask" />
                <ConsumableEffectResource effectDescription="@Desc_Resource" consumableResourceType="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee" amount="2.5" />
                <ConsumableEffectGas effectDescription="@Desc_Gas">
                  <gasMass Gas="Oxygen" Mass="0.05" />
                </ConsumableEffectGas>
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
        self::assertCount(11, $document->getEffects());
    }

    public function test_parses_resource_and_gas_effects(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/entities/consumables/subtypes/test_consumable_49.xml',
            <<<'XML'
            <ConsumableSubtype.TestConsumable49
              __type="ConsumableSubtype"
              __ref="22222222-3333-4444-5555-666666666666"
              __path="libs/foundry/records/entities/consumables/subtypes/test_consumable_49.xml"
              typeName="Food"
              consumableName="Test Ration">
              <effectsPerMicroSCU>
                <ConsumableEffectResource effectDescription="@Desc_Iron" consumableResourceType="11111111-1111-1111-1111-111111111111" amount="1.25" />
                <ConsumableEffectResource effectDescription="@Desc_Carbon" consumableResourceType="22222222-2222-2222-2222-222222222222" amount="0.5" />
                <ConsumableEffectGas effectDescription="@Desc_Methane">
                  <gasMass Gas="Methane" Mass="0.025" />
                </ConsumableEffectGas>
              </effectsPerMicroSCU>
            </ConsumableSubtype.TestConsumable49>
            XML
        );

        $document = new ConsumableSubtype;
        $document->load($path);

        self::assertSame(
            [
                [
                    'consumableResourceType' => '11111111-1111-1111-1111-111111111111',
                    'amount' => 1.25,
                ],
                [
                    'consumableResourceType' => '22222222-2222-2222-2222-222222222222',
                    'amount' => 0.5,
                ],
            ],
            $document->getResourceEffects()
        );
        self::assertSame(
            [
                ['gasMass' => ['gas' => 'Methane', 'mass' => 0.025]],
            ],
            $document->getGasEffects()
        );
    }

    public function test_resource_and_gas_effects_empty_when_absent(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/entities/consumables/subtypes/test_consumable_no_tint.xml',
            <<<'XML'
            <ConsumableSubtype.NoTint
              __type="ConsumableSubtype"
              __ref="33333333-3333-4444-5555-777777777777"
              __path="libs/foundry/records/entities/consumables/subtypes/test_consumable_no_tint.xml"
              typeName="Food"
              consumableName="Plain Water">
              <effectsPerMicroSCU>
                <ConsumableEffectHealth healthChange="1.0" />
              </effectsPerMicroSCU>
            </ConsumableSubtype.NoTint>
            XML
        );

        $document = new ConsumableSubtype;
        $document->load($path);

        self::assertSame([], $document->getResourceEffects());
        self::assertSame([], $document->getGasEffects());
    }
}
