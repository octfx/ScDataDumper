<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class SubHarvestableMultiConfigRecord extends RootDocument
{
    /**
     * @return list<TaggedSubHarvestableConfig>
     */
    public function getTaggedConfigs(): array
    {
        $configs = [];

        foreach ($this->getAll('multiConfig/taggedConfigs/TaggedSubHarvestableConfig') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $config = TaggedSubHarvestableConfig::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($config instanceof TaggedSubHarvestableConfig) {
                $configs[] = $config;
            }
        }

        return $configs;
    }
}
