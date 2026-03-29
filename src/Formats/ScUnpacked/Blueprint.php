<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class Blueprint extends BaseFormat
{
    protected ?string $elementKey = 'blueprint/CraftingBlueprint';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $blueprint = $this->get();
        $uuid = $this->item?->getUuid();
        $slots = $this->buildSlots(
            $blueprint->get('tiers/CraftingBlueprintTier/recipe/CraftingRecipe/costs/CraftingRecipeCosts/mandatoryCost')
        );

        return $this->removeNulls([
            'uuid' => $uuid,
            'key' => $this->item?->getClassName(),
            'kind' => 'creation',
            'category_uuid' => $blueprint->get('@category'),
            'output' => $this->buildOutput($blueprint->get('processSpecificData/CraftingProcess_Creation/OutputEntity')),
            'craft_time_seconds' => $this->buildCraftTimeSeconds(
                $blueprint->get('tiers/CraftingBlueprintTier/recipe/CraftingRecipe/costs/CraftingRecipeCosts/craftTime/TimeValue_Partitioned')
            ),
            'availability' => $this->buildAvailability($uuid),
            'quality_sensitive' => $this->isQualitySensitive($slots) ? true : null,
            'slots' => $slots,
        ]);
    }

    private function buildOutput(?Element $outputEntity): array
    {
        if ($outputEntity === null) {
            return [];
        }

        $attachDef = $outputEntity->get('Components/SAttachableComponentParams/AttachDef');

        return $this->removeNulls([
            'uuid' => $this->readAttribute($outputEntity, '__ref'),
            'class' => $this->extractClassNameFromPath($this->readAttribute($outputEntity, '__path')),
            'type' => $this->readAttribute($attachDef, 'Type'),
            'subtype' => $this->readAttribute($attachDef, 'SubType'),
            'grade' => $this->readAttribute($attachDef, 'Grade'),
            'name' => $this->readItemName($outputEntity),
        ]);
    }

    private function buildCraftTimeSeconds(?Element $timeValue): int|float|null
    {
        if ($timeValue === null) {
            return null;
        }

        $days = (float) ($timeValue->get('@days') ?? 0);
        $hours = (float) ($timeValue->get('@hours') ?? 0);
        $minutes = (float) ($timeValue->get('@minutes') ?? 0);
        $seconds = (float) ($timeValue->get('@seconds') ?? 0);

        return $this->normalizeNumber(
            ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds
        );
    }

    /**
     * @return array{default: bool, reward_pools: list<array{uuid: string, key: string}>}
     */
    private function buildAvailability(?string $uuid): array
    {
        if ($uuid === null || $uuid === '') {
            return [
                'default' => false,
                'reward_pools' => [],
            ];
        }

        try {
            $blueprintService = ServiceFactory::getBlueprintService();
        } catch (RuntimeException) {
            return [
                'default' => false,
                'reward_pools' => [],
            ];
        }

        return [
            'default' => $blueprintService->isDefaultBlueprint($uuid),
            'reward_pools' => $blueprintService->getRewardPoolsForBlueprint($uuid),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSlots(?Element $mandatoryCost): array
    {
        if ($mandatoryCost === null) {
            return [];
        }

        $slots = [];

        foreach ($mandatoryCost->children() as $child) {
            array_push($slots, ...$this->extractSlots($child));
        }

        return $slots;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractSlots(Element $node): array
    {
        if ($node->nodeName === 'CraftingCost_Select') {
            $nameInfo = $node->get('nameInfo');

            if ($nameInfo instanceof Element && ! $this->isPlaceholderSlotContainer($nameInfo)) {
                $slot = $this->buildSlot($node);

                return $slot === null ? [] : [$slot];
            }

            return $this->buildSlots($node->get('options') ?? $node);
        }

        $input = $this->buildInput($node);

        if ($input !== null) {
            return [[
                'inputs' => [$input],
            ]];
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSlot(Element $slot): ?array
    {
        $nameInfo = $slot->get('nameInfo');

        if (! $nameInfo instanceof Element) {
            return null;
        }

        $inputs = $this->collectInputs($slot->get('options'));

        if ($inputs === []) {
            return null;
        }

        $statModifiers = $this->buildStatModifiers($slot);

        return $this->removeNulls([
            'slot_key' => $this->readAttribute($nameInfo, 'debugName'),
            'slot_name' => $this->translate($this->readAttribute($nameInfo, 'displayName'))
                ?? $this->readAttribute($nameInfo, 'debugName'),
            'inputs' => $inputs,
            'stat_modifiers' => $statModifiers === [] ? null : $statModifiers,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectInputs(?Element $container): array
    {
        if ($container === null) {
            return [];
        }

        $inputs = [];

        foreach ($container->children() as $child) {
            $input = $this->buildInput($child);

            if ($input !== null) {
                $inputs[] = $input;

                continue;
            }

            if ($child->nodeName === 'CraftingCost_Select') {
                array_push($inputs, ...$this->collectInputs($child->get('options') ?? $child));

                continue;
            }

            array_push($inputs, ...$this->collectInputs($child));
        }

        return $inputs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildInput(Element $cost): ?array
    {
        return match ($cost->nodeName) {
            'CraftingCost_Item' => $this->buildItemInput($cost),
            'CraftingCost_Resource' => $this->buildResourceInput($cost),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItemInput(Element $cost): array
    {
        $inputEntity = $cost->get('InputEntity');

        return $this->removeNulls([
            'kind' => 'item',
            'uuid' => $this->readAttribute($inputEntity, '__ref')
                ?? $this->readAttribute($cost, 'entityClass'),
            'name' => $this->readItemName($inputEntity),
            'quantity' => $this->normalizeNumber($cost->get('@quantity')),
            'min_quality' => $this->normalizeNumber($cost->get('@minQuality')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResourceInput(Element $cost): array
    {
        $resourceType = $cost->get('ResourceType');
        $quantityScu = Item::convertToScu($cost->get('quantity'));

        if ($quantityScu !== null) {
            // XML quantities are decimal strings, so round after applying multipliers to avoid float artifacts.
            $quantityScu = round($quantityScu * $this->readResourceQuantityMultiplier($cost), 9);
        }

        return $this->removeNulls([
            'kind' => 'resource',
            'uuid' => $this->readAttribute($resourceType, '__ref')
                ?? $this->readAttribute($cost, 'resource'),
            'name' => $this->translate($this->readAttribute($resourceType, 'displayName')),
            'quantity_scu' => $quantityScu,
            'min_quality' => $this->normalizeNumber($cost->get('@minQuality')),
        ]);
    }

    private function isPlaceholderSlotContainer(Element $nameInfo): bool
    {
        $slotKey = $this->readAttribute($nameInfo, 'debugName')
            ?? $this->readAttribute($nameInfo, 'displayName');

        return is_string($slotKey) && strtoupper($slotKey) === 'ASPECTS';
    }

    private function readResourceQuantityMultiplier(Element $cost): float
    {
        $context = $cost->get('context/CraftingCostContext_QuantityMultiplier');

        if (! $context instanceof Element) {
            return 1.0;
        }

        foreach (['quantityMultiplier', 'multiplier', 'value'] as $attribute) {
            $value = $context->get('@'.$attribute);

            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                return (float) $value;
            }
        }

        $attributes = $context->getNode()->attributes;

        if ($attributes === null) {
            return 1.0;
        }

        foreach ($attributes as $attribute) {
            if (is_numeric($attribute->nodeValue)) {
                return (float) $attribute->nodeValue;
            }
        }

        return 1.0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStatModifiers(Element $slot): array
    {
        $modifiers = $slot->get(
            'context/CraftingCostContext_ResultGameplayPropertyModifiers/gameplayPropertyModifiers/CraftingGameplayPropertyModifiers_List/gameplayPropertyModifiers'
        );

        if (! $modifiers instanceof Element) {
            return [];
        }

        $results = [];

        foreach ($modifiers->children() as $modifier) {
            if ($modifier->nodeName !== 'CraftingGameplayPropertyModifierCommon') {
                continue;
            }

            $formatted = $this->buildStatModifier($modifier);

            if ($formatted !== null) {
                $results[] = $formatted;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildStatModifier(Element $modifier): ?array
    {
        $property = $modifier->get('GameplayProperty');
        $valueRange = $modifier->get('valueRanges/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $modifier->get('valueRange/CraftingGameplayPropertyModifierValueRange_Linear');

        $formatted = $this->removeNulls([
            'property_uuid' => $this->readAttribute($property, '__ref')
                ?? $this->readAttribute($modifier, 'gameplayPropertyRecord'),
            'property_key' => $this->extractGameplayPropertyKey($property),
            'quality_range' => $this->removeNulls([
                'min' => $this->normalizeNumber(
                    $this->readAttribute($valueRange, 'startQuality')
                    ?? $this->readAttribute($valueRange, 'minInputValue')
                ),
                'max' => $this->normalizeNumber(
                    $this->readAttribute($valueRange, 'endQuality')
                    ?? $this->readAttribute($valueRange, 'maxInputValue')
                ),
            ]),
            'modifier_range' => $this->removeNulls([
                'at_min_quality' => $this->normalizeNumber(
                    $this->readAttribute($valueRange, 'modifierAtStart')
                    ?? $this->readAttribute($valueRange, 'minOutputMultiplier')
                ),
                'at_max_quality' => $this->normalizeNumber(
                    $this->readAttribute($valueRange, 'modifierAtEnd')
                    ?? $this->readAttribute($valueRange, 'maxOutputMultiplier')
                ),
            ]),
        ]);

        return $formatted === [] ? null : $formatted;
    }

    private function readItemName(?Element $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        $name = $entity->get('Components/SAttachableComponentParams/AttachDef/Localization/English@Name')
            ?? $entity->get('Components/SAttachableComponentParams/AttachDef/Localization@Name');

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $this->translate($name);
    }

    private function extractClassNameFromPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    private function extractGameplayPropertyKey(?Element $property): ?string
    {
        $path = $this->readAttribute($property, '__path');

        if ($path === null) {
            return null;
        }

        $key = strtolower(pathinfo($path, PATHINFO_FILENAME));

        if (str_starts_with($key, 'gpp_')) {
            $key = substr($key, 4);
        }

        return $key === '' ? null : $key;
    }

    private function readAttribute(?Element $element, string $name): ?string
    {
        if ($element === null) {
            return null;
        }

        $value = $element->getNode()->attributes?->getNamedItem($name)?->nodeValue;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function translate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! str_starts_with($value, '@')) {
            return $value;
        }

        try {
            return ServiceFactory::getLocalizationService()->getTranslation($value);
        } catch (RuntimeException) {
            return $value;
        }
    }

    private function normalizeNumber(mixed $value): int|float|null
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $number = (float) $value;

        if ((float) ((int) $number) === $number) {
            return (int) $number;
        }

        return $number;
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     */
    private function isQualitySensitive(array $slots): bool
    {
        foreach ($slots as $slot) {
            if (($slot['stat_modifiers'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function removeNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_is_list($value)
                    ? array_map(
                        static fn (mixed $item): mixed => is_array($item) ? self::removeNestedNulls($item) : $item,
                        $value
                    )
                    : self::removeNestedNulls($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function removeNestedNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_is_list($value)
                    ? array_map(
                        static fn (mixed $item): mixed => is_array($item) ? self::removeNestedNulls($item) : $item,
                        $value
                    )
                    : self::removeNestedNulls($value);
            }
        }

        return $data;
    }
}
