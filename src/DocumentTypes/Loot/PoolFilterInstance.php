<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class PoolFilterInstance extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getMode(): ?string
    {
        return $this->getString('@mode');
    }

    public function getFilterRecordReference(): ?string
    {
        return $this->getString('filter/PoolFilter_RecordRef@filterRecord');
    }
}
