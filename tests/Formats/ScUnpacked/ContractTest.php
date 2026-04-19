<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use DOMDocument;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\HaulingOrdersValue;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use ReflectionMethod;

final class ContractTest extends ScDataTestCase
{
    private function bootServices(): void
    {
        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
        ]);

        $ref = new \ReflectionClass(ServiceFactory::class);
        $services = $ref->getProperty('services')->getValue();

        $services['FoundryLookupService'] = new FoundryLookupService($this->tempDir);
        $services['FoundryLookupService']->initialize();

        $ref->getProperty('services')->setValue(null, $services);
    }

    public function test_offset_extracts_hex_refs_from_entry_overrides(): void
    {
        $this->writeCacheFiles();
        $this->writeMultiHandlerFixture();
        $this->bootServices();

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_multi.xml');

        $handler1 = $record->getHandlers()[0];
        $entry1 = $handler1->getContracts()[0];

        $contract = new Contract($entry1, $handler1, $record);
        $overrides = $this->invokeMethod($contract, 'getAllOverrides');

        $hexRefs = [];
        foreach ($overrides as $prop) {
            $value = $prop->getValue();
            if ($value instanceof HaulingOrdersValue) {
                foreach ($value->getOrders() as $order) {
                    if ($order['missionItem'] !== null) {
                        $hexRefs[] = $order['missionItem'];
                    }
                }
            }
        }

        self::assertCount(1, $hexRefs);
        self::assertSame('MissionProperty[0106]', $hexRefs[0]);
    }

    public function test_offset_uses_only_current_entry_refs(): void
    {
        $this->writeCacheFiles();
        $this->writeMultiHandlerFixture();
        $this->bootServices();

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_multi.xml');

        $handler2 = $record->getHandlers()[1];
        $entry2 = $handler2->getContracts()[0];

        $contract = new Contract($entry2, $handler2, $record);
        $overrides = $this->invokeMethod($contract, 'getAllOverrides');

        $hexRefs = [];
        foreach ($overrides as $prop) {
            $value = $prop->getValue();
            if ($value instanceof HaulingOrdersValue) {
                foreach ($value->getOrders() as $order) {
                    if ($order['missionItem'] !== null) {
                        $hexRefs[] = $order['missionItem'];
                    }
                }
            }
        }

        self::assertCount(1, $hexRefs);
        self::assertSame('MissionProperty[00FE]', $hexRefs[0]);
    }

    public function test_handler_scoped_offset_resolves_correctly_for_multi_handler(): void
    {
        $this->writeCacheFiles();
        $this->writeMultiHandlerFixture();
        $this->bootServices();

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_multi.xml');

        $handlers = $record->getHandlers();
        self::assertCount(2, $handlers);

        $handler2 = $handlers[1];
        $entry2 = $handler2->getContracts()[0];

        $contract = new Contract($entry2, $handler2, $record);

        $overrides = $this->invokeMethod($contract, 'getAllOverrides');
        $offset = $this->invokeMethod($contract, 'computeHandlerPropertyBaseOffset', [$overrides]);

        self::assertNotNull($offset, 'Handler-scoped offset should be computed for handler 2');

        $missionItemIndex = null;
        foreach ($overrides as $i => $prop) {
            if ($prop->getValueTypeName() === 'MissionPropertyValue_MissionItem') {
                $missionItemIndex = $i;
                break;
            }
        }
        self::assertNotNull($missionItemIndex, 'Handler 2 should have a MissionItem property');

        $refs = [];
        foreach ($overrides as $prop) {
            $value = $prop->getValue();
            if ($value instanceof HaulingOrdersValue) {
                foreach ($value->getOrders() as $order) {
                    if ($order['missionItem'] !== null) {
                        $refs[] = $order['missionItem'];
                    }
                }
            }
        }
        self::assertNotEmpty($refs);

        preg_match('/MissionProperty\[([0-9A-Fa-f]+)\]/', $refs[0], $m);
        $hexVal = hexdec($m[1]);
        $localIndex = $hexVal - $offset;

        self::assertSame($missionItemIndex, $localIndex, 'Hex reference should map to the MissionItem property index');
        self::assertSame('MissionPropertyValue_MissionItem', $overrides[$localIndex]->getValueTypeName());
    }

    public function test_hauling_orders_resolve_for_second_handler(): void
    {
        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/test_item.xml',
            '<EntityClassDefinition.TestItem __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/scitem/test_item.xml"><entityClass entityClass="aaaa0000-0000-0000-0000-000000000001" /></EntityClassDefinition.TestItem>'
        );

        $missionItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/missions/test_mission_item.xml',
            '<MissionItem.TestItem entityClass="aaaa0000-0000-0000-0000-000000000001" __type="MissionItem" __ref="bbbb0000-0000-0000-0000-000000000001" __path="libs/foundry/records/missions/test_mission_item.xml" />'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000001' => $itemPath,
                'bbbb0000-0000-0000-0000-000000000001' => $missionItemPath,
            ],
            uuidToClassMap: [
                'aaaa0000-0000-0000-0000-000000000001' => 'TestItem',
                'bbbb0000-0000-0000-0000-000000000001' => 'TestMissionItem',
            ],
        );

        $this->writeMultiHandlerFixture();
        $this->bootServices();

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_multi.xml');

        $handler2 = $record->getHandlers()[1];
        $entry2 = $handler2->getContracts()[0];

        $contract = new Contract($entry2, $handler2, $record);

        $orders = $this->invokeMethod($contract, 'buildHaulingOrders');

        self::assertCount(1, $orders);
        self::assertSame('MissionItem', $orders[0]['kind']);
        self::assertArrayNotHasKey('mission_item_ref', $orders[0], 'mission_item_ref should be resolved and removed');
    }

    public function test_handler_with_no_mission_items_returns_null_offset(): void
    {
        $this->writeCacheFiles();

        $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_empty.xml',
            '<ContractGenerator.Empty __type="ContractGenerator" __ref="dd000000-0000-0000-0000-000000000001" __path="libs/foundry/records/contracts/contractgenerator/test_empty.xml"><generators /></ContractGenerator.Empty>'
        );

        $this->bootServices();

        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="NoItems">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty missionVariableName="Loc">
                        <value><MissionPropertyValue_Location /></value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="c1" debugName="Test"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $handler = ContractHandler::fromNode($dom->documentElement);

        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_empty.xml');

        $contract = new Contract($entry, $handler, $record);
        $overrides = $this->invokeMethod($contract, 'getAllOverrides');
        $offset = $this->invokeMethod($contract, 'computeHandlerPropertyBaseOffset', [$overrides]);

        self::assertNull($offset);
    }

    private function writeMultiHandlerFixture(): void
    {
        $handler1Props = <<<'XML'
            <propertyOverrides>
                <MissionProperty missionVariableName="Loc1"><value><MissionPropertyValue_Location /></value></MissionProperty>
                <MissionProperty missionVariableName="Loc2"><value><MissionPropertyValue_Location /></value></MissionProperty>
                <MissionProperty missionVariableName="Item1"><value><MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1"><matchConditions><DataSetMatchCondition_SpecificItemsDef><items><Reference value="item-uuid-1" /></items></DataSetMatchCondition_SpecificItemsDef></matchConditions></MissionPropertyValue_MissionItem></value></MissionProperty>
                <MissionProperty missionVariableName="Hauling"><value><MissionPropertyValue_HaulingOrders><haulingOrderContent><HaulingOrderContent_MissionItem minAmount="1" maxAmount="1"><item value="MissionProperty[0106]" /></HaulingOrderContent_MissionItem></haulingOrderContent></MissionPropertyValue_HaulingOrders></value></MissionProperty>
            </propertyOverrides>
        XML;

        $handler2Props = <<<'XML'
            <propertyOverrides>
                <MissionProperty missionVariableName="Item2"><value><MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1"><matchConditions><DataSetMatchCondition_SpecificItemsDef><items><Reference value="bbbb0000-0000-0000-0000-000000000001" /></items></DataSetMatchCondition_SpecificItemsDef></matchConditions></MissionPropertyValue_MissionItem></value></MissionProperty>
                <MissionProperty missionVariableName="Hauling"><value><MissionPropertyValue_HaulingOrders><haulingOrderContent><HaulingOrderContent_MissionItem minAmount="1" maxAmount="1"><item value="MissionProperty[00FE]" /></HaulingOrderContent_MissionItem></haulingOrderContent></MissionPropertyValue_HaulingOrders></value></MissionProperty>
            </propertyOverrides>
        XML;

        $fullXml = <<<XML
        <ContractGenerator.TestMulti __type="ContractGenerator" __ref="cc000000-0000-0000-0000-000000000001" __path="libs/foundry/records/contracts/contractgenerator/test_multi.xml">
            <generators>
                <ContractGeneratorHandler_Recovery debugName="Handler1">
                    <contractParams>{$handler1Props}</contractParams>
                    <contracts>
                        <Contract id="c1" debugName="Contract1">
                            <paramOverrides />
                            <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                            <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                        </Contract>
                    </contracts>
                </ContractGeneratorHandler_Recovery>
                <ContractGeneratorHandler_Recovery debugName="Handler2">
                    <contractParams>{$handler2Props}</contractParams>
                    <contracts>
                        <Contract id="c2" debugName="Contract2">
                            <paramOverrides />
                            <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                            <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                        </Contract>
                    </contracts>
                </ContractGeneratorHandler_Recovery>
            </generators>
        </ContractGenerator.TestMulti>
        XML;

        $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_multi.xml',
            $fullXml
        );
    }

    private function invokeMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);

        return $ref->invokeArgs($object, $args);
    }
}
