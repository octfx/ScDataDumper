<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingBlueprintRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGameplayPropertyDef;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
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

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $uuid,
            'key' => $this->item?->getClassName(),
            'kind' => 'creation',
            'category_uuid' => $blueprint->get('@category'),
            'output' => $this->buildOutput($this->resolveOutputEntity()),
            'availability' => $this->buildAvailability($uuid),
            'tiers' => $this->buildTiers($blueprint->get('tiers')),
        ]);
    }

    private function buildOutput(?EntityClassDefinition $outputEntity): array
    {
        if ($outputEntity === null) {
            return [];
        }

        $attachDef = $outputEntity->getAttachDef();
        $grade = $attachDef?->get('@Grade');

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $outputEntity->getUuid(),
            'class' => $this->extractClassNameFromPath($outputEntity->getPath()),
            'type' => $attachDef?->get('@Type'),
            'subtype' => $attachDef?->get('@SubType'),
            'grade' => $grade !== null ? (string) $grade : null,
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
            $results[] = $this->removeNullValuesPreservingEmptyArrays([
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

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => 'group',
            'key' => $nameInfo?->get('@debugName'),
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

        return $this->removeNullValuesPreservingEmptyArrays([
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

        return $this->removeNullValuesPreservingEmptyArrays([
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

        $slotKey = $nameInfo->get('@debugName')
            ?? $nameInfo->get('@displayName');

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
        $inputEntity = $this->resolveInputEntity($cost);

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $inputEntity?->getUuid()
                ?? $cost->get('@entityClass'),
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
        $resourceType = $this->resolveResourceType($cost);
        $quantityScu = Item::convertToScu($cost->get('quantity'));
        $resourceName = $resourceType?->getDisplayName();

        if (is_string($resourceName) && trim($resourceName) !== '') {
            try {
                $resourceName = ServiceFactory::getLocalizationService()->getTranslation($resourceName);
            } catch (RuntimeException) {
            }
        } else {
            $resourceName = null;
        }

        if ($quantityScu !== null) {
            // XML quantities are decimal strings, so round after applying multipliers to avoid float artifacts.
            $quantityScu = round($quantityScu * $this->readResourceQuantityMultiplier($cost), 9);
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $resourceType?->getUuid()
                ?? $cost->get('@resource'),
            'name' => $resourceName,
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
        $property = $this->resolveGameplayProperty($modifier);
        $valueRange = $modifier->get('valueRanges/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $modifier->get('valueRange/CraftingGameplayPropertyModifierValueRange_Linear');

        $formatted = $this->removeNullValuesPreservingEmptyArrays([
            'property_uuid' => $property?->getUuid()
                ?? $modifier->get('@gameplayPropertyRecord'),
            'property_key' => $this->extractGameplayPropertyKey($property),
            'quality_range' => $this->removeNullValuesPreservingEmptyArrays([
                'min' => $this->normalizeNumber(
                    $valueRange?->get('@startQuality')
                    ?? $valueRange?->get('@minInputValue')
                ),
                'max' => $this->normalizeNumber(
                    $valueRange?->get('@endQuality')
                    ?? $valueRange?->get('@maxInputValue')
                ),
            ]),
            'modifier_range' => $this->removeNullValuesPreservingEmptyArrays([
                'at_min_quality' => $this->normalizeNumber(
                    $valueRange?->get('@modifierAtStart')
                    ?? $valueRange?->get('@minOutputMultiplier')
                ),
                'at_max_quality' => $this->normalizeNumber(
                    $valueRange?->get('@modifierAtEnd')
                    ?? $valueRange?->get('@maxOutputMultiplier')
                ),
            ]),
        ]);

        return $formatted === [] ? null : $formatted;
    }

    private function resolveOutputEntity(): ?EntityClassDefinition
    {
        return $this->item instanceof CraftingBlueprintRecord
            ? $this->item->getOutputEntity()
            : null;
    }

    private function resolveInputEntity(Element $cost): ?EntityClassDefinition
    {
        $inputEntity = $cost->get('InputEntity');

        if ($inputEntity instanceof Element) {
            $entity = EntityClassDefinition::fromNode($inputEntity->getNode());

            if ($entity instanceof EntityClassDefinition) {
                return $entity;
            }
        }

        $reference = $cost->get('@entityClass');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getItemService()->getByReference($reference)
            : null;
    }

    private function resolveResourceType(Element $cost): ?ResourceType
    {
        $resourceType = $cost->get('ResourceType');

        if ($resourceType instanceof Element) {
            $resolved = ResourceType::fromNode($resourceType->getNode());

            if ($resolved instanceof ResourceType) {
                return $resolved;
            }
        }

        $reference = $cost->get('@resource');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference)
            : null;
    }

    private function resolveGameplayProperty(Element $modifier): ?CraftingGameplayPropertyDef
    {
        $property = $modifier->get('GameplayProperty');

        if ($property instanceof Element) {
            $resolved = CraftingGameplayPropertyDef::fromNode($property->getNode());

            if ($resolved instanceof CraftingGameplayPropertyDef) {
                return $resolved;
            }
        }

        $reference = $modifier->get('@gameplayPropertyRecord');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getFoundryLookupService()->getCraftingGameplayPropertyByReference($reference)
            : null;
    }

    private function buildResolvedNodeName(?Element $nameInfo): ?string
    {
        if ($nameInfo === null) {
            return null;
        }

        $displayName = $nameInfo->get('@displayName');

        if (is_string($displayName) && trim($displayName) !== '') {
            try {
                return ServiceFactory::getLocalizationService()->getTranslation($displayName);
            } catch (RuntimeException) {
                return $displayName;
            }
        }

        $debugName = $nameInfo->get('@debugName');

        return is_string($debugName) && trim($debugName) !== '' ? $debugName : null;
    }

    private function readItemName(?EntityClassDefinition $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        $attachDef = $entity->getAttachDef();
        $name = $attachDef?->get('Localization/English@Name')
            ?? $attachDef?->get('Localization@Name');

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        try {
            return ServiceFactory::getLocalizationService()->getTranslation($name);
        } catch (RuntimeException) {
            return $name;
        }
    }

    private function extractClassNameFromPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    private function extractGameplayPropertyKey(?CraftingGameplayPropertyDef $property): ?string
    {
        if ($property === null) {
            return null;
        }

        $key = $property->getPropertyKey();

        return $key === '' ? null : $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlueprintReferenceInput(Element $cost): array
    {
        $uuid = $cost->get('@blueprintRecord')
            ?? $cost->get('@blueprint')
            ?? $cost->get('@entityClass')
            ?? $cost->get('@record');

        return $this->removeNullValuesPreservingEmptyArrays([
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

        $name = $record->get('blueprint/CraftingBlueprint@blueprintName');

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        try {
            return ServiceFactory::getLocalizationService()->getTranslation($name);
        } catch (RuntimeException) {
            return $name;
        }
    }
}
