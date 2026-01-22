<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprint;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Blueprint extends BaseFormat
{
    protected ?string $elementKey = '/blueprint';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $document = $this->item;

        $categoryFormat = new BlueprintCategory($this->get('/blueprint/@blueprintCategory'));
        $category = $categoryFormat->toArray();
        dd($category);
        $blueprintName = $document instanceof CraftingBlueprint
            ? $document->getBlueprintName()
            : $this->get('/blueprint/@blueprintName');

        $tiersElements = $document instanceof CraftingBlueprint
            ? $document->getTiers()
            : [];

        $tiers = [];
        foreach ($tiersElements as $tierElement) {
            $tierFormat = new BlueprintTier($tierElement);
            $tierData = $tierFormat->toArray();
            if ($tierData !== null) {
                $tiers[] = $tierData;
            }
        }

        $data = [
            'ClassName' => $document->getClassName(),
            'Reference' => $document->getUuid(),
            'Category' => $category,
            'BlueprintName' => $blueprintName,
            'Tiers' => $tiers,
        ];
        dd($data);

        return $this->removeNullValues($data);
    }
}
