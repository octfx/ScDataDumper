<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;
use RuntimeException;

final class CraftingBlueprint extends RootDocument
{
    public function getCategoryUuid(): ?string
    {
        return $this->get('/blueprint/CraftingBlueprint@category');
    }

    public function getBlueprintName(): ?string
    {
        return $this->get('/blueprint/CraftingBlueprint@blueprintName');
    }

    public function getTiers(): array
    {
        if ($this->domXPath === null) {
            throw new RuntimeException('DOMXPath object not set');
        }

        $nodes = $this->domXPath->query('blueprint/CraftingBlueprint/tiers/CraftingBlueprintTier');

        if ($nodes === false) {
            return [];
        }

        $tiers = [];
        foreach ($nodes as $node) {
            $tiers[] = new Element($node);
        }

        return $tiers;
    }
}
