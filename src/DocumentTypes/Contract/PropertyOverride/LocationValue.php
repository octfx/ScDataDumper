<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class LocationValue extends RootDocument
{
    use HasTagSearchTerms;

    public function getLogErrorOnSearchFail(): bool
    {
        return $this->getBool('@logErrorOnSearchFail');
    }

    /**
     * @return list<string>
     */
    public function getResourceTags(): array
    {
        return $this->queryAttributeValues('resourceTags/Reference', 'value');
    }

    public function getMatchConditionTagType(): ?string
    {
        return $this->getString('matchConditions/DataSetMatchCondition_TagSearch@tagType');
    }
}
