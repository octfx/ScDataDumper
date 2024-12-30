<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Loadout extends BaseFormat
{
    protected ?string $elementKey = 'loadout';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $loadout = [
            'PortName' => $this->get('@itemPortName'),
            'ClassName' => $this->get('@entityClassName'),
            'Entries' => [],
        ];

        if ($this->get('/InstalledItem')) {
            $entity = EntityClassDefinition::fromNode($this->get('/InstalledItem')?->getNode()?->firstChild);

            $loadout['InstalledItem'] = (new Item($entity))->toArray();
        }

        foreach ($this->get('/entries')?->children() ?? [] as $subLoadout) {
            if ($subLoadout->get('@skipPart') === '1') {
                continue;
            }

            $loadout['Entries'][] = (new Loadout($subLoadout))->toArray();
        }

        return $loadout;
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'SItemPortLoadoutManualParams' || $this->item?->nodeName === 'SItemPortLoadoutEntryParams';
    }
}
