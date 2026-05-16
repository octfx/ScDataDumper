<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;

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

        foreach (['params/*', 'clusterParamsArray/*'] as $path) {
            foreach ($this->getAll($path) as $node) {
                if (! $node instanceof Element) {
                    continue;
                }

                $params[] = $this->toArrayRecursive($node);
            }
        }

        return $params;
    }
}
