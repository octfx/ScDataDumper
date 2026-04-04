<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadFactions;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadFactionsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_lazy_resolved_faction_export(): void
    {
        $factionPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/test_faction.xml',
            <<<'XML'
            <Faction.TestFaction name="@loc_faction_name" description="@loc_faction_desc" defaultReaction="Neutral" factionType="Government" ableToArrest="1" policesLawfulTrespass="1" policesCriminality="1" noLegalRights="0" factionReputationRef="40000000-0000-0000-0000-000000000002" __type="Faction" __ref="40000000-0000-0000-0000-000000000001" __path="libs/foundry/records/factions/test_faction.xml" />
            XML
        );
        $reputationPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/factionreputation/test_reputation.xml',
            <<<'XML'
            <FactionReputation.TestReputation displayName="@loc_reputation_name" reputationContextPropertiesUI="40000000-0000-0000-0000-000000000003" isNPC="0" hideInDelpihApp="0" __type="FactionReputation" __ref="40000000-0000-0000-0000-000000000002" __path="libs/foundry/records/factions/factionreputation/test_reputation.xml">
              <hostilityParams scope="40000000-0000-0000-0000-000000000004" standing="40000000-0000-0000-0000-000000000005">
                <markerParams description="@loc_hostility_marker" />
              </hostilityParams>
              <alliedParams scope="40000000-0000-0000-0000-000000000006" standing="40000000-0000-0000-0000-000000000007">
                <markerParams description="@loc_allied_marker" />
              </alliedParams>
              <propertiesBB>
                <SReputationContextBBPropertyParams name="entityGreeting">
                  <dynamicProperty>
                    <SBBDynamicPropertyLocString value="@loc_property_value" />
                  </dynamicProperty>
                </SReputationContextBBPropertyParams>
              </propertiesBB>
            </FactionReputation.TestReputation>
            XML
        );
        $contextPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/contexts/test_context.xml',
            <<<'XML'
            <SReputationContextUI.TestContext sortOrderScope="alpha" __type="SReputationContextUI" __ref="40000000-0000-0000-0000-000000000003" __path="libs/foundry/records/reputation/contexts/test_context.xml">
              <primaryScopeContext scope="40000000-0000-0000-0000-000000000004" />
            </SReputationContextUI.TestContext>
            XML
        );
        $hostilityScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_hostility_scope.xml',
            <<<'XML'
            <SReputationScopeParams.Hostility scopeName="hostility" displayName="@loc_hostility_scope" description="@loc_hostility_scope_desc" __type="SReputationScopeParams" __ref="40000000-0000-0000-0000-000000000004" __path="libs/foundry/records/reputation/scopes/test_hostility_scope.xml">
              <standingMap reputationCeiling="100" initialReputation="-10">
                <standings>
                  <Reference value="40000000-0000-0000-0000-000000000005" />
                </standings>
              </standingMap>
            </SReputationScopeParams.Hostility>
            XML
        );
        $hostilityStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/test_hostility_standing.xml',
            <<<'XML'
            <SReputationStandingParams.Hostile name="Hostile" displayName="@loc_hostile_name" description="@loc_hostile_desc" perkDescription="@loc_hostile_perk" minReputation="-50" driftReputation="-10" driftTimeHours="24" gated="1" __type="SReputationStandingParams" __ref="40000000-0000-0000-0000-000000000005" __path="libs/foundry/records/reputation/standings/test_hostility_standing.xml" />
            XML
        );
        $alliedScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_allied_scope.xml',
            <<<'XML'
            <SReputationScopeParams.AlliedScope scopeName="allied" displayName="@loc_allied_scope" description="@loc_allied_scope_desc" __type="SReputationScopeParams" __ref="40000000-0000-0000-0000-000000000006" __path="libs/foundry/records/reputation/scopes/test_allied_scope.xml">
              <standingMap reputationCeiling="1000" initialReputation="50">
                <standings>
                  <Reference value="40000000-0000-0000-0000-000000000007" />
                </standings>
              </standingMap>
            </SReputationScopeParams.AlliedScope>
            XML
        );
        $alliedStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/test_allied_standing.xml',
            <<<'XML'
            <SReputationStandingParams.AlliedStanding name="Allied" displayName="@loc_allied_name" description="@loc_allied_desc" perkDescription="@loc_allied_perk" minReputation="500" driftReputation="0" driftTimeHours="0" gated="0" __type="SReputationStandingParams" __ref="40000000-0000-0000-0000-000000000007" __path="libs/foundry/records/reputation/standings/test_allied_standing.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'Faction' => ['TestFaction' => $factionPath],
                'FactionReputation' => ['TestReputation' => $reputationPath],
                'SReputationContextUI' => ['TestContext' => $contextPath],
                'SReputationScopeParams' => [
                    'Hostility' => $hostilityScopePath,
                    'AlliedScope' => $alliedScopePath,
                ],
                'SReputationStandingParams' => [
                    'Hostile' => $hostilityStandingPath,
                    'AlliedStanding' => $alliedStandingPath,
                ],
            ],
            uuidToClassMap: [
                '40000000-0000-0000-0000-000000000001' => 'TestFaction',
                '40000000-0000-0000-0000-000000000002' => 'TestReputation',
                '40000000-0000-0000-0000-000000000003' => 'TestContext',
                '40000000-0000-0000-0000-000000000004' => 'Hostility',
                '40000000-0000-0000-0000-000000000005' => 'Hostile',
                '40000000-0000-0000-0000-000000000006' => 'AlliedScope',
                '40000000-0000-0000-0000-000000000007' => 'AlliedStanding',
            ],
            classToUuidMap: [
                'TestFaction' => '40000000-0000-0000-0000-000000000001',
                'TestReputation' => '40000000-0000-0000-0000-000000000002',
                'TestContext' => '40000000-0000-0000-0000-000000000003',
                'Hostility' => '40000000-0000-0000-0000-000000000004',
                'Hostile' => '40000000-0000-0000-0000-000000000005',
                'AlliedScope' => '40000000-0000-0000-0000-000000000006',
                'AlliedStanding' => '40000000-0000-0000-0000-000000000007',
            ],
            uuidToPathMap: [
                '40000000-0000-0000-0000-000000000001' => $factionPath,
                '40000000-0000-0000-0000-000000000002' => $reputationPath,
                '40000000-0000-0000-0000-000000000003' => $contextPath,
                '40000000-0000-0000-0000-000000000004' => $hostilityScopePath,
                '40000000-0000-0000-0000-000000000005' => $hostilityStandingPath,
                '40000000-0000-0000-0000-000000000006' => $alliedScopePath,
                '40000000-0000-0000-0000-000000000007' => $alliedStandingPath,
            ],
        );
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'loc_faction_name=Test Faction',
                'loc_faction_desc=Faction Description',
                'loc_reputation_name=Test Reputation',
                'loc_hostility_marker=Hostility Marker',
                'loc_allied_marker=Allied Marker',
                'loc_property_value=Hello Citizen',
                'loc_hostility_scope=Hostility Scope',
                'loc_hostility_scope_desc=Hostility Scope Description',
                'loc_allied_scope=Allied Scope',
                'loc_allied_scope_desc=Allied Scope Description',
                'loc_hostile_name=Hostile Display',
                'loc_hostile_desc=Hostile Description',
                'loc_hostile_perk=Hostile Perk',
                'loc_allied_name=Allied Display',
                'loc_allied_desc=Allied Description',
                'loc_allied_perk=Allied Perk',
            ])
        );

        $tester = new CommandTester(new LoadFactions);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $export = $this->readJsonFile('factions/testfaction.json');

        self::assertSame('Test Faction', $export['name']);
        self::assertSame('Test Reputation', $export['reputation']['displayName']);
        self::assertSame('Hostility Scope', $export['reputation']['context']['primaryScope']['displayName']);
        self::assertSame('Hostile Display', $export['reputation']['hostility']['standing']['displayName']);
        self::assertSame('Allied Display', $export['reputation']['allied']['standing']['displayName']);
        self::assertSame('Hello Citizen', $export['reputation']['properties']['greeting']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
