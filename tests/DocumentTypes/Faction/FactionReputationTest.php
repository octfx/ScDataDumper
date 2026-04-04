<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Faction;

use Octfx\ScDataDumper\DocumentTypes\Faction\Faction;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class FactionReputationTest extends ScDataTestCase
{
    private const FACTION_UUID = '20000000-0000-0000-0000-000000000001';

    private const REPUTATION_UUID = '20000000-0000-0000-0000-000000000002';

    private const CONTEXT_UUID = '20000000-0000-0000-0000-000000000003';

    private const HOSTILITY_SCOPE_UUID = '20000000-0000-0000-0000-000000000004';

    private const HOSTILITY_STANDING_UUID = '20000000-0000-0000-0000-000000000005';

    private const ALLIED_SCOPE_UUID = '20000000-0000-0000-0000-000000000006';

    private const ALLIED_STANDING_UUID = '20000000-0000-0000-0000-000000000007';

    protected function setUp(): void
    {
        parent::setUp();

        $factionPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/test_faction.xml',
            sprintf(
                '<Faction.TestFaction name="@loc_faction_name" description="@loc_faction_desc" defaultReaction="Neutral" factionType="Government" ableToArrest="1" policesLawfulTrespass="1" policesCriminality="1" noLegalRights="0" factionReputationRef="%2$s" __type="Faction" __ref="%1$s" __path="libs/foundry/records/factions/test_faction.xml" />',
                self::FACTION_UUID,
                self::REPUTATION_UUID,
            )
        );
        $reputationPath = $this->writeFile(
            'Game2/libs/foundry/records/factions/factionreputation/test_reputation.xml',
            sprintf(
                '<FactionReputation.TestReputation displayName="@loc_reputation_name" reputationContextPropertiesUI="%2$s" isNPC="0" hideInDelpihApp="0" __type="FactionReputation" __ref="%1$s" __path="libs/foundry/records/factions/factionreputation/test_reputation.xml"><hostilityParams scope="%3$s" standing="%4$s"><markerParams description="@loc_hostility_marker" /></hostilityParams><alliedParams scope="%5$s" standing="%6$s"><markerParams description="@loc_allied_marker" /></alliedParams><propertiesBB><SReputationContextBBPropertyParams name="entityGreeting"><dynamicProperty><SBBDynamicPropertyLocString value="@loc_property_value" /></dynamicProperty></SReputationContextBBPropertyParams></propertiesBB></FactionReputation.TestReputation>',
                self::REPUTATION_UUID,
                self::CONTEXT_UUID,
                self::HOSTILITY_SCOPE_UUID,
                self::HOSTILITY_STANDING_UUID,
                self::ALLIED_SCOPE_UUID,
                self::ALLIED_STANDING_UUID,
            )
        );
        $contextPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/contexts/test_context.xml',
            sprintf(
                '<SReputationContextUI.TestContext sortOrderScope="alpha" __type="SReputationContextUI" __ref="%1$s" __path="libs/foundry/records/reputation/contexts/test_context.xml"><primaryScopeContext scope="%2$s" /></SReputationContextUI.TestContext>',
                self::CONTEXT_UUID,
                self::HOSTILITY_SCOPE_UUID,
            )
        );
        $hostilityScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_hostility_scope.xml',
            sprintf(
                '<SReputationScopeParams.Hostility scopeName="hostility" displayName="@loc_hostility_scope" description="@loc_hostility_scope_desc" __type="SReputationScopeParams" __ref="%1$s" __path="libs/foundry/records/reputation/scopes/test_hostility_scope.xml"><standingMap reputationCeiling="100" initialReputation="-10"><standings><Reference value="%2$s" /></standings></standingMap></SReputationScopeParams.Hostility>',
                self::HOSTILITY_SCOPE_UUID,
                self::HOSTILITY_STANDING_UUID,
            )
        );
        $alliedScopePath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/scopes/test_allied_scope.xml',
            sprintf(
                '<SReputationScopeParams.Allied scopeName="allied" displayName="@loc_allied_scope" description="@loc_allied_scope_desc" __type="SReputationScopeParams" __ref="%1$s" __path="libs/foundry/records/reputation/scopes/test_allied_scope.xml"><standingMap reputationCeiling="1000" initialReputation="50"><standings><Reference value="%2$s" /></standings></standingMap></SReputationScopeParams.Allied>',
                self::ALLIED_SCOPE_UUID,
                self::ALLIED_STANDING_UUID,
            )
        );
        $hostilityStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/test_hostility_standing.xml',
            sprintf(
                '<SReputationStandingParams.Hostile name="Hostile" displayName="@loc_hostile_name" description="@loc_hostile_desc" perkDescription="@loc_hostile_perk" minReputation="-50" driftReputation="-10" driftTimeHours="24" gated="1" __type="SReputationStandingParams" __ref="%1$s" __path="libs/foundry/records/reputation/standings/test_hostility_standing.xml" />',
                self::HOSTILITY_STANDING_UUID,
            )
        );
        $alliedStandingPath = $this->writeFile(
            'Game2/libs/foundry/records/reputation/standings/test_allied_standing.xml',
            sprintf(
                '<SReputationStandingParams.Allied name="Allied" displayName="@loc_allied_name" description="@loc_allied_desc" perkDescription="@loc_allied_perk" minReputation="500" driftReputation="0" driftTimeHours="0" gated="0" __type="SReputationStandingParams" __ref="%1$s" __path="libs/foundry/records/reputation/standings/test_allied_standing.xml" />',
                self::ALLIED_STANDING_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'Faction' => [
                    'TestFaction' => $factionPath,
                ],
                'FactionReputation' => [
                    'TestReputation' => $reputationPath,
                ],
                'SReputationContextUI' => [
                    'TestContext' => $contextPath,
                ],
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
                strtolower(self::FACTION_UUID) => 'TestFaction',
                strtolower(self::REPUTATION_UUID) => 'TestReputation',
                strtolower(self::CONTEXT_UUID) => 'TestContext',
                strtolower(self::HOSTILITY_SCOPE_UUID) => 'Hostility',
                strtolower(self::HOSTILITY_STANDING_UUID) => 'Hostile',
                strtolower(self::ALLIED_SCOPE_UUID) => 'AlliedScope',
                strtolower(self::ALLIED_STANDING_UUID) => 'AlliedStanding',
            ],
            classToUuidMap: [
                'TestFaction' => strtolower(self::FACTION_UUID),
                'TestReputation' => strtolower(self::REPUTATION_UUID),
                'TestContext' => strtolower(self::CONTEXT_UUID),
                'Hostility' => strtolower(self::HOSTILITY_SCOPE_UUID),
                'Hostile' => strtolower(self::HOSTILITY_STANDING_UUID),
                'AlliedScope' => strtolower(self::ALLIED_SCOPE_UUID),
                'AlliedStanding' => strtolower(self::ALLIED_STANDING_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::FACTION_UUID) => $factionPath,
                strtolower(self::REPUTATION_UUID) => $reputationPath,
                strtolower(self::CONTEXT_UUID) => $contextPath,
                strtolower(self::HOSTILITY_SCOPE_UUID) => $hostilityScopePath,
                strtolower(self::HOSTILITY_STANDING_UUID) => $hostilityStandingPath,
                strtolower(self::ALLIED_SCOPE_UUID) => $alliedScopePath,
                strtolower(self::ALLIED_STANDING_UUID) => $alliedStandingPath,
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

        (new ServiceFactory($this->tempDir))->initialize();
    }

    public function test_resolves_reputation_chain_when_reference_hydration_is_disabled(): void
    {
        $document = (new Faction)
            ->setReferenceHydrationEnabled(false);
        $document->load($this->tempDir.'/Game2/libs/foundry/records/factions/test_faction.xml');

        $reputation = $document->getFactionReputation();

        self::assertNotNull($reputation);
        self::assertSame(self::REPUTATION_UUID, $reputation?->getUuid());
        self::assertSame(self::CONTEXT_UUID, $reputation?->getReputationContext()?->getUuid());
        self::assertSame(self::HOSTILITY_SCOPE_UUID, $reputation?->getHostilityScope()?->getUuid());
        self::assertSame(self::HOSTILITY_STANDING_UUID, $reputation?->getHostilityStanding()?->getUuid());
        self::assertSame(self::ALLIED_SCOPE_UUID, $reputation?->getAlliedScope()?->getUuid());
        self::assertSame(self::ALLIED_STANDING_UUID, $reputation?->getAlliedStanding()?->getUuid());
        self::assertSame(self::HOSTILITY_SCOPE_UUID, $reputation?->getReputationContext()?->getPrimaryScope()?->getUuid());
        self::assertCount(1, $reputation?->getHostilityScope()?->getStandings() ?? []);
        self::assertSame(self::HOSTILITY_STANDING_UUID, $reputation?->getHostilityScope()?->getStandings()[0]?->getUuid());
        self::assertCount(1, $reputation?->getAlliedScope()?->getStandings() ?? []);
        self::assertSame(self::ALLIED_STANDING_UUID, $reputation?->getAlliedScope()?->getStandings()[0]?->getUuid());
    }
}
