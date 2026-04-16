<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

class HaulingOrdersValue extends RootDocument
{
    /**
     * @return list<array{type: string, entityClass: ?string, haulingEntityClasses: ?string, resource: ?string, minAmount: int, maxAmount: int, maxContainerSize: int, minSCU: int, maxSCU: int, missionItem: ?string, orOptions: list<list<array{type: string, resource: ?string, maxContainerSize: int, minSCU: int, maxSCU: int, entityClass: ?string, minAmount: int, maxAmount: int}>}>}>
     */
    public function getOrders(): array
    {
        $results = [];
        $container = $this->get('haulingOrderContent');

        if ($container === null) {
            return $results;
        }

        foreach ($container->children() as $child) {
            $type = $child->nodeName;

            if ($type === 'HaulingOrderContent_Or') {
                $results[] = $this->parseOrContent($child);

                continue;
            }

            if ($type === 'HaulingOrderContent_MissionItem') {
                $itemRef = $child->get('item@value');
                $results[] = [
                    'type' => 'MissionItem',
                    'entityClass' => null,
                    'haulingEntityClasses' => null,
                    'resource' => null,
                    'minAmount' => (int) ($child->get('@minAmount') ?? 0),
                    'maxAmount' => (int) ($child->get('@maxAmount') ?? 0),
                    'maxContainerSize' => 0,
                    'minSCU' => 0,
                    'maxSCU' => 0,
                    'missionItem' => is_string($itemRef) ? $itemRef : null,
                    'orOptions' => [],
                ];

                continue;
            }

            $results[] = [
                'type' => match ($type) {
                    'HaulingOrderContent_EntityClass' => 'EntityClass',
                    'HaulingOrderContent_EntityClasses' => 'EntityClasses',
                    'HaulingOrderContent_Resource' => 'Resource',
                    default => $type,
                },
                'entityClass' => $child->get('@entityClass'),
                'haulingEntityClasses' => $child->get('@haulingEntityClasses'),
                'resource' => $child->get('@resource'),
                'minAmount' => (int) ($child->get('@minAmount') ?? 0),
                'maxAmount' => (int) ($child->get('@maxAmount') ?? 0),
                'maxContainerSize' => (int) ($child->get('@maxContainerSize') ?? -1),
                'minSCU' => (int) ($child->get('@minSCU') ?? 0),
                'maxSCU' => (int) ($child->get('@maxSCU') ?? 0),
                'missionItem' => null,
                'orOptions' => [],
            ];
        }

        return $results;
    }

    /**
     * @return array{type: string, entityClass: null, haulingEntityClasses: null, resource: null, minAmount: int, maxAmount: int, maxContainerSize: int, minSCU: int, maxSCU: int, missionItem: null, orOptions: list<list<array{type: string, resource: ?string, maxContainerSize: int, minSCU: int, maxSCU: int, entityClass: ?string, haulingEntityClasses: ?string, minAmount: int, maxAmount: int}>>}
     */
    private function parseOrContent(mixed $orNode): array
    {
        $orOptions = [];
        $andNodes = $orNode->getAll('options/HaulingOrder_OrOption_And');

        foreach ($andNodes as $andNode) {
            $ordersList = [];
            $orders = $andNode->get('orders');

            if ($orders === null) {
                continue;
            }

            foreach ($orders->children() as $orderChild) {
                $ordersList[] = [
                    'type' => match ($orderChild->nodeName) {
                        'HaulingOrderContent_Resource' => 'Resource',
                        'HaulingOrderContent_EntityClass' => 'EntityClass',
                        'HaulingOrderContent_EntityClasses' => 'EntityClasses',
                        default => $orderChild->nodeName,
                    },
                    'resource' => $orderChild->get('@resource'),
                    'maxContainerSize' => (int) ($orderChild->get('@maxContainerSize') ?? -1),
                    'minSCU' => (int) ($orderChild->get('@minSCU') ?? 0),
                    'maxSCU' => (int) ($orderChild->get('@maxSCU') ?? 0),
                    'entityClass' => $orderChild->get('@entityClass'),
                    'haulingEntityClasses' => $orderChild->get('@haulingEntityClasses'),
                    'minAmount' => (int) ($orderChild->get('@minAmount') ?? 0),
                    'maxAmount' => (int) ($orderChild->get('@maxAmount') ?? 0),
                ];
            }

            $orOptions[] = $ordersList;
        }

        return [
            'type' => 'Or',
            'entityClass' => null,
            'haulingEntityClasses' => null,
            'resource' => null,
            'minAmount' => 0,
            'maxAmount' => 0,
            'maxContainerSize' => 0,
            'minSCU' => 0,
            'maxSCU' => 0,
            'missionItem' => null,
            'orOptions' => $orOptions,
        ];
    }

