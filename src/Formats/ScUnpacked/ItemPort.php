<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class ItemPort extends BaseFormat
{
    protected ?string $elementKey = 'SItemPortDef';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $port = $this->item;

        $stdPort = [
            'PortName' => $port->get('Name'),
            'Size' => $port->get('MaxSize'),
            'Flags' => $this->buildFlagsList($port),
            'Tags' => array_filter(explode(' ', $port->get('PortTags'))),
            'RequiredTags' => array_filter(explode(' ', $port->get('RequiredPortTags'))),
            'Types' => $this->buildTypesList($port),
        ];

        $stdPort['Uneditable'] = in_array('$uneditable', $stdPort['Flags'], true) || in_array('uneditable', $stdPort['Flags'], true);

        return $stdPort;
    }

    private function buildTypesList($port): array
    {
        $types = [];

        foreach ($port->get('Types', [])->children() as $portType) {
            $major = $portType->get('Type');
            if ($portType->get('SubType')) {
                $types[] = Item::buildTypeName($major, null);
            } else {
                foreach ($portType->get('SubTypes')?->children() as $subType) {
                    $minor = $subType->get('value');
                    $types[] = Item::buildTypeName($major, $minor);
                }
            }
        }

        return $types;
    }

    private function buildFlagsList($port): ?array
    {
        $flags = explode(' ', $port->get('Flags'));

        return array_filter(array_map('trim', $flags));
    }

    public function canTransform(): bool
    {
        return $this->item?->getName() === 'SItemPortDef';
    }
}
