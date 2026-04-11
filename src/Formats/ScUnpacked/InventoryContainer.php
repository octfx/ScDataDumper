<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class InventoryContainer extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemInventoryContainerComponentParams@containerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $container = $this->item?->getInventoryContainer();

        return [
            'SCU' => $container?->getSCU(),
            'Unit' => $container?->getScuConversionUnit(),
            'UnitName' => $container?->getCapacityName(),
            ...($container?->getInteriorDimensions() ?? []),
            'MinSize' => $container?->getMinPermittedItemSize(),
            'MaxSize' => $container?->getMaxPermittedItemSize(),
            'IsOpenContainer' => $container?->isOpenContainer(),
            'IsExternalContainer' => $container?->isExternalContainer(),
            'IsClosedContainer' => $container?->isClosedContainer(),
            'UUID' => $container?->getUuid(),
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && $this->item?->getInventoryContainer() !== null;
    }
}
