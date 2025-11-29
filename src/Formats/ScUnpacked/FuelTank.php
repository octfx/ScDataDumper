<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class FuelTank extends BaseFormat
{
    protected ?string $elementKey = 'Components/ResourceContainer';

    public function __construct(RootDocument|Element $element, private readonly string $type = 'FuelTank')
    {
        parent::__construct($element);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'Capacity' => (float) Item::convertToScu($this->get('capacity')),
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && $this->item?->getAttachType() === $this->type;
    }
}
