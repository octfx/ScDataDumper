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

    public function test_to_array_curated_manufacturer_code_wins_over_conflicting_xml_name(): void
    {
        // Game-data copy-paste bug: FSKI's XML Name token is @AEGS, so its display
        // name resolves to "Aegis Dynamics". The curated code FSKI is identity
        // and must win -- the item is FireStorm Kinetics, not Aegis Dynamics.
        $canonicalManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.aegs.xml',
            <<<'XML'
            <SCItemManufacturer.AEGS Code="AEGS" __type="SCItemManufacturer" __ref="cf4a74bf-eb2c-462a-9b78-f7f2724c31d2" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.aegs.xml">
                <Localization Name="@manufacturer_NameAEGS" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.AEGS>
            XML
        );

        $buggyFskiPath = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.fski.xml',
            <<<'XML'
            <SCItemManufacturer.FSKI Code="FSKI" __type="SCItemManufacturer" __ref="5f81335d-44e6-4104-841e-c5af9df06829" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.fski.xml">
                <Localization Name="@manufacturer_NameAEGS" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.FSKI>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2' => 'AEGS',
                '5f81335d-44e6-4104-841e-c5af9df06829' => 'FSKI',
            ],
            classToUuidMap: [
                'AEGS' => 'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2',
                'FSKI' => '5f81335d-44e6-4104-841e-c5af9df06829',
            ],
            uuidToPathMap: [
                'cf4a74bf-eb2c-462a-9b78-f7f2724c31d2' => $canonicalManufacturerPath,
                '5f81335d-44e6-4104-841e-c5af9df06829' => $buggyFskiPath,
            ]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_NameAEGS' => 'Aegis Dynamics',
            'item_name' => 'Curated Code Wins Test Item',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/curated_code_item.xml',
            <<<'XML'
            <EntityClassDefinition.CURATED_CODE_ITEM __type="EntityClassDefinition" __ref="33333333-3333-3333-3333-333333333333" __path="libs/foundry/records/entities/scitem/curated_code_item.xml">
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
            </EntityClassDefinition.CURATED_CODE_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        // Item references FSKI (uuid 5f81335d...). FSKI is curated in data.json,
        // so the code wins over the buggy XML name -> FireStorm Kinetics, not
        // Aegis Dynamics. UUID is FSKI's own primary record.
        self::assertSame('FSKI', $result['manufacturer']);
        self::assertSame('FSKI', $result['stdItem']['Manufacturer']['Code']);
        self::assertSame('5f81335d-44e6-4104-841e-c5af9df06829', $result['stdItem']['Manufacturer']['UUID']);
        self::assertSame('FireStorm Kinetics', $result['stdItem']['Manufacturer']['Name']);
    }

    public function test_to_array_unifies_manufacturer_uuid_to_canonical_primary(): void
    {
        // Primary AEGS record + a codeless alias (no Code attr) reusing the same
        // name token. The item points at the alias; via the token path
        // (canonicalIndex) the uuid collapses to the primary's. This is the
        // "100 variations of RSI -> one uuid" guarantee at the item level.
        $canonicalManufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.aegs.xml',
            <<<'XML'
            <SCItemManufacturer.AEGS Code="AEGS" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.aegs.xml">
                <Localization Name="@manufacturer_NameAEGS" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.AEGS>
            XML
        );

        $codelessAliasPath = $this->writeFile(
            'records/scitemmanufacturer/paintcolorlogo_aegs.xml',
            <<<'XML'
            <SCItemManufacturer.AEGS_LOGO __type="SCItemManufacturer" __ref="22222222-2222-2222-2222-222222222222" __path="libs/foundry/records/scitemmanufacturer/paintcolorlogo_aegs.xml">
                <Localization Name="@manufacturer_NameAEGS" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.AEGS_LOGO>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                '11111111-1111-1111-1111-111111111111' => 'AEGS',
                '22222222-2222-2222-2222-222222222222' => 'AEGS_LOGO',
            ],
            classToUuidMap: [
                'AEGS' => '11111111-1111-1111-1111-111111111111',
                'AEGS_LOGO' => '22222222-2222-2222-2222-222222222222',
            ],
            uuidToPathMap: [
                '11111111-1111-1111-1111-111111111111' => $canonicalManufacturerPath,
                '22222222-2222-2222-2222-222222222222' => $codelessAliasPath,
            ]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_NameAEGS' => 'Aegis Dynamics',
            'item_name' => 'UUID Unification Test Item',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/uuid_unification_item.xml',
            <<<'XML'
            <EntityClassDefinition.UUID_UNIFICATION_ITEM __type="EntityClassDefinition" __ref="33333333-3333-3333-3333-333333333333" __path="libs/foundry/records/entities/scitem/uuid_unification_item.xml">
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
            </EntityClassDefinition.UUID_UNIFICATION_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        // Item references the codeless alias (uuid 22222222...). The alias has
        // no Code attr, so the token path resolves it to AEGS, and the uuid
        // collapses to the AEGS primary's.
        self::assertSame('AEGS', $result['stdItem']['Manufacturer']['Code']);
        self::assertSame('11111111-1111-1111-1111-111111111111', $result['stdItem']['Manufacturer']['UUID']);
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

    public function test_to_array_wiki_manufacturer_code_wins_over_name_resolution(): void
    {
        // Item UUID 047ae622.. is curated in wiki_items.json as manufacturer=GRIN.
        // The XML manufacturer name "Anvil Aerospace" would resolve to ANVL via
        // the name->code reverse map; the wiki code GRIN must win, and the name
        // forward-resolves to "Greycat Industrial".
        $manufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/anvl.xml',
            <<<'XML'
            <SCItemManufacturer.ANVL Code="ANVL" __type="SCItemManufacturer" __ref="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa" __path="libs/foundry/records/scitemmanufacturer/anvl.xml">
                <Localization Name="@manufacturer_anvl" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.ANVL>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => 'ANVL'],
            classToUuidMap: ['ANVL' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
            uuidToPathMap: ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => $manufacturerPath]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_anvl' => 'Anvil Aerospace',
            'item_name' => 'Test Paint',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/curated_grin_item.xml',
            <<<'XML'
            <EntityClassDefinition.CURATED_GRIN_ITEM __type="EntityClassDefinition" __ref="047ae622-d622-4d72-9482-008ab7d9600d" __path="libs/foundry/records/entities/scitem/curated_grin_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="UNDEFINED" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="1" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.CURATED_GRIN_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertSame('GRIN', $result['manufacturer']);
        self::assertSame('GRIN', $result['stdItem']['Manufacturer']['Code']);
        self::assertSame('Greycat Industrial', $result['stdItem']['Manufacturer']['Name']);
    }

    public function test_to_array_applies_wiki_item_name_override_at_both_output_sites(): void
    {
        // Item UUID 047ae622.. is curated with name="MXC Moonstone Livery".
        // The XML item name "Test Paint" must be overridden at data.name and
        // data.stdItem.Name.
        $manufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/grin.xml',
            <<<'XML'
            <SCItemManufacturer.GRIN Code="GRIN" __type="SCItemManufacturer" __ref="bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb" __path="libs/foundry/records/scitemmanufacturer/grin.xml">
                <Localization Name="@manufacturer_grin" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.GRIN>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: ['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb' => 'GRIN'],
            classToUuidMap: ['GRIN' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'],
            uuidToPathMap: ['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb' => $manufacturerPath]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_grin' => 'Greycat Industrial',
            'item_name' => 'Test Paint',
        ]);

        $path = $this->writeFile(
            'records/entities/scitem/curated_named_item.xml',
            <<<'XML'
            <EntityClassDefinition.CURATED_NAMED_ITEM __type="EntityClassDefinition" __ref="047ae622-d622-4d72-9482-008ab7d9600d" __path="libs/foundry/records/entities/scitem/curated_named_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="UNDEFINED" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="1" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
                </Components>
            </EntityClassDefinition.CURATED_NAMED_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertSame('MXC Moonstone Livery', $result['name']);
        self::assertSame('MXC Moonstone Livery', $result['stdItem']['Name']);
    }

    public function test_ammo_feeder_backpack_exposes_magazine_association(): void
    {
        $manufacturerUuid = '11111111-1111-1111-1111-111111111111';
        $magazineUuid = 'b601c979-1a7e-41a9-b4f4-7e74d954d186';
        $backpackUuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

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

        $magazinePath = $this->writeFile(
            'records/entities/scitem/weapons/magazines/apar_hmg_ballistic_01_mag.xml',
            <<<'XML'
            <EntityClassDefinition.apar_hmg_ballistic_01_mag __type="EntityClassDefinition" __ref="b601c979-1a7e-41a9-b4f4-7e74d954d186" __path="libs/foundry/records/entities/scitem/weapons/magazines/apar_hmg_ballistic_01_mag.xml" />
            XML
        );

        $backpackPath = $this->writeFile(
            'records/entities/scitem/backpack/test_ammo_feeder_backpack.xml',
            sprintf(
                <<<'XML'
                <EntityClassDefinition.TEST_AMMO_FEEDER_BACKPACK __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/scitem/backpack/test_ammo_feeder_backpack.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="Armor" SubType="Backpack" Size="2" Grade="1" Manufacturer="%2$s">
                                <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                                <inventoryOccupancyDimensions x="2" y="2" z="2" />
                                <inventoryOccupancyLocalBoundsMin x="-1" y="-1" z="0" />
                                <inventoryOccupancyLocalBoundsMax x="1" y="1" z="2" />
                                <inventoryOccupancyVolume>
                                    <SMicroCargoUnit microSCU="1000" />
                                </inventoryOccupancyVolume>
                            </AttachDef>
                        </SAttachableComponentParams>
                        <SCItemAmmoContainerFeederComponentParams ammoContainerRecord="%3$s">
                            <ejectParams impulseStrength="10">
                                <impulseDirection x="0" y="0" z="1" />
                            </ejectParams>
                        </SCItemAmmoContainerFeederComponentParams>
                    </Components>
                </EntityClassDefinition.TEST_AMMO_FEEDER_BACKPACK>
                XML,
                $backpackUuid,
                $manufacturerUuid,
                $magazineUuid,
            )
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                $manufacturerUuid => 'FALLBACK',
                $magazineUuid => 'apar_hmg_ballistic_01_mag',
                $backpackUuid => 'TEST_AMMO_FEEDER_BACKPACK',
            ],
            classToUuidMap: [
                'FALLBACK' => $manufacturerUuid,
                'apar_hmg_ballistic_01_mag' => $magazineUuid,
                'TEST_AMMO_FEEDER_BACKPACK' => $backpackUuid,
            ],
            uuidToPathMap: [
                $manufacturerUuid => $manufacturerPath,
                $magazineUuid => $magazinePath,
                $backpackUuid => $backpackPath,
            ],
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'item_name' => 'Ammo Feeder Backpack',
        ]);

        $item = new EntityClassDefinition;
        $item->load($backpackPath);

        $result = (new Item($item))->toArray();

        self::assertArrayHasKey('ammo_feed', $result);
        self::assertSame($magazineUuid, $result['ammo_feed']['magazine_reference']);
        self::assertSame('apar_hmg_ballistic_01_mag', $result['ammo_feed']['magazine_class_name']);
    }

    public function test_item_without_feeder_has_no_ammo_feed(): void
    {
        $manufacturerUuid = '11111111-1111-1111-1111-111111111111';

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

        $path = $this->writeFile(
            'records/entities/scitem/plain_item.xml',
            sprintf(
                <<<'XML'
                <EntityClassDefinition.TEST_PLAIN_ITEM __type="EntityClassDefinition" __ref="bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb" __path="libs/foundry/records/entities/scitem/plain_item.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="Armor" SubType="Backpack" Size="1" Grade="1" Manufacturer="%s">
                                <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                                <inventoryOccupancyDimensions x="1" y="1" z="1" />
                                <inventoryOccupancyLocalBoundsMin x="-0.5" y="-0.5" z="0" />
                                <inventoryOccupancyLocalBoundsMax x="0.5" y="0.5" z="1" />
                                <inventoryOccupancyVolume>
                                    <SMicroCargoUnit microSCU="1" />
                                </inventoryOccupancyVolume>
                            </AttachDef>
                        </SAttachableComponentParams>
                    </Components>
                </EntityClassDefinition.TEST_PLAIN_ITEM>
                XML,
                $manufacturerUuid,
            )
        );

        $this->writeCacheFiles(
            uuidToClassMap: [$manufacturerUuid => 'FALLBACK'],
            classToUuidMap: ['FALLBACK' => $manufacturerUuid],
            uuidToPathMap: [$manufacturerUuid => $manufacturerPath],
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'item_name' => 'Plain Backpack',
        ]);

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Item($item))->toArray();

        self::assertArrayNotHasKey('ammo_feed', $result);
    }
}
