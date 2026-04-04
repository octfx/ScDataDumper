<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SItemPortLoadoutXMLParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        $path = $this->get('@loadoutPath');

        if ($path === null) {
            return;
        }

        $loadOutSvc = ServiceFactory::getLoadoutFileService();

        $file = $loadOutSvc->getByLoadoutPath($path);

        if ($file === null || ! $file->get('//Loadout/Items')?->getNode()->hasChildNodes()) {
            return;
        }

        parent::initialize($document);

        $parentEl = new Element($this->node->parentNode);
        if ($parentEl->get('SItemPortLoadoutManualParams')) {
            return;
        }

        $manualParams = $parentEl->get('SItemPortLoadoutManualParams');

        foreach ($this->node->parentNode->childNodes as $childNode) {
            if ($childNode->nodeName === 'SItemPortLoadoutManualParams') {
                return;
            }
        }

        if (! $manualParams) {
            $manualParams = $document->createElement('SItemPortLoadoutManualParams');

            $entries = $document->createElement('entries');
            $manualParams->appendChild($entries);

            $this->node->parentNode->appendChild($manualParams);
        } else {
            $entries = $manualParams->get('entries');
        }

        foreach ($file->getEntries() as $entry) {
            $portName = $entry->getPortName();
            $className = $entry->getEntityClassName();

            if ($portName === null || $portName === '' || $className === null || $className === '') {
                continue;
            }

            $port = $document->createElement('SItemPortLoadoutEntryParams');
            $port->setAttribute('itemPortName', $portName);
            $port->setAttribute('entityClassName', $className);
            $entries->appendChild($port);
        }

        $this->node->parentNode->appendChild($manualParams);
    }
}
