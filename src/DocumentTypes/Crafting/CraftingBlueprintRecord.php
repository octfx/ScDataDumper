<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

/**
 * @method list<CraftingBlueprintTier>
 */
final class CraftingBlueprintRecord extends RootDocument
{
    public function getBlueprint(): ?Element
    {
        return $this->get('blueprint/CraftingBlueprint');
    }

    public function getCategoryUuid(): ?string
    {
        return $this->get('blueprint/CraftingBlueprint@category');
    }

    public function getOutputEntityUuid(): ?string
    {
        return $this->get('blueprint/CraftingBlueprint/processSpecificData/CraftingProcess_Creation@entityClass');
    }

    public function getOutputEntity(): ?EntityClassDefinition
    {
        $entity = $this->resolveRelatedDocument(
            'blueprint/CraftingBlueprint/processSpecificData/CraftingProcess_Creation/OutputEntity',
            EntityClassDefinition::class,
            $this->getOutputEntityUuid(),
            static fn (string $reference): ?EntityClassDefinition => ServiceFactory::getItemService()->getByReference($reference)
        );

        return $entity instanceof EntityClassDefinition ? $entity : null;
    }

    public function getCraftTier(): ?Element
    {
        return $this->get('blueprint/CraftingBlueprint/tiers/CraftingBlueprintTier');
    }

    public static function parseTimeValue(?Element $timeValue): ?float
    {
        if ($timeValue === null) {
            return null;
        }

        $days = (float) ($timeValue->get('@days') ?? 0);
        $hours = (float) ($timeValue->get('@hours') ?? 0);
        $minutes = (float) ($timeValue->get('@minutes') ?? 0);
        $seconds = (float) ($timeValue->get('@seconds') ?? 0);

        return ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public static function readBlueprintKey(?string $uuid): ?string
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

    public static function readBlueprintName(?string $uuid): ?string
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

        return ServiceFactory::getLocalizationService()->translateValue($name);
    }

    /**
     * @return list<CraftingBlueprintTier>
     */
    public function getTierElements(): array
    {
        $tiers = $this->get('blueprint/CraftingBlueprint/tiers');

        if ($tiers === null) {
            return [];
        }

        $results = [];

        foreach ($tiers->children() as $tier) {
            if ($tier->nodeName === 'CraftingBlueprintTier') {
                $instance = CraftingBlueprintTier::fromTierElement($tier);

                if ($instance !== null) {
                    $results[] = $instance;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<CraftingCost>
     */
    public function getAllLeafCosts(): array
    {
        $leaves = [];

        foreach ($this->getTierElements() as $tier) {
            $mandatory = $tier->getMandatoryCost();

            if ($mandatory !== null) {
                array_push($leaves, ...$mandatory->getFlatLeaves());
            }
        }

        return $leaves;
    }

    public static function readResourceQuantityMultiplier(Element $cost): float
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
}
