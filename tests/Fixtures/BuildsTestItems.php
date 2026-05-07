<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Fixtures;

trait BuildsTestItems
{
    /**
     * Build a minimal item array stub for aggregator tests.
     */
    protected function makeItem(string $type, array $stdItem = [], array $extra = []): array
    {
        return array_replace_recursive(['type' => $type, 'stdItem' => $stdItem], $extra);
    }

    /**
     * Build a weapon item stub with DPS / alpha / sustained damage.
     */
    protected function makeWeapon(string $type, string $className, float $dps, float $alpha, ?float $sustained = null): array
    {
        $damage = ['DpsTotal' => $dps, 'AlphaTotal' => $alpha];
        if ($sustained !== null) {
            $damage['Sustained'] = $sustained;
        }

        return $this->makeItem($type, [
            'UUID' => $className.'_UUID',
            'ClassName' => $className,
            'Weapon' => ['Damage' => $damage],
        ]);
    }

    /**
     * Build a missile item stub.
     */
    protected function makeMissile(string $className, array $damageByType): array
    {
        return $this->makeItem('Missile', [
            'UUID' => $className.'_UUID',
            'ClassName' => $className,
            'Weapon' => ['Damage' => $damageByType],
        ]);
    }

    /**
     * Build a countermeasure item stub.
     */
    protected function makeCountermeasure(string $className, string $cmType, int $capacity): array
    {
        return $this->makeItem('WeaponDefensive', [
            'UUID' => $className.'_UUID',
            'ClassName' => $className,
            'CounterMeasure' => [
                'Type' => $cmType,
                'InitialResistance' => $capacity,
            ],
        ]);
    }

    /**
     * Build a bomb item stub.
     */
    protected function makeBomb(string $className, array $damageByType): array
    {
        return $this->makeItem('WeaponDefensive', [
            'UUID' => $className.'_UUID',
            'ClassName' => $className,
            'Explosive' => ['Damage' => $damageByType],
        ]);
    }

    /**
     * Wrap an array of items into standardised-parts format:
     * [{Name: "Part N", Port: {PortName: "Port N", InstalledItem: item}}]
     */
    protected function wrapItemsAsParts(array $items): array
    {
        return array_map(
            fn (array $item, int $i): array => [
                'Name' => "Part {$i}",
                'Port' => ['PortName' => "Port {$i}", 'InstalledItem' => $item],
            ],
            $items,
            array_keys($items),
        );
    }

    /**
     * Build a classifiable item array for ItemClassifierService tests.
     */
    protected function makeClassifiable(string $type, ?string $subType = null, string $path = '', string $tags = ''): array
    {
        return [
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => $type,
                        'SubType' => $subType,
                        'Tags' => $tags,
                    ],
                ],
            ],
            '__path' => $path,
        ];
    }
}
