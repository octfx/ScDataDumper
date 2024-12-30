<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

class ItemPort extends BaseFormat
{
    protected ?string $elementKey = 'SItemPortDef';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $port = $this->item;

        $stdPort = [
            'PortName' => $this instanceof VehiclePartPort ? $port->parentNode->attributes->getNamedItem('name')?->nodeValue : $port->get('@Name'),
            'DisplayName' => $this instanceof VehiclePartPort ? $port->get('@display_name') : null,
            'Size' => $port->get('@MaxSize') ?? $port->get('@maxSize'),
            'Flags' => $this->buildFlagsList($port),
            'Tags' => array_filter(explode(' ', $port->get('@PortTags'))),
            'RequiredTags' => array_filter(explode(' ', $port->get('@RequiredPortTags'))),
            'Types' => $this->buildTypesList($port),
        ];

        $stdPort['Uneditable'] = in_array('$uneditable', $stdPort['Flags'], true) || in_array('uneditable', $stdPort['Flags'], true);

        return $stdPort;
    }

    private function buildTypesList($port): array
    {
        $types = [];

        foreach ($port->get('/Types')?->children() ?? [] as $portType) {
            $major = $this instanceof VehiclePartPort ? $portType->get('@type') : $portType->get('@Type');

            $subtypeKey = $this instanceof VehiclePartPort ? '@subtypes' : '@SubTypes';

            if (! empty($portType->get($subtypeKey))) {
                $types[] = Item::buildTypeName($major, null);
            } else {
                foreach ($portType->get('/SubTypes')?->children() ?? [] as $subType) {
                    $minor = $subType->get('value');
                    $types[] = Item::buildTypeName($major, $minor);
                }

                foreach (explode(',', $portType->get($subtypeKey)) as $subType) {
                    $types[] = Item::buildTypeName($major, $subType);
                }
            }
        }

        return $types;
    }

    private function buildFlagsList($port): ?array
    {
        $flags = explode(' ', $port->get('@Flags') ?? $port->get('@flags'));

        return array_filter(array_map('trim', $flags));
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'SItemPortDef';
    }
}
