<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class LocationsValue extends RootDocument
{
    use HasTagSearchTerms;

    public function getLogErrorOnSearchFail(): bool
    {
        return $this->getBool('@logErrorOnSearchFail');
    }

    public function getMinLocationsToFind(): ?int
    {
        return $this->getInt('@minLocationsToFind');
    }

    public function getMaxLocationsToFind(): ?int
    {
        return $this->getInt('@maxLocationsToFind');
    }

    public function getFailIfMinAmountNotFound(): bool
    {
        return $this->getBool('@failIfMinAmountNotFound');
    }

    public function getMatchConditionTagType(): ?string
    {
        return $this->getString('matchConditions/DataSetMatchCondition_TagSearch@tagType');
    }
}
