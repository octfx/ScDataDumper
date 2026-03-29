<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class Blueprint extends BaseFormat
{
    private const PLACEHOLDER_TRANSLATION = '<= PLACEHOLDER =>';

    protected ?string $elementKey = 'blueprint/CraftingBlueprint';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $blueprint = $this->get();
        $uuid = $this->item?->getUuid();

        return $this->removeNulls([
            'uuid' => $uuid,
            'key' => $this->item?->getClassName(),
            'kind' => 'creation',
            'category_uuid' => $blueprint->get('@category'),
            'output' => $this->buildOutput($blueprint->get('processSpecificData/CraftingProcess_Creation/OutputEntity')),
            'availability' => $this->buildAvailability($uuid),
            'tiers' => $this->buildTiers($blueprint->get('tiers')),
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
    private function collectTierElements(?Element $tiers): array
    {
        if ($tiers === null) {
            return [];
        }

        $results = [];

        foreach ($tiers->children() as $tier) {
            if ($tier->nodeName === 'CraftingBlueprintTier') {
                $results[] = $tier;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTiers(?Element $tiers): array
    {
        $results = [];

        foreach ($this->collectTierElements($tiers) as $index => $tier) {
            $results[] = $this->removeNulls([
                'tier_index' => $index,
                'craft_time_seconds' => $this->buildCraftTimeSeconds(
                    $tier->get('recipe/CraftingRecipe/costs/CraftingRecipeCosts/craftTime/TimeValue_Partitioned')
                ),
                'requirements' => $this->buildRequirements($tier->get('recipe/CraftingRecipe/costs/CraftingRecipeCosts/mandatoryCost')),
            ]);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRequirements(?Element $mandatoryCost): ?array
    {
        if ($mandatoryCost === null) {
            return null;
        }

        return $this->buildRootNode($mandatoryCost);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRootNode(Element $mandatoryCost): array
    {
        return [
            'kind' => 'root',
            'children' => $this->buildCostChildren($mandatoryCost),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCostNode(Element $node): ?array
    {
        return match ($node->nodeName) {
            'CraftingCost_Select' => $this->buildGroupNode($node),
            'CraftingCost_Item' => $this->buildLeafNode('item', $node, $this->buildItemInput($node)),
            'CraftingCost_Resource' => $this->buildLeafNode('resource', $node, $this->buildResourceInput($node)),
            'CraftingCost_Blueprint', 'CraftingCost_BlueprintRef' => $this->buildLeafNode('blueprint_ref', $node, $this->buildBlueprintReferenceInput($node)),
            default => $this->buildUnknownNode($node),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupNode(Element $node): array
    {
        $nameInfo = $node->get('nameInfo');
        $modifiers = $this->buildStatModifiers($node);

        return $this->removeNulls([
            'kind' => 'group',
            'key' => $this->readAttribute($nameInfo, 'debugName'),
            'name' => $this->buildResolvedNodeName($nameInfo),
            'required_count' => $this->normalizeNumber($node->get('@count')),
            'modifiers' => $modifiers === [] ? null : $modifiers,
            'children' => $this->buildCostChildren($node),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildLeafNode(string $kind, Element $node, array $payload): array
    {
        $modifiers = $this->buildStatModifiers($node);

        return $this->removeNulls([
            'kind' => $kind,
            ...$payload,
            'modifiers' => $modifiers === [] ? null : $modifiers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnknownNode(Element $node): array
    {
        $children = $this->buildCostChildren($node);
        $attributes = $this->buildAttributes($node);

        return $this->removeNulls([
            'kind' => 'unknown',
            'xml_node_name' => $node->nodeName,
            'attributes' => $attributes === [] ? null : $attributes,
            'children' => $children === [] ? null : $children,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCostChildren(Element $node): array
    {
        $container = $node->get('options');

        if (! $container instanceof Element) {
            $container = $node;
        }

        $children = [];

        foreach ($container->children() as $child) {
            if (! $this->isCostNode($child)) {
                continue;
            }

            if ($this->isSyntheticSelectWrapper($child)) {
                array_push($children, ...$this->buildCostChildren($child));

                continue;
            }

            $formatted = $this->buildCostNode($child);

            if ($formatted !== null) {
                $children[] = $formatted;
            }
        }

        return $children;
    }

    private function isCostNode(Element $node): bool
    {
        return str_starts_with($node->nodeName, 'CraftingCost_');
    }

    private function isSyntheticSelectWrapper(Element $node): bool
    {
        if ($node->nodeName !== 'CraftingCost_Select') {
            return false;
        }

        $selectChildCount = $this->countSelectChildren($node);

        if ($selectChildCount === 0) {
            return false;
        }

        if ($this->buildStatModifiers($node) !== []) {
            return false;
        }

        $requiredCount = $this->normalizeNumber($node->get('@count'));

        if (
            $requiredCount !== null
            && (float) $requiredCount !== 1.0
            && (float) $requiredCount !== (float) $selectChildCount
        ) {
            return false;
        }

        $nameInfo = $node->get('nameInfo');

        if (! $nameInfo instanceof Element) {
            return $requiredCount === null
                || (float) $requiredCount === (float) $selectChildCount;
        }

        $slotKey = $this->readAttribute($nameInfo, 'debugName')
            ?? $this->readAttribute($nameInfo, 'displayName');

        return is_string($slotKey) && strtoupper($slotKey) === 'ASPECTS';
    }

    private function countSelectChildren(Element $node): int
    {
        $container = $node->get('options');

        if (! $container instanceof Element) {
            $container = $node;
        }

        $count = 0;

        foreach ($container->children() as $child) {
            if (! $this->isCostNode($child)) {
                continue;
            }

            if ($child->nodeName !== 'CraftingCost_Select') {
                return 0;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItemInput(Element $cost): array
    {
        $inputEntity = $cost->get('InputEntity');

        return $this->removeNulls([
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
            'uuid' => $this->readAttribute($resourceType, '__ref')
                ?? $this->readAttribute($cost, 'resource'),
            'name' => $this->translate($this->readAttribute($resourceType, 'displayName')),
            'quantity_scu' => $quantityScu,
            'min_quality' => $this->normalizeNumber($cost->get('@minQuality')),
        ]);
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
    private function buildStatModifiers(Element $node): array
    {
        $modifiers = $node->get(
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

    private function buildResolvedNodeName(?Element $nameInfo): ?string
    {
        if ($nameInfo === null) {
            return null;
        }

        return $this->translate($this->readAttribute($nameInfo, 'displayName'))
            ?? $this->readAttribute($nameInfo, 'debugName');
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

    /**
     * @return array<string, mixed>
     */
    private function buildBlueprintReferenceInput(Element $cost): array
    {
        $uuid = $this->readAttribute($cost, 'blueprintRecord')
            ?? $this->readAttribute($cost, 'blueprint')
            ?? $this->readAttribute($cost, 'entityClass')
            ?? $this->readAttribute($cost, 'record');

        return $this->removeNulls([
            'uuid' => $uuid,
            'key' => $this->readBlueprintKey($uuid),
            'name' => $this->readBlueprintName($uuid),
            'quantity' => $this->normalizeNumber($cost->get('@quantity')),
            'min_quality' => $this->normalizeNumber($cost->get('@minQuality')),
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function buildAttributes(Element $node): array
    {
        $attributes = [];

        foreach ($node->getNode()->attributes ?? [] as $attribute) {
            if (in_array($attribute->nodeName, ['__type', '__polymorphicType'], true)) {
                continue;
            }

            $attributes[$attribute->nodeName] = $this->normalizeNumber($attribute->nodeValue) ?? $attribute->nodeValue;
        }

        return $attributes;
    }

    private function readBlueprintKey(?string $uuid): ?string
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        try {
            return ServiceFactory::getBlueprintService()->getByReference($uuid)?->getClassName();
        } catch (RuntimeException) {
            return null;
        }
    }

    private function readBlueprintName(?string $uuid): ?string
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        try {
            $record = ServiceFactory::getBlueprintService()->getByReference($uuid);
        } catch (RuntimeException) {
            return null;
        }

        if ($record === null) {
            return null;
        }

        return $this->translate($record->get('blueprint/CraftingBlueprint@blueprintName'));
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
        $normalizedValue = $this->normalizeString($value);

        if ($normalizedValue === null) {
            return null;
        }

        if (! str_starts_with($normalizedValue, '@')) {
            return $this->isPlaceholderTranslation($normalizedValue) ? null : $normalizedValue;
        }

        try {
            $translatedValue = $this->normalizeString(
                ServiceFactory::getLocalizationService()->getTranslation($normalizedValue)
            );
        } catch (RuntimeException) {
            return null;
        }

        if (
            $translatedValue === null
            || $translatedValue === $normalizedValue
            || $this->isPlaceholderTranslation($translatedValue)
        ) {
            return null;
        }

        return $translatedValue;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    private function isPlaceholderTranslation(string $value): bool
    {
        return strtoupper(trim($value)) === self::PLACEHOLDER_TRANSLATION;
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
