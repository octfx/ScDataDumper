<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class SubHarvestableMultiConfigRecord extends RootDocument
{
    /**
     * @return list<TaggedSubHarvestableConfig>
     */
    public function getTaggedConfigs(): array
    {
        return $this->hydrateAll('multiConfig/taggedConfigs/TaggedSubHarvestableConfig', TaggedSubHarvestableConfig::class, $this->isReferenceHydrationEnabled());
    }
}
