<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class FuelTank extends BaseFormat
{
    protected ?string $elementKey = 'Components.SCItemFuelTankParams';

    public function __construct(Element $element, private readonly string $type = 'FuelTank')
    {
        parent::__construct($element);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $tank = $this->get();

        return [
            'Capacity' => (float) $tank['capacity'],
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && $this->item?->getAttachType() === $this->type;
    }
}
