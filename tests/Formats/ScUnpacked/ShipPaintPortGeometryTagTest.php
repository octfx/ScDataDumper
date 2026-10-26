<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * End-to-end coverage of paint port tag derivation from SubGeometry tags.
 *
 * The Constellation Mk V regression: the port definition carries the stale
 * Mk IV RequiredPortTags "Paint_Constellation" while the Mk V liveries
 * require "Paint_Constellation_Mk5". The hull's SubGeometry tags (with one
 * miscased CIG variant) plus the template item must promote the port tag.
 */
final class ShipPaintPortGeometryTagTest extends ScDataTestCase
{
    public function test_stale_port_tag_is_promoted_to_the_hull_generation(): void
    {
        $result = $this->buildShipArray(
            portTags: 'Paint_Constellation',
            subGeometryTags: self::mk5SubGeometryTags(),
            seedMk5Template: true,
        );

        self::assertSame(
            ['Paint_Constellation_Mk5'],
            $this->findPaintPort($result)['RequiredTags'] ?? null,
        );
    }

    public function test_matching_port_tag_is_kept(): void
    {
        $result = $this->buildShipArray(
            portTags: 'Paint_Constellation_Mk5',
            subGeometryTags: self::mk5SubGeometryTags(),
            seedMk5Template: true,
        );

        self::assertSame(
            ['Paint_Constellation_Mk5'],
            $this->findPaintPort($result)['RequiredTags'] ?? null,
        );
    }

    public function test_untagged_port_is_filled_from_the_hull_generation(): void
    {
        $result = $this->buildShipArray(
            portTags: null,
            subGeometryTags: self::mk5SubGeometryTags(),
            seedMk5Template: true,
        );

        self::assertSame(
            ['Paint_Constellation_Mk5'],
            $this->findPaintPort($result)['RequiredTags'] ?? null,
        );
    }

    public function test_incomplete_geometry_does_not_promote_without_a_template_item(): void
    {
        // Special editions list only their event livery in SubGeometry while the
        // port still accepts the full family (e.g. Starfarer Invictus).
        $result = $this->buildShipArray(
            portTags: 'Paint_Starfarer',
            subGeometryTags: ['Paint_Starfarer_Invictus_Blue_Gold'],
            seedMk5Template: false,
        );

        self::assertSame(
            ['Paint_Starfarer'],
            $this->findPaintPort($result)['RequiredTags'] ?? null,
        );
    }

    public function test_family_without_template_resolution_is_kept(): void
    {
        // Mk IV geometry has no Paint_Constellation_Template item; the port tag
        // must survive unchanged instead of being derived from a prefix scan.
        $result = $this->buildShipArray(
            portTags: 'Paint_Constellation',
            subGeometryTags: [
                'Paint_Constellation_Invictus_Blue_Gold',
                'Paint_Constellation_Black_Black_Orange',
            ],
            seedMk5Template: false,
        );

        self::assertSame(
            ['Paint_Constellation'],
            $this->findPaintPort($result)['RequiredTags'] ?? null,
        );
    }

    public function test_tied_casing_resolves_the_template_in_either_order(): void
    {
        $tags = ['Paint_Constellation_mk5_Red', 'Paint_Constellation_Mk5_Blue'];

        foreach ([$tags, array_reverse($tags)] as $orderedTags) {
            $result = $this->buildShipArray($orderedTags, 'Paint_Constellation', true);
            self::assertSame(['Paint_Constellation_Mk5'], $this->findPaintPort($result)['RequiredTags'] ?? null);
        }
    }

    public function test_miscased_geometry_resolves_the_indexed_template(): void
    {
        $result = $this->buildShipArray(
            ['Paint_Constellation_mk5_Red', 'Paint_Constellation_mk5_Blue'],
            'Paint_Constellation',
            true,
        );

        self::assertSame(['Paint_Constellation_Mk5'], $this->findPaintPort($result)['RequiredTags'] ?? null);
    }

    public function test_untagged_port_uses_livery_when_template_is_missing(): void
    {
        $result = $this->buildShipArray(
            ['Paint_Constellation_Black_Black_Orange', 'Paint_Constellation_Invictus_Blue_Gold'],
            null,
            false,
        );

        self::assertSame(['Paint_Constellation'], $this->findPaintPort($result)['RequiredTags'] ?? null);
    }

    /**
     * @return list<string>
     */
    private static function mk5SubGeometryTags(): array
    {
        return [
            'Paint_Constellation_Mk5_Black_Gray_Purple',
            'Paint_Constellation_Mk5_White_Orange_Gray',
            'Paint_Constellation_Mk5_Blue_Black_Yellow',
            'Paint_Constellation_Mk5_Blue_Orange_Blue',
            'Paint_Constellation_Mk5_Red_Gray_Yellow',
            'Paint_Constellation_mk5_Black_Red_Red',
            'Paint_Constellation_Mk5_Black_Black_Orange',
        ];
    }

