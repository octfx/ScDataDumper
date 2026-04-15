<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class SecondaryChoiceEntry extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getWeight(): ?float
    {
        return $this->getFloat('@weight');
    }

    /**
     * @return list<string>
     */
    public function getPositiveTags(): array
    {
        return $this->queryAttributeValues(
            'selector/LootV3SecondaryChoiceEntrySelector_Tags/tags/positiveTags/Reference',
            'value'
        );
    }

    /**
     * @return list<string>
     */
    public function getNegativeTags(): array
    {
        return $this->queryAttributeValues(
            'selector/LootV3SecondaryChoiceEntrySelector_Tags/tags/negativeTags/Reference',
            'value'
        );
    }
}
