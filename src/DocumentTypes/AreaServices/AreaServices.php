<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\AreaServices;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;

/**
 * Represents an AreaServices foundry record (e.g. CryAstro_VehicleServices).
 *
 * These records define which services are available (repair, restock, refuel, etc.)
 * and their gameplay parameters (delay time, fuel rates, repair efficiency).
 *
 * Root element: `AreaServices.{name}`
 */
final class AreaServices extends RootDocument
{
    /**
     * Map of XML service element node names to canonical type identifiers.
     * Covers both ship-service variants (`RepairShipServiceDef`) and
     * station variants (`RepairService`).
     */
    private const array SERVICE_TYPE_MAP = [
        'RepairShipServiceDef' => 'Repair',
        'RestockShipServiceDef' => 'Restock',
        'RefuelShipServiceDefQuantum' => 'RefuelQuantum',
        'RefuelShipServiceDefHydrogen' => 'RefuelHydrogen',
        'RepairService' => 'Repair',
        'RestockService' => 'Restock',
        'QuantumRefuelService' => 'RefuelQuantum',
        'HydrogenRefuelService' => 'RefuelHydrogen',
    ];

    /**
     * All services offered by this record with their parameters.
     *
     * @return list<array{Name: string, DelayTime: float, CommodityToHitPoints: float|null, CommodityToDegradationLifetime: float|null, InstantRefuel: bool|null, RefuelUnitPerSecond: float|null}>|null
     */
    public function getServices(): ?array
    {
        $serviceRoot = $this->get('service');

        if ($serviceRoot === null) {
            return null;
        }

        $services = [];
        foreach ($serviceRoot->children() as $node) {
            $nodeName = $node->nodeName;

            if (! isset(self::SERVICE_TYPE_MAP[$nodeName])) {
                continue;
            }

            $services[] = $this->buildServiceEntry(self::SERVICE_TYPE_MAP[$nodeName], $node);
        }

        return $services !== [] ? $services : null;
    }

    /**
     * Build a structured service entry from an XML element.
     *
     * @return array{Name: string, DelayTime: float, CommodityToHitPoints: float|null, CommodityToDegradationLifetime: float|null, InstantRefuel: bool|null, RefuelUnitPerSecond: float|null}
     */
    private function buildServiceEntry(string $type, Element $node): array
    {
        return [
            'Name' => $type,
            'DelayTime' => (float) ($node->get('@serviceDelayTime') ?? 0),
            'CommodityToHitPoints' => $node->get('@commodityToHitPoints') !== null
                ? (float) $node->get('@commodityToHitPoints')
                : null,
            'CommodityToDegradationLifetime' => $node->get('@commodityToDegradationLifetime') !== null
                ? (float) $node->get('@commodityToDegradationLifetime')
                : null,
            'InstantRefuel' => $node->get('@instantRefuel') !== null
                ? (bool) (int) $node->get('@instantRefuel')
                : null,
            'RefuelUnitPerSecond' => $node->get('@refuelUnitPerSecond') !== null
                ? (float) $node->get('@refuelUnitPerSecond')
                : null,
        ];
    }
}
