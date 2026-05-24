<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGameplayPropertyModifier;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class CraftingGameplayPropertyModifierTest extends ScDataTestCase
{
    private const PROPERTY_UUID = 'cfc129ce-488a-46f2-92f7-9272cd0cfdfb';

    public function test_get_value_range_type_returns_linear_for_single_segment(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="1000" modifierAtStart="0.9" modifierAtEnd="1.1" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        self::assertSame('linear', $modifier->getValueRangeType());
    }

    public function test_get_value_range_type_returns_linear_for_multi_segment(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="499" modifierAtStart="0.95" modifierAtEnd="1" />
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="500" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.05" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        self::assertSame('linear', $modifier->getValueRangeType());
    }

    public function test_get_value_range_type_returns_linear_integer_additive(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="0" endQuality="399" additiveModifierAtStart="-1" additiveModifierAtEnd="-1" />
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="400" endQuality="899" additiveModifierAtStart="0" additiveModifierAtEnd="0" />
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="900" endQuality="1000" additiveModifierAtStart="1" additiveModifierAtEnd="1" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        self::assertSame('linear_integer_additive', $modifier->getValueRangeType());
    }

    public function test_get_value_range_type_returns_unknown_for_empty_modifier(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" />
        XML);

        self::assertSame('unknown', $modifier->getValueRangeType());
    }

    public function test_get_value_segments_returns_single_linear_segment(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="1000" modifierAtStart="0.925" modifierAtEnd="1.075" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        $segments = $modifier->getValueSegments();

        self::assertCount(1, $segments);
        self::assertSame('linear', $segments[0]['type']);
        self::assertSame(0.0, $segments[0]['quality_min']);
        self::assertSame(1000.0, $segments[0]['quality_max']);
        self::assertSame(0.925, $segments[0]['modifier_at_start']);
        self::assertSame(1.075, $segments[0]['modifier_at_end']);
    }

    public function test_get_value_segments_returns_multiple_linear_segments(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="0" endQuality="499" modifierAtStart="0.95" modifierAtEnd="1" />
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="500" endQuality="1000" modifierAtStart="1" modifierAtEnd="1.05" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        $segments = $modifier->getValueSegments();

        self::assertCount(2, $segments);

        self::assertSame('linear', $segments[0]['type']);
        self::assertSame(0.0, $segments[0]['quality_min']);
        self::assertSame(499.0, $segments[0]['quality_max']);
        self::assertSame(0.95, $segments[0]['modifier_at_start']);
        self::assertSame(1.0, $segments[0]['modifier_at_end']);

        self::assertSame('linear', $segments[1]['type']);
        self::assertSame(500.0, $segments[1]['quality_min']);
        self::assertSame(1000.0, $segments[1]['quality_max']);
        self::assertSame(1.0, $segments[1]['modifier_at_start']);
        self::assertSame(1.05, $segments[1]['modifier_at_end']);
    }

    public function test_get_value_segments_returns_integer_additive_segments(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="0" endQuality="399" additiveModifierAtStart="-1" additiveModifierAtEnd="-1" />
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="400" endQuality="899" additiveModifierAtStart="0" additiveModifierAtEnd="0" />
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="900" endQuality="1000" additiveModifierAtStart="1" additiveModifierAtEnd="1" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        $segments = $modifier->getValueSegments();

        self::assertCount(3, $segments);

        self::assertSame('linear_integer_additive', $segments[0]['type']);
        self::assertSame(0.0, $segments[0]['quality_min']);
        self::assertSame(399.0, $segments[0]['quality_max']);
        self::assertSame(-1.0, $segments[0]['additive_at_start']);
        self::assertSame(-1.0, $segments[0]['additive_at_end']);
        self::assertArrayNotHasKey('modifier_at_start', $segments[0]);

        self::assertSame('linear_integer_additive', $segments[1]['type']);
        self::assertSame(400.0, $segments[1]['quality_min']);
        self::assertSame(0.0, $segments[1]['additive_at_start']);

        self::assertSame('linear_integer_additive', $segments[2]['type']);
        self::assertSame(900.0, $segments[2]['quality_min']);
        self::assertSame(1.0, $segments[2]['additive_at_start']);
    }

    public function test_get_value_segments_returns_empty_for_empty_modifier(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" />
        XML);

        self::assertSame([], $modifier->getValueSegments());
    }

    public function test_get_value_range_falls_through_to_integer_additive(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive startQuality="0" endQuality="1000" additiveModifierAtStart="-1" additiveModifierAtEnd="1" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        // getValueRange() should fall through to IntegerAdditive
        $range = $modifier->getValueRange();
        self::assertNotNull($range);

        // Quality methods should work (same attribute names)
        self::assertSame(0.0, $modifier->getQualityMin());
        self::assertSame(1000.0, $modifier->getQualityMax());

        // Modifier methods should return null (no @modifierAtStart/@modifierAtEnd)
        self::assertNull($modifier->getModifierAtMinQuality());
        self::assertNull($modifier->getModifierAtMaxQuality());
    }

    public function test_existing_linear_methods_unchanged(): void
    {
        $modifier = $this->createModifier(<<<'XML'
        <CraftingGameplayPropertyModifierCommon gameplayPropertyRecord="cfc129ce-488a-46f2-92f7-9272cd0cfdfb">
          <valueRanges>
            <CraftingGameplayPropertyModifierValueRange_Linear startQuality="100" endQuality="900" modifierAtStart="0.8" modifierAtEnd="1.2" />
          </valueRanges>
        </CraftingGameplayPropertyModifierCommon>
        XML);

        self::assertSame(self::PROPERTY_UUID, $modifier->getPropertyReference());
        self::assertSame(100.0, $modifier->getQualityMin());
        self::assertSame(900.0, $modifier->getQualityMax());
        self::assertSame(0.8, $modifier->getModifierAtMinQuality());
        self::assertSame(1.2, $modifier->getModifierAtMaxQuality());
    }

    private function createModifier(string $xml): CraftingGameplayPropertyModifier
    {
        $doc = new CraftingGameplayPropertyModifier;
        $doc->loadXML($xml);

        return $doc;
    }
}
