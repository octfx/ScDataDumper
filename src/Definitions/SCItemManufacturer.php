<?php

namespace Octfx\ScDataDumper\Definitions;

final class SCItemManufacturer extends Element
{
    public function getCode(): ?string
    {

        $array = $this->toArrayRecursive($this);

        return $array['Code'] ?? null;
    }

    public function toArray(): array
    {
        $array = $this->toArrayRecursive($this);
        unset($array['Localization']['displayFeatures']);

        return $array;
    }
}
