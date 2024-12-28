<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\DOMElementProxy;

final class FuelTank extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemFuelTankParams';

    public function __construct(RootDocument|DOMElementProxy $element, private readonly string $type = 'FuelTank')
    {
        parent::__construct($element);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'Capacity' => (float) $this->get('capacity'),
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && $this->item?->getAttachType() === $this->type;
    }
}
