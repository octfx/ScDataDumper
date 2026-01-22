<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class CraftingCost extends BaseFormat
{
    public function toArray(): ?array
    {
        if ($this->item === null) {
            return null;
        }

        $data = [];

        $craftTime = $this->formatCraftTime();
        if ($craftTime !== null) {
            $data['CraftTime'] = $craftTime;
        }

        $mandatoryCost = $this->get('/mandatoryCost');
        if ($mandatoryCost !== null) {
            $data['MandatoryCost'] = $this->formatCostElement($mandatoryCost);
        }

        $optionalCosts = $this->get('/optionalCosts');
        if ($optionalCosts !== null) {
            $data['OptionalCosts'] = $this->formatOptionalCosts($optionalCosts);
        }

        return empty($data) ? null : $this->removeNullValues($data);
    }

    private function formatCraftTime(): ?array
    {
        $craftTimeElement = $this->get('/craftTime');

        if ($craftTimeElement === null || ! method_exists($craftTimeElement, 'children')) {
            return null;
        }

        $time = [];
        $timeValueElements = $craftTimeElement->children();

        if ($timeValueElements === null) {
            return null;
        }

        foreach ($timeValueElements as $timeValue) {
            if (! method_exists($timeValue, 'nodeName') || ! method_exists($timeValue, 'get')) {
                continue;
            }

            $nodeName = $timeValue->nodeName;

            if ($nodeName === 'TimeValue_Partitioned') {
                $days = $timeValue->get('@days');
                $hours = $timeValue->get('@hours');
                $minutes = $timeValue->get('@minutes');
                $seconds = $timeValue->get('@seconds');

                if ($days !== null) {
                    $time['Days'] = (int) $days;
                }
                if ($hours !== null) {
                    $time['Hours'] = (int) $hours;
                }
                if ($minutes !== null) {
                    $time['Minutes'] = (int) $minutes;
                }
                if ($seconds !== null) {
                    $time['Seconds'] = (int) $seconds;
                }
            }
        }

        return empty($time) ? null : $time;
    }

    private function formatCostElement(mixed $cost): ?array
    {
        if ($cost === null) {
            return null;
        }

        if (method_exists($cost, 'nodeName')) {
            return $this->formatCostByType($cost);
        }

        return null;
    }

    private function formatCostByType(Element $cost): ?array
    {
        $nodeName = $cost->nodeName;

        return match ($nodeName) {
            'CraftingCost_Select' => $this->formatSelectCost($cost),
            'CraftingCost_Resource' => $this->formatResourceCost($cost),
            'CraftingCost_Item' => $this->formatItemCost($cost),
            'CraftingCost_RecordRef' => $this->formatRecordRefCost($cost),
            default => null,
        };
    }

    private function formatSelectCost(Element $cost): ?array
    {
        $nameInfo = $cost->get('/nameInfo');
        $options = $cost->get('/options');

        $data = [
            'Count' => $this->getAttributeAsInt($cost, 'count'),
        ];

        if ($nameInfo !== null) {
            $data['NameInfo'] = $this->formatNameInfo($nameInfo);
        }

        if ($options !== null && method_exists($options, 'children')) {
            $data['Options'] = $this->formatOptions($options);
        }

        return $this->removeNullValues($data);
    }

    private function formatNameInfo(mixed $nameInfo): ?array
    {
        if ($nameInfo === null) {
            return null;
        }

        $data = [];

        if (method_exists($nameInfo, 'get')) {
            $debugName = $nameInfo->get('debugName');
            $displayName = $nameInfo->get('displayName');

            if ($debugName !== null) {
                $data['DebugName'] = $debugName;
            }
            if ($displayName !== null) {
                $data['DisplayName'] = $displayName;
            }
        }

        return empty($data) ? null : $data;
    }

    private function formatOptions(mixed $options): array
    {
        $output = [];

        if (method_exists($options, 'children')) {
            $optionElements = $options->children();

            if ($optionElements === null) {
                return $output;
            }

            foreach ($optionElements as $option) {
                if (method_exists($option, 'nodeName')) {
                    $formattedOption = $this->formatCostByType($option);
                    if ($formattedOption !== null) {
                        $output[] = $formattedOption;
                    }
                }
            }
        }

        return $output;
    }

    private function formatResourceCost(Element $cost): ?array
    {
        $resourceUuid = $this->getAttribute($cost, 'resource');
        $quantity = $cost->get('/quantity');

        $data = [
            'ResourceUUID' => $resourceUuid,
        ];

        // Resolve UUID using CraftingMaterial Format
        if ($resourceUuid !== null) {
            $resource = new CraftingMaterial($resourceUuid);
            $resourceData = $resource->toArray();
            if ($resourceData !== null) {
                $data['Resource'] = $resourceData;
            }
        }

        if ($quantity !== null) {
            $data['Quantity'] = $this->formatQuantity($quantity);
        }

        return $this->removeNullValues($data);
    }

    private function formatItemCost(Element $cost): ?array
    {
        $itemUuid = $this->getAttribute($cost, 'item');
        $quantity = $cost->get('/quantity');

        $data = [
            'ItemUUID' => $itemUuid,
        ];

        // Resolve UUID using CraftingMaterial Format
        if ($itemUuid !== null) {
            $item = new CraftingMaterial($itemUuid);
            $itemData = $item->toArray();
            if ($itemData !== null) {
                $data['Item'] = $itemData;
            }
        }

        if ($quantity !== null) {
            $data['Quantity'] = $this->formatQuantity($quantity);
        }

        return $this->removeNullValues($data);
    }

    private function formatRecordRefCost(Element $cost): ?array
    {
        $recordUuid = $this->getAttribute($cost, 'costRecord');
        $multiplier = $this->getAttributeAsFloat($cost, 'multiplier');

        $data = [
            'RecordUUID' => $recordUuid,
            'Multiplier' => $multiplier,
        ];

        return $this->removeNullValues($data);
    }

    private function formatQuantity(mixed $quantity): ?array
    {
        if ($quantity === null) {
            return null;
        }

        $data = [];

        if (method_exists($quantity, 'children')) {
            $children = $quantity->children();

            if ($children !== null) {
                foreach ($children as $child) {
                    if (method_exists($child, 'nodeName') && method_exists($child, 'getAttributes')) {
                        $nodeName = $child->nodeName;
                        $attributes = $child->getAttributes();

                        if ($attributes !== null) {
                            foreach ($attributes as $attrName => $attrValue) {
                                $data[$nodeName.'_'.$attrName] = $attrValue;
                            }
                        }
                    }
                }
            }
        }

        return empty($data) ? null : $this->transformArrayKeysToPascalCase($data);
    }

    private function formatOptionalCosts(mixed $optionalCosts): array
    {
        $output = [];

        if ($optionalCosts === null) {
            return $output;
        }

        if (method_exists($optionalCosts, 'children')) {
            $entries = $optionalCosts->children();

            if ($entries === null) {
                return $output;
            }

            foreach ($entries as $entry) {
                if (method_exists($entry, 'nodeName') && $entry->nodeName === 'CraftingOptionalEntry') {
                    $optionalCost = $entry->get('/optionalCost');
                    $effect = $entry->get('/effect');

                    $entryData = [];

                    if ($optionalCost !== null) {
                        $entryData['OptionalCost'] = $this->formatCostByType($optionalCost);
                    }

                    if ($effect !== null) {
                        $entryData['Effect'] = $this->formatEffect($effect);
                    }

                    $entryData = $this->removeNullValues($entryData);
                    if (! empty($entryData)) {
                        $output[] = $entryData;
                    }
                }
            }
        }

        return $output;
    }

    private function formatEffect(mixed $effect): ?array
    {
        if ($effect === null) {
            return null;
        }

        $data = [];

        if (method_exists($effect, 'children')) {
            $children = $effect->children();

            if ($children !== null) {
                foreach ($children as $child) {
                    if (method_exists($child, 'nodeName') && method_exists($child, 'getAttributes')) {
                        $nodeName = $child->nodeName;
                        $attributes = $child->getAttributes();

                        if ($attributes !== null) {
                            foreach ($attributes as $attrName => $attrValue) {
                                $data[$nodeName.'_'.$attrName] = $attrValue;
                            }
                        }
                    }
                }
            }
        }

        return empty($data) ? null : $this->transformArrayKeysToPascalCase($data);
    }

    private function getAttribute(Element $element, string $name): ?string
    {
        return $element->get('@'.$name);
    }

    private function getAttributeAsInt(Element $element, string $name): ?int
    {
        $value = $this->getAttribute($element, $name);

        return $value !== null ? (int) $value : null;
    }

    private function getAttributeAsFloat(Element $element, string $name): ?float
    {
        $value = $this->getAttribute($element, $name);

        return $value !== null ? (float) $value : null;
    }
}
