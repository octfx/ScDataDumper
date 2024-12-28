<?php

namespace Octfx\ScDataDumper\DocumentTypes;

final class SCItemManufacturer extends RootDocument
{
    public function getCode(): ?string
    {
        return $this->get('Code');
    }
}
