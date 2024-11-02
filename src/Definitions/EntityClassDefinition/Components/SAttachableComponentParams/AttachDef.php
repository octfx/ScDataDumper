<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class AttachDef extends Element
{
    public function toArray(): array
    {
        return [
            'Manufacturer' => ServiceFactory::getManufacturerService()->getByReference((string) $this->attributes()['Manufacturer'])?->toArray() ?? [],
        ];
    }
}
