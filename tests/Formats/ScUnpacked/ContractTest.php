<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use DOMDocument;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\HaulingOrdersValue;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract;
use Octfx\ScDataDumper\Services\FoundryLookupService;
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
}
