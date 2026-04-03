<?php

namespace Octfx\ScDataDumper\DocumentTypes\Starmap;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class Jurisdiction extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getMaxStolenGoodsPossessionScu(): ?int
    {
        return $this->getInt('@maxStolenGoodsPossessionSCU');
    }

    public function getBaseFine(): ?int
    {
        return $this->getInt('@baseFine');
    }

    public function getIsPrison(): bool
    {
        return $this->getBool('@isPrison');
    }
}