    /**
     * @return list<array{kind: string, uuid: ?string, name: ?string, min_amount: int, max_amount: int, max_container_size: int, min_scu: int, max_scu: int, items?: list<array{uuid: string, name: ?string}>, mission_item_ref?: string, or_options?: list<list<array>>}>
     */
    public function toArray(?string $variableName = null): array
    {
        $orders = [];
        foreach ($this->getOrders() as $order) {
            $orders[] = $this->buildOrderEntry($order);
        }

        return $orders;
    }

    private function buildOrderEntry(array $order): array
    {
        $itemService = ServiceFactory::getItemService();
        $lookup = ServiceFactory::getFoundryLookupService();

        $kind = match ($order['type']) {
            'EntityClass' => 'Entity',
            'EntityClasses' => 'Entities',
            'Resource' => 'Resource',
            'MissionItem' => 'MissionItem',
            'Or' => 'Or',
            default => $order['type'],
        };

        $uuid = null;
        $name = null;
        $items = null;

        if ($order['type'] === 'EntityClass' && $order['entityClass'] !== null) {
            $uuid = $order['entityClass'];
            $name = $itemService->getByReference($order['entityClass'])?->getDisplayName();
        } elseif ($order['type'] === 'EntityClasses' && $order['haulingEntityClasses'] !== null) {
            $uuid = $order['haulingEntityClasses'];
            $record = $lookup->getHaulingEntityClassesByReference($order['haulingEntityClasses']);
            $displayName = $record?->getStringValue('@orderDisplayName');
            $name = $displayName !== null ? ServiceFactory::getLocalizationService()->translateValue($displayName, true) : $record?->getClassName();

            $items = $this->resolveEntityClassItems($record, $itemService);
        } elseif ($order['type'] === 'Resource' && $order['resource'] !== null) {
            $uuid = $order['resource'];
            $resource = $lookup->getResourceTypeByReference($order['resource']);
            $name = $resource !== null ? ServiceFactory::getLocalizationService()->translateValue($resource->getDisplayName(), true) : null;
        }

        $result = [
            'kind' => $kind,
            'uuid' => $uuid,
            'name' => $name,
            'min_amount' => $order['minAmount'],
            'max_amount' => $order['maxAmount'],
            'max_container_size' => $order['maxContainerSize'],
            'min_scu' => $order['minSCU'],
            'max_scu' => $order['maxSCU'],
        ];

        if ($items !== null) {
            $result['items'] = $items;
        }

        if ($order['type'] === 'MissionItem') {
            $result['mission_item_ref'] = $order['missionItem'];
        }

        if ($order['type'] === 'Or') {
            $result['or_options'] = array_map(
                fn (array $andGroup): array => array_map(
                    fn (array $orOrder): array => $this->buildOrderEntry($orOrder),
                    $andGroup,
                ),
                $order['orOptions'],
            );
        }

        return $result;
    }

    private function resolveEntityClassItems(?FoundryRecord $record, $itemService): array
    {
        if ($record === null) {
            return [];
        }

        $seen = [];
        $items = [];

        foreach ($record->getAll('entityClasses//Reference@value') as $refUuid) {
            if (is_string($refUuid) && ! isset($seen[strtolower($refUuid)])) {
                $seen[strtolower($refUuid)] = true;
                $items[] = [
                    'uuid' => $refUuid,
                    'name' => $itemService->getByReference($refUuid)?->getDisplayName(),
                ];
            }
        }

        return $items;
    }
}
