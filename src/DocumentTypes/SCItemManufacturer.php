<?php

namespace Octfx\ScDataDumper\DocumentTypes;

final class SCItemManufacturer extends RootDocument
{
    public function getCode(): ?string
    {
        return $this->get('@Code');
    }

    /**
     * Code from the element name suffix (`SCItemManufacturer.LBCO` -> `LBCO`)
     */
    public function getElementCode(): ?string
    {
        $nodeName = $this->documentElement?->nodeName ?? '';

        if (! str_starts_with($nodeName, 'SCItemManufacturer.')) {
            return null;
        }

        $suffix = substr($nodeName, strlen('SCItemManufacturer.'));

        return $suffix === '' ? null : $suffix;
    }
}
