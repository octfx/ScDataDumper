<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class InventoryContainer extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemInventoryContainerComponentParams/containerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $container = ServiceFactory::getInventoryContainerService()->getByReference($this->get());

        return [
            'SCU' => $container?->getSCU(),
            'unit' => $container?->getScuConversionUnit(),
            ...($container?->getInteriorDimensions() ?? []),
            'minSize' => $container?->getMinPermittedItemSize(),
            'maxSize' => $container?->getMaxPermittedItemSize(),
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && ServiceFactory::getInventoryContainerService()->getByReference($this->get());
    }
}
