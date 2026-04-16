<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class MissionItemValue extends RootDocument
{
    use HasTagSearchTerms;

    public function getMinItemsToFind(): int
    {
        return (int) ($this->get('@minItemsToFind') ?? 0);
    }

    public function getMaxItemsToFind(): int
    {
        return (int) ($this->get('@maxItemsToFind') ?? 0);
    }

    /**
     * @return list<string>
     */
    public function getSpecificItems(): array
    {
        return $this->queryAttributeValues(
            'matchConditions/DataSetMatchCondition_SpecificItemsDef/items/Reference',
            'value'
        );
    }

    public function getMatchConditionTagType(): ?string
    {
        return $this->getString('matchConditions/DataSetMatchCondition_TagSearch@tagType');
    }
}
