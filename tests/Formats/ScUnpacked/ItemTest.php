<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ItemTest extends ScDataTestCase
{
    public function test_to_array_translates_raw_localized_fields_without_hydrated_english_nodes(): void
    {
        $manufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/fallback.xml',
            <<<'XML'
            <SCItemManufacturer.FALLBACK Code="FALL" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/fallback.xml">
                <Localization Name="@manufacturer_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.FALLBACK>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: ['11111111-1111-1111-1111-111111111111' => 'FALLBACK'],
            classToUuidMap: ['FALLBACK' => '11111111-1111-1111-1111-111111111111'],
            uuidToPathMap: ['11111111-1111-1111-1111-111111111111' => $manufacturerPath]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_name' => 'Fallback Industries',
            'item_name' => 'Localized Test Item',
            'item_description' => 'Manufacturer: Fallback Industries\nType: Test\n\nUtility mount.',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/test_item.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_ITEM __type="EntityClassDefinition" __ref="22222222-2222-2222-2222-222222222222" __path="libs/foundry/records/entities/scitem/test_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="SeatDashboard" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@item_description" />
                            <inventoryOccupancyDimensions x="1" y="1" z="1" />
                            <inventoryOccupancyLocalBoundsMin x="-0.5" y="-0.5" z="0" />
                            <inventoryOccupancyLocalBoundsMax x="0.5" y="0.5" z="1" />
                            <inventoryOccupancyVolume>
                                <SMicroCargoUnit microSCU="1" />
                            </inventoryOccupancyVolume>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="12" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.TEST_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertSame('Localized Test Item', $result['name']);
        self::assertSame('Localized Test Item', $result['stdItem']['Name']);
        self::assertSame(
            "Manufacturer: Fallback Industries\nType: Test\n\nUtility mount.",
            $result['stdItem']['Description']
        );
        self::assertSame(
            ['Manufacturer' => 'Fallback Industries', 'Type' => 'Test'],
            $result['stdItem']['DescriptionData']
        );
        self::assertSame('Utility mount.', $result['stdItem']['DescriptionText']);
        self::assertSame('Fallback Industries', $result['stdItem']['Manufacturer']['Name']);

        // InventoryOccupancy: Dimensions from bounds, CargoGrid from inventoryOccupancyDimensions
        $occ = $result['stdItem']['InventoryOccupancy'];
        self::assertEqualsWithDelta(1.0, $occ['Dimensions']['Width'], 0.001);
        self::assertEqualsWithDelta(1.0, $occ['Dimensions']['Length'], 0.001);
        self::assertEqualsWithDelta(1.0, $occ['Dimensions']['Height'], 0.001);
        self::assertEqualsWithDelta(1.0, $occ['CargoGrid']['Width'], 0.001);
        self::assertEqualsWithDelta(1.0, $occ['CargoGrid']['Length'], 0.001);
        self::assertEqualsWithDelta(1.0, $occ['CargoGrid']['Height'], 0.001);
    }

    public function test_to_array_uses_canonical_manufacturer_for_legacy_field_and_order_independent_localization_lookup(): void
    {
        $canonicalManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.rsi.xml',
            <<<'XML'
            <SCItemManufacturer.RSI Code="RSI" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.rsi.xml">
                <Localization __type="SCItemLocalization" ShortName="@LOC_EMPTY" Name="@manufacturer_NameRSI" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.RSI>
            XML
        );

        $codelessManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/paintcolorlogo_rsi.xml',
            <<<'XML'
            <SCItemManufacturer.RSI_LOGO __type="SCItemManufacturer" __ref="22222222-2222-2222-2222-222222222222" __path="libs/foundry/records/scitemmanufacturer/paintcolorlogo_rsi.xml">
                <Localization Name="@manufacturer_NameRSI" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.RSI_LOGO>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                '11111111-1111-1111-1111-111111111111' => 'RSI',
                '22222222-2222-2222-2222-222222222222' => 'RSI_LOGO',
            ],
            classToUuidMap: [
                'RSI' => '11111111-1111-1111-1111-111111111111',
                'RSI_LOGO' => '22222222-2222-2222-2222-222222222222',
            ],
            uuidToPathMap: [
                '11111111-1111-1111-1111-111111111111' => $canonicalManufacturerPath,
                '22222222-2222-2222-2222-222222222222' => $codelessManufacturerPath,
            ]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_NameRSI' => 'Roberts Space Industries',
            'item_name' => 'Canonical Manufacturer Test Item',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/canonical_manufacturer_item.xml',
            <<<'XML'
            <EntityClassDefinition.CANONICAL_MANUFACTURER_ITEM __type="EntityClassDefinition" __ref="33333333-3333-3333-3333-333333333333" __path="libs/foundry/records/entities/scitem/canonical_manufacturer_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="UNDEFINED" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="22222222-2222-2222-2222-222222222222">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="1" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.CANONICAL_MANUFACTURER_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertSame('RSI', $result['manufacturer']);
        self::assertSame('RSI', $result['stdItem']['Manufacturer']['Code']);
        self::assertSame('11111111-1111-1111-1111-111111111111', $result['stdItem']['Manufacturer']['UUID']);
        self::assertSame('Roberts Space Industries', $result['stdItem']['Manufacturer']['Name']);
    }

    public function test_to_array_preserves_coded_manufacturer_even_when_canonical_shares_name_key(): void
    {
        $canonicalManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.aeg.xml',
            <<<'XML'
            <SCItemManufacturer.AEG Code="AEG" __type="SCItemManufacturer" __ref="cf4a74bf-eb2c-462a-9b78-f7f2724c31d2" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.aeg.xml">
                <Localization Name="@manufacturer_NameAEG" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.AEG>
            XML
        );

        $codedAliasManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/paintcolorlogo_fski.xml',
            <<<'XML'
            <SCItemManufacturer.FSKI Code="FSKI" __type="SCItemManufacturer" __ref="5f81335d-44e6-4104-841e-c5af9df06829" __path="libs/foundry/records/scitemmanufacturer/paintcolorlogo_fski.xml">
                <Localization Name="@manufacturer_NameAEG" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.FSKI>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2' => 'AEG',
                '5f81335d-44e6-4104-841e-c5af9df06829' => 'FSKI',
            ],
            classToUuidMap: [
                'AEG' => 'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2',
                'FSKI' => '5f81335d-44e6-4104-841e-c5af9df06829',
            ],
            uuidToPathMap: [
                'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2' => $canonicalManufacturerPath,
                '5f81335d-44e6-4104-841e-c5af9df06829' => $codedAliasManufacturerPath,
            ]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_NameAEG' => 'Aegis Dynamics',
            'item_name' => 'Coded Alias Manufacturer Test Item',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/coded_alias_manufacturer_item.xml',
            <<<'XML'
            <EntityClassDefinition.CODED_ALIAS_MANUFACTURER_ITEM __type="EntityClassDefinition" __ref="33333333-3333-3333-3333-333333333333" __path="libs/foundry/records/entities/scitem/coded_alias_manufacturer_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="UNDEFINED" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="5f81335d-44e6-4104-841e-c5af9df06829">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="1" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.CODED_ALIAS_MANUFACTURER_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertSame('FSKI', $result['manufacturer']);
        self::assertSame('FSKI', $result['stdItem']['Manufacturer']['Code']);
        self::assertSame('5f81335d-44e6-4104-841e-c5af9df06829', $result['stdItem']['Manufacturer']['UUID']);
        self::assertSame('Aegis Dynamics', $result['stdItem']['Manufacturer']['Name']);
    }

    public function test_inventory_occupancy_falls_back_to_cargo_grid_when_bounds_zero(): void
    {
        $manufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/fallback.xml',
            <<<'XML'
            <SCItemManufacturer.FALLBACK Code="FALL" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/fallback.xml">
                <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.FALLBACK>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: ['11111111-1111-1111-1111-111111111111' => 'FALLBACK'],
            classToUuidMap: ['FALLBACK' => '11111111-1111-1111-1111-111111111111'],
            uuidToPathMap: ['11111111-1111-1111-1111-111111111111' => $manufacturerPath]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/test_item_zero_bounds.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_ITEM_ZERO_BOUNDS __type="EntityClassDefinition" __ref="33333333-3333-3333-3333-333333333333" __path="libs/foundry/records/entities/scitem/test_item_zero_bounds.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="UNDEFINED" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                            <inventoryOccupancyDimensions x="0.15" y="0.15" z="0.15" />
                            <inventoryOccupancyLocalBoundsMin x="0" y="0" z="0" />
                            <inventoryOccupancyLocalBoundsMax x="0" y="0" z="0" />
                            <inventoryOccupancyVolume>
                                <SMicroCargoUnit microSCU="1" />
                            </inventoryOccupancyVolume>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="1" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.TEST_ITEM_ZERO_BOUNDS>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        $occ = $result['stdItem']['InventoryOccupancy'];

        // When bounds are zero, Dimensions is absent (no true physical size available)
        self::assertArrayNotHasKey('Dimensions', $occ);

        // CargoGrid is also absent when dims are the 0.15³ placeholder
        self::assertArrayNotHasKey('CargoGrid', $occ);

        // Volume is still present
        self::assertArrayHasKey('Volume', $occ);
    }
}
