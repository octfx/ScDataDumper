<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\Contract\MissionPropertyOverride;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class CombinedDataSetEntriesValue extends RootDocument
{
    /**
     * @return list<MissionPropertyOverride>
     */
    public function getProperties(): array
    {
        $results = [];
        $properties = $this->getAll('dataSetEntryProperties/MissionProperty');

        foreach ($properties as $property) {
            $override = MissionPropertyOverride::fromNode(
                $property->getNode(),
                $this->isReferenceHydrationEnabled()
            );

            if ($override instanceof MissionPropertyOverride) {
                $results[] = $override;
            }
        }

        return $results;
    }
}
