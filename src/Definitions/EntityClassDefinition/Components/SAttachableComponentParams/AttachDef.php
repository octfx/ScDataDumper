<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class AttachDef extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        if ($this->get('Manufacturer@__ref') !== $this->get('@Manufacturer')) {
            $manufacturer = ServiceFactory::getManufacturerService()->getByReference($this->get('@Manufacturer'));
            $this->appendNode($document, $manufacturer, 'Manufacturer');
        }
    }
}
