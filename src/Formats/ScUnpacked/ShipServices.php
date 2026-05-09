<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Extracts ship service capabilities from ShipServicesProviderParams.
 *
 * Reads the servicesClass UUID to resolve the AreaServices record, then extracts
 * the service definitions (repair, restock, refuel quantum, refuel hydrogen) with
 * their rates and commodity references.
 *
 * Present on multi-crew ships with internal service facilities
 * (Carrack, 890J, 600i, Idris, Polaris, Starlancer TAC, etc.).
 *
 * @extends BaseFormat<EntityClassDefinition>
 */
final class ShipServices extends BaseFormat
{
    protected ?string $elementKey = 'Components/ShipServicesProviderParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $services = $this->get();

        $servicesClassUuid = $services?->get('@servicesClass');
        $record = $this->resolveServicesRecord($servicesClassUuid);

        $data = [
            'RepairAvailable' => (bool) $services?->get('@repairAvailable'),
            'UseZoneServicing' => (bool) $services?->get('@useZoneServicing'),
            'AllowHostile' => (bool) $services?->get('@allowHostile'),
        ];

        if ($record !== null) {
            $data['Services'] = $this->parseServiceDefinitions($record);
        }

        return $data;
    }

    /**
     * Load the AreaServices record for the given UUID.
     */
    private function resolveServicesRecord(?string $servicesClassUuid)
    {
        if ($servicesClassUuid === null || $servicesClassUuid === '') {
            return null;
        }

        return ServiceFactory::getFoundryLookupService()->getByReference($servicesClassUuid);
    }

    /**
     * Parse the four service definitions from the AreaServices record.
     *
     * RepairShipServiceDef → Repair
     * RestockShipServiceDef → Restock
     * RefuelShipServiceDefQuantum → RefuelQuantum
     * RefuelShipServiceDefHydrogen → RefuelHydrogen
     */
    private function parseServiceDefinitions($record): array
    {
        $services = [];

        $repair = $record->get('service/RepairShipServiceDef');
        if ($repair !== null) {
            $services['Repair'] = [
                'ServiceDelayTime' => (float) $repair->get('@serviceDelayTime'),
                'CommodityToHitPoints' => (float) $repair->get('@commodityToHitPoints'),
                'CommodityToDegradationLifetime' => (float) $repair->get('@commodityToDegradationLifetime'),
            ];
        }

        $restock = $record->get('service/RestockShipServiceDef');
        if ($restock !== null) {
            $services['Restock'] = [
                'ServiceDelayTime' => (float) $restock->get('@serviceDelayTime'),
            ];
        }

        $refuelQuantum = $record->get('service/RefuelShipServiceDefQuantum');
        if ($refuelQuantum !== null) {
            $services['RefuelQuantum'] = [
                'ServiceDelayTime' => (float) $refuelQuantum->get('@serviceDelayTime'),
                'InstantRefuel' => (bool) $refuelQuantum->get('@instantRefuel'),
                'RefuelUnitPerSecond' => (float) $refuelQuantum->get('@refuelUnitPerSecond'),
            ];
        }

        $refuelHydrogen = $record->get('service/RefuelShipServiceDefHydrogen');
        if ($refuelHydrogen !== null) {
            $services['RefuelHydrogen'] = [
                'ServiceDelayTime' => (float) $refuelHydrogen->get('@serviceDelayTime'),
                'InstantRefuel' => (bool) $refuelHydrogen->get('@instantRefuel'),
                'RefuelUnitPerSecond' => (float) $refuelHydrogen->get('@refuelUnitPerSecond'),
            ];
        }

        return $services;
    }
}
