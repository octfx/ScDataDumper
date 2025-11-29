<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Interactions extends BaseFormat
{
    protected ?string $elementKey = 'Components/SEntityInteractableParams/Interactable/SharedInteractions';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return [];
        }

        $interactions = $this->get();

        if (! $interactions instanceof Element) {
            return [];
        }

        return collect($interactions->children())
            ->map(fn (Element $child) => $child->get('Name'))
            ->filter()
            ->unique()
            ->map('trim')
            ->map('strtolower')
            ->map('ucwords')
            ->toArray();
    }
}
