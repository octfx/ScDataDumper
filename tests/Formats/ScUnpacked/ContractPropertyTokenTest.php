<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use DOMDocument;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ContractPropertyTokenTest extends ScDataTestCase
{
    private function bootServices(array $translations = []): void
    {
        $this->initializeMinimalItemServices($translations);

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);
    }

    private function createContractFromXml(string $handlerXml, array $translations = []): Contract
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_prop.xml',
            '<ContractGenerator.Test __type="ContractGenerator" __ref="ff000000-0000-0000-0000-000000000001" __path="libs/foundry/records/contracts/contractgenerator/test_prop.xml"><generators /></ContractGenerator.Test>'
        );
        $this->bootServices($translations);

        $dom = new DOMDocument;
        $dom->loadXML($handlerXml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_prop.xml');

        return new Contract($entry, $handler, $record);
    }

    public function test_stringhash_token_resolved_from_override(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="StringHashTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="CargoGradeToken">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@cargo_grade_bulk" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="sh1" debugName="StringHashContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(CargoGradeToken)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, ['cargo_grade_bulk' => 'Bulk']);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(CargoGradeToken)', '', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('CargoGradeToken', $tokens);
        self::assertContains('Bulk', $tokens['CargoGradeToken']);
    }

    public function test_stringhash_uninitialized_skipped(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="SentinelTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="ReputationRank">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@LOC_UNINITIALIZED" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="st1" debugName="SentinelContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(ReputationRank)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(ReputationRank)', '', []);

        self::assertNull($tokens);
    }

    public function test_stringhash_empty_skipped(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="EmptyTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="Danger">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@LOC_EMPTY" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="em1" debugName="EmptyContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(Danger)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Danger)', '', []);

        self::assertNull($tokens);
    }

    public function test_stringhash_multiple_options_resolved(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="MultiOptionTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="Hint_Tool">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@hint_multitool" weighting="1" />
                                    <MissionPropertyValueOption_StringHash textId="@hint_tractor" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="mo1" debugName="MultiOptionContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(Hint_Tool)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, [
            'hint_multitool' => 'Use the multitool',
            'hint_tractor' => 'Use the tractor beam',
        ]);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Hint_Tool)', '', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Hint_Tool', $tokens);
        self::assertCount(2, $tokens['Hint_Tool']);
        self::assertContains('Use the multitool', $tokens['Hint_Tool']);
        self::assertContains('Use the tractor beam', $tokens['Hint_Tool']);
    }

    public function test_stringhash_plain_value_used_when_no_textid(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="PlainValueTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="System">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@LOC_UNINITIALIZED" weighting="1" />
                                    <MissionPropertyValueOption_StringHash value="Stanton" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="pv1" debugName="PlainValueContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(System)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(System)', '', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('System', $tokens);
        self::assertContains('Stanton', $tokens['System']);
    }

    public function test_entry_override_wins_over_handler_in_phase_a(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="PrecedenceTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="CargoGradeToken">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@grade_handler" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="pr1" debugName="PrecedenceContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(CargoGradeToken)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                        <propertyOverrides>
                            <MissionProperty extendedTextToken="CargoGradeToken">
                                <value>
                                    <MissionPropertyValue_StringHash>
                                        <options>
                                            <MissionPropertyValueOption_StringHash textId="@grade_entry" weighting="1" />
                                        </options>
                                    </MissionPropertyValue_StringHash>
                                </value>
                            </MissionProperty>
                        </propertyOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, [
            'grade_handler' => 'Handler Grade',
            'grade_entry' => 'Entry Grade',
        ]);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(CargoGradeToken)', '', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('CargoGradeToken', $tokens);
        self::assertContains('Entry Grade', $tokens['CargoGradeToken']);
        self::assertNotContains('Handler Grade', $tokens['CargoGradeToken']);
    }

    public function test_entry_override_wins_in_phase_b_undeclared_scan(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="PhaseBTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="ScripAmount">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@scrip_handler" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="pb1" debugName="PhaseBContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="No tokens in title" />
                            <ContractStringParam param="Description" value="No tokens here" />
                        </stringParamOverrides>
                        <propertyOverrides>
                            <MissionProperty extendedTextToken="ScripAmount">
                                <value>
                                    <MissionPropertyValue_StringHash>
                                        <options>
                                            <MissionPropertyValueOption_StringHash textId="@scrip_entry" weighting="1" />
                                        </options>
                                    </MissionPropertyValue_StringHash>
                                </value>
                            </MissionProperty>
                        </propertyOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, [
            'scrip_handler' => 'Handler Value',
            'scrip_entry' => 'Entry Value',
        ]);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', 'No tokens in title', 'No tokens here', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('ScripAmount', $tokens);
        self::assertContains('Entry Value', $tokens['ScripAmount']);
        self::assertNotContains('Handler Value', $tokens['ScripAmount']);
    }

    public function test_targetname_denylisted_even_with_override(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="DenyTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="TargetName">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash value="Some Target" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="dn1" debugName="DenyContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(TargetName)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(TargetName)', '', []);

        self::assertNull($tokens);
    }

    public function test_nearbylocation_denylisted(): void
    {
        $contract = $this->createContractFromXml(
            '<ContractGeneratorHandler_Recovery debugName="Deny"><contractParams /><contracts><Contract id="d2" debugName="Deny"><paramOverrides><stringParamOverrides><ContractStringParam param="Title" value="~mission(NearbyLocation)" /><ContractStringParam param="Description" value="Test" /></stringParamOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>'
        );

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(NearbyLocation)', '', []);

        self::assertNull($tokens);
    }

    public function test_item_prefix_denylisted(): void
    {
        $contract = $this->createContractFromXml(
            '<ContractGeneratorHandler_Recovery debugName="ItemDeny"><contractParams /><contracts><Contract id="d3" debugName="ItemDeny"><paramOverrides><stringParamOverrides><ContractStringParam param="Title" value="~mission(Item1|SerialNumber)" /><ContractStringParam param="Description" value="Test" /></stringParamOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></contracts></ContractGeneratorHandler_Recovery>'
        );

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Item1|SerialNumber)', '', []);

        self::assertNull($tokens);
    }

    public function test_undeclared_override_appears_in_tokens(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="UndeclaredTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="FuelRate">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@fuel_rate_standard" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="un1" debugName="UndeclaredContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="Title without tokens" />
                            <ContractStringParam param="Description" value="Description without tokens" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, ['fuel_rate_standard' => 'Standard Fuel']);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', 'Title without tokens', 'Description without tokens', []);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('FuelRate', $tokens);
        self::assertContains('Standard Fuel', $tokens['FuelRate']);
    }

    public function test_nested_location_tokens_inside_property_values_are_resolved(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="NestedTokenTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="DescriptionToken">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@desc_with_location" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="nt1" debugName="NestedTokenContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(DescriptionToken)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, [
            'desc_with_location' => 'Go to ~mission(Location|Address).',
        ]);

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(DescriptionToken)', '', [
            'loc' => [
                'purpose' => 'Location',
                'resolved_locations' => [
                    ['uuid' => 'test', 'location_template_uuid' => null, 'name' => 'Orison'],
                ],
            ],
        ]);

        self::assertNotNull($tokens);
        self::assertSame(['Orison'], $tokens['Location|Address'] ?? null);

        $resolvedTokens = $this->invokeMethod($contract, 'resolveTokenValueReferences', $tokens);

        self::assertSame(['Go to Orison.'], $resolvedTokens['DescriptionToken'] ?? null);
    }

    public function test_location_and_property_tokens_both_resolve(): void
    {
        $xml = <<<'XML'
        <ContractGeneratorHandler_Recovery debugName="MixedTest">
            <contractParams>
                <propertyOverrides>
                    <MissionProperty extendedTextToken="ReputationRank">
                        <value>
                            <MissionPropertyValue_StringHash>
                                <options>
                                    <MissionPropertyValueOption_StringHash textId="@rep_rank_3" weighting="1" />
                                </options>
                            </MissionPropertyValue_StringHash>
                        </value>
                    </MissionProperty>
                </propertyOverrides>
            </contractParams>
            <contracts>
                <Contract id="mx1" debugName="MixedContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="~mission(ReputationRank) Mission at ~mission(Location|Address)" />
                            <ContractStringParam param="Description" value="Test" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $contract = $this->createContractFromXml($xml, ['rep_rank_3' => 'Veteran']);

        $pools = [
            'loc' => [
                'purpose' => 'Location',
                'resolved_locations' => [
                    ['uuid' => 'test', 'location_template_uuid' => null, 'name' => 'Grim HEX'],
                ],
            ],
        ];

        $tokens = $this->invokeMethod(
            $contract,
            'buildMissionTokens',
            '~mission(ReputationRank) Mission at ~mission(Location|Address)',
            '',
            $pools,
        );

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Location|Address', $tokens);
        self::assertArrayHasKey('ReputationRank', $tokens);
        self::assertContains('Grim HEX', $tokens['Location|Address']);
        self::assertContains('Veteran', $tokens['ReputationRank']);
    }
}
