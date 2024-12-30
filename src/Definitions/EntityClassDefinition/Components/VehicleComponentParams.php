<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams\AttachDef\Localization;
use Octfx\ScDataDumper\Services\ServiceFactory;

class VehicleComponentParams extends Localization
{
    protected array $keys = [
        'vehicleName',
        'vehicleDescription',
        'vehicleCareer',
        'vehicleRole',
    ];

    public function initialize(DOMDocument $document): void
    {
        if ($this->initialized) {
            return;
        }

        parent::initialize($document);

        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($this->get('@Manufacturer'));
        $this->appendNode($document, $manufacturer, 'Manufacturer');
    }
}
