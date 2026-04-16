<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mission;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MissionLocality extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getAvailableLocationReferences(): array
    {
        return $this->queryAttributeValues('availableLocations/Reference', 'value');
    }
}
