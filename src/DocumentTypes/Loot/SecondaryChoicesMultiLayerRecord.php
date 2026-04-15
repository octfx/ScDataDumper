<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SecondaryChoicesMultiLayerRecord extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getLayerReferences(): array
    {
        $refs = [];

        foreach ($this->getAll('layers/Reference@value') as $value) {
            if (is_string($value) && $value !== '') {
                $refs[] = $value;
            }
        }

        return $refs;
    }

    /**
     * @return list<SecondaryChoicesSingleLayerRecord>
     */
    public function getLayers(): array
    {
        $layers = [];

        foreach ($this->getLayerReferences() as $reference) {
            $resolved = ServiceFactory::getFoundryLookupService()
                ->getSecondaryChoicesSingleLayerByReference($reference);

            if ($resolved instanceof SecondaryChoicesSingleLayerRecord) {
                $layers[] = $resolved;
            }
        }

        return $layers;
    }
}
