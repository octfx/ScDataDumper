<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class BlueprintRecipe extends BaseFormat
{
    public function toArray(): ?array
    {
        if ($this->item === null) {
            return null;
        }

        $costs = $this->get('/CraftingRecipeCosts');
        $resultsElement = $this->get('/CraftingRecipeResults');

        $data = [];

        if ($costs !== null) {
            $data['Costs'] = new CraftingCost($costs);
        }

        if ($resultsElement !== null) {
            $data['Results'] = $this->formatResults($resultsElement);
        }

        $craftTime = $this->formatCraftTime($costs);
        if ($craftTime !== null) {
            $data['CraftTime'] = $craftTime;
        }

        return $this->removeNullValues($data);
    }

    private function formatResults(mixed $results): array
    {
        $output = [];

        if ($results === null) {
            return $output;
        }

        if (method_exists($results, 'children')) {
            $resultsContainer = $results->children();

            if ($resultsContainer === null) {
                return $output;
            }

            foreach ($resultsContainer as $container) {
                if (method_exists($container, 'nodeName') && $container->nodeName === 'results') {
                    $resultItems = $container->children();

                    if ($resultItems === null) {
                        continue;
                    }

                    foreach ($resultItems as $result) {
                        if (method_exists($result, 'nodeName') && $result->nodeName === 'CraftingResult_Item') {
                            $output[] = $this->formatResult($result);
                        }
                    }
                }
            }
        }

        return $output;
    }

    private function formatResult(mixed $result): ?array
    {
        if ($result === null) {
            return null;
        }

        $entityClassUuid = $this->getFromElement($result, 'entityClass');
        $quantity = $this->getFromElement($result, 'quantity');
        $tier = $this->getFromElement($result, 'tier');

        $data = [];

        if ($quantity !== null) {
            $data['Quantity'] = (int) $quantity;
        }

        if ($tier !== null) {
            $data['Tier'] = (int) $tier;
        }

        $item = null;
        if ($entityClassUuid !== null) {
            $item = ServiceFactory::getItemService()->getByReference($entityClassUuid);
        }

        if ($item !== null) {
            $attachDef = $item->getAttachDef();

            $manufacturer = null;
            if ($attachDef !== null) {
                $manufacturerUuid = $attachDef->get('Manufacturer');
                if ($manufacturerUuid !== null) {
                    $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturerUuid);
                }
            }

            $itemData = [
                'UUID' => $item->getUuid(),
                'ClassName' => $item->getClassName(),
            ];

            if ($attachDef !== null) {
                $itemData['Name'] = $attachDef->get('Localization/English@Name');
                $itemData['Description'] = $attachDef->get('Localization/English@Description');
                $itemData['Type'] = $attachDef->get('Type');
                $itemData['SubType'] = $attachDef->get('SubType');
                $itemData['Size'] = $attachDef->get('Size');
            }

            if ($manufacturer !== null) {
                $itemData['Manufacturer'] = [
                    'Code' => $manufacturer->getCode(),
                    'Name' => $manufacturer->get('Localization/English@Name'),
                    'UUID' => $manufacturer->getUuid(),
                ];
            }

            $data['Item'] = $this->removeNullValues($itemData);
        } else {
            $data['ItemUUID'] = $entityClassUuid;
        }

        return $this->removeNullValues($data);
    }

    private function formatCraftTime(mixed $costs): ?array
    {
        if ($costs === null) {
            return null;
        }

        $time = [];

        $craftTimeElement = null;
        if (method_exists($costs, 'get')) {
            $craftTimeElement = $costs->get('/craftTime');
        }

        if ($craftTimeElement === null || ! method_exists($craftTimeElement, 'children')) {
            return null;
        }

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

    private function getFromElement(mixed $element, string $key): mixed
    {
        if ($element === null) {
            return null;
        }

        if (method_exists($element, 'get')) {
            return $element->get('@'.$key);
        }

        return null;
    }
}
