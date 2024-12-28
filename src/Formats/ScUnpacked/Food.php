<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Food extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemConsumableParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $consumable = $this->get();

        return [
            'oneShotConsume' => $consumable->get('oneShotConsume'),
            'consumeVolume' => Item::convertToScu($consumable->get('Volume')),
            'containerClosed' => $consumable->get('containerClosed'),
            'canBeReclosed' => $consumable->get('canBeReclosed'),
            'discardWhenConsumed' => $consumable->get('discardWhenConsumed'),
            'containerTypeTag' => $consumable->get('containerTypeTag'),
        ];
    }
}
