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

final class ContractTest extends ScDataTestCase
{
    private function bootServices(): void
    {
        $this->initializeMinimalItemServices();

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);
    }

    private function buildInlineContract(): Contract
    {
        $dom = new DOMDocument;
        $dom->loadXML('<ContractGeneratorHandler_Recovery debugName="H"><contractParams /><contracts><Contract id="e1" debugName="E"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>');
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];
        $record = new ContractGeneratorRecord;

        return new Contract($entry, $handler, $record);
    }

    public function test_faction_unifies_to_manufacturer_canonical_name_and_uuid(): void
    {
        // "Crusader Industries" is both a faction and a manufacturer. The faction
        // entity has its own uuid; the override must collapse to the manufacturer
        // primary uuid while keeping the canonical name.
        $crus = $this->writeFile(
            'records/scitemmanufacturer/scitemmanufacturer.crus.xml',
            <<<'XML'
            <SCItemManufacturer.CRUS Code="CRUS" __type="SCItemManufacturer" __ref="crus-primary-uuid-aaaa" __path="libs/foundry/records/scitemmanufacturer/scitemmanufacturer.crus.xml">
                <Localization Name="@manufacturer_NameCRUS" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.CRUS>
            XML
        );

        $this->writeCacheFiles(uuidToPathMap: ['crus-primary-uuid-aaaa' => $crus]);
        $this->bootServices();

        $result = $this->invokeMethod(
            $this->buildInlineContract(),
            'applyManufacturerFactionOverride',
            'Crusader Industries',
            'faction-entity-uuid',
        );

        self::assertSame('Crusader Industries', $result['name']);
        self::assertSame('crus-primary-uuid-aaaa', $result['uuid']);
    }

    public function test_faction_passes_through_when_not_a_manufacturer(): void
    {
        // ~32 factions (XenoThreat, BHG, Covalex, ...) are not manufacturers;
        // they must pass through untouched.
        $this->writeCacheFiles();
        $this->bootServices();

        $result = $this->invokeMethod(
            $this->buildInlineContract(),
            'applyManufacturerFactionOverride',
            'XenoThreat',
            'xt-faction-uuid',
        );

        self::assertSame('XenoThreat', $result['name']);
        self::assertSame('xt-faction-uuid', $result['uuid']);
    }

    public function test_faction_override_keeps_fallback_uuid_when_manufacturer_has_no_xml(): void
    {
        // Manufacturer name matches but no XML record (codeToUuid misses) ->
        // canonical name wins, faction entity uuid is kept (never nulled).
        $this->writeCacheFiles();
        $this->bootServices();

        $result = $this->invokeMethod(
            $this->buildInlineContract(),
            'applyManufacturerFactionOverride',
            'ArcCorp',
            'arccorp-faction-uuid',
        );

        self::assertSame('ArcCorp', $result['name']);
        self::assertSame('arccorp-faction-uuid', $result['uuid']);
    }

    public function test_mission_giver_unifies_to_manufacturer_canonical_name(): void
    {
        // Shubin Interstellar is both a contractor org and a manufacturer.
        $this->writeCacheFiles();
        $this->bootServices();

        $name = $this->invokeMethod($this->buildInlineContract(), 'applyManufacturerNameOverride', 'Shubin Interstellar');

        self::assertSame('Shubin Interstellar', $name);
    }

    public function test_mission_giver_passes_through_when_not_a_manufacturer(): void
    {
        // HeadHunters is an org, not a manufacturer; name is untouched.
        $this->writeCacheFiles();
        $this->bootServices();

        $name = $this->invokeMethod($this->buildInlineContract(), 'applyManufacturerNameOverride', 'HeadHunters');

        self::assertSame('HeadHunters', $name);
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
        $offset = $this->invokeMethod($contract, 'computeHandlerPropertyBaseOffset', $overrides);

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
            uuidToClassMap: [
                'aaaa0000-0000-0000-0000-000000000001' => 'TestItem',
                'bbbb0000-0000-0000-0000-000000000001' => 'TestMissionItem',
            ],
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000001' => $itemPath,
                'bbbb0000-0000-0000-0000-000000000001' => $missionItemPath,
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

    public function test_template_objective_hauling_orders_used_as_fallback(): void
    {
        // Orders declared on the template's ObjectiveHandler_Hauling objective (the
        // TheCollector_Vehicle_Polaris shape), not as property overrides.

        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/test_component.xml',
            '<EntityClassDefinition.TestComponent __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000010" __path="libs/foundry/records/entities/scitem/test_component.xml"><Components><SAttachableComponentParams><AttachDef><Localization><English Name="Test Component" /></Localization></AttachDef></SAttachableComponentParams></Components><entityClass entityClass="aaaa0000-0000-0000-0000-000000000010" /></EntityClassDefinition.TestComponent>'
        );

        $resourcePath = $this->writeFile(
            'Data/Libs/Foundry/Records/resources/test_carbon.xml',
            '<ResourceType.TestCarbon displayName="Test Carbon" __type="ResourceType" __ref="bbbb0000-0000-0000-0000-000000000020" __path="libs/foundry/records/resources/test_carbon.xml" />'
        );

        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_template_hauling.xml',
            '<ContractTemplate.TestTemplate __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000030" __path="libs/foundry/records/contracts/contracttemplates/test_template_hauling.xml"><objectiveTokens><ObjectiveToken id="dddd0000-0000-0000-0000-000000000040" debugName="ItemResourceGathering"><objectiveHandler><ObjectiveHandler_Hauling><haulingOrders><HaulingOrder_Property><haulingOrdersProperty value="ObjectiveProperty_Referenced[142A]" /></HaulingOrder_Property><HaulingOrder_EntityClass entityClass="aaaa0000-0000-0000-0000-000000000010" minAmount="15" maxAmount="15"><dropOffLocation value="ObjectiveProperty_Referenced[1428]" /></HaulingOrder_EntityClass><HaulingOrder_Resource resource="bbbb0000-0000-0000-0000-000000000020" maxContainerSize="32" minSCU="100" maxSCU="200"><dropOffLocation value="ObjectiveProperty_Referenced[1428]" /></HaulingOrder_Resource></haulingOrders></ObjectiveHandler_Hauling></objectiveHandler></ObjectiveToken></objectiveTokens></ContractTemplate.TestTemplate>'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000010' => $itemPath,
                'bbbb0000-0000-0000-0000-000000000020' => $resourcePath,
                'cccc0000-0000-0000-0000-000000000030' => $templatePath,
            ],
        );

        $this->bootServices();

        // Entry references the template and defines NO hauling property overrides, so
        // buildHaulingOrders() returns [] and the template-objective fallback applies.
        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="Handler"><contractParams /><contracts><Contract id="e1" debugName="TestEntry" template="cccc0000-0000-0000-0000-000000000030"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $contract = new Contract($entry, $handler, $record);

        // Property-override path is empty; fallback should read the template objective.
        self::assertSame([], $this->invokeMethod($contract, 'buildHaulingOrders'));

        $orders = $this->invokeMethod($contract, 'buildTemplateObjectiveHaulingOrders');

        // HaulingOrder_Property is skipped: its cargo lives in a property override emitted
        // by buildHaulingOrders(), so by the time this fallback runs it would be hollow.
        self::assertCount(2, $orders);

        // HaulingOrder_EntityClass -> resolved via ItemService with display name.
        self::assertSame('Entity', $orders[0]['kind']);
        self::assertSame('aaaa0000-0000-0000-0000-000000000010', $orders[0]['uuid']);
        self::assertSame('Test Component', $orders[0]['name']);
        self::assertSame(15, $orders[0]['min_amount']);
        self::assertSame(15, $orders[0]['max_amount']);

        // HaulingOrder_Resource -> resolved via ResourceType with SCU/container fields.
        self::assertSame('Resource', $orders[1]['kind']);
        self::assertSame('bbbb0000-0000-0000-0000-000000000020', $orders[1]['uuid']);
        self::assertSame('Test Carbon', $orders[1]['name']);
        self::assertSame(32, $orders[1]['max_container_size']);
        self::assertSame(100, $orders[1]['min_scu']);
        self::assertSame(200, $orders[1]['max_scu']);
    }

    public function test_template_mission_item_order_resolves_via_entry_override(): void
    {
        // HaulingOrder_MissionItem in the template references a mission variable by
        // ObjectiveProperty_Referenced[hex]; the entry overrides that variable with concrete
        // specificItems. Mirrors the courier_5boxes shape (e.g. CFP_Pyro 5box single courier),
        // where buildHaulingOrders() returns [] (no HaulingOrders property override) but the
        // template-objective fallback must still resolve the delivered boxes.

        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/test_box.xml',
            '<EntityClassDefinition.TestBox __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000100" __path="libs/foundry/records/entities/scitem/test_box.xml"><Components><SAttachableComponentParams><AttachDef><Localization><English Name="Test Box" /></Localization></AttachDef></SAttachableComponentParams></Components><entityClass entityClass="aaaa0000-0000-0000-0000-000000000100" /></EntityClassDefinition.TestBox>'
        );

        // Template: one HaulingOrder_MissionItem whose <item> ref decodes to var "Item1".
        // ObjectiveProperty base = 0x1000; item ref = 0x1002 -> idx 2.
        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_missionitem_haul.xml',
            '<ContractTemplate.TestMI __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000200" __path="libs/foundry/records/contracts/contracttemplates/test_missionitem_haul.xml"><objectiveTokens><ObjectiveToken id="dddd0000-0000-0000-0000-000000000300" debugName="Delivery"><properties><ObjectiveProperty_Referenced missionVariableName="PickupLocation_BP"><property value="MissionProperty[2000]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DropoffLocation_BP"><property value="MissionProperty[2001]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="Item1"><property value="MissionProperty[2002]" /></ObjectiveProperty_Referenced></properties><objectiveHandler><ObjectiveHandler_Hauling><haulingOrders><HaulingOrder_MissionItem minAmount="1" maxAmount="0"><pickUpLocation value="ObjectiveProperty_Referenced[1000]" /><dropOffLocation value="ObjectiveProperty_Referenced[1001]" /><item value="ObjectiveProperty_Referenced[1002]" /></HaulingOrder_MissionItem></haulingOrders></ObjectiveHandler_Hauling></objectiveHandler></ObjectiveToken></objectiveTokens></ContractTemplate.TestMI>'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000100' => $itemPath,
                'cccc0000-0000-0000-0000-000000000200' => $templatePath,
            ],
        );

        $this->bootServices();

        // Entry overrides Item1 with a concrete specificItems set (the box to deliver).
        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="Handler"><contractParams><propertyOverrides><MissionProperty missionVariableName="Item1"><value><MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1"><matchConditions><DataSetMatchCondition_SpecificItemsDef><items><Reference value="aaaa0000-0000-0000-0000-000000000100" /></items></DataSetMatchCondition_SpecificItemsDef></matchConditions></MissionPropertyValue_MissionItem></value></MissionProperty></propertyOverrides></contractParams><contracts><Contract id="e1" debugName="TestCourier" template="cccc0000-0000-0000-0000-000000000200"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $contract = new Contract($entry, $handler, $record);

        $orders = $this->invokeMethod($contract, 'buildTemplateObjectiveHaulingOrders');

        // The HaulingOrder_MissionItem resolves through Item1 to the concrete box.
        self::assertCount(1, $orders);
        self::assertSame('MissionItem', $orders[0]['kind']);
        self::assertSame('aaaa0000-0000-0000-0000-000000000100', $orders[0]['uuid']);
        self::assertSame('Test Box', $orders[0]['name']);
        self::assertSame(1, $orders[0]['min_amount']);
    }

    public function test_template_mission_item_order_scoped_to_hauling_token_in_multi_objective_template(): void
    {
        // Regression for a multi-ObjectiveToken template: the hauling node lives in ONE
        // ObjectiveToken, but the old code fetched ObjectiveProperty_Referenced from the
        // template root, concatenating properties across ALL tokens. With a non-hauling
        // token placed BEFORE the hauling token in document order, the decoded index
        // landed on the wrong token's property -> wrong missionVariableName -> silent drop.
        //
        // Real-world shape: eliminateall_courier.xml (EliminateAll token has 12 properties,
        // Courier token has the HaulingOrder_MissionItem). Here we use a minimal 2-token
        // template where the hauling token's properties are NOT at template-wide position 0.

        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/test_crate.xml',
            '<EntityClassDefinition.TestCrate __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000210" __path="libs/foundry/records/entities/scitem/test_crate.xml"><Components><SAttachableComponentParams><AttachDef><Localization><English Name="Test Crate" /></Localization></AttachDef></SAttachableComponentParams></Components><entityClass entityClass="aaaa0000-0000-0000-0000-000000000210" /></EntityClassDefinition.TestCrate>'
        );

        // Token 1 (Combat) has 3 ObjectiveProperty_Referenced entries at template-wide
        // positions 0-2. Token 2 (Delivery/Hauling) has its properties at positions 3-5.
        // The hauling node's item ref decodes to idx 2, which -- scoped correctly -- hits
        // DeliverItem (position 2 within the Delivery token), NOT CombatReward (position 2
        // in the template-wide concatenation).
        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_multi_token_haul.xml',
            '<ContractTemplate.TestMultiToken __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000600" __path="libs/foundry/records/contracts/contracttemplates/test_multi_token_haul.xml"><objectiveTokens><ObjectiveToken id="combat_token_id" debugName="Combat"><properties><ObjectiveProperty_Referenced missionVariableName="CombatTarget"><property value="MissionProperty[6000]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="CombatZone"><property value="MissionProperty[6001]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="CombatReward"><property value="MissionProperty[6002]" /></ObjectiveProperty_Referenced></properties><objectiveHandler><ObjectiveHandler_Kill /></objectiveHandler></ObjectiveToken><ObjectiveToken id="delivery_token_id" debugName="Delivery"><properties><ObjectiveProperty_Referenced missionVariableName="PickupLocation"><property value="MissionProperty[5000]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DropoffLocation"><property value="MissionProperty[5001]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DeliverItem"><property value="MissionProperty[5002]" /></ObjectiveProperty_Referenced></properties><objectiveHandler><ObjectiveHandler_Hauling><haulingOrders><HaulingOrder_MissionItem minAmount="2" maxAmount="0"><pickUpLocation value="ObjectiveProperty_Referenced[1000]" /><dropOffLocation value="ObjectiveProperty_Referenced[1001]" /><item value="ObjectiveProperty_Referenced[1002]" /></HaulingOrder_MissionItem></haulingOrders></ObjectiveHandler_Hauling></objectiveHandler></ObjectiveToken></objectiveTokens></ContractTemplate.TestMultiToken>'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000210' => $itemPath,
                'cccc0000-0000-0000-0000-000000000600' => $templatePath,
            ],
        );

        $this->bootServices();

        // Entry overrides DeliverItem (the Delivery token's variable) with a concrete crate.
        // Pre-fix: the resolver reads CombatReward (wrong token), finds no override, and
        // silently drops the order. Post-fix: it reads DeliverItem and resolves correctly.
        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="Handler"><contractParams><propertyOverrides><MissionProperty missionVariableName="DeliverItem"><value><MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1"><matchConditions><DataSetMatchCondition_SpecificItemsDef><items><Reference value="aaaa0000-0000-0000-0000-000000000210" /></items></DataSetMatchCondition_SpecificItemsDef></matchConditions></MissionPropertyValue_MissionItem></value></MissionProperty></propertyOverrides></contractParams><contracts><Contract id="e1" debugName="TestMultiTokenCourier" template="cccc0000-0000-0000-0000-000000000600"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $contract = new Contract($entry, $handler, $record);

        $orders = $this->invokeMethod($contract, 'buildTemplateObjectiveHaulingOrders');

        // Pre-fix this returned [] (CombatReward has no override -> silent drop).
        // Post-fix the Delivery token's DeliverItem resolves to the crate.
        self::assertCount(1, $orders);
        self::assertSame('MissionItem', $orders[0]['kind']);
        self::assertSame('aaaa0000-0000-0000-0000-000000000210', $orders[0]['uuid']);
        self::assertSame('Test Crate', $orders[0]['name']);
        self::assertSame(2, $orders[0]['min_amount']);
    }

    public function test_template_mission_item_order_emits_tag_terms_when_no_specific_items(): void
    {
        // Tag-based MissionItem (e.g. CFP courier: "deliver any size 1/2 weapon"). The entry
        // override has no specificItems, only a DataSetMatchCondition_TagSearch, so the order
        // must carry tag_search_terms instead of being dropped (which would hide the hauling).

        // Two tags the entry will search for. writeExtractedTagFiles makes them resolvable to names.
        $tag1 = ['name' => '2H', 'uuid' => 'eeee0000-0000-0000-0000-000000000001'];
        $tag2 = ['name' => '1H', 'uuid' => 'eeee0000-0000-0000-0000-000000000002'];

        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_tag_haul.xml',
            '<ContractTemplate.TestTag __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000400" __path="libs/foundry/records/contracts/contracttemplates/test_tag_haul.xml"><objectiveTokens><ObjectiveToken id="dddd0000-0000-0000-0000-000000000500" debugName="Delivery"><properties><ObjectiveProperty_Referenced missionVariableName="PickupLocation_BP"><property value="MissionProperty[3000]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DropoffLocation_BP"><property value="MissionProperty[3001]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="Item1"><property value="MissionProperty[3002]" /></ObjectiveProperty_Referenced></properties><objectiveHandler><ObjectiveHandler_Hauling><haulingOrders><HaulingOrder_MissionItem minAmount="1" maxAmount="0"><pickUpLocation value="ObjectiveProperty_Referenced[2000]" /><dropOffLocation value="ObjectiveProperty_Referenced[2001]" /><item value="ObjectiveProperty_Referenced[2002]" /></HaulingOrder_MissionItem></haulingOrders></ObjectiveHandler_Hauling></objectiveHandler></ObjectiveToken></objectiveTokens></ContractTemplate.TestTag>'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'cccc0000-0000-0000-0000-000000000400' => $templatePath,
            ],
        );

        // Register the tags so TagDatabaseService can resolve UUID -> name.
        $this->initializeMinimalItemServices(tags: [$tag1, $tag2]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        // Entry overrides Item1 with a TagSearch (no specificItems).
        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="Handler"><contractParams><propertyOverrides><MissionProperty missionVariableName="Item1"><value><MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1"><matchConditions><DataSetMatchCondition_TagSearch tagType="General"><tagSearch><TagSearchTerm><positiveTags><Reference value="eeee0000-0000-0000-0000-000000000001" /><Reference value="eeee0000-0000-0000-0000-000000000002" /></positiveTags></TagSearchTerm></tagSearch></DataSetMatchCondition_TagSearch></matchConditions></MissionPropertyValue_MissionItem></value></MissionProperty></propertyOverrides></contractParams><contracts><Contract id="e1" debugName="TestTagCourier" template="cccc0000-0000-0000-0000-000000000400"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $contract = new Contract($entry, $handler, $record);

        $orders = $this->invokeMethod($contract, 'buildTemplateObjectiveHaulingOrders');

        // Not dropped: one MissionItem order carrying the resolved tag search terms.
        self::assertCount(1, $orders);
        self::assertSame('MissionItem', $orders[0]['kind']);
        self::assertNull($orders[0]['uuid']);
        self::assertSame(1, $orders[0]['min_amount']);
        self::assertArrayHasKey('tag_search_terms', $orders[0]);
        $terms = $orders[0]['tag_search_terms'];
        self::assertCount(1, $terms);
        $names = array_column($terms[0]['positive_tags'], 'name');
        self::assertSame(['2H', '1H'], $names);
    }

    public function test_hauling_order_property_attaches_pickup_and_dropoff_pools(): void
    {
        // HaulingOrder_Property nodes bind cargo to location POOLS: each carries
        // pickUpLocation/dropOffLocation/haulingOrdersProperty refs that index into the
        // template's ObjectiveProperty list, whose entries carry missionVariableName --
        // the SAME names that become the contract's LocationPools keys (PickupLocation,
        // DropoffLocation1, ...). The override path emits the cargo rows but drops the
        // binding, so a 1->2 split-delivery (one pickup, two dropoffs) exports as a flat
        // list of identical-looking rows. Resolve the refs and tag each row with its pool.
        //
        // The refs are absolute positions, and routing nodes reference a non-contiguous
        // subset that does NOT start at the list head (Organization sits unreferenced at
        // index 0), so the min-hex heuristic used elsewhere mis-decodes -- the base must be
        // anchored on the cargo variable matching a HaulingOrdersValue override.
        $resA = $this->writeFile(
            'Data/Libs/Foundry/Records/resources/test_res_a.xml',
            '<ResourceType.ResA displayName="Resource A" __type="ResourceType" __ref="bbbb0000-0000-0000-0000-0000000000a1" __path="libs/foundry/records/resources/test_res_a.xml" />'
        );
        $resB = $this->writeFile(
            'Data/Libs/Foundry/Records/resources/test_res_b.xml',
            '<ResourceType.ResB displayName="Resource B" __type="ResourceType" __ref="bbbb0000-0000-0000-0000-0000000000a2" __path="libs/foundry/records/resources/test_res_b.xml" />'
        );

        // ObjectiveProperty list (6 entries). Base = 0x1000 (Organization, unreferenced).
        //   [0] Organization        [3] PickupLocation
        //   [1] HaulingOrderForDropoff1   [4] DropoffLocation1
        //   [2] HaulingOrderForDropoff2   [5] DropoffLocation2
        // Edge1: cargo[1001] pickup[1003] dropoff[1004]   Edge2: cargo[1002] pickup[1003] dropoff[1005]
        // min(1001,1003,1004)=1001 != base 1000, so the naive min-heuristic mis-decodes.
        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_routing_haul.xml',
            '<ContractTemplate.TestRoute __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000700" __path="libs/foundry/records/contracts/contracttemplates/test_routing_haul.xml"><objectiveTokens><ObjectiveToken id="delivery_token_id" debugName="Delivery"><properties><ObjectiveProperty_Referenced missionVariableName="Organization"><property value="MissionProperty[7000]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="HaulingOrderForDropoff1"><property value="MissionProperty[7001]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="HaulingOrderForDropoff2"><property value="MissionProperty[7002]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="PickupLocation"><property value="MissionProperty[7003]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DropoffLocation1"><property value="MissionProperty[7004]" /></ObjectiveProperty_Referenced><ObjectiveProperty_Referenced missionVariableName="DropoffLocation2"><property value="MissionProperty[7005]" /></ObjectiveProperty_Referenced></properties><objectiveHandler><ObjectiveHandler_Hauling><haulingOrders><HaulingOrder_Property><pickUpLocation value="ObjectiveProperty_Referenced[1003]" /><dropOffLocation value="ObjectiveProperty_Referenced[1004]" /><haulingOrdersProperty value="ObjectiveProperty_Referenced[1001]" /></HaulingOrder_Property><HaulingOrder_Property><pickUpLocation value="ObjectiveProperty_Referenced[1003]" /><dropOffLocation value="ObjectiveProperty_Referenced[1005]" /><haulingOrdersProperty value="ObjectiveProperty_Referenced[1002]" /></HaulingOrder_Property></haulingOrders></ObjectiveHandler_Hauling></objectiveHandler></ObjectiveToken></objectiveTokens></ContractTemplate.TestRoute>'
        );

        $this->writeCacheFiles(
            uuidToPathMap: [
                'bbbb0000-0000-0000-0000-0000000000a1' => $resA,
                'bbbb0000-0000-0000-0000-0000000000a2' => $resB,
                'cccc0000-0000-0000-0000-000000000700' => $templatePath,
            ],
        );

        $this->bootServices();

        // Two cargo overrides -- one per routing edge -- keyed by the variables the
        // routing nodes reference. These are what buildHaulingOrders emits rows from.
        $ha = '<MissionProperty missionVariableName="HaulingOrderForDropoff1"><value><MissionPropertyValue_HaulingOrders><haulingOrderContent><HaulingOrderContent_Resource resource="bbbb0000-0000-0000-0000-0000000000a1" maxContainerSize="16" minSCU="48" /></haulingOrderContent></MissionPropertyValue_HaulingOrders></value></MissionProperty>';
        $hb = '<MissionProperty missionVariableName="HaulingOrderForDropoff2"><value><MissionPropertyValue_HaulingOrders><haulingOrderContent><HaulingOrderContent_Resource resource="bbbb0000-0000-0000-0000-0000000000a2" maxContainerSize="16" minSCU="48" /></haulingOrderContent></MissionPropertyValue_HaulingOrders></value></MissionProperty>';
        $handlerXml = "<ContractGeneratorHandler_Recovery debugName=\"Handler\"><contractParams><propertyOverrides>{$ha}{$hb}</propertyOverrides></contractParams><contracts><Contract id=\"e1\" debugName=\"TestRoute\" template=\"cccc0000-0000-0000-0000-000000000700\"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances=\"1\" maxInstancesPerPlayer=\"1\" respawnTime=\"0\" respawnTimeVariation=\"0\" /></generationParams><contractResults contractBuyInAmount=\"0\" timeToComplete=\"-1\" /></Contract></contracts></ContractGeneratorHandler_Recovery>";

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $orders = $this->invokeMethod($contract, 'buildHaulingOrders');

        // One row per cargo override; each must carry the pool binding from its edge.
        $byUuid = array_column($orders, null, 'uuid');

        self::assertArrayHasKey('bbbb0000-0000-0000-0000-0000000000a1', $byUuid);
        self::assertSame('PickupLocation', $byUuid['bbbb0000-0000-0000-0000-0000000000a1']['pickup_pool']);
        self::assertSame('DropoffLocation1', $byUuid['bbbb0000-0000-0000-0000-0000000000a1']['dropoff_pool']);

        self::assertArrayHasKey('bbbb0000-0000-0000-0000-0000000000a2', $byUuid);
        self::assertSame('PickupLocation', $byUuid['bbbb0000-0000-0000-0000-0000000000a2']['pickup_pool']);
        self::assertSame('DropoffLocation2', $byUuid['bbbb0000-0000-0000-0000-0000000000a2']['dropoff_pool']);
    }

    public function test_handler_level_completed_contract_tags_prerequisite_is_surfaced(): void
    {
        // TheCollector (Wikelo) gates EVERY contract under a handler via the handler's
        // defaultAvailability/prerequisites/ContractPrerequisite_CompletedContractTags (a
        // shared chain gate). Pre-fix buildTagPrerequisites read the entry container only,
        // so contracts with no entry-level chain prereq exported Prerequisites: [] despite
        // a real handler-level gate (e.g. eda899b9 -> tag 87e124b5).
        $tag1 = ['name' => 'CollectorChain', 'uuid' => 'eeee0000-0000-0000-0000-000000000001'];
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices(tags: [$tag1]);

        $handlerXml = '<ContractGeneratorHandler_List debugName="TheCollector_Small_Items"><defaultAvailability><prerequisites><ContractPrerequisite_CompletedContractTags includePrerequisiteWhenSharing="0" requiredCountValue="1" excludedCountValue="1"><requiredCompletedContractTags><tags><Reference value="eeee0000-0000-0000-0000-000000000001" /></tags></requiredCompletedContractTags></ContractPrerequisite_CompletedContractTags></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="SmallItemRun"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $prerequisites = $this->invokeMethod($contract, 'buildTagPrerequisites', ServiceFactory::getTagDatabaseService());

        self::assertCount(1, $prerequisites);
        self::assertSame(1, $prerequisites[0]['required_count']);
        self::assertSame('eeee0000-0000-0000-0000-000000000001', $prerequisites[0]['required_tags'][0]['uuid']);
        self::assertSame('CollectorChain', $prerequisites[0]['required_tags'][0]['name']);
    }

    public function test_handler_and_entry_completed_contract_tags_merge_without_duplicates(): void
    {
        // When the handler and the entry both gate on the same tag (the TheCollector shape,
        // where the entry mirrors the handler), the merged Prerequisites must not duplicate.
        // When they gate on different tags, both must survive (they are cumulative AND gates).
        $tagShared = ['name' => 'Shared', 'uuid' => 'eeee0000-0000-0000-0000-000000000010'];
        $tagEntryOnly = ['name' => 'EntryOnly', 'uuid' => 'eeee0000-0000-0000-0000-000000000020'];
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices(tags: [$tagShared, $tagEntryOnly]);

        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability><prerequisites><ContractPrerequisite_CompletedContractTags includePrerequisiteWhenSharing="0" requiredCountValue="1" excludedCountValue="0"><requiredCompletedContractTags><tags><Reference value="eeee0000-0000-0000-0000-000000000010" /></tags></requiredCompletedContractTags></ContractPrerequisite_CompletedContractTags></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="E"><additionalPrerequisites><ContractPrerequisite_CompletedContractTags includePrerequisiteWhenSharing="0" requiredCountValue="1" excludedCountValue="0"><requiredCompletedContractTags><tags><Reference value="eeee0000-0000-0000-0000-000000000010" /><Reference value="eeee0000-0000-0000-0000-000000000020" /></tags></requiredCompletedContractTags></ContractPrerequisite_CompletedContractTags></additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $prerequisites = $this->invokeMethod($contract, 'buildTagPrerequisites', ServiceFactory::getTagDatabaseService());

        // Handler gate {Shared} and entry gate {Shared, EntryOnly} are distinct prereq blocks
        // (different tag sets), so both survive -- NOT deduped into one.
        self::assertCount(2, $prerequisites);

        $allRequiredUuids = [];
        foreach ($prerequisites as $p) {
            foreach ($p['required_tags'] as $t) {
                $allRequiredUuids[] = $t['uuid'];
            }
        }
        sort($allRequiredUuids);
        self::assertSame(['eeee0000-0000-0000-0000-000000000010', 'eeee0000-0000-0000-0000-000000000010', 'eeee0000-0000-0000-0000-000000000020'], $allRequiredUuids);
    }

    public function test_handler_and_entry_completed_contract_tags_dedup_identical(): void
    {
        // Identical handler+entry gates (same tags, same counts) collapse to one entry to
        // avoid noise when an entry mirrors its handler's default gate.
        $tag = ['name' => 'Shared', 'uuid' => 'eeee0000-0000-0000-0000-000000000030'];
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices(tags: [$tag]);

        $cct = '<ContractPrerequisite_CompletedContractTags includePrerequisiteWhenSharing="0" requiredCountValue="1" excludedCountValue="0"><requiredCompletedContractTags><tags><Reference value="eeee0000-0000-0000-0000-000000000030" /></tags></requiredCompletedContractTags></ContractPrerequisite_CompletedContractTags>';
        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability><prerequisites>'.$cct.'</prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="E"><additionalPrerequisites>'.$cct.'</additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $prerequisites = $this->invokeMethod($contract, 'buildTagPrerequisites', ServiceFactory::getTagDatabaseService());

        self::assertCount(1, $prerequisites, 'Identical handler+entry gates must collapse to one');
    }

    public function test_notify_on_available_entry_override_wins_over_handler_default(): void
    {
        // Same shape as the reputation bug: NotifyOnAvailable is readable from both the
        // entry (paramOverrides boolParam) and the handler (defaultAvailability attr +
        // contractParams boolParam), but buildProperties read the handler default only.
        // Four unaffiliated counter-offer contracts (866fb0fd ...) set the entry override
        // to 1 while the handler default is 0, and exported null.
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability notifyOnAvailable="0" /><contractParams /><contracts><Contract id="e1" debugName="E"><paramOverrides><boolParamOverrides><ContractBoolParam param="NotifyOnAvailable" value="1" /></boolParamOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $properties = $this->invokeMethod($contract, 'buildProperties');

        self::assertTrue($properties['notify_on_available']);
    }

    public function test_notify_on_available_handler_contract_params_override_beats_default(): void
    {
        // The handler's own contractParams/boolParamOverrides override must beat its
        // defaultAvailability attr when no entry override is present. Pre-fix the handler
        // method read defaultAvailability only, dropping the contractParams override.
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability notifyOnAvailable="0" /><contractParams><boolParamOverrides><ContractBoolParam param="NotifyOnAvailable" value="1" /></boolParamOverrides></contractParams><contracts><Contract id="e1" debugName="E"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $properties = $this->invokeMethod($contract, 'buildProperties');

        self::assertTrue($properties['notify_on_available']);
    }

    public function test_notify_on_available_falls_back_to_handler_default(): void
    {
        // No overrides anywhere -> the handler defaultAvailability attr is the source.
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability notifyOnAvailable="1" /><contractParams /><contracts><Contract id="e1" debugName="E"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $properties = $this->invokeMethod($contract, 'buildProperties');

        self::assertTrue($properties['notify_on_available']);
    }

    public function test_location_prerequisite_surfaces_specific_poi(): void
    {
        // ContractPrerequisite_Location (@locationAvailable) gates a contract to a SPECIFIC
        // POI/system (a StarMapObject), distinct from Locality (a region/set of POIs).
        // Pre-fix buildLocalityPrerequisites filtered type==='Locality' only, so all 453
        // Location prereqs vanished (e.g. Klescher 1464ed09 -> Aberdeen prison POI).
        $poi = $this->writeStarmapObject('55555555-0000-0000-0000-000000000001', '@Stanton1b_Aberdeen_Prison', 'AberdeenPrison');

        $this->writeCacheFiles(uuidToPathMap: [strtolower('55555555-0000-0000-0000-000000000001') => $poi]);
        $this->initializeMinimalItemServices(translations: ['Stanton1b_Aberdeen_Prison' => 'Aberdeen Prison']);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability><prerequisites><ContractPrerequisite_Location locationAvailable="55555555-0000-0000-0000-000000000001" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="E"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $requirements = $this->invokeMethod($contract, 'buildRequirements');

        self::assertArrayHasKey('required_locations', $requirements);
        self::assertCount(1, $requirements['required_locations']);
        self::assertSame('55555555-0000-0000-0000-000000000001', $requirements['required_locations'][0]['uuid']);
        self::assertSame('Aberdeen Prison', $requirements['required_locations'][0]['name']);
    }

    public function test_location_prerequisite_merges_entry_and_handler_and_dedups(): void
    {
        // Location prereqs on BOTH the handler defaultAvailability and the entry
        // additionalPrerequisites must both surface (and dedup when identical).
        $poi1 = $this->writeStarmapObject('55555555-0000-0000-0000-000000000010', '@LocA', 'LocA');
        $poi2 = $this->writeStarmapObject('55555555-0000-0000-0000-000000000020', '@LocB', 'LocB');

        $this->writeCacheFiles(uuidToPathMap: [
            strtolower('55555555-0000-0000-0000-000000000010') => $poi1,
            strtolower('55555555-0000-0000-0000-000000000020') => $poi2,
        ]);
        $this->initializeMinimalItemServices(translations: ['LocA' => 'Loc A', 'LocB' => 'Loc B']);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        // Handler gates LocA; entry gates LocA (dup) + LocB. Expect LocA, LocB (deduped).
        $handlerXml = '<ContractGeneratorHandler_List debugName="H"><defaultAvailability><prerequisites><ContractPrerequisite_Location locationAvailable="55555555-0000-0000-0000-000000000010" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="E"><additionalPrerequisites><ContractPrerequisite_Location locationAvailable="55555555-0000-0000-0000-000000000010" /><ContractPrerequisite_Location locationAvailable="55555555-0000-0000-0000-000000000020" /></additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $requirements = $this->invokeMethod($contract, 'buildRequirements');

        $uuids = array_column($requirements['required_locations'], 'uuid');
        sort($uuids);
        self::assertSame(['55555555-0000-0000-0000-000000000010', '55555555-0000-0000-0000-000000000020'], $uuids);
    }

    public function test_mission_result_outcome_labels_each_reputation_reward(): void
    {
        // Every ContractResult_* carries a 5-bool <missionResults> vector gating which
        // mission outcome (slot 0 = Success, slot 2 = Failure, all-off = unconditional)
        // fires the reward. The dumper parsed it via an orphaned getter and emitted nothing,
        // so a failure-only reputation penalty was indistinguishable from a success reward,
        // and an unconditional reward was indistinguishable from a success-only one. The
        // sign heuristic (<0 => Lost) happens to align for LegacyReputation but is not the
        // real gate and cannot express 'unconditional' at all.
        $chain = $this->writeReputationChain([
            'factionUuid' => '41000000-0000-0000-0000-000000000001',
            'factionRepUuid' => '41000000-0000-0000-0000-000000000002',
            'scopeUuid' => '41000000-0000-0000-0000-000000000003',
            'minStandingUuid' => '41000000-0000-0000-0000-000000000004',
            'maxStandingUuid' => '41000000-0000-0000-0000-000000000005',
            'factionNameKey' => '@loc_fac',
            'factionName' => 'TestFaction',
            'scopeName' => 'TestScope',
            'minStandingNameKey' => '@loc_min',
            'minStandingName' => 'Min',
            'maxStandingNameKey' => '@loc_max',
            'maxStandingName' => 'Max',
            'minReputation' => 0,
            'maxReputation' => 1000,
        ]);

        // Three reputation reward tiers, one per outcome slot we exercise.
        $rewardSuccess = $this->writeReputationReward('41100000-0000-0000-0000-000000000001', 500, '+T_success');
        $rewardFailure = $this->writeReputationReward('41100000-0000-0000-0000-000000000002', -250, '-T_fail');
        $rewardUncond = $this->writeReputationReward('41100000-0000-0000-0000-000000000003', 50, '+T_uncond');

        $this->writeCacheFiles(
            classToPathMap: $chain['classToPathMap'],
            uuidToPathMap: array_merge($chain['uuidToPathMap'], [
                '41100000-0000-0000-0000-000000000001' => $rewardSuccess,
                '41100000-0000-0000-0000-000000000002' => $rewardFailure,
                '41100000-0000-0000-0000-000000000003' => $rewardUncond,
            ]),
            uuidToClassMap: $chain['uuidToClassMap'],
            classToUuidMap: $chain['classToUuidMap'],
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'loc_fac' => 'TestFaction',
            'loc_min' => 'Min',
            'loc_max' => 'Max',
        ]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $facRep = '41000000-0000-0000-0000-000000000002';
        $scope = '41000000-0000-0000-0000-000000000003';
        $handlerXml = "<ContractGeneratorHandler_Recovery debugName=\"Outcome\"><defaultAvailability><prerequisites><ContractPrerequisite_CrimeStat includePrerequisiteWhenSharing=\"0\" minCrimeStat=\"0\" maxCrimeStat=\"2\" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id=\"e1\" debugName=\"Outcome\"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances=\"1\" maxInstancesPerPlayer=\"1\" respawnTime=\"0\" respawnTimeVariation=\"0\" /></generationParams><contractResults contractBuyInAmount=\"0\" timeToComplete=\"-1\"><contractResults><ContractResult_LegacyReputation><missionResults><Bool value=\"1\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /></missionResults><contractResultReputationAmounts factionReputation=\"{$facRep}\" reputationScope=\"{$scope}\" reward=\"41100000-0000-0000-0000-000000000001\" /></ContractResult_LegacyReputation><ContractResult_LegacyReputation><missionResults><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"1\" /><Bool value=\"0\" /><Bool value=\"0\" /></missionResults><contractResultReputationAmounts factionReputation=\"{$facRep}\" reputationScope=\"{$scope}\" reward=\"41100000-0000-0000-0000-000000000002\" /></ContractResult_LegacyReputation><ContractResult_LegacyReputation><missionResults><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /></missionResults><contractResultReputationAmounts factionReputation=\"{$facRep}\" reputationScope=\"{$scope}\" reward=\"41100000-0000-0000-0000-000000000003\" /></ContractResult_LegacyReputation></contractResults></contractResults></Contract></contracts></ContractGeneratorHandler_Recovery>";

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $results = $this->invokeMethod($contract, 'buildResults', $entry->getResults());

        $byAmount = [];
        foreach ($results['reputation_gained'] ?? [] as $g) {
            $byAmount[$g['amount']] = $g;
        }
        foreach ($results['reputation_lost'] ?? [] as $l) {
            $byAmount[$l['amount']] = $l;
        }

        // Success-only reward: outcome is the default and is omitted (anti-noise).
        self::assertArrayHasKey(500, $byAmount);
        self::assertArrayNotHasKey('outcome', $byAmount[500], 'Success outcome must be omitted as the default');

        // Failure-only reward: labelled, regardless of sign.
        self::assertArrayHasKey(-250, $byAmount);
        self::assertSame('failure', $byAmount[-250]['outcome']);

        // Unconditional reward: distinguishable from a success reward -- the one thing the
        // sign heuristic could never express.
        self::assertArrayHasKey(50, $byAmount);
        self::assertSame('unconditional', $byAmount[50]['outcome']);
    }

    public function test_subcontract_localities_merge_into_availability_locations(): void
    {
        // A SubContract (nested under <Contract>/<CareerContract>) is a location-specific
        // variant: it carries its own additionalPrerequisites gating where that variant
        // is offered. The dumper read only the parent entry's direct additionalPrerequisites,
        // so a Shubin mining career contract exporting as 'Stanton1 (Hurston) only' would
        // actually be offered at Stanton2/3/4 too -- the subcontract gates vanish. Merge
        // the subcontract localities into the parent's availability_locations.
        $locStanton1 = $this->writeMissionLocality('42000000-0000-0000-0000-000000000001', 'Stanton1');
        $locStanton2 = $this->writeMissionLocality('42000000-0000-0000-0000-000000000002', 'Stanton2');
        $locStanton3 = $this->writeMissionLocality('42000000-0000-0000-0000-000000000003', 'Stanton3');

        $this->writeCacheFiles(
            uuidToPathMap: [
                '42000000-0000-0000-0000-000000000001' => $locStanton1,
                '42000000-0000-0000-0000-000000000002' => $locStanton2,
                '42000000-0000-0000-0000-000000000003' => $locStanton3,
            ],
        );
        $this->initializeMinimalItemServices();
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $l1 = '42000000-0000-0000-0000-000000000001';
        $l2 = '42000000-0000-0000-0000-000000000002';
        $l3 = '42000000-0000-0000-0000-000000000003';
        $handlerXml = "<ContractGeneratorHandler_Career debugName=\"Sub\"><contractParams /><contracts><CareerContract id=\"e1\" debugName=\"Sub\" template=\"t\"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances=\"1\" maxInstancesPerPlayer=\"1\" respawnTime=\"0\" respawnTimeVariation=\"0\" /></generationParams><subContracts><SubContract id=\"s1\"><additionalPrerequisites><ContractPrerequisite_Locality localityAvailable=\"{$l2}\" /></additionalPrerequisites></SubContract><SubContract id=\"s2\"><additionalPrerequisites><ContractPrerequisite_Locality localityAvailable=\"{$l3}\" /></additionalPrerequisites></SubContract></subContracts><additionalPrerequisites><ContractPrerequisite_Locality localityAvailable=\"{$l1}\" /></additionalPrerequisites><contractResults contractBuyInAmount=\"0\" timeToComplete=\"-1\" /></CareerContract></contracts></ContractGeneratorHandler_Career>";

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $requirements = $this->invokeMethod($contract, 'buildRequirements');

        $names = array_column($requirements['availability_locations'], 'name');
        sort($names);

        // Parent (Stanton1) + both subcontract variants (Stanton2, Stanton3) must all surface.
        self::assertSame(['Stanton1', 'Stanton2', 'Stanton3'], $names);
    }

    public function test_entry_level_reputation_prerequisite_is_surfaced(): void
    {
        // TheCollector (Wikelo) contracts put their reputation gate on the contract
        // ENTRY under additionalPrerequisites/ContractPrerequisite_Reputation, not on
        // the handler's defaultAvailability/prerequisites. Pre-fix the dumper only read
        // the handler path, so ReputationPrerequisite came out null for both Wikelo
        // collector contracts (77fa8882 / 3c839c32).
        $chain = $this->writeReputationChain([
            'factionUuid' => '40000000-0000-0000-0000-000000000001',
            'factionRepUuid' => '40000000-0000-0000-0000-000000000002',
            'scopeUuid' => '40000000-0000-0000-0000-000000000003',
            'minStandingUuid' => '40000000-0000-0000-0000-000000000004',
            'maxStandingUuid' => '40000000-0000-0000-0000-000000000005',
            'factionNameKey' => '@loc_wikelo',
            'factionName' => 'Wikelo',
            'scopeName' => 'WikeloScope',
            'minStandingNameKey' => '@loc_wikelo_rank1',
            'minStandingName' => 'Rank 1',
            'maxStandingNameKey' => '@loc_wikelo_rank2',
            'maxStandingName' => 'Rank 2',
            'minReputation' => 340,
            'maxReputation' => 999,
        ]);

        $this->writeCacheFiles(
            classToPathMap: $chain['classToPathMap'],
            uuidToPathMap: $chain['uuidToPathMap'],
            uuidToClassMap: $chain['uuidToClassMap'],
            classToUuidMap: $chain['classToUuidMap'],
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'loc_wikelo' => 'Wikelo',
            'loc_wikelo_rank1' => 'Rank 1',
            'loc_wikelo_rank2' => 'Rank 2',
        ]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="TheCollector_Vehicles"><defaultAvailability><prerequisites><ContractPrerequisite_CrimeStat includePrerequisiteWhenSharing="0" minCrimeStat="0" maxCrimeStat="2" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="WikeloRun"><additionalPrerequisites><ContractPrerequisite_Reputation includePrerequisiteWhenSharing="0" factionReputation="40000000-0000-0000-0000-000000000002" scope="40000000-0000-0000-0000-000000000003" minStanding="40000000-0000-0000-0000-000000000004" maxStanding="40000000-0000-0000-0000-000000000005" /></additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $requirements = $this->invokeMethod($contract, 'buildRequirements');

        self::assertArrayHasKey('reputation_prerequisite', $requirements);
        $rep = $requirements['reputation_prerequisite'];
        self::assertSame('Wikelo', $rep['faction']);
        self::assertSame('40000000-0000-0000-0000-000000000001', $rep['faction_uuid']);
        self::assertSame('WikeloScope', $rep['scope']);
        self::assertSame('40000000-0000-0000-0000-000000000003', $rep['scope_uuid']);
        self::assertSame('Rank 1', $rep['min_standing']['name']);
        self::assertSame(340, $rep['min_standing']['min_reputation']);
        self::assertSame('Rank 2', $rep['max_standing']['name']);
        self::assertSame(999, $rep['max_standing']['min_reputation']);
    }

    public function test_build_faction_falls_back_to_entry_reputation_prerequisite(): void
    {
        $chain = $this->writeReputationChain([
            'factionUuid' => '40000000-0000-0000-0000-000000000001',
            'factionRepUuid' => '40000000-0000-0000-0000-000000000002',
            'scopeUuid' => '40000000-0000-0000-0000-000000000003',
            'minStandingUuid' => '40000000-0000-0000-0000-000000000004',
            'maxStandingUuid' => '40000000-0000-0000-0000-000000000005',
            'factionNameKey' => '@loc_wikelo',
            'factionName' => 'Wikelo',
            'scopeName' => 'Wikelo',
            'minStandingNameKey' => '@loc_wikelo_rank1',
            'minStandingName' => 'Very Good Customer',
            'maxStandingNameKey' => '@loc_wikelo_rank2',
            'maxStandingName' => 'Very Best Customer',
            'minReputation' => 340,
            'maxReputation' => 999,
        ]);

        $this->writeCacheFiles(
            classToPathMap: $chain['classToPathMap'],
            uuidToClassMap: $chain['uuidToClassMap'],
            classToUuidMap: $chain['classToUuidMap'],
            uuidToPathMap: $chain['uuidToPathMap'],
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'loc_wikelo' => 'Wikelo',
            'loc_wikelo_rank1' => 'Very Good Customer',
            'loc_wikelo_rank2' => 'Very Best Customer',
        ]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="TheCollector_Vehicles"><defaultAvailability><prerequisites><ContractPrerequisite_CrimeStat includePrerequisiteWhenSharing="0" minCrimeStat="0" maxCrimeStat="2" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="WikeloRun"><additionalPrerequisites><ContractPrerequisite_Reputation includePrerequisiteWhenSharing="0" factionReputation="40000000-0000-0000-0000-000000000002" scope="40000000-0000-0000-0000-000000000003" minStanding="40000000-0000-0000-0000-000000000004" maxStanding="40000000-0000-0000-0000-000000000005" /></additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $faction = $this->invokeMethod($contract, 'buildFaction');

        self::assertNotNull($faction, 'buildFaction() must fall back to the entry reputation prerequisite');
        self::assertSame('Wikelo', $faction['faction_reputation']['name']);
        self::assertSame('40000000-0000-0000-0000-000000000001', $faction['faction_reputation']['uuid']);
        self::assertSame('Wikelo', $faction['reputation_scope']['scope_name']);
        self::assertSame('40000000-0000-0000-0000-000000000003', $faction['reputation_scope']['uuid']);
    }

    public function test_build_faction_inherits_handler_reputation_consensus(): void
    {
        $chain = $this->writeReputationChain([
            'factionUuid' => '40000000-0000-0000-0000-000000000001',
            'factionRepUuid' => '40000000-0000-0000-0000-000000000002',
            'scopeUuid' => '40000000-0000-0000-0000-000000000003',
            'minStandingUuid' => '40000000-0000-0000-0000-000000000004',
            'maxStandingUuid' => '40000000-0000-0000-0000-000000000005',
            'factionNameKey' => '@loc_wikelo',
            'factionName' => 'Wikelo',
            'scopeName' => 'Wikelo',
            'minStandingNameKey' => '@loc_wikelo_rank1',
            'minStandingName' => 'Very Good Customer',
            'maxStandingNameKey' => '@loc_wikelo_rank2',
            'maxStandingName' => 'Very Best Customer',
            'minReputation' => 340,
            'maxReputation' => 999,
        ]);

        $this->writeCacheFiles(
            classToPathMap: $chain['classToPathMap'],
            uuidToPathMap: $chain['uuidToPathMap'],
            uuidToClassMap: $chain['uuidToClassMap'],
            classToUuidMap: $chain['classToUuidMap'],
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'loc_wikelo' => 'Wikelo',
            'loc_wikelo_rank1' => 'Very Good Customer',
            'loc_wikelo_rank2' => 'Very Best Customer',
        ]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_List debugName="TheCollector_Standard"><defaultAvailability><prerequisites><ContractPrerequisite_CrimeStat includePrerequisiteWhenSharing="0" minCrimeStat="0" maxCrimeStat="2" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="favors" debugName="TheCollector_Favours_CouncilScrip"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="0" /></Contract><Contract id="sibling" debugName="TheCollector_Intro"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="0"><contractResults><ContractResult_LegacyReputation><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults><contractResultReputationAmounts factionReputation="40000000-0000-0000-0000-000000000002" reputationScope="40000000-0000-0000-0000-000000000003" reward="40000000-0000-0000-0000-000000000009" /></ContractResult_LegacyReputation></contractResults></contractResults></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $faction = $this->invokeMethod($contract, 'buildFaction');

        self::assertNotNull($faction, 'faction-less entry must inherit the handler reputation consensus');
        self::assertSame('Wikelo', $faction['faction_reputation']['name']);
        self::assertSame('40000000-0000-0000-0000-000000000001', $faction['faction_reputation']['uuid']);
        self::assertSame('Wikelo', $faction['reputation_scope']['scope_name']);
        self::assertSame('40000000-0000-0000-0000-000000000003', $faction['reputation_scope']['uuid']);
    }

    public function test_entry_reputation_prerequisite_overrides_handler_level(): void
    {
        // When both the handler and the entry declare a reputation prerequisite, the
        // entry is the more specific (per-contract) gate and must win.
        $entry = $this->writeReputationChain([
            'factionUuid' => '40000000-0000-0000-0000-000000000011',
            'factionRepUuid' => '40000000-0000-0000-0000-000000000012',
            'scopeUuid' => '40000000-0000-0000-0000-000000000013',
            'minStandingUuid' => '40000000-0000-0000-0000-000000000014',
            'maxStandingUuid' => '40000000-0000-0000-0000-000000000015',
            'factionNameKey' => '@loc_entry_faction',
            'factionName' => 'EntryFaction',
            'scopeName' => 'EntryScope',
            'minStandingNameKey' => '@loc_entry_min',
            'minStandingName' => 'Entry Min',
            'maxStandingNameKey' => '@loc_entry_max',
            'maxStandingName' => 'Entry Max',
            'minReputation' => 10,
            'maxReputation' => 20,
        ]);
        $handler = $this->writeReputationChain([
            'factionUuid' => '40000000-0000-0000-0000-000000000021',
            'factionRepUuid' => '40000000-0000-0000-0000-000000000022',
            'scopeUuid' => '40000000-0000-0000-0000-000000000023',
            'minStandingUuid' => '40000000-0000-0000-0000-000000000024',
            'maxStandingUuid' => '40000000-0000-0000-0000-000000000025',
            'factionNameKey' => '@loc_handler_faction',
            'factionName' => 'HandlerFaction',
            'scopeName' => 'HandlerScope',
            'minStandingNameKey' => '@loc_handler_min',
            'minStandingName' => 'Handler Min',
            'maxStandingNameKey' => '@loc_handler_max',
            'maxStandingName' => 'Handler Max',
            'minReputation' => 30,
            'maxReputation' => 40,
        ]);

        $this->writeCacheFiles(
            classToPathMap: array_replace_recursive($entry['classToPathMap'], $handler['classToPathMap']),
            uuidToPathMap: array_replace($entry['uuidToPathMap'], $handler['uuidToPathMap']),
            uuidToClassMap: array_replace($entry['uuidToClassMap'], $handler['uuidToClassMap']),
            classToUuidMap: array_replace($entry['classToUuidMap'], $handler['classToUuidMap']),
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'loc_entry_faction' => 'EntryFaction',
            'loc_handler_faction' => 'HandlerFaction',
        ]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="H"><defaultAvailability><prerequisites><ContractPrerequisite_Reputation includePrerequisiteWhenSharing="0" factionReputation="40000000-0000-0000-0000-000000000022" scope="40000000-0000-0000-0000-000000000023" minStanding="40000000-0000-0000-0000-000000000024" maxStanding="40000000-0000-0000-0000-000000000025" /></prerequisites></defaultAvailability><contractParams /><contracts><Contract id="e1" debugName="E"><additionalPrerequisites><ContractPrerequisite_Reputation includePrerequisiteWhenSharing="0" factionReputation="40000000-0000-0000-0000-000000000012" scope="40000000-0000-0000-0000-000000000013" minStanding="40000000-0000-0000-0000-000000000014" maxStanding="40000000-0000-0000-0000-000000000015" /></additionalPrerequisites><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handlerDoc = ContractHandler::fromNode($dom->documentElement);
        $entryDoc = $handlerDoc->getContracts()[0];

        $contract = new Contract($entryDoc, $handlerDoc, new ContractGeneratorRecord);
        $requirements = $this->invokeMethod($contract, 'buildRequirements');

        $rep = $requirements['reputation_prerequisite'];
        self::assertSame('EntryFaction', $rep['faction'], 'Entry-level reputation prereq must override handler-level');
        self::assertSame('EntryScope', $rep['scope']);
        self::assertSame(10, $rep['min_standing']['min_reputation']);
    }

    /**
     * Write a minimal StarMapObject record (a specific POI / system) used as a
     * ContractPrerequisite_Location @locationAvailable target.
     */
    private function writeStarmapObject(string $uuid, string $nameKey, string $className = 'TestPOI'): string
    {
        return $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/starmap/pu/%s.xml', strtolower($uuid)),
            sprintf('<StarMapObject.%1$s name="%2$s" __type="StarMapObject" __ref="%3$s" __path="libs/foundry/records/starmap/pu/%4$s.xml" />', $className, $nameKey, $uuid, strtolower($uuid))
        );
    }

    /**
     * Write a minimal MissionLocality record (a region/set, e.g. Stanton1=Hurston system) used
     * as a ContractPrerequisite_Locality @localityAvailable target. className is the part after
     * '.' in the element name -> getClassName() returns it as the locality name.
     */
    private function writeMissionLocality(string $uuid, string $className): string
    {
        return $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/missiondata/pu_missionlocality/%s.xml', strtolower($uuid)),
            sprintf('<MissionLocality.%2$s __type="MissionLocality" __ref="%1$s" __path="libs/foundry/records/missiondata/pu_missionlocality/%3$s.xml"><availableLocations /></MissionLocality.%2$s>', $uuid, $className, strtolower($uuid))
        );
    }

    /**
     * Write a reputation reward-amount record (the +T/-T tier a ContractResult_LegacyReputation
     * points at via @reward). Resolved by FoundryLookupService::getReputationRewardByReference,
     * which path-matches under /records/reputation/rewards/missionrewards_reputation/.
     */
    private function writeReputationReward(string $uuid, int $amount, string $editorName): string
    {
        return $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/reputation/rewards/missionrewards_reputation/%s.xml', strtolower($uuid)),
            sprintf('<SReputationRewardAmount.Test editorName="%2$s" reputationAmount="%3$d" __type="SReputationRewardAmount" __ref="%1$s" __path="libs/foundry/records/reputation/rewards/missionrewards_reputation/%4$s.xml" />', $uuid, $editorName, $amount, strtolower($uuid))
        );
    }

    /**
     * @param  array<string, string|int>  $c
     * @return array{classToPathMap: array<string, array<string, string>>, uuidToPathMap: array<string, string>, uuidToClassMap: array<string, string>, classToUuidMap: array<string, string>}
     */
    private function writeReputationChain(array $c): array
    {
        $factionUuid = $c['factionUuid'];
        $factionRepUuid = $c['factionRepUuid'];
        $scopeUuid = $c['scopeUuid'];
        $minStandingUuid = $c['minStandingUuid'];
        $maxStandingUuid = $c['maxStandingUuid'];

        $factionPath = $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/factions/%s.xml', strtolower((string) $factionUuid)),
            sprintf('<Faction.TestFaction name="%1$s" factionReputationRef="%3$s" __type="Faction" __ref="%2$s" __path="libs/foundry/records/factions/test.xml" />', (string) $c['factionNameKey'], $factionUuid, $factionRepUuid)
        );
        $factionRepPath = $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/factions/factionreputation/%s.xml', strtolower((string) $factionRepUuid)),
            sprintf('<FactionReputation.TestRep displayName="%1$s" __type="FactionReputation" __ref="%2$s" __path="libs/foundry/records/factions/factionreputation/test.xml" />', (string) $c['factionNameKey'], $factionRepUuid)
        );
        $scopePath = $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/reputation/scopes/%s.xml', strtolower((string) $scopeUuid)),
            sprintf('<SReputationScopeParams.TestScope scopeName="%1$s" __type="SReputationScopeParams" __ref="%2$s" __path="libs/foundry/records/reputation/scopes/test.xml"><standingMap reputationCeiling="1000" initialReputation="0"><standings /></standingMap></SReputationScopeParams.TestScope>', (string) $c['scopeName'], $scopeUuid)
        );
        $minStandingPath = $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/reputation/standings/%s.xml', strtolower((string) $minStandingUuid)),
            sprintf('<SReputationStandingParams.Min name="Min" displayName="%1$s" minReputation="%2$d" __type="SReputationStandingParams" __ref="%3$s" __path="libs/foundry/records/reputation/standings/test.xml" />', (string) $c['minStandingNameKey'], (int) $c['minReputation'], $minStandingUuid)
        );
        $maxStandingPath = $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/reputation/standings/%s.xml', strtolower((string) $maxStandingUuid)),
            sprintf('<SReputationStandingParams.Max name="Max" displayName="%1$s" minReputation="%2$d" __type="SReputationStandingParams" __ref="%3$s" __path="libs/foundry/records/reputation/standings/test.xml" />', (string) $c['maxStandingNameKey'], (int) $c['maxReputation'], $maxStandingUuid)
        );

        return [
            'classToPathMap' => [
                'Faction' => ['TestFaction' => $factionPath],
                'FactionReputation' => ['TestRep' => $factionRepPath],
                'SReputationScopeParams' => ['TestScope' => $scopePath],
                'SReputationStandingParams' => ['Min' => $minStandingPath, 'Max' => $maxStandingPath],
            ],
            'uuidToPathMap' => [
                strtolower((string) $factionUuid) => $factionPath,
                strtolower((string) $factionRepUuid) => $factionRepPath,
                strtolower((string) $scopeUuid) => $scopePath,
                strtolower((string) $minStandingUuid) => $minStandingPath,
                strtolower((string) $maxStandingUuid) => $maxStandingPath,
            ],
            'uuidToClassMap' => [
                strtolower((string) $factionUuid) => 'TestFaction',
                strtolower((string) $factionRepUuid) => 'TestRep',
                strtolower((string) $scopeUuid) => 'TestScope',
                strtolower((string) $minStandingUuid) => 'Min',
                strtolower((string) $maxStandingUuid) => 'Max',
            ],
            'classToUuidMap' => [
                'TestFaction' => strtolower((string) $factionUuid),
            ],
        ];
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
        $offset = $this->invokeMethod($contract, 'computeHandlerPropertyBaseOffset', $overrides);

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

    public function test_faction_name_resolves_through_uninitialized_sentinel(): void
    {
        // Some Faction records carry name="@LOC_UNINITIALIZED" as a placeholder;
        // the real display name lives on the inner FactionReputation's displayName.
        // The dumper emitted "<= UNINITIALIZED =>" instead of falling through to the
        // FactionReputation name (e.g. "Vaughn").
        $factionUuid = '42000000-0000-0000-0000-000000000001';
        $factionRepUuid = '42000000-0000-0000-0000-000000000002';
        $scopeUuid = '42000000-0000-0000-0000-000000000003';

        $factionPath = $this->writeFile(
            'Data/Libs/Foundry/Records/factions/faction_unlawful_test.xml',
            sprintf(
                '<Faction.Faction_Unlawful_Test name="@LOC_UNINITIALIZED" factionReputationRef="%2$s" __type="Faction" __ref="%1$s" __path="libs/foundry/records/factions/faction_unlawful_test.xml" />',
                $factionUuid,
                $factionRepUuid,
            )
        );
        $factionRepPath = $this->writeFile(
            'Data/Libs/Foundry/Records/factions/factionreputation/factionreputation_unlawful_test.xml',
            sprintf(
                '<FactionReputation.FactionReputation_Unlawful_Test displayName="@TestFaction_RepUI_Name" __type="FactionReputation" __ref="%1$s" __path="libs/foundry/records/factions/factionreputation/factionreputation_unlawful_test.xml" />',
                $factionRepUuid,
            )
        );
        $scopePath = $this->writeFile(
            'Data/Libs/Foundry/Records/reputation/scopes/test_scope.xml',
            sprintf(
                '<SReputationScopeParams.TestScope scopeName="TestScope" __type="SReputationScopeParams" __ref="%1$s" __path="libs/foundry/records/reputation/scopes/test_scope.xml"><standingMap reputationCeiling="1000" initialReputation="0"><standings /></standingMap></SReputationScopeParams.TestScope>',
                $scopeUuid,
            )
        );
        $rewardPath = $this->writeReputationReward('42100000-0000-0000-0000-000000000001', 500, '+T_success');

        $this->writeCacheFiles(
            classToPathMap: [
                'Faction' => ['Faction_Unlawful_Test' => $factionPath],
                'FactionReputation' => ['FactionReputation_Unlawful_Test' => $factionRepPath],
                'SReputationScopeParams' => ['TestScope' => $scopePath],
            ],
            uuidToClassMap: [
                strtolower($factionUuid) => 'Faction_Unlawful_Test',
                strtolower($factionRepUuid) => 'FactionReputation_Unlawful_Test',
                strtolower($scopeUuid) => 'TestScope',
            ],
            uuidToPathMap: [
                strtolower($factionUuid) => $factionPath,
                strtolower($factionRepUuid) => $factionRepPath,
                strtolower($scopeUuid) => $scopePath,
                '42100000-0000-0000-0000-000000000001' => $rewardPath,
            ],
        );
        $this->initializeMinimalItemServices(translations: [
            'LOC_EMPTY' => '',
            'LOC_UNINITIALIZED' => '<= UNINITIALIZED =>',
            'TestFaction_RepUI_Name' => 'TestFaction',
        ]);

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = "<ContractGeneratorHandler_Test debugName=\"RepTest\"><defaultAvailability><prerequisites /></defaultAvailability><contractParams /><contracts><Contract id=\"t1\" debugName=\"RepTest\"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances=\"1\" maxInstancesPerPlayer=\"1\" respawnTime=\"0\" respawnTimeVariation=\"0\" /></generationParams><contractResults contractBuyInAmount=\"0\" timeToComplete=\"-1\"><contractResults><ContractResult_LegacyReputation><missionResults><Bool value=\"1\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /><Bool value=\"0\" /></missionResults><contractResultReputationAmounts factionReputation=\"{$factionRepUuid}\" reputationScope=\"{$scopeUuid}\" reward=\"42100000-0000-0000-0000-000000000001\" /></ContractResult_LegacyReputation></contractResults></contractResults></Contract></contracts></ContractGeneratorHandler_Test>";

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $results = $this->invokeMethod($contract, 'buildResults', $entry->getResults());

        $gained = $results['reputation_gained'] ?? [];
        self::assertCount(1, $gained);
        self::assertSame('TestFaction', $gained[0]['faction'], 'Faction name must resolve through @LOC_UNINITIALIZED sentinel');
        self::assertStringNotContainsString('UNINITIALIZED', $gained[0]['faction']);
    }

    public function test_rent_ship_modifiers_from_entry_and_template(): void
    {
        // Entry paramOverrides/modifierOverrides + template modifiers/MissionModifier_RequestRentShip
        // are both surfaced, with item names resolved.
        $shipPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/spaceships/rental_ship.xml',
            '<EntityClassDefinition.RentalShip __type="EntityClassDefinition" __ref="f68ee841-88d1-46f3-a1e2-5dc71d9d5d97" __path="libs/foundry/records/entities/spaceships/rental_ship.xml"><Components><VehicleComponentParams vehicleName="@vehicle_NamePROSPECTOR" /></Components></EntityClassDefinition.RentalShip>'
        );

        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_rent_template.xml',
            '<ContractTemplate.TestRent __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000800" __path="libs/foundry/records/contracts/contracttemplates/test_rent_template.xml"><modifiers><MissionModifier_RequestRentShip modifierName="RentalMiner" enabled="1" itemRecordGUID="f68ee841-88d1-46f3-a1e2-5dc71d9d5d97" durationSeconds="7200" clearRentalOnFail="1" /></modifiers></ContractTemplate.TestRent>'
        );

        $this->writeCacheFiles(uuidToPathMap: [
            'f68ee841-88d1-46f3-a1e2-5dc71d9d5d97' => $shipPath,
            'cccc0000-0000-0000-0000-000000000800' => $templatePath,
        ]);
        $this->bootServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="RentTest"><contractParams /><contracts><Contract id="e1" debugName="RentEntry" template="cccc0000-0000-0000-0000-000000000800"><paramOverrides><modifierOverrides><MissionModifier_RequestRentShip modifierName="EntryShip" enabled="1" itemRecordGUID="f68ee841-88d1-46f3-a1e2-5dc71d9d5d97" durationSeconds="3600" clearRentalOnFail="0" /></modifierOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $mods = $this->invokeMethod($contract, 'buildRentShipModifiers');

        self::assertCount(2, $mods);

        $entryMod = array_find($mods, static fn (array $m): bool => $m['source'] === 'entry');
        self::assertNotNull($entryMod);
        self::assertSame('EntryShip', $entryMod['modifier_name']);
        self::assertSame(3600, $entryMod['duration_seconds']);
        self::assertFalse($entryMod['clear_rental_on_fail']);

        $templateMod = array_find($mods, static fn (array $m): bool => $m['source'] === 'template');
        self::assertNotNull($templateMod);
        self::assertSame('RentalMiner', $templateMod['modifier_name']);
        self::assertSame(7200, $templateMod['duration_seconds']);
        self::assertTrue($templateMod['clear_rental_on_fail']);
    }

    public function test_contract_plugins_are_surfaced(): void
    {
        $this->writeCacheFiles();
        $this->bootServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="PluginTest"><contractParams /><contracts><Contract id="e1" debugName="PluginEntry"><paramOverrides /><contractPlugins><SContractPlugin_SMissionProvider tag="provider-tag-uuid" storylineMission="1" availableToAcceptFromContractManager="0" /></contractPlugins><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $plugins = $this->invokeMethod($contract, 'buildContractPlugins');

        self::assertCount(1, $plugins);
        self::assertSame('SContractPlugin_SMissionProvider', $plugins[0]['plugin_type']);
        self::assertSame('provider-tag-uuid', $plugins[0]['tag']);
        self::assertTrue($plugins[0]['storyline_mission']);
        self::assertFalse($plugins[0]['available_to_accept_from_contract_manager']);
    }

    public function test_objective_tokens_expose_phase_tag_and_meet_and_talk(): void
    {
        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_obj_tokens.xml',
            '<ContractTemplate.TestObj __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000900" __path="libs/foundry/records/contracts/contracttemplates/test_obj_tokens.xml">'
            .'<objectiveTokens>'
            .'<ObjectiveToken id="dddd0000-0000-0000-0000-000000000901" debugName="Meet And Talk" missionPhaseIdentifierTag="75dd7a80-2da1-4513-b92d-abe1811ebf26">'
            .'<objectiveHandler><ObjectiveHandler_MeetAndTalk travelRadiusKM="30" meetAndTalkObjectiveMarkerLabel="@SOO2_MeetMarker"><location value="ObjectiveProperty_Referenced[0979]" /><ocTagsToSearch><tags><Reference value="11111111-0000-0000-0000-0000000000a1" /></tags></ocTagsToSearch><travelObjectiveInfo shortDescription="@Short" longDescription="@Long" objectiveMarkerLabel="@Marker" category="Action" hideOnHUD="0" /></ObjectiveHandler_MeetAndTalk></objectiveHandler>'
            .'</ObjectiveToken>'
            .'</objectiveTokens>'
            .'</ContractTemplate.TestObj>'
        );

        $this->writeCacheFiles(uuidToPathMap: [
            'cccc0000-0000-0000-0000-000000000900' => $templatePath,
        ]);
        $this->bootServices();

        $handlerXml = '<ContractGeneratorHandler_List debugName="ObjTest"><contractParams /><contracts><Contract id="e1" debugName="ObjEntry" template="cccc0000-0000-0000-0000-000000000900"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_List>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $tokens = $this->invokeMethod($contract, 'buildObjectiveTokens');

        self::assertCount(1, $tokens);
        $token = $tokens[0];

        // Gap 7: missionPhaseIdentifierTag
        self::assertSame('75dd7a80-2da1-4513-b92d-abe1811ebf26', $token['phase_identifier_tag']);

        // Gap 4: ObjectiveHandler_MeetAndTalk
        self::assertSame('ObjectiveHandler_MeetAndTalk', $token['handler_type']);
        self::assertNotNull($token['meet_and_talk']);
        self::assertSame(30.0, $token['meet_and_talk']['travel_radius_km']);
        self::assertSame('ObjectiveProperty_Referenced[0979]', $token['meet_and_talk']['location_ref']);
        self::assertSame(['11111111-0000-0000-0000-0000000000a1'], $token['meet_and_talk']['oc_tags']);
    }

    public function test_hauling_order_mission_item_drop_off_surfaces_target_types(): void
    {
        $tag = ['name' => 'FreightElevator', 'uuid' => '61bca4cf-95a1-49b3-ae5b-cf9dc9ee3127'];

        $templatePath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contracttemplates/test_dropoff_haul.xml',
            '<ContractTemplate.TestDropOff __type="ContractTemplate" __ref="cccc0000-0000-0000-0000-000000000950" __path="libs/foundry/records/contracts/contracttemplates/test_dropoff_haul.xml">'
            .'<objectiveTokens>'
            .'<ObjectiveToken id="drop_token" debugName="Delivery">'
            .'<objectiveHandler><ObjectiveHandler_Hauling><haulingOrders>'
            .'<HaulingOrder_MissionItemDropOff><dropOffLocation value="ObjectiveProperty_Referenced[1001]" /><dropOffTargetTypes><tags><Reference value="'.$tag['uuid'].'" /></tags></dropOffTargetTypes><deliveryOrderInput value="ObjectiveProperty_Referenced[1002]" /></HaulingOrder_MissionItemDropOff>'
            .'</haulingOrders></ObjectiveHandler_Hauling></objectiveHandler>'
            .'</ObjectiveToken>'
            .'</objectiveTokens>'
            .'</ContractTemplate.TestDropOff>'
        );

        $this->writeCacheFiles(uuidToPathMap: [
            'cccc0000-0000-0000-0000-000000000950' => $templatePath,
        ]);
        $this->initializeMinimalItemServices(tags: [$tag]);
        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);

        $handlerXml = '<ContractGeneratorHandler_Recovery debugName="DropTest"><contractParams /><contracts><Contract id="e1" debugName="DropEntry" template="cccc0000-0000-0000-0000-000000000950"><paramOverrides /><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>';

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, new ContractGeneratorRecord);
        $orders = $this->invokeMethod($contract, 'buildTemplateObjectiveHaulingOrders');

        self::assertCount(1, $orders);
        self::assertSame('MissionItemDropOff', $orders[0]['kind']);
        self::assertArrayHasKey('drop_off_target_types', $orders[0]);
        self::assertSame('FreightElevator', $orders[0]['drop_off_target_types'][0]['name']);
        self::assertSame('ObjectiveProperty_Referenced[1002]', $orders[0]['delivery_order_input']);
    }
}
