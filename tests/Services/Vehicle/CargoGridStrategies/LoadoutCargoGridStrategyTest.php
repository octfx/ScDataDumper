<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle\CargoGridStrategies;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\LoadoutCargoGridStrategy;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;
use ReflectionMethod;

final class LoadoutCargoGridStrategyTest extends ScDataTestCase
{
    private LoadoutCargoGridStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new LoadoutCargoGridStrategy;
    }

    public function test_nested_manual_recursion_normalizes_to_item_raw_and_keeps_legitimate_gating_strict(): void
    {
        $loadout = [
            'portName' => 'hardpoint_root',
            'ItemRaw' => [
                'className' => 'ROOT_NOT_A_GRID',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'Vehicle',
                        ],
                    ],
                    'SEntityComponentDefaultLoadoutParams' => [
                        'loadout' => [
                            'SItemPortLoadoutManualParams' => [
                                'entries' => [
                                    ['InstalledItem' => $this->buildItemRaw('LEGIT_MANUAL_GRID', 'CargoGrid', true)],
                                    ['InstalledItem' => $this->buildItemRaw('MANUAL_MISSING_CONTAINER', 'CargoGrid', false)],
                                    ['InstalledItem' => $this->buildItemRaw('MANUAL_WRONG_ATTACH_TYPE', 'Turret', true)],
                                    ['InstalledItem' => $this->buildItemRaw('MANUAL_MISSING_ATTACH_TYPE', null, true)],
                                    [
                                        'entries' => [
                                            ['ItemRaw' => $this->buildItemRaw('LEGIT_MANUAL_SUBENTRY_GRID', 'CargoGrid', true)],
                                            ['ItemRaw' => $this->buildItemRaw('MANUAL_SUBENTRY_WRONG_ATTACH_TYPE', 'Door', true)],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'entries' => [
                ['ItemRaw' => $this->buildItemRaw('LEGIT_DIRECT_ENTRY_GRID', 'CargoGrid', true)],
                ['Item' => $this->buildItemRaw('ITEM_KEY_ONLY_GRID_SHOULD_BE_IGNORED', 'CargoGrid', true)],
            ],
        ];

        $extracted = $this->invokeExtractCargoGrids($loadout)->values();
        $classNames = $extracted
            ->map(static fn (array $item): string => (string) ($item['className'] ?? $item['ClassName'] ?? ''))
            ->filter(static fn (string $className): bool => $className !== '')
            ->values()
            ->all();

        self::assertEqualsCanonicalizing(
            [
                'LEGIT_DIRECT_ENTRY_GRID',
                'LEGIT_MANUAL_GRID',
                'LEGIT_MANUAL_SUBENTRY_GRID',
            ],
            $classNames
        );
        self::assertNotContains('ITEM_KEY_ONLY_GRID_SHOULD_BE_IGNORED', $classNames);
        self::assertNotContains('MANUAL_MISSING_CONTAINER', $classNames);
        self::assertNotContains('MANUAL_WRONG_ATTACH_TYPE', $classNames);
        self::assertNotContains('MANUAL_MISSING_ATTACH_TYPE', $classNames);
        self::assertNotContains('MANUAL_SUBENTRY_WRONG_ATTACH_TYPE', $classNames);

        foreach ($extracted as $item) {
            self::assertSame('CargoGrid', Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Type'));
            self::assertTrue(Arr::has($item, 'Components.SCItemInventoryContainerComponentParams'));
        }
    }

    public function test_real_ship_fixture_regression_only_legitimate_non_template_grids_are_extracted(): void
    {
        $fixturePath = dirname(__DIR__, 3).'/Fixtures/exports/ships/aegs_reclaimer.json';
        self::assertFileExists($fixturePath);

        $fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
        $fixtureLoadout = $fixture['Loadout'] ?? null;
        self::assertIsArray($fixtureLoadout);

        $allFixtureEntries = $this->flattenExportLoadoutEntries($fixtureLoadout);
        $templateEntriesInFixture = array_values(array_filter(
            $allFixtureEntries,
            static fn (array $entry): bool => str_ends_with(strtolower((string) ($entry['ClassName'] ?? '')), '_template')
        ));
        self::assertNotEmpty($templateEntriesInFixture);

        $expectedCargoCount = count(array_filter(
            $allFixtureEntries,
            fn (array $entry): bool => $this->extractAttachTypeFromExportEntry($entry) === 'CargoGrid'
                && trim((string) ($entry['ClassName'] ?? '')) !== ''
                && ! str_ends_with(strtolower((string) ($entry['ClassName'] ?? '')), '_template')
        ));
        self::assertGreaterThan(0, $expectedCargoCount);

        $normalizedLoadout = array_map(
            fn (array $entry): array => $this->normalizeExportLoadoutEntry($entry),
            $fixtureLoadout
        );

        $extracted = (new Collection($normalizedLoadout))
            ->flatMap(fn (array $entry): Collection => $this->invokeExtractCargoGrids($entry))
            ->filter(fn (array $item): bool => ! $this->invokeIsTemplateGrid($item))
            ->values();

        self::assertCount($expectedCargoCount, $extracted);

        foreach ($extracted as $item) {
            self::assertSame('CargoGrid', Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Type'));
            self::assertTrue(Arr::has($item, 'Components.SCItemInventoryContainerComponentParams'));
            $className = strtolower((string) ($item['className'] ?? $item['ClassName'] ?? ''));
            self::assertNotSame('', $className);
            self::assertFalse(str_ends_with($className, '_template'));
        }
    }

    public function test_resolve_counts_capacity_from_resolved_inventory_container_refs(): void
    {
        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/cargogrid/test_grid.xml',
            <<<'XML'
            <InventoryContainer.TEST_GRID __type="InventoryContainer" __ref="uuid-grid" __path="libs/foundry/records/inventorycontainers/cargogrid/test_grid.xml">
              <interiorDimensions x="3.75" y="2.5" z="3.75" />
              <inventoryType>
                <InventoryOpenContainerType isExternalContainer="0">
                  <minPermittedItemSize x="1.25" y="1.25" z="1.25" />
                  <maxPermittedItemSize x="2.5" y="2.5" z="2.5" />
                </InventoryOpenContainerType>
              </inventoryType>
            </InventoryContainer.TEST_GRID>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_vehicle.xml',
            <<<'XML'
            <VehicleDefinition.TEST_VEHICLE __type="VehicleDefinition" __ref="uuid-vehicle" __path="libs/foundry/records/entities/vehicles/test_vehicle.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Vehicle" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </VehicleDefinition.TEST_VEHICLE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_VEHICLE' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_GRID' => $containerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-vehicle' => 'TEST_VEHICLE',
                'uuid-grid' => 'TEST_GRID',
            ],
            classToUuidMap: [
                'TEST_VEHICLE' => 'uuid-vehicle',
                'TEST_GRID' => 'uuid-grid',
            ],
            uuidToPathMap: [
                'uuid-vehicle' => $vehiclePath,
                'uuid-grid' => $containerPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $loadout = [[
            'portName' => 'hardpoint_cargogrid_main',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'TEST_CARGO_GRID',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'uuid-grid',
                    ],
                ],
            ],
        ]];

        $result = new CargoGridResult;
        $this->strategy->resolve(new VehicleWrapper(null, $vehicle, $loadout), $result);

        self::assertSame(18.0, $result->totalCapacity);
        self::assertCount(1, $result->grids);
        self::assertSame(18.0, $result->grids->first()['SCU']);
    }

    public function test_resolve_keeps_resolved_capacity_when_backing_container_class_is_template_named(): void
    {
        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/cargogrid/test_grid_template.xml',
            <<<'XML'
            <InventoryContainer.TEST_GRID_TEMPLATE __type="InventoryContainer" __ref="uuid-grid-template" __path="libs/foundry/records/inventorycontainers/cargogrid/test_grid_template.xml">
              <interiorDimensions x="2.5" y="20" z="2.5" />
              <inventoryType>
                <InventoryOpenContainerType isExternalContainer="0">
                  <minPermittedItemSize x="1.25" y="1.25" z="1.25" />
                  <maxPermittedItemSize x="2.5" y="2.5" z="10" />
                </InventoryOpenContainerType>
              </inventoryType>
            </InventoryContainer.TEST_GRID_TEMPLATE>
            XML
        );
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_vehicle_template_backing.xml',
            <<<'XML'
            <VehicleDefinition.TEST_VEHICLE_TEMPLATE_BACKING __type="VehicleDefinition" __ref="uuid-vehicle-template-backing" __path="libs/foundry/records/entities/vehicles/test_vehicle_template_backing.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Vehicle Template Backing" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </VehicleDefinition.TEST_VEHICLE_TEMPLATE_BACKING>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_VEHICLE_TEMPLATE_BACKING' => $vehiclePath,
                ],
                'InventoryContainer' => [
                    'TEST_GRID_TEMPLATE' => $containerPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-vehicle-template-backing' => 'TEST_VEHICLE_TEMPLATE_BACKING',
                'uuid-grid-template' => 'TEST_GRID_TEMPLATE',
            ],
            classToUuidMap: [
                'TEST_VEHICLE_TEMPLATE_BACKING' => 'uuid-vehicle-template-backing',
                'TEST_GRID_TEMPLATE' => 'uuid-grid-template',
            ],
            uuidToPathMap: [
                'uuid-vehicle-template-backing' => $vehiclePath,
                'uuid-grid-template' => $containerPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $loadout = [[
            'portName' => 'hardpoint_cargogrid_main',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'REAL_CARGO_GRID_ITEM',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'containerParams' => 'uuid-grid-template',
                    ],
                ],
            ],
        ]];

        $result = new CargoGridResult;
        $this->strategy->resolve(new VehicleWrapper(null, $vehicle, $loadout), $result);

        self::assertSame(64.0, $result->totalCapacity);
        self::assertCount(1, $result->grids);
        self::assertSame('TEST_GRID_TEMPLATE', $result->grids->first()['class']);
        self::assertSame(64.0, $result->grids->first()['SCU']);
    }

    public function test_resolve_keeps_inline_capacity_when_inventory_container_cannot_be_resolved(): void
    {
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_vehicle_inline.xml',
            <<<'XML'
            <VehicleDefinition.TEST_VEHICLE_INLINE __type="VehicleDefinition" __ref="uuid-vehicle-inline" __path="libs/foundry/records/entities/vehicles/test_vehicle_inline.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Vehicle Inline" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </VehicleDefinition.TEST_VEHICLE_INLINE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_VEHICLE_INLINE' => $vehiclePath,
                ],
                'InventoryContainer' => [],
            ],
            uuidToClassMap: [
                'uuid-vehicle-inline' => 'TEST_VEHICLE_INLINE',
            ],
            classToUuidMap: [
                'TEST_VEHICLE_INLINE' => 'uuid-vehicle-inline',
            ],
            uuidToPathMap: [
                'uuid-vehicle-inline' => $vehiclePath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $loadout = [[
            'portName' => 'hardpoint_cargogrid_main',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'UNRESOLVED_CARGO_GRID',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'inventoryContainer' => [
                            'interiorDimensions' => [
                                'x' => 35.0,
                                'y' => 2.5,
                                'z' => 5.0,
                            ],
                            'inventoryType' => [
                                'InventoryOpenContainerType' => [
                                    'isExternalContainer' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]];

        $result = new CargoGridResult;
        $this->strategy->resolve(new VehicleWrapper(null, $vehicle, $loadout), $result);

        self::assertSame(224.0, $result->totalCapacity);
        self::assertCount(1, $result->grids);
        self::assertSame(224.0, $result->grids->first()['SCU']);
    }

    public function test_resolve_excludes_template_grids_from_capacity(): void
    {
        $vehiclePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/vehicles/test_vehicle_template.xml',
            <<<'XML'
            <VehicleDefinition.TEST_VEHICLE_TEMPLATE __type="VehicleDefinition" __ref="uuid-vehicle-template" __path="libs/foundry/records/entities/vehicles/test_vehicle_template.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Vehicle" SubType="Ship">
                    <Localization>
                      <English Name="Test Vehicle Template" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </VehicleDefinition.TEST_VEHICLE_TEMPLATE>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_VEHICLE_TEMPLATE' => $vehiclePath,
                ],
                'InventoryContainer' => [],
            ],
            uuidToClassMap: [
                'uuid-vehicle-template' => 'TEST_VEHICLE_TEMPLATE',
            ],
            classToUuidMap: [
                'TEST_VEHICLE_TEMPLATE' => 'uuid-vehicle-template',
            ],
            uuidToPathMap: [
                'uuid-vehicle-template' => $vehiclePath,
            ],
        );
        $this->initializeMinimalItemServices();

        $vehicle = new VehicleDefinition;
        $vehicle->load($vehiclePath);

        $loadout = [[
            'portName' => 'hardpoint_cargogrid_template',
            'entries' => [],
            'ItemRaw' => [
                'className' => 'UNRESOLVED_CARGO_GRID_TEMPLATE',
                'Components' => [
                    'SAttachableComponentParams' => [
                        'AttachDef' => [
                            'Type' => 'CargoGrid',
                        ],
                    ],
                    'SCItemInventoryContainerComponentParams' => [
                        'inventoryContainer' => [
                            'interiorDimensions' => [
                                'x' => 35.0,
                                'y' => 2.5,
                                'z' => 5.0,
                            ],
                        ],
                    ],
                ],
            ],
        ]];

        $result = new CargoGridResult;
        $this->strategy->resolve(new VehicleWrapper(null, $vehicle, $loadout), $result);

        self::assertSame(0.0, $result->totalCapacity);
        self::assertCount(0, $result->grids);
    }

    private function invokeExtractCargoGrids(array $loadout): Collection
    {
        $method = new ReflectionMethod(LoadoutCargoGridStrategy::class, 'extractCargoGrids');

        $result = $method->invoke($this->strategy, $loadout);
        self::assertInstanceOf(Collection::class, $result);

        return $result;
    }

    private function invokeIsTemplateGrid(array $item): bool
    {
        $method = new ReflectionMethod(LoadoutCargoGridStrategy::class, 'isTemplateGrid');

        $result = $method->invoke($this->strategy, $item);
        self::assertIsBool($result);

        return $result;
    }

    private function buildItemRaw(string $className, ?string $attachType, bool $withInventoryContainer): array
    {
        $components = [];

        if ($attachType !== null) {
            $components['SAttachableComponentParams'] = [
                'AttachDef' => [
                    'Type' => $attachType,
                ],
            ];
        }

        if ($withInventoryContainer) {
            $components['SCItemInventoryContainerComponentParams'] = [
                'inventoryContainer' => [
                    'interiorDimensions' => [
                        'x' => 1.25,
                        'y' => 1.25,
                        'z' => 1.25,
                    ],
                ],
            ];
        }

        return [
            'className' => $className,
            'Components' => $components,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flattenExportLoadoutEntries(array $entries): array
    {
        $flattened = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $flattened[] = $entry;

            $nested = $entry['Loadout'] ?? [];
            if (is_array($nested) && $nested !== []) {
                $flattened = array_merge($flattened, $this->flattenExportLoadoutEntries($nested));
            }
        }

        return $flattened;
    }

    private function extractAttachTypeFromExportEntry(array $entry): ?string
    {
        $semanticType = trim((string) ($entry['Type'] ?? ''));
        if ($semanticType === '') {
            return null;
        }

        $segments = explode('.', $semanticType, 2);
        $attachType = trim($segments[0] ?? '');

        return $attachType === '' ? null : $attachType;
    }

    /**
     * @return array{portName: mixed, entries: array<int, array<string, mixed>>, ItemRaw?: array<string, mixed>}
     */
    private function normalizeExportLoadoutEntry(array $entry): array
    {
        $normalized = [
            'portName' => $entry['HardpointName'] ?? null,
            'entries' => [],
        ];

        $className = trim((string) ($entry['ClassName'] ?? ''));
        if ($className !== '') {
            $attachType = $this->extractAttachTypeFromExportEntry($entry);

            $itemRaw = [
                'className' => $className,
                'Components' => [],
            ];

            if ($attachType !== null) {
                $itemRaw['Components']['SAttachableComponentParams'] = [
                    'AttachDef' => ['Type' => $attachType],
                ];
            }

            if ($attachType === 'CargoGrid') {
                $itemRaw['Components']['SCItemInventoryContainerComponentParams'] = [
                    'inventoryContainer' => [
                        'interiorDimensions' => ['x' => 1.25, 'y' => 1.25, 'z' => 1.25],
                    ],
                ];
            }

            $normalized['ItemRaw'] = $itemRaw;
        }

        $nested = $entry['Loadout'] ?? [];
        if (is_array($nested)) {
            $normalized['entries'] = array_map(
                fn (array $child): array => $this->normalizeExportLoadoutEntry($child),
                array_values(array_filter($nested, 'is_array'))
            );
        }

        return $normalized;
    }
}
