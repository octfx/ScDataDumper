<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use DOMDocument;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Tests for location-based mission token resolution using the unified candidate model.
 * Covers: full token-content keys, case-insensitive pool matching, exact-first candidate resolution,
 * numbered location variants, and MBE full-token flow.
 */
final class MissionTokenLocationTest extends ScDataTestCase
{
    private function bootServices(): void
    {
        $this->initializeMinimalItemServices();

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);
    }

    private function createContractWithText(string $title, string $description): Contract
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_loc.xml',
            '<ContractGenerator.Test __type="ContractGenerator" __ref="ee000000-0000-0000-0000-000000000001" __path="libs/foundry/records/contracts/contractgenerator/test_loc.xml"><generators /></ContractGenerator.Test>'
        );
        $this->bootServices();

        $xml = <<<XML
        <ContractGeneratorHandler_Recovery debugName="LocationTest">
            <contractParams />
            <contracts>
                <Contract id="loc1" debugName="LocationTestContract">
                    <paramOverrides>
                        <stringParamOverrides>
                            <ContractStringParam param="Title" value="{$title}" />
                            <ContractStringParam param="Description" value="{$description}" />
                        </stringParamOverrides>
                    </paramOverrides>
                    <generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" /></generationParams>
                    <contractResults contractBuyInAmount="0" timeToComplete="-1" />
                </Contract>
            </contracts>
        </ContractGeneratorHandler_Recovery>
        XML;

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $handler = ContractHandler::fromNode($dom->documentElement);
        $entry = $handler->getContracts()[0];

        $record = new ContractGeneratorRecord;
        $record->load($this->tempDir.'/Data/Libs/Foundry/Records/contracts/contractgenerator/test_loc.xml');

        return new Contract($entry, $handler, $record);
    }

    private function locationPool(string $purpose, array $names): array
    {
        return [
            'purpose' => $purpose,
            'resolved_locations' => array_map(
                static fn (string $name): array => ['uuid' => $name, 'location_template_uuid' => null, 'name' => $name],
                $names,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $tokens
     */
    private function buildDisplayText(Contract $contract, string $text, ?array $tokens): ?string
    {
        $method = new \ReflectionMethod($contract::class, 'buildDisplayTextFromMissionTokens');

        return $method->invoke($contract, $text, $tokens);
    }

    public function test_location_address_resolved_under_full_key(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Location|Address)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['Port Tressler']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Location|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Location|Address', $tokens);
        self::assertContains('Port Tressler', $tokens['Location|Address']);
    }

    public function test_pickup1_address_resolves_to_pickup1_pool_before_location_fallback(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Pickup1|Address)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['Generic Location']),
            'pick1' => $this->locationPool('Pickup1', ['Specific Pickup Point']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Pickup1|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Pickup1|Address', $tokens);
        self::assertContains('Specific Pickup Point', $tokens['Pickup1|Address']);
        self::assertNotContains('Generic Location', $tokens['Pickup1|Address']);
    }

    public function test_dropoff2_address_resolves_case_insensitively(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Dropoff2|Address)',
            'No tokens here',
        );

        // Pool has DropOff2 (capital F) but token is Dropoff2 (lowercase f)
        $pools = [
            'drop2' => $this->locationPool('DropOff2', ['Capital F Location']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Dropoff2|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Dropoff2|Address', $tokens);
        self::assertContains('Capital F Location', $tokens['Dropoff2|Address']);
    }

    public function test_location01_resolves_to_location01_pool(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Location01)',
            'No tokens here',
        );

        $pools = [
            'loc01' => $this->locationPool('Location01', ['Checkpoint Alpha']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Location01)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Location01', $tokens);
        self::assertContains('Checkpoint Alpha', $tokens['Location01']);
    }

    public function test_drop1_singleton_resolves(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Drop1)',
            'No tokens here',
        );

        $pools = [
            'd1' => $this->locationPool('Drop1', ['Delivery Bay']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Drop1)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Drop1', $tokens);
        self::assertContains('Delivery Bay', $tokens['Drop1']);
    }

    public function test_defend_location_wrapper_resolves_to_own_pool(): void
    {
        $contract = $this->createContractWithText(
            '~mission(DefendLocationWrapperLocation|Address)',
            'No tokens here',
        );

        $pools = [
            'generic' => $this->locationPool('Location', ['Generic Location']),
            'defend' => $this->locationPool('DefendLocationWrapperLocation', ['Defend Point Bravo']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(DefendLocationWrapperLocation|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('DefendLocationWrapperLocation|Address', $tokens);
        self::assertContains('Defend Point Bravo', $tokens['DefendLocationWrapperLocation|Address']);
        self::assertNotContains('Generic Location', $tokens['DefendLocationWrapperLocation|Address']);
    }

    public function test_lowercase_location_address_preserves_original_key(): void
    {
        $contract = $this->createContractWithText(
            '~mission(location|address)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['Orison']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(location|address)', '', $pools);

        self::assertNotNull($tokens);
        // Key preserves original token content: lowercase variant
        self::assertArrayHasKey('location|address', $tokens);
        self::assertContains('Orison', $tokens['location|address']);
    }

    public function test_bare_location_token_still_resolves(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Location)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['Area18']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Location)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Location', $tokens);
        self::assertContains('Area18', $tokens['Location']);
    }

    public function test_bare_destination_token_still_resolves(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Destination)',
            'No tokens here',
        );

        $pools = [
            'dest' => $this->locationPool('Destination', ['Lorville']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Destination)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Destination', $tokens);
        self::assertContains('Lorville', $tokens['Destination']);
    }

    public function test_destination_tokens_shift_when_pools_start_at_one(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Destination|Address) ~mission(Destination1|Address)',
            'No tokens here',
        );

        $pools = [
            'drop1' => $this->locationPool('Destination1', ['First Dropoff']),
            'drop2' => $this->locationPool('Destination2', ['Second Dropoff']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Destination|Address) ~mission(Destination1|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertSame(['First Dropoff'], $tokens['Destination|Address'] ?? null);
        self::assertSame(['Second Dropoff'], $tokens['Destination1|Address'] ?? null);
    }

    public function test_destination_zero_pool_prevents_shifted_destination_aliases(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Destination|Address) ~mission(Destination1|Address)',
            'No tokens here',
        );

        $pools = [
            'drop0' => $this->locationPool('Destination0', ['Zero Dropoff']),
            'drop1' => $this->locationPool('Destination1', ['First Dropoff']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Destination|Address) ~mission(Destination1|Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayNotHasKey('Destination|Address', $tokens);
        self::assertSame(['First Dropoff'], $tokens['Destination1|Address'] ?? null);
    }

    public function test_bare_address_token_falls_back_to_location_then_destination(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Address)',
            'No tokens here',
        );

        $pools = [
            'dest' => $this->locationPool('Destination', ['Destination Loc']),
            'loc' => $this->locationPool('Location', ['Location Loc']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Address)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Address', $tokens);
        // Address candidates: ['Location', 'Destination'] - Location comes first
        self::assertContains('Location Loc', $tokens['Address']);
    }

    public function test_bare_gotolocation_token_still_resolves(): void
    {
        $contract = $this->createContractWithText(
            '~mission(GoToLocation)',
            'No tokens here',
        );

        $pools = [
            'goto' => $this->locationPool('GoToLocation', ['Waypoint Delta']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(GoToLocation)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('GoToLocation', $tokens);
        self::assertContains('Waypoint Delta', $tokens['GoToLocation']);
    }

    public function test_pickup1_falls_back_to_location_pool(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Pickup1)',
            'No tokens here',
        );

        // No Pickup1 pool, only generic Location
        $pools = [
            'loc' => $this->locationPool('Location', ['Generic Location']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Pickup1)', '', $pools);

        self::assertNotNull($tokens);
        self::assertArrayHasKey('Pickup1', $tokens);
        self::assertContains('Generic Location', $tokens['Pickup1']);
    }

    public function test_non_location_token_not_resolved_by_location_resolver(): void
    {
        $contract = $this->createContractWithText(
            '~mission(CargoGradeToken)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['Some Location']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(CargoGradeToken)', '', $pools);

        // CargoGradeToken is not a location token and has no override, so buildMissionTokens returns null
        self::assertNull($tokens);
    }

    public function test_display_text_uses_placeholder_for_multi_value_tokens(): void
    {
        $contract = $this->createContractWithText('', '');
        $display = $this->buildDisplayText($contract, 'Pick up at ~mission(Pickup1|Address).', [
            'Pickup1|Address' => ['Area18', 'Orison'],
        ]);

        self::assertSame('Pick up at [Pickup1|Address].', $display);
    }

    public function test_display_text_keeps_raw_token_placeholders_unique(): void
    {
        $contract = $this->createContractWithText('', '');

        $display = $this->buildDisplayText($contract, 'From ~mission(Pickup1|Address) to ~mission(Pickup2|Address).', [
            'Pickup1|Address' => ['Area18', 'Orison'],
            'Pickup2|Address' => ['Lorville', 'New Babbage'],
        ]);

        self::assertSame('From [Pickup1|Address] to [Pickup2|Address].', $display);
    }

    public function test_display_text_inlines_single_value_tokens(): void
    {
        $contract = $this->createContractWithText('', '');
        $display = $this->buildDisplayText($contract, 'System: ~mission(System).', [
            'System' => ['Stanton'],
        ]);

        self::assertSame('System: Stanton.', $display);
    }

    public function test_mission_tokens_filter_uninitialized_values_before_export(): void
    {
        $contract = $this->createContractWithText(
            '~mission(Location|Address)',
            'No tokens here',
        );

        $pools = [
            'loc' => $this->locationPool('Location', ['<= UNINITIALIZED =>', 'Area18']),
        ];

        $tokens = $this->invokeMethod($contract, 'buildMissionTokens', '~mission(Location|Address)', '', $pools);

        self::assertSame(['Area18'], $tokens['Location|Address'] ?? null);
    }

    public function test_display_text_bracketifies_unresolved_tokens_without_placeholder_mapping(): void
    {
        $contract = $this->createContractWithText('', '');
        $display = $this->buildDisplayText($contract, 'Target: ~mission(TargetName).', []);

        self::assertSame('Target: [TargetName].', $display);
    }
}