    /**
     * @param  list<string>  $subGeometryTags
     */
    private function buildShipArray(array $subGeometryTags, ?string $portTags, bool $seedMk5Template): array
    {
        $manufacturerPath = $this->writeFallbackManufacturerFile();

        $entityClassDefinitions = [];

        if ($seedMk5Template) {
            $mk5TemplatePath = $this->writePaintItemFile(
                'Paint_Constellation_Mk5_Template',
                'paint_constellation_mk5_template',
                '00000000-0000-0000-0000-00000000mk5t',
                'Paint_Constellation_Mk5',
            );
            $entityClassDefinitions['Paint_Constellation_Mk5_Template'] = $mk5TemplatePath;
        }

        // The Mk IV paint is the prefix-scan trap: it must never be consulted
        // when deciding whether to promote an explicit port tag.
        $mk4PaintPath = $this->writePaintItemFile(
            'Paint_Constellation_Black_Black_Orange',
            'paint_constellation_black_black_orange',
            '00000000-0000-0000-0000-0000000mk4001',
            'Paint_Constellation',
        );
        $entityClassDefinitions['Paint_Constellation_Black_Black_Orange'] = $mk4PaintPath;

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => $entityClassDefinitions,
                'SCItemManufacturer' => ['FALLBACK' => $manufacturerPath],
            ],
            uuidToClassMap: [
                '11111111-1111-1111-1111-111111111111' => 'FALLBACK',
            ],
            classToUuidMap: ['FALLBACK' => '11111111-1111-1111-1111-111111111111'],
            uuidToPathMap: ['11111111-1111-1111-1111-111111111111' => $manufacturerPath],
        );

        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile($subGeometryTags));

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile($portTags));

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, []));

        return $ship->toArray();
    }

    private function configureServiceFactory(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            "vehicle_name=Test Ship\nvehicle_description=Description\nvehicle_career=Combat\nvehicle_role=Fighter\nLOC_EMPTY=",
        );

        $inventoryContainerService = new InventoryContainerService($this->tempDir);
        $inventoryContainerService->initialize();

        $manufacturerService = new ManufacturerService($this->tempDir);
        $manufacturerService->initialize();

        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $localizationService = new LocalizationService($this->tempDir);
        $localizationService->initialize();

        $this->setPrivateProperty(ServiceFactory::class, 'initialized', true);
        $this->setPrivateProperty(ServiceFactory::class, 'activeScDataPath', $this->tempDir);
        $this->setPrivateProperty(ServiceFactory::class, 'services', [
            'InventoryContainerService' => $inventoryContainerService,
            'ManufacturerService' => $manufacturerService,
            'ItemService' => $itemService,
            'LocalizationService' => $localizationService,
        ]);
    }

    /**
     * @param  list<string>  $subGeometryTags
     */
    private function writeVehicleEntityFile(array $subGeometryTags): string
    {
        $subGeometry = implode("\n", array_map(
            static fn (string $tags): string => sprintf('<SGeometryNodeParams Tags="%s" />', $tags),
            $subGeometryTags,
        ));

        return $this->writeFile(
            'records/entity/test_ship.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP __type="EntityClassDefinition" __ref="ship-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="2" manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" vehicleCareer="@vehicle_career" vehicleRole="@vehicle_role" />
                    <SGeometryResourceParams __type="SGeometryResourceParams">
                        <Geometry __type="GlobalResourceGeometry">
                            <SubGeometry>{$subGeometry}</SubGeometry>
                        </Geometry>
                    </SGeometryResourceParams>
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="11111111-1111-1111-1111-111111111111" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.TEST_SHIP>
            XML,
        );
    }

    private function writeVehicleImplementationFile(?string $portTags): string
    {
        $portTagsAttribute = $portTags !== null ? sprintf(' RequiredPortTags="%s"', $portTags) : '';

        return $this->writeFile(
            'records/vehicles/test_ship_impl.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Vehicle.TEST_SHIP_IMPL>
                <Parts>
                    <Part name="hardpoint_paint" mass="100" damageMax="500">
                        <ItemPort maxSize="1" minSize="1"{$portTagsAttribute}>
                            <Types>
                                <Type type="Paints" />
                            </Types>
                        </ItemPort>
                    </Part>
                </Parts>
            </Vehicle.TEST_SHIP_IMPL>
            XML,
        );
    }

    private function writePaintItemFile(
        string $className,
        string $fileName,
        string $uuid,
        string $requiredTag,
    ): string {
        return $this->writeFile(
            "Data/Libs/Foundry/Records/entities/items/{$fileName}.xml",
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.{$className} __type="EntityClassDefinition" __ref="{$uuid}" __path="libs/foundry/records/entities/items/{$fileName}.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Paints" SubType="UNDEFINED" Size="1" Grade="1" RequiredTags="{$requiredTag}">
                    <Localization>
                      <English Name="Factory Livery" Description="" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.{$className}>
            XML,
        );
    }

    private function findPaintPort(array $result): ?array
    {
        foreach ($result['Loadout'] ?? [] as $entry) {
            if (($entry['HardpointName'] ?? '') === 'hardpoint_paint') {
                return $entry;
            }
        }

        return null;
    }
}
