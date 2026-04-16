<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class AINameValue extends RootDocument
{
    public function isRandomName(): bool
    {
        return $this->getBool('@randomName');
    }

    public function isRandomLastName(): bool
    {
        return $this->getBool('@randomLastName');
    }

    public function isRandomNickName(): bool
    {
        return $this->getBool('@randomNickName');
    }

    public function getCharacterGivenName(): ?string
    {
        return $this->getString('@characterGivenName');
    }

    public function getCharacterGivenLastName(): ?string
    {
        return $this->getString('@characterGivenLastName');
    }

    public function getCharacterGivenNickName(): ?string
    {
        return $this->getString('@characterGivenNickName');
    }

    public function getCharacterNameDataReference(): ?string
    {
        return $this->getString('@characterNameData');
    }

    public function getChanceOfNickName(): ?float
    {
        return $this->getFloat('@chanceOfNickName');
    }
}
