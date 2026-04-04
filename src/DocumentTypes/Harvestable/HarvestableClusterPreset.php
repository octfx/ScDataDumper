<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableClusterPreset extends RootDocument
{
    public function getProbabilityOfClustering(): ?float
    {
        return $this->getFloat('@probabilityOfClustering');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getParams(): array
    {
        $params = [];

        foreach ($this->getAll('params/*') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $params[] = $this->toArrayRecursive($node);
        }

        return $params;
    }
}
