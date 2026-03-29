<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SItemPortLoadoutEntryParams extends Element
{
    private const string NULL_UUID = '00000000-0000-0000-0000-000000000000';

    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('InstalledItem')) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getItemService();

        $className = $this->get('@entityClassName');
        $uuid = $this->get('@entityClassReference');

        if ($uuid && $uuid !== '00000000-0000-0000-0000-000000000000') {
            $item = $svc->getByReference($uuid);
        } else {
            $item = $svc->getByClassName($className);
        }

        if ($item) {
            if ($this->hasCircularAncestorReference($item->getUuid(), $item->getClassName())) {
                return;
            }

            if ($this->get('InstalledItem@__ref') === $item->getUuid()) {
                return;
            }

            $importedNode = $document->importNode($item->documentElement, true);
            $element = $document->createElement('InstalledItem');

            if ($item->firstChild?->attributes) {
                foreach ($item->firstChild?->attributes as $name => $attribute) {
                    $element->setAttribute($name, $attribute->nodeValue);
                }
            }

            $element->append(...$importedNode->childNodes);
            $this->node->appendChild($element);
        }
    }

    private function hasCircularAncestorReference(?string $uuid, ?string $className): bool
    {
        for ($ancestor = $this->node->parentNode; $ancestor !== null; $ancestor = $ancestor->parentNode) {
            if ($ancestor->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($ancestor->nodeName === 'InstalledItem') {
                $ancestorUuid = $ancestor->attributes?->getNamedItem('__ref')?->nodeValue;
                if ($this->matchesUuid($uuid, $ancestorUuid)) {
                    return true;
                }
            }

            if ($ancestor->nodeName === 'SItemPortLoadoutEntryParams') {
                $ancestorUuid = $ancestor->attributes?->getNamedItem('entityClassReference')?->nodeValue;
                if ($this->matchesUuid($uuid, $ancestorUuid)) {
                    return true;
                }

                $ancestorClassName = $ancestor->attributes?->getNamedItem('entityClassName')?->nodeValue;
                if ($className !== null && $className !== '' && $ancestorClassName === $className) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matchesUuid(?string $expected, ?string $actual): bool
    {
        if ($expected === null || $expected === '' || $expected === self::NULL_UUID) {
            return false;
        }

        return $actual === $expected;
    }
}
