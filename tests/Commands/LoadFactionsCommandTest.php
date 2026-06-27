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
              <primaryScopeContext scope="40000000-0000-0000-0000-00000000000a" />
              <scopeContextList>
                <SReputationScopeContextUI scope="40000000-0000-0000-0000-000000000008" />
              </scopeContextList>
            </SReputationContextUI.TestContext>
            XML
        );
        $otherContextPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/contexts/test_other_context.xml',
            <<<'XML'
            <SReputationContextUI.OtherContext sortOrderScope="alpha" __type="SReputationContextUI" __ref="40000000-0000-0000-0000-00000000000b" __path="libs/foundry/records/reputation/contexts/test_other_context.xml">
              <primaryScopeContext scope="40000000-0000-0000-0000-00000000000a" />
            </SReputationContextUI.OtherContext>
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
        $genericScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_generic_scope.xml',
            <<<'XML'
            <SReputationScopeParams.Generic scopeName="affinity" displayName="@loc_generic_scope" description="@loc_generic_scope_desc" __type="SReputationScopeParams" __ref="40000000-0000-0000-0000-00000000000a" __path="libs/foundry/records/reputation/scopes/test_generic_scope.xml">
              <standingMap reputationCeiling="10000" initialReputation="0">
                <standings />
              </standingMap>
            </SReputationScopeParams.Generic>
            XML
        );
        $barterScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_barter_scope.xml',
            <<<'XML'
            <SReputationScopeParams.Barter scopeName="barter" displayName="@loc_barter_scope" description="@loc_barter_scope_desc" __type="SReputationScopeParams" __ref="40000000-0000-0000-0000-000000000008" __path="libs/foundry/records/reputation/scopes/test_barter_scope.xml">
              <standingMap reputationCeiling="1000" initialReputation="0">
                <standings>
                  <Reference value="40000000-0000-0000-0000-000000000009" />
                </standings>
              </standingMap>
            </SReputationScopeParams.Barter>
            XML
        );
        $barterStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/test_barter_standing.xml',
            <<<'XML'
            <SReputationStandingParams.BarterStanding name="BestCustomer" displayName="@loc_barter_name" description="@loc_barter_desc" perkDescription="@loc_barter_perk" minReputation="999" driftReputation="0" driftTimeHours="0" gated="0" __type="SReputationStandingParams" __ref="40000000-0000-0000-0000-000000000009" __path="libs/foundry/records/reputation/standings/test_barter_standing.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'Faction' => ['TestFaction' => $factionPath],
                'FactionReputation' => ['TestReputation' => $reputationPath],
                'SReputationContextUI' => ['TestContext' => $contextPath, 'OtherContext' => $otherContextPath],
                'SReputationScopeParams' => [
                    'Hostility' => $hostilityScopePath,
                    'AlliedScope' => $alliedScopePath,
                    'Generic' => $genericScopePath,
                    'Barter' => $barterScopePath,
                ],
                'SReputationStandingParams' => [
                    'Hostile' => $hostilityStandingPath,
                    'AlliedStanding' => $alliedStandingPath,
                    'BarterStanding' => $barterStandingPath,
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
                '40000000-0000-0000-0000-000000000008' => 'Barter',
                '40000000-0000-0000-0000-000000000009' => 'BarterStanding',
                '40000000-0000-0000-0000-00000000000a' => 'Generic',
                '40000000-0000-0000-0000-00000000000b' => 'OtherContext',
            ],
            classToUuidMap: [
                'TestFaction' => '40000000-0000-0000-0000-000000000001',
                'TestReputation' => '40000000-0000-0000-0000-000000000002',
                'TestContext' => '40000000-0000-0000-0000-000000000003',
                'Hostility' => '40000000-0000-0000-0000-000000000004',
                'Hostile' => '40000000-0000-0000-0000-000000000005',
                'AlliedScope' => '40000000-0000-0000-0000-000000000006',
                'AlliedStanding' => '40000000-0000-0000-0000-000000000007',
                'Barter' => '40000000-0000-0000-0000-000000000008',
                'BarterStanding' => '40000000-0000-0000-0000-000000000009',
                'Generic' => '40000000-0000-0000-0000-00000000000a',
                'OtherContext' => '40000000-0000-0000-0000-00000000000b',
            ],
            uuidToPathMap: [
                '40000000-0000-0000-0000-000000000001' => $factionPath,
                '40000000-0000-0000-0000-000000000002' => $reputationPath,
                '40000000-0000-0000-0000-000000000003' => $contextPath,
                '40000000-0000-0000-0000-000000000004' => $hostilityScopePath,
                '40000000-0000-0000-0000-000000000005' => $hostilityStandingPath,
                '40000000-0000-0000-0000-000000000006' => $alliedScopePath,
                '40000000-0000-0000-0000-000000000007' => $alliedStandingPath,
                '40000000-0000-0000-0000-000000000008' => $barterScopePath,
                '40000000-0000-0000-0000-000000000009' => $barterStandingPath,
                '40000000-0000-0000-0000-00000000000a' => $genericScopePath,
                '40000000-0000-0000-0000-00000000000b' => $otherContextPath,
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
                'loc_generic_scope=Generic Scope',
                'loc_generic_scope_desc=Generic Scope Description',
                'loc_barter_scope=Barter Scope',
                'loc_barter_scope_desc=Barter Scope Description',
                'loc_barter_name=Very Best Customer',
                'loc_barter_desc=Barter Description',
                'loc_barter_perk=Barter Perk',
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

        self::assertSame('Test Faction', $export['Name']);
        self::assertSame('Test Reputation', $export['Reputation']['DisplayName']);
        self::assertSame('Hostile Display', $export['Reputation']['Hostility']['Standing']['DisplayName']);
        self::assertSame('Allied Display', $export['Reputation']['Allied']['Standing']['DisplayName']);
        self::assertSame('Hello Citizen', $export['Reputation']['Properties']['Greeting']);

        self::assertSame('Barter Scope', $export['Reputation']['Context']['PrimaryScope']['DisplayName']);
        self::assertSame('Very Best Customer', $export['Reputation']['Context']['PrimaryScope']['Standings'][0]['DisplayName']);
        self::assertSame(999, $export['Reputation']['Context']['PrimaryScope']['Standings'][0]['MinReputation']);
        self::assertSame([], $export['Reputation']['Context']['AdditionalScopes']);
    }

    public function test_allied_scope_is_used_when_no_faction_specific_ladder(): void
    {
        // HeadHunters shape: primary is the shared generic Affinity bar, scopeContextList
        // holds shared scopes (Racing/HiredMuscle/Assassination/FactionReputation), and
        // none are faction-specific. The ladder must fall back to the allied scope (the
        // generic Contractor bar HeadHunters actually rewards and progresses on), not
        // the flavorless Affinity primary.
        $genericScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/affinity_scope.xml',
            <<<'XML'
            <SReputationScopeParams.Affinity scopeName="Affinity" displayName="@loc_affinity" __type="SReputationScopeParams" __ref="aaaa0000-0000-0000-0000-000000000001" __path="libs/foundry/records/reputation/scopes/affinity_scope.xml">
              <standingMap reputationCeiling="10000" initialReputation="0"><standings /></standingMap>
            </SReputationScopeParams.Affinity>
            XML
        );
        // Second context shares the generic scope, so its context-count is 2 (not faction-specific).
        $otherContextPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/contexts/other_context.xml',
            <<<'XML'
            <SReputationContextUI.Other sortOrderScope="alpha" __type="SReputationContextUI" __ref="aaaa0000-0000-0000-0000-000000000010" __path="libs/foundry/records/reputation/contexts/other_context.xml">
              <primaryScopeContext scope="aaaa0000-0000-0000-0000-000000000001" />
            </SReputationContextUI.Other>
            XML
        );
        $contractorScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/contractor_scope.xml',
            <<<'XML'
            <SReputationScopeParams.Contractor scopeName="FactionReputation" displayName="@loc_contractor" __type="SReputationScopeParams" __ref="bbbb0000-0000-0000-0000-000000000001" __path="libs/foundry/records/reputation/scopes/contractor_scope.xml">
              <standingMap reputationCeiling="95250" initialReputation="0">
                <standings><Reference value="bbbb0000-0000-0000-0000-000000000002" /></standings>
              </standingMap>
            </SReputationScopeParams.Contractor>
            XML
        );
        $contractorStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/contractor_standing.xml',
            <<<'XML'
            <SReputationStandingParams.Rank1 name="FactionRep_Allied_Rank1" displayName="@loc_rank1" minReputation="800" driftReputation="0" driftTimeHours="0" gated="0" __type="SReputationStandingParams" __ref="bbbb0000-0000-0000-0000-000000000002" __path="libs/foundry/records/reputation/standings/contractor_standing.xml" />
            XML
        );
        $factionContextPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/contexts/faction_context.xml',
            <<<'XML'
            <SReputationContextUI.Faction sortOrderScope="alpha" __type="SReputationContextUI" __ref="cccc0000-0000-0000-0000-000000000001" __path="libs/foundry/records/reputation/contexts/faction_context.xml">
              <primaryScopeContext scope="aaaa0000-0000-0000-0000-000000000001" />
              <scopeContextList><SReputationScopeContextUI scope="bbbb0000-0000-0000-0000-000000000001" /></scopeContextList>
            </SReputationContextUI.Faction>
            XML
        );
        $repPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/factionreputation/test_rep.xml',
            <<<'XML'
            <FactionReputation.TestRep displayName="@loc_name" reputationContextPropertiesUI="cccc0000-0000-0000-0000-000000000001" isNPC="0" hideInDelpihApp="0" __type="FactionReputation" __ref="dddd0000-0000-0000-0000-000000000001" __path="libs/foundry/records/factions/factionreputation/test_rep.xml">
              <hostilityParams scope="bbbb0000-0000-0000-0000-000000000001" standing="bbbb0000-0000-0000-0000-000000000002"><markerParams description="@LOC_PLACEHOLDER" /></hostilityParams>
              <alliedParams scope="bbbb0000-0000-0000-0000-000000000001" standing="bbbb0000-0000-0000-0000-000000000002"><markerParams description="@LOC_PLACEHOLDER" /></alliedParams>
            </FactionReputation.TestRep>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'FactionReputation' => ['TestRep' => $repPath],
                'SReputationContextUI' => ['Faction' => $factionContextPath, 'Other' => $otherContextPath],
                'SReputationScopeParams' => ['Affinity' => $genericScopePath, 'Contractor' => $contractorScopePath],
                'SReputationStandingParams' => ['Rank1' => $contractorStandingPath],
            ],
            uuidToClassMap: [
                'dddd0000-0000-0000-0000-000000000001' => 'TestRep',
                'cccc0000-0000-0000-0000-000000000001' => 'Faction',
                'aaaa0000-0000-0000-0000-000000000010' => 'Other',
                'aaaa0000-0000-0000-0000-000000000001' => 'Affinity',
                'bbbb0000-0000-0000-0000-000000000001' => 'Contractor',
                'bbbb0000-0000-0000-0000-000000000002' => 'Rank1',
            ],
            classToUuidMap: [
                'TestRep' => 'dddd0000-0000-0000-0000-000000000001',
                'Faction' => 'cccc0000-0000-0000-0000-000000000001',
                'Other' => 'aaaa0000-0000-0000-0000-000000000010',
                'Affinity' => 'aaaa0000-0000-0000-0000-000000000001',
                'Contractor' => 'bbbb0000-0000-0000-0000-000000000001',
                'Rank1' => 'bbbb0000-0000-0000-0000-000000000002',
            ],
            uuidToPathMap: [
                'dddd0000-0000-0000-0000-000000000001' => $repPath,
                'cccc0000-0000-0000-0000-000000000001' => $factionContextPath,
                'aaaa0000-0000-0000-0000-000000000010' => $otherContextPath,
                'aaaa0000-0000-0000-0000-000000000001' => $genericScopePath,
                'bbbb0000-0000-0000-0000-000000000001' => $contractorScopePath,
                'bbbb0000-0000-0000-0000-000000000002' => $contractorStandingPath,
            ],
        );
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'LOC_EMPTY=',
                'loc_name=Test Faction',
                'loc_affinity=Affinity',
                'loc_contractor=Standing',
                'loc_rank1=Jr. Contractor',
            ])
        );

        $tester = new CommandTester(new LoadFactions);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ]);

        self::assertSame(0, $exitCode);

        $export = $this->readJsonFile('factions/testrep.json');

        // No faction-specific ladder: must fall back to the allied Contractor scope,
        // not the shared Affinity primary.
        self::assertSame('FactionReputation', $export['Reputation']['Context']['PrimaryScope']['ScopeName']);
        self::assertNotSame('Affinity', $export['Reputation']['Context']['PrimaryScope']['ScopeName']);
        self::assertSame('Jr. Contractor', $export['Reputation']['Context']['PrimaryScope']['Standings'][0]['DisplayName']);
        self::assertSame([], $export['Reputation']['Context']['AdditionalScopes']);
    }
}
