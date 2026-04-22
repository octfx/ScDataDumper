<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @extends BaseFormat<EntityClassDefinition>
 */
final class PowerPlant extends BaseFormat
{
    protected ?string $elementKey = 'Components/ItemResourceComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $generation = $this->get('states/ItemResourceState/deltas/ItemResourceDeltaGeneration/generation/resourceAmountPerSecond/SPowerSegmentResourceUnit@units');

        return [
            /** @deprecated 4.7.0 PowerDraw no longer available from game data, use Generation instead */
            'Output' => $generation,
            'Generation' => $generation,
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'PowerPlant' && parent::canTransform();
    }
}
