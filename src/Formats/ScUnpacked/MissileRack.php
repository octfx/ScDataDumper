<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @extends BaseFormat<EntityClassDefinition>
 */
final class MissileRack extends BaseFormat
{
    protected ?string $elementKey = 'Components/SItemPortContainerComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $rack = $this->get();
        $ports = $rack->getAll('Ports/SItemPortDef');

        return [
            'MissileCount' => count($ports),
            'MissileSize' => ! empty($ports) ? $ports[0]->get('@MaxSize') : null,
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'MissileLauncher' && $this->item?->getAttachSubType() === 'MissileRack' && parent::canTransform();
    }
}
