<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class PoolFilterRecord extends RootDocument
{
    /**
     * @return list<PoolFilterInstance>
     */
    public function getFilterInstances(): array
    {
        $instances = [];

        foreach ($this->getAll('filter/PoolFilter_Sequence/filters/PoolFilterInstance') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $instance = PoolFilterInstance::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($instance instanceof PoolFilterInstance) {
                $instances[] = $instance;
            }
        }

        return $instances;
    }
}
