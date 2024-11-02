<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class MissileRack extends BaseFormat
{
    protected ?string $elementKey = 'Components.SItemPortContainerComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $rack = $this->get();

        return [
            'Count' => count($rack->children()),
            'Size' => $rack->children()[0]->get('MaxSize'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'MissileLauncher' && $this->item?->getAttachSubType() === 'MissileRack' && parent::canTransform();
    }
}
