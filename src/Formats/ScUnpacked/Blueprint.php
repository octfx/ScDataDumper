<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingBlueprintRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingCost;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGameplayPropertyModifier;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
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

        $uuid = $this->item?->getUuid();

        $record = $this->item instanceof CraftingBlueprintRecord ? $this->item : null;

        return $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $uuid,
            'key' => $this->item?->getClassName(),
            'kind' => 'creation',
            'category_uuid' => $record?->getCategoryUuid(),
            'output' => $this->buildOutput($record?->getOutputEntity()),
            'availability' => $this->buildAvailability($uuid),
            'tiers' => $this->buildTiers($record),
            'dismantle' => $this->buildDismantle($record),
        ]));
    }

    private function buildOutput(?EntityClassDefinition $outputEntity): array
    {
        if ($outputEntity === null) {
            return [];
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $outputEntity->getUuid(),
            'class' => EntityClassDefinition::extractClassNameFromPath($outputEntity->getPath()),
            'type' => $outputEntity->getAttachType(),
            'subtype' => $outputEntity->getAttachSubType(),
            'grade' => $outputEntity->getGrade(),
            'name' => $outputEntity->getDisplayName(),
        ]);
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
    private function buildTiers(?CraftingBlueprintRecord $record): array
    {
        if ($record === null) {
            return [];
        }

        $results = [];

        foreach ($record->getTierElements() as $index => $tier) {
            $results[] = $this->removeNullValuesPreservingEmptyArrays([
                'tier_index' => $index,
                'craft_time_seconds' => $this->normalizeNumber($tier->getCraftTimeSeconds()),
                'requirements' => $this->buildRequirements($tier->getMandatoryCost()),
            ]);
        }

        return $results;
    }

    /**
     * @return array{time_seconds: int, efficiency: float, returns: list<array<string, mixed>>}|null
     */
    private function buildDismantle(?CraftingBlueprintRecord $record): ?array
    {
        if ($record === null) {
            return null;
        }

        try {
            $dismantleParams = ServiceFactory::getBlueprintService()->getDismantleParams();
        } catch (RuntimeException) {
            return null;
        }

        if ($dismantleParams === null) {
            return null;
        }

        $efficiency = $dismantleParams['efficiency'];
        $returns = [];

        foreach ($record->getAllLeafCosts() as $leaf) {
            $kind = $leaf->getCostKind();

            if ($kind === 'item') {
                $entry = $this->buildDismantleItemReturn($leaf, $efficiency);

                if ($entry !== null) {
                    $returns[] = $entry;
                }
            } elseif ($kind === 'material') {
                $returns[] = $this->buildDismantleResourceReturn($leaf, $efficiency);
            }
        }

        return [
            'time_seconds' => $dismantleParams['time_seconds'],
            'efficiency' => $efficiency,
            'returns' => $returns,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDismantleItemReturn(CraftingCost $cost, float $efficiency): ?array
    {
        $inputEntity = $cost->getInputEntity();
        $rawQuantity = $cost->getQuantity();

        if ($rawQuantity === null) {
            return null;
        }

        $quantity = (int) floor((float) $rawQuantity * $efficiency);

        if ($quantity <= 0) {
            return null;
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => 'item',
            'uuid' => $inputEntity?->getUuid() ?? $cost->getEntityClassReference(),
            'name' => $inputEntity?->getDisplayName(),
            'quantity' => $quantity,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDismantleResourceReturn(CraftingCost $cost, float $efficiency): array
    {
        $resourceType = $cost->getResourceType();
        $quantityScu = Item::convertToScu($cost->getQuantityElement());
        $resourceName = ServiceFactory::getLocalizationService()->translateValue($resourceType?->getDisplayName());

        if ($quantityScu !== null) {
            $quantityScu = round($quantityScu * $cost->getQuantityMultiplier() * $efficiency, 9);
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => 'material',
            'uuid' => $resourceType?->getUuid() ?? $cost->getResourceReference(),
            'name' => $resourceName,
            'quantity_scu' => $quantityScu,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRequirements(?CraftingCost $mandatoryCost): ?array
    {
        if ($mandatoryCost === null) {
            return null;
        }

        return [
            'kind' => 'root',
            'children' => $this->buildCostChildren($mandatoryCost),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCostNode(CraftingCost $cost): ?array
    {
        return match ($cost->getCostKind()) {
            'group' => $this->buildGroupNode($cost),
            'item' => $this->buildLeafNode('item', $cost, $this->buildItemInput($cost)),
            'resource' => $this->buildLeafNode('material', $cost, $this->buildResourceInput($cost)),
            'blueprint_ref' => $this->buildLeafNode('blueprint_ref', $cost, $this->buildBlueprintReferenceInput($cost)),
            default => $this->buildUnknownNode($cost),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupNode(CraftingCost $cost): array
    {
        $modifiers = $this->buildStatModifiers($cost);

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => 'group',
            'key' => $cost->getDebugName(),
            'name' => $cost->getResolvedName(),
            'required_count' => $cost->getRequiredCount(),
            'modifiers' => $modifiers === [] ? null : $modifiers,
            'children' => $this->buildCostChildren($cost),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildLeafNode(string $kind, CraftingCost $cost, array $payload): array
    {
        $modifiers = $this->buildStatModifiers($cost);

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => $kind,
            ...$payload,
            'modifiers' => $modifiers === [] ? null : $modifiers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnknownNode(CraftingCost $cost): array
    {
        $children = $this->buildCostChildren($cost);
        $attributes = $cost->getAttributes();

        return $this->removeNullValuesPreservingEmptyArrays([
            'kind' => 'unknown',
            'xml_node_name' => $cost->getNodeName(),
            'attributes' => $attributes === [] ? null : $attributes,
            'children' => $children === [] ? null : $children,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCostChildren(CraftingCost $cost): array
    {
        $children = [];

        foreach ($cost->getCostChildren() as $child) {
            if ($child->isSyntheticSelectWrapper()) {
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

    /**
     * @return array<string, mixed>
     */
    private function buildItemInput(CraftingCost $cost): array
    {
        $inputEntity = $cost->getInputEntity();

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $inputEntity?->getUuid() ?? $cost->getEntityClassReference(),
            'name' => $inputEntity?->getDisplayName(),
            'quantity' => $cost->getQuantity(),
            'min_quality' => $cost->getMinQuality(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResourceInput(CraftingCost $cost): array
    {
        $resourceType = $cost->getResourceType();
        $quantityScu = Item::convertToScu($cost->getQuantityElement());
        $resourceName = ServiceFactory::getLocalizationService()->translateValue($resourceType?->getDisplayName());

        if ($quantityScu !== null) {
            $quantityScu = round($quantityScu * $cost->getQuantityMultiplier(), 9);
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $resourceType?->getUuid() ?? $cost->getResourceReference(),
            'name' => $resourceName,
            'quantity_scu' => $quantityScu,
            'min_quality' => $cost->getMinQuality(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStatModifiers(CraftingCost $cost): array
    {
        $results = [];

        foreach ($cost->getStatModifierElements() as $modifier) {
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
    private function buildStatModifier(CraftingGameplayPropertyModifier $modifier): ?array
    {
        $property = $modifier->getResolvedProperty();

        $formatted = $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $property?->getUuid() ?? $modifier->getPropertyReference(),
            'key' => $property?->getNormalizedPropertyKey(),
            'quality_range' => $this->removeNullValuesPreservingEmptyArrays([
                'min' => $this->normalizeNumber($modifier->getQualityMin()),
                'max' => $this->normalizeNumber($modifier->getQualityMax()),
            ]),
            'modifier_range' => $this->removeNullValuesPreservingEmptyArrays([
                'at_min_quality' => $this->normalizeNumber($modifier->getModifierAtMinQuality()),
                'at_max_quality' => $this->normalizeNumber($modifier->getModifierAtMaxQuality()),
            ]),
        ]);

        return $formatted === [] ? null : $formatted;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlueprintReferenceInput(CraftingCost $cost): array
    {
        $uuid = $cost->getBlueprintReference();

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $uuid,
            'key' => CraftingBlueprintRecord::readBlueprintKey($uuid),
            'name' => CraftingBlueprintRecord::readBlueprintName($uuid),
            'quantity' => $cost->getQuantity(),
            'min_quality' => $cost->getMinQuality(),
        ]);
    }
}
