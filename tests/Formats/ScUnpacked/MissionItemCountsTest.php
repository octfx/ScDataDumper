<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract;
use Octfx\ScDataDumper\Formats\ScUnpacked\MissionBroker;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * RED-phase tests: contract output must include specific item details
 * for missions that require finding/collecting items (non-hauling).
 */
final class MissionItemCountsTest extends ScDataTestCase
{
    private function bootServices(): void
    {
        $this->initializeMinimalItemServices(
            translations: [
                'LOC_EMPTY' => '',
                'item_display_CovalexBox' => 'Covalex Personal Package',
                'item_display_DataChip' => 'Data Chip',
                'item_display_MedPen' => 'MedPen',
            ],
        );

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);
    }

    // ---------------------------------------------------------- //
    //  1. Contract (generator-based) with MissionItem + SpecificItems
    // ---------------------------------------------------------- //

    public function test_contract_item_counts_includes_specific_items(): void
    {
        $this->setupContractWithMissionItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_covalex.xml');

        self::assertArrayHasKey('ItemCounts', $result, 'Contract output must contain ItemCounts');
        $itemCounts = $result['ItemCounts'];

        self::assertArrayHasKey('Items', $itemCounts,
            'ItemCounts must contain Items with resolved item details');
        self::assertIsArray($itemCounts['Items']);
        self::assertNotEmpty($itemCounts['Items'],
            'Items should not be empty when the mission specifies concrete items');

        // The specific item should have uuid and name resolved
        $item = $itemCounts['Items'][0];
        self::assertArrayHasKey('UUID', $item, 'Each specific item must have a UUID');
        self::assertArrayHasKey('Name', $item, 'Each specific item must have a resolved Name');
    }

    public function test_contract_item_counts_preserves_min_max(): void
    {
        $this->setupContractWithMissionItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_covalex.xml');

        self::assertArrayHasKey('ItemCounts', $result);
        $itemCounts = $result['ItemCounts'];

        self::assertArrayHasKey('MinItems', $itemCounts, 'ItemCounts must preserve MinItems');
        self::assertArrayHasKey('MaxItems', $itemCounts, 'ItemCounts must preserve MaxItems');
        self::assertSame(1, $itemCounts['MinItems']);
        self::assertSame(1, $itemCounts['MaxItems']);
    }

    public function test_contract_item_counts_resolves_item_name(): void
    {
        $this->setupContractWithMissionItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_covalex.xml');

        self::assertArrayHasKey('ItemCounts', $result);
        $items = $result['ItemCounts']['Items'];

        self::assertSame('Covalex Personal Package', $items[0]['Name'],
            'Specific item name should be resolved via ItemService');
    }

    public function test_contract_item_counts_resolves_entity_class_from_mission_item(): void
    {
        $this->setupContractWithMissionItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_covalex.xml');

        self::assertArrayHasKey('ItemCounts', $result);
        $items = $result['ItemCounts']['Items'];

        // The UUID should be the entity class UUID (resolved from mission item),
        // not the raw mission item UUID
        self::assertSame('aaaa0000-0000-0000-0000-000000000001', $items[0]['UUID'],
            'Specific item UUID should be the resolved entity class UUID');
    }

    public function test_contract_multiple_specific_items_all_resolved(): void
    {
        $this->setupContractWithMultipleMissionItems();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_multi.xml');

        self::assertArrayHasKey('ItemCounts', $result);
        $items = $result['ItemCounts']['Items'];

        self::assertCount(2, $items, 'Both specific items should be resolved');
        $names = array_map(static fn (array $i): string => $i['Name'], $items);
        sort($names);
        self::assertContains('Covalex Personal Package', $names);
        self::assertContains('Data Chip', $names);
    }

    public function test_contract_specific_item_counts_generate_mission_item_hauling_order(): void
    {
        $this->setupContractWithMissionItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_covalex.xml');

        self::assertArrayHasKey('HaulingOrders', $result);
        self::assertCount(1, $result['HaulingOrders']);

        $order = $result['HaulingOrders'][0];
        self::assertSame('MissionItem', $order['Kind']);
        self::assertSame('Covalex Personal Package', $order['Name']);
        self::assertSame('aaaa0000-0000-0000-0000-000000000001', $order['UUID']);
        self::assertSame([], $order['Items']);
        self::assertSame(1, $order['MinAmount']);
        self::assertSame(1, $order['MaxAmount']);
    }

    public function test_contract_multiple_specific_item_counts_generate_grouped_mission_item_hauling_order(): void
    {
        $this->setupContractWithMultipleMissionItems();
        $this->bootServices();

        $result = $this->buildContractOutput('test_collect_multi.xml');

        self::assertArrayHasKey('HaulingOrders', $result);
        self::assertCount(1, $result['HaulingOrders']);

        $order = $result['HaulingOrders'][0];
        self::assertSame('MissionItem', $order['Kind']);
        self::assertArrayNotHasKey('UUID', $order);
        self::assertArrayHasKey('Items', $order);
        self::assertCount(2, $order['Items']);
        self::assertSame(1, $order['MinAmount']);
        self::assertSame(2, $order['MaxAmount']);
    }

    // ---------------------------------------------------------- //
    //  2. Contract with tag-based item search (no SpecificItems)
    // ---------------------------------------------------------- //

    public function test_contract_item_counts_with_tag_search_only(): void
    {
        $this->setupContractWithTagSearchItem();
        $this->bootServices();

        $result = $this->buildContractOutput('test_tag_search.xml');

        self::assertArrayHasKey('ItemCounts', $result);
        $itemCounts = $result['ItemCounts'];

        // Tag search terms should be included
        self::assertArrayHasKey('TagSearchTerms', $itemCounts,
            'ItemCounts must contain TagSearchTerms when items are matched by tags');

        $terms = $itemCounts['TagSearchTerms'];
        self::assertNotEmpty($terms);
        self::assertArrayHasKey('PositiveTags', $terms[0]);
    }

    // ---------------------------------------------------------- //
    //  3. MissionBroker with MissionItem + SpecificItems
    // ---------------------------------------------------------- //

    public function test_mission_broker_includes_item_counts(): void
    {
        $this->setupMissionBrokerWithMissionItem();
        $this->bootServices();

        $result = $this->buildMissionBrokerOutput();

        self::assertArrayHasKey('ItemCounts', $result,
            'MissionBroker output must contain ItemCounts for missions with MissionItem properties');
    }

    public function test_mission_broker_item_counts_have_specific_items(): void
    {
        $this->setupMissionBrokerWithMissionItem();
        $this->bootServices();

        $result = $this->buildMissionBrokerOutput();

        self::assertArrayHasKey('ItemCounts', $result);
        self::assertArrayHasKey('Items', $result['ItemCounts']);
        self::assertNotEmpty($result['ItemCounts']['Items']);

        $item = $result['ItemCounts']['Items'][0];
        self::assertSame('Covalex Personal Package', $item['Name']);
    }

    public function test_mission_broker_specific_item_counts_generate_mission_item_hauling_order(): void
    {
        $this->setupMissionBrokerWithMissionItem();
        $this->bootServices();

        $result = $this->buildMissionBrokerOutput();

        self::assertArrayHasKey('HaulingOrders', $result);
        self::assertCount(1, $result['HaulingOrders']);

        $order = $result['HaulingOrders'][0];
        self::assertSame('MissionItem', $order['Kind']);
        self::assertSame('Covalex Personal Package', $order['Name']);
        self::assertSame('aaaa0000-0000-0000-0000-000000000001', $order['UUID']);
        self::assertSame([], $order['Items']);
    }

    // ---------------------------------------------------------- //
    //  Setup helpers
    // ---------------------------------------------------------- //

    private function setupContractWithMissionItem(): void
    {
        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/covalex_box.xml',
            '<EntityClassDefinition.TestCovalexBox __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/scitem/covalex_box.xml"><entityClass entityClass="aaaa0000-0000-0000-0000-000000000001" /><Components><SAttachableComponentParams><AttachDef Type="Weapon" SubType="Undefined" Size="1"><Localization Name="@item_display_CovalexBox" /></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.TestCovalexBox>'
        );

        $missionItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/missions/covalex_personal_item.xml',
            '<MissionItem.CovalexPersonal entityClass="aaaa0000-0000-0000-0000-000000000001" __type="MissionItem" __ref="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" __path="libs/foundry/records/missions/covalex_personal_item.xml" />'
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                'aaaa0000-0000-0000-0000-000000000001' => 'TestCovalexBox',
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => 'CovalexPersonal',
            ],
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000001' => $itemPath,
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => $missionItemPath,
            ],
        );

        $this->writeContractGeneratorFile(
            'test_collect_covalex',
            <<<'XML'
            <propertyOverrides>
                <MissionProperty missionVariableName="Item" extendedTextToken="Item">
                    <value>
                        <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1">
                            <matchConditions>
                                <DataSetMatchCondition_SpecificItemsDef>
                                    <items>
                                        <Reference value="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" />
                                    </items>
                                </DataSetMatchCondition_SpecificItemsDef>
                            </matchConditions>
                        </MissionPropertyValue_MissionItem>
                    </value>
                </MissionProperty>
            </propertyOverrides>
            XML
        );
    }

    private function setupContractWithMultipleMissionItems(): void
    {
        $item1Path = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/covalex_box.xml',
            '<EntityClassDefinition.TestCovalexBox __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/scitem/covalex_box.xml"><entityClass entityClass="aaaa0000-0000-0000-0000-000000000001" /><Components><SAttachableComponentParams><AttachDef Type="Weapon" SubType="Undefined" Size="1"><Localization Name="@item_display_CovalexBox" /></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.TestCovalexBox>'
        );

        $item2Path = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/data_chip.xml',
            '<EntityClassDefinition.TestDataChip __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000002" __path="libs/foundry/records/entities/scitem/data_chip.xml"><entityClass entityClass="aaaa0000-0000-0000-0000-000000000002" /><Components><SAttachableComponentParams><AttachDef Type="Weapon" SubType="Undefined" Size="1"><Localization Name="@item_display_DataChip" /></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.TestDataChip>'
        );

        $missionItem1Path = $this->writeFile(
            'Data/Libs/Foundry/Records/missions/covalex_item.xml',
            '<MissionItem.CovalexPersonal entityClass="aaaa0000-0000-0000-0000-000000000001" __type="MissionItem" __ref="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" __path="libs/foundry/records/missions/covalex_item.xml" />'
        );

        $missionItem2Path = $this->writeFile(
            'Data/Libs/Foundry/Records/missions/data_chip_item.xml',
            '<MissionItem.DataChip entityClass="aaaa0000-0000-0000-0000-000000000002" __type="MissionItem" __ref="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66952" __path="libs/foundry/records/missions/data_chip_item.xml" />'
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                'aaaa0000-0000-0000-0000-000000000001' => 'TestCovalexBox',
                'aaaa0000-0000-0000-0000-000000000002' => 'TestDataChip',
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => 'CovalexPersonal',
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66952' => 'DataChipItem',
            ],
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000001' => $item1Path,
                'aaaa0000-0000-0000-0000-000000000002' => $item2Path,
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => $missionItem1Path,
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66952' => $missionItem2Path,
            ],
        );

        $this->writeContractGeneratorFile(
            'test_collect_multi',
            <<<'XML'
            <propertyOverrides>
                <MissionProperty missionVariableName="Item" extendedTextToken="Item">
                    <value>
                        <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="2">
                            <matchConditions>
                                <DataSetMatchCondition_SpecificItemsDef>
                                    <items>
                                        <Reference value="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" />
                                        <Reference value="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66952" />
                                    </items>
                                </DataSetMatchCondition_SpecificItemsDef>
                            </matchConditions>
                        </MissionPropertyValue_MissionItem>
                    </value>
                </MissionProperty>
            </propertyOverrides>
            XML
        );
    }

    private function setupContractWithTagSearchItem(): void
    {
        $this->writeCacheFiles();

        $tagUuid = 'cc00cc00-0000-0000-0000-000000000001';

        $this->writeContractGeneratorFile(
            'test_tag_search',
            <<<XML
            <propertyOverrides>
                <MissionProperty missionVariableName="Item" extendedTextToken="Item">
                    <value>
                        <MissionPropertyValue_MissionItem minItemsToFind="2" maxItemsToFind="4">
                            <matchConditions>
                                <DataSetMatchCondition_TagSearch tagType="positive">
                                    <tagSearch>
                                        <TagSearchTerm>
                                            <positiveTags>
                                                <Reference value="{$tagUuid}" />
                                            </positiveTags>
                                            <negativeTags />
                                        </TagSearchTerm>
                                    </tagSearch>
                                </DataSetMatchCondition_TagSearch>
                            </matchConditions>
                        </MissionPropertyValue_MissionItem>
                    </value>
                </MissionProperty>
            </propertyOverrides>
            XML
        );
    }

    private function setupMissionBrokerWithMissionItem(): void
    {
        $itemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/scitem/covalex_box.xml',
            '<EntityClassDefinition.TestCovalexBox __type="EntityClassDefinition" __ref="aaaa0000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/scitem/covalex_box.xml"><entityClass entityClass="aaaa0000-0000-0000-0000-000000000001" /><Components><SAttachableComponentParams><AttachDef Type="Weapon" SubType="Undefined" Size="1"><Localization Name="@item_display_CovalexBox" /></AttachDef></SAttachableComponentParams></Components></EntityClassDefinition.TestCovalexBox>'
        );

        $missionItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/missions/covalex_personal_item.xml',
            '<MissionItem.CovalexPersonal entityClass="aaaa0000-0000-0000-0000-000000000001" __type="MissionItem" __ref="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" __path="libs/foundry/records/missions/covalex_personal_item.xml" />'
        );

        $mbePath = $this->writeFile(
            'Data/Libs/Foundry/Records/missionbroker/pu_missions/test_mbe_collect.xml',
            <<<XML
            <MissionBrokerEntry.Test_MBE_Collect __type="MissionBrokerEntry" __ref="fd504984-8667-4eca-839e-bbf3e72ff5ac" __path="libs/foundry/records/missionbroker/pu_missions/test_mbe_collect.xml">
                <properties>
                    <MissionProperty missionVariableName="Item" extendedTextToken="Item">
                        <value>
                            <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1">
                                <matchConditions>
                                    <DataSetMatchCondition_SpecificItemsDef>
                                        <items>
                                            <Reference value="d4ca6b3f-1d53-45b8-ab2a-2b0d87c66951" />
                                        </items>
                                    </DataSetMatchCondition_SpecificItemsDef>
                                </matchConditions>
                            </MissionPropertyValue_MissionItem>
                        </value>
                    </MissionProperty>
                </properties>
            </MissionBrokerEntry.Test_MBE_Collect>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                'aaaa0000-0000-0000-0000-000000000001' => 'TestCovalexBox',
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => 'CovalexPersonal',
                'fd504984-8667-4eca-839e-bbf3e72ff5ac' => 'Test_MBE_Collect',
            ],
            uuidToPathMap: [
                'aaaa0000-0000-0000-0000-000000000001' => $itemPath,
                'd4ca6b3f-1d53-45b8-ab2a-2b0d87c66951' => $missionItemPath,
                'fd504984-8667-4eca-839e-bbf3e72ff5ac' => $mbePath,
            ],
        );
    }

    private function writeContractGeneratorFile(string $debugName, string $propertyOverrides): void
    {
        $this->writeFile(
            "Data/Libs/Foundry/Records/contracts/contractgenerator/{$debugName}.xml",
            <<<XML
            <ContractGenerator.TestCollect __type="ContractGenerator" __ref="cc000000-0000-0000-0000-000000000001" __path="libs/foundry/records/contracts/contractgenerator/{$debugName}.xml">
                <generators>
                    <ContractGeneratorHandler_Recovery debugName="{$debugName}">
                        <contractParams>
                            {$propertyOverrides}
                        </contractParams>
                        <contracts>
                            <Contract id="c1" debugName="{$debugName}">
                                <paramOverrides />
                                <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                                <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                            </Contract>
                        </contracts>
                    </ContractGeneratorHandler_Recovery>
                </generators>
            </ContractGenerator.TestCollect>
            XML
        );
    }

    private function buildContractOutput(string $fileName): array
    {
        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir . '/Data/Libs/Foundry/Records/contracts/contractgenerator/' . $fileName);

        $handler = $record->getHandlers()[0];
        $entry = $handler->getContracts()[0];

        $contract = new Contract($entry, $handler, $record);

        return $contract->toArray() ?? [];
    }

    private function buildMissionBrokerOutput(): array
    {
        $mbe = new MissionBrokerEntry;
        $mbe->load($this->tempDir . '/Data/Libs/Foundry/Records/missionbroker/pu_missions/test_mbe_collect.xml');

        $format = new MissionBroker($mbe);

        return $format->toArray() ?? [];
    }
}
