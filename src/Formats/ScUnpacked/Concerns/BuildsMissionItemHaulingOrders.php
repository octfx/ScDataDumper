<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

trait BuildsMissionItemHaulingOrders
{
    /**
     * Enrich placeholder MissionItem hauling orders with explicit ItemCounts.Items data.
     *
     * @param  list<array>  $orders
     * @param  array{items?: list<array{uuid?: string, name?: ?string}>, min_items?: int, max_items?: int}  $itemCounts
     * @return list<array>
     */
    private function enrichMissionItemHaulingOrdersFromItemCounts(array $orders, array $itemCounts): array
    {
        $items = $itemCounts['items'] ?? [];

        if ($items === []) {
            return $orders;
        }

        return array_map(function (array $order) use ($items): array {
            if (($order['kind'] ?? null) === 'Or') {
                $order['or_options'] = array_map(
                    fn (array $group): array => array_map(
                        fn (array $nestedOrder): array => $this->enrichMissionItemHaulingOrdersFromItemCounts([$nestedOrder], ['items' => $items])[0],
                        $group,
                    ),
                    $order['or_options'] ?? [],
                );

                return $order;
            }

            if (($order['kind'] ?? null) !== 'MissionItem') {
                return $order;
            }

            if (($order['uuid'] ?? null) !== null || ! empty($order['items'] ?? [])) {
                return $order;
            }

            if (count($items) === 1) {
                $item = $items[0];
                $order['uuid'] = $item['uuid'] ?? null;
                $order['name'] = $item['name'] ?? null;
                $order['items'] = [];

                return $order;
            }

            $order['items'] = $items;

            return $order;
        }, $orders);
    }

    /**
     * @param  array{items?: list<array{uuid?: string, name?: ?string}>, min_items?: int, max_items?: int}  $itemCounts
     * @return list<array>
     */
    private function buildMissionItemHaulingOrdersFromItemCounts(array $itemCounts): array
    {
        $items = $itemCounts['items'] ?? [];

        if ($items === []) {
            return [];
        }

        if (count($items) === 1) {
            $item = $items[0];

            return [[
                'kind' => 'MissionItem',
                'name' => $item['name'] ?? null,
                'uuid' => $item['uuid'] ?? null,
                'items' => [],
                'max_amount' => $itemCounts['max_items'] ?? null,
                'min_amount' => $itemCounts['min_items'] ?? null,
            ]];
        }

        return [[
            'kind' => 'MissionItem',
            'items' => $items,
            'max_amount' => $itemCounts['max_items'] ?? null,
            'min_amount' => $itemCounts['min_items'] ?? null,
        ]];
    }
}
