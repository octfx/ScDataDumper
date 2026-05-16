<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Ship-to-ship service provider component (CryAstro-style repair/restock/refuel).
 *
 * Extracted from `Components/ShipServicesProviderParams` on vehicle entities.
 * Ships with this component can service other ships that land in their hangar,
 * providing repair, restock, quantum refuel, and hydrogen refuel.
 */
final class ShipServices extends BaseFormat
{
    protected ?string $elementKey = 'Components/ShipServicesProviderParams';

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element $params */
        $params = $this->get();

        $servicesClassUuid = $params->get('@servicesClass');

        $services = null;
        if ($servicesClassUuid !== null) {
            $record = ServiceFactory::getFoundryLookupService()->getAreaServicesByReference($servicesClassUuid);
            $services = $record?->getServices();
        }

        return $this->removeNullValues([
            'RepairAvailable' => (bool) $params->get('@repairAvailable'),
            'UseZoneServicing' => (bool) $params->get('@useZoneServicing'),
            'AllowHostile' => (bool) $params->get('@allowHostile'),
            'RequireBeingLanded' => (bool) $params->get('@usingRequireBeingLanded'),
            'Services' => $services,
        ]);
    }
}
