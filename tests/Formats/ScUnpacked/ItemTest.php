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
    }
}
