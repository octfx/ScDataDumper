<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\DOMElementProxy;

final class MissileRack extends BaseFormat
{
    protected ?string $elementKey = 'Components/SItemPortContainerComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $rack = $this->get();

        return [
            'Count' => count($rack->childNodes),
            'Size' => (new DOMElementProxy($rack->getNode()->firstChild))->get('MaxSize'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'MissileLauncher' && $this->item?->getAttachSubType() === 'MissileRack' && parent::canTransform();
    }
}
