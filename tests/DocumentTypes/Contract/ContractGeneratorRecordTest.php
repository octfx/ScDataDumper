<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\Contract\ContractEntry;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractResultBlock;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ContractGeneratorRecordTest extends ScDataTestCase
{
    private const GENERATOR_UUID = '30000000-0000-0000-0000-000000000001';

    private const FACTION_REP_UUID = '30000000-0000-0000-0000-000000000002';

    private const REP_SCOPE_UUID = '30000000-0000-0000-0000-000000000003';

    private const BLUEPRINT_POOL_UUID = '30000000-0000-0000-0000-000000000004';

    private const TEMPLATE_UUID = '30000000-0000-0000-0000-000000000005';

    private string $generatorPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generatorPath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_cfp.xml',
            sprintf(
                '<ContractGenerator.TestCFP __type="ContractGenerator" __ref="%1$s" __path="libs/foundry/records/contracts/contractgenerator/test_cfp.xml"><generators><ContractGeneratorHandler_Career notForRelease="0" workInProgress="0" debugName="CFP_Career" factionReputation="%2$s" reputationScope="%3$s"><defaultAvailability notifyOnAvailable="0" maxPlayersPerInstance="1" onceOnly="0" availableInPrison="0" canReacceptAfterAbandoning="1" abandonedCooldownTime="15" abandonedCooldownTimeVariation="5" canReacceptAfterFailing="1" hasPersonalCooldown="1" personalCooldownTime="30" personalCooldownTimeVariation="10" hideInMobiGlas="0"><prerequisites><ContractPrerequisite_CrimeStat includePrerequisiteWhenSharing="0" minCrimeStat="0" maxCrimeStat="2" /><ContractPrerequisite_Locality localityAvailable="aaa11111-0000-0000-0000-000000000001" /></prerequisites></defaultAvailability><contractParams /><introContracts><Contract id="intro-001" notForRelease="0" workInProgress="0" debugName="CFP_Intro"><paramOverrides><stringParamOverrides><ContractStringParam param="Title" value="@cfp_intro_title" /><ContractStringParam param="Description" value="@cfp_intro_desc" /></stringParamOverrides><boolParamOverrides><ContractBoolParam param="Illegal" value="0" /><ContractBoolParam param="OnceOnly" value="1" /></boolParamOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="5" respawnTimeVariation="2" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="-1" /></Contract></introContracts><contracts><CareerContract id="career-001" notForRelease="0" workInProgress="0" debugName="CFP_EliminateAll" template="%4$s" minStanding="00000000-0000-0000-0000-000000000010" maxStanding="00000000-0000-0000-0000-000000000011"><paramOverrides><stringParamOverrides><ContractStringParam param="Title" value="@cfp_eliminate_title" /><ContractStringParam param="Description" value="@cfp_eliminate_desc" /></stringParamOverrides><boolParamOverrides><ContractBoolParam param="Illegal" value="0" /><ContractBoolParam param="FailIfBecameCriminal" value="1" /></boolParamOverrides></paramOverrides><additionalPrerequisites><ContractPrerequisite_CompletedContractTags requiredCountValue="1" excludedCountValue="0"><requiredCompletedContractTags><tags><Reference value="00000000-0000-0000-0000-000000000030" /></tags></requiredCompletedContractTags></ContractPrerequisite_CompletedContractTags></additionalPrerequisites><generationParams><ContractGenerationParams_Legacy maxInstances="5" maxInstancesPerPlayer="1" respawnTime="10" respawnTimeVariation="5" /></generationParams><contractLifeTime><ContractLifeTime instanceLifeTime="15" instanceLifeTimeVariation="5" /></contractLifeTime><contractResults contractBuyInAmount="0" timeToComplete="20"><contractResults><ContractResult_CalculatedReward><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults></ContractResult_CalculatedReward><ContractResult_LegacyReputation><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults><contractResultReputationAmounts factionReputation="%2$s" reputationScope="%3$s" reward="00000000-0000-0000-0000-000000000020" /></ContractResult_LegacyReputation><ContractResult_CompletionTags><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults><completionTags><ContractResult_CompletionTag count="1" tag="00000000-0000-0000-0000-000000000030" /></completionTags></ContractResult_CompletionTags><BlueprintRewards chance="1" blueprintPool="%5$s"><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults></BlueprintRewards><ContractResult_Item entityClass="00000000-0000-0000-0000-000000000040" amount="3" sendToPlayerHomeLocation="1" awardOnlyToMissionOwner="1"><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults></ContractResult_Item></contractResults><difficulty><ContractDifficulty difficultyProfile="00000000-0000-0000-0000-000000000050" mechanicalSkill="Easy_PvE_only_action_3" mentalLoad="Routine_light_work_3" riskOfLoss="Minimal_danger_FPS_NOT_ship_action_3" gameKnowledge="Standard_understanding_FPS_flight_professions_4" /></difficulty></contractResults></CareerContract><CareerContract id="career-002" notForRelease="0" workInProgress="0" debugName="CFP_Defend" template="%4$s" minStanding="00000000-0000-0000-0000-000000000012" maxStanding="00000000-0000-0000-0000-000000000013"><paramOverrides><stringParamOverrides><ContractStringParam param="Title" value="@cfp_defend_title" /></stringParamOverrides></paramOverrides><generationParams><ContractGenerationParams_Legacy maxInstances="3" maxInstancesPerPlayer="-1" respawnTime="5" respawnTimeVariation="2" /></generationParams><contractResults contractBuyInAmount="0" timeToComplete="10"><contractResults><ContractResult_CalculatedReward><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults></ContractResult_CalculatedReward><ContractResult_Reward><missionResults><Bool value="1" /><Bool value="0" /><Bool value="0" /><Bool value="0" /><Bool value="0" /></missionResults><contractReward reward="5000" max="0" plusBonuses="0" currencyType="UEC" /></ContractResult_Reward></contractResults><difficulty><ContractDifficulty difficultyProfile="00000000-0000-0000-0000-000000000051" mechanicalSkill="Normal_PvE_only_action_4" mentalLoad="Moments_of_concentration_required_4" riskOfLoss="Ship_could_get_damaged_Could_lose_cargo_4" gameKnowledge="Flight_mechanics_fly_dock_quantum_3" /></difficulty></contractResults></CareerContract></contracts></ContractGeneratorHandler_Career></generators></ContractGenerator.TestCFP>',
                self::GENERATOR_UUID,
                self::FACTION_REP_UUID,
                self::REP_SCOPE_UUID,
                self::TEMPLATE_UUID,
                self::BLUEPRINT_POOL_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'ContractGenerator' => [
                    'TestCFP' => $this->generatorPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::GENERATOR_UUID) => 'TestCFP',
            ],
            classToUuidMap: [
                'TestCFP' => strtolower(self::GENERATOR_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::GENERATOR_UUID) => $this->generatorPath,
            ],
        );

        (new ServiceFactory($this->tempDir))->initialize();
    }

    private function loadGenerator(): ContractGeneratorRecord
    {
        $doc = new ContractGeneratorRecord;
        $doc->load($this->generatorPath);

        return $doc;
    }

    public function test_parses_contract_generator_root(): void
    {
        $document = $this->loadGenerator();

        self::assertSame('ContractGenerator', $document->getType());
        self::assertSame(self::GENERATOR_UUID, $document->getUuid());
        self::assertSame('TestCFP', $document->getClassName());
    }

    public function test_extracts_handlers(): void
    {
        $document = $this->loadGenerator();

        $handlers = $document->getHandlers();
        self::assertCount(1, $handlers);

        $handler = $handlers[0];
        self::assertInstanceOf(ContractHandler::class, $handler);
        self::assertSame('ContractGeneratorHandler_Career', $handler->getHandlerType());
        self::assertSame('CFP_Career', $handler->getDebugName());
        self::assertFalse($handler->isNotForRelease());
        self::assertSame(self::FACTION_REP_UUID, $handler->getFactionReputationReference());
        self::assertSame(self::REP_SCOPE_UUID, $handler->getReputationScopeReference());
    }

    public function test_handler_default_availability(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];

        self::assertFalse($handler->isOnceOnly());
        self::assertSame(1, $handler->getMaxPlayersPerInstance());
        self::assertFalse($handler->isAvailableInPrison());
        self::assertTrue($handler->canReacceptAfterAbandoning());
        self::assertSame(15.0, $handler->getAbandonedCooldownTime());
        self::assertSame(5.0, $handler->getAbandonedCooldownTimeVariation());
        self::assertTrue($handler->canReacceptAfterFailing());
        self::assertTrue($handler->hasPersonalCooldown());
        self::assertSame(30.0, $handler->getPersonalCooldownTime());
        self::assertSame(10.0, $handler->getPersonalCooldownTimeVariation());
        self::assertFalse($handler->isHideInMobiGlas());
        self::assertFalse($handler->notifyOnAvailable());
    }

    public function test_handler_prerequisites(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];

        $prereqs = $handler->getDefaultPrerequisites();
        self::assertCount(2, $prereqs);

        self::assertSame('ContractPrerequisite_CrimeStat', $prereqs[0]['type']);
        self::assertSame(0, $prereqs[0]['minCrimeStat']);
        self::assertSame(2, $prereqs[0]['maxCrimeStat']);

        self::assertSame('ContractPrerequisite_Locality', $prereqs[1]['type']);
        self::assertSame('aaa11111-0000-0000-0000-000000000001', $prereqs[1]['localityAvailable']);
    }

    public function test_intro_contracts(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];

        $introContracts = $handler->getIntroContracts();
        self::assertCount(1, $introContracts);

        $intro = $introContracts[0];
        self::assertInstanceOf(ContractEntry::class, $intro);
        self::assertSame('intro-001', $intro->getId());
        self::assertSame('CFP_Intro', $intro->getDebugName());
        self::assertSame('@cfp_intro_title', $intro->getTitle());
        self::assertSame('@cfp_intro_desc', $intro->getDescription());
        self::assertFalse($intro->isIllegal());
        self::assertTrue($intro->isOnceOnly());
        self::assertSame(1, $intro->getMaxInstances());
        self::assertSame(5.0, $intro->getRespawnTime());
        self::assertSame(-1.0, $intro->getResults()?->getTimeToComplete());
    }

    public function test_career_contracts(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];

        $contracts = $handler->getContracts();
        self::assertCount(2, $contracts);

        $first = $contracts[0];
        self::assertSame('career-001', $first->getId());
        self::assertSame('CFP_EliminateAll', $first->getDebugName());
        self::assertSame(self::TEMPLATE_UUID, $first->getTemplateReference());
        self::assertSame('00000000-0000-0000-0000-000000000010', $first->getMinStandingReference());
        self::assertSame('00000000-0000-0000-0000-000000000011', $first->getMaxStandingReference());
        self::assertSame('@cfp_eliminate_title', $first->getTitle());
        self::assertFalse($first->isIllegal());
        self::assertTrue($first->failIfBecameCriminal());
        self::assertSame(5, $first->getMaxInstances());
        self::assertSame(1, $first->getMaxInstancesPerPlayer());
        self::assertSame(10.0, $first->getRespawnTime());
        self::assertSame(15.0, $first->getInstanceLifeTime());
        self::assertSame(5.0, $first->getInstanceLifeTimeVariation());
    }

    public function test_contract_results_block(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];
        $contract = $handler->getContracts()[0];

        $results = $contract->getResults();
        self::assertInstanceOf(ContractResultBlock::class, $results);
        self::assertSame(0, $results->getContractBuyInAmount());
        self::assertSame(20.0, $results->getTimeToComplete());
        self::assertTrue($results->getCalculatedReward());
        self::assertNull($results->getFixedReward());

        $legacyRep = $results->getLegacyReputationRewards();
        self::assertCount(1, $legacyRep);
        self::assertSame(self::FACTION_REP_UUID, $legacyRep[0]['factionReputation']);
        self::assertSame(self::REP_SCOPE_UUID, $legacyRep[0]['reputationScope']);
        self::assertSame('00000000-0000-0000-0000-000000000020', $legacyRep[0]['reward']);

        $completionTags = $results->getCompletionTags();
        self::assertCount(1, $completionTags);
        self::assertSame('00000000-0000-0000-0000-000000000030', $completionTags[0]);

        $blueprintRewards = $results->getBlueprintRewards();
        self::assertNotNull($blueprintRewards);
        self::assertSame(1.0, $blueprintRewards['chance']);
        self::assertSame(self::BLUEPRINT_POOL_UUID, $blueprintRewards['blueprintPool']);

        $itemResults = $results->getItemResults();
        self::assertCount(1, $itemResults);
        self::assertSame('00000000-0000-0000-0000-000000000040', $itemResults[0]['entityClass']);
        self::assertSame(3, $itemResults[0]['amount']);
        self::assertTrue($itemResults[0]['sendToPlayerHomeLocation']);
    }

    public function test_difficulty(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];
        $contract = $handler->getContracts()[0];

        $difficulty = $contract->getResults()?->getDifficulty();
        self::assertNotNull($difficulty);
        self::assertSame('Easy_PvE_only_action_3', $difficulty['mechanicalSkill']);
        self::assertSame('Routine_light_work_3', $difficulty['mentalLoad']);
        self::assertSame('Minimal_danger_FPS_NOT_ship_action_3', $difficulty['riskOfLoss']);
        self::assertSame('Standard_understanding_FPS_flight_professions_4', $difficulty['gameKnowledge']);
        self::assertSame('00000000-0000-0000-0000-000000000050', $difficulty['difficultyProfile']);
    }

    public function test_second_career_contract(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];
        $second = $handler->getContracts()[1];

        self::assertSame('career-002', $second->getId());
        self::assertSame('CFP_Defend', $second->getDebugName());
        self::assertSame('@cfp_defend_title', $second->getTitle());
        self::assertSame(3, $second->getMaxInstances());
        self::assertSame(-1, $second->getMaxInstancesPerPlayer());
        self::assertSame(10.0, $second->getResults()?->getTimeToComplete());

        $fixedReward = $second->getResults()?->getFixedReward();
        self::assertNotNull($fixedReward);
        self::assertSame(5000, $fixedReward['reward']);
        self::assertSame('UEC', $fixedReward['currencyType']);
    }

    public function test_completed_contract_tag_prerequisites(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];
        $contract = $handler->getContracts()[0];

        $prereqs = $contract->getCompletedContractTagPrerequisites();
        self::assertCount(1, $prereqs);
        self::assertSame(1, $prereqs[0]['requiredCountValue']);
        self::assertSame(0, $prereqs[0]['excludedCountValue']);
        self::assertSame(['00000000-0000-0000-0000-000000000030'], $prereqs[0]['requiredTags']);
        self::assertSame([], $prereqs[0]['excludedTags']);
    }

    public function test_service_iteration(): void
    {
        $service = ServiceFactory::getContractGeneratorService();
        self::assertSame(1, $service->count());

        $generators = iterator_to_array($service->iterator());
        self::assertCount(1, $generators);
        self::assertInstanceOf(ContractGeneratorRecord::class, $generators[0]);
        self::assertSame(self::GENERATOR_UUID, $generators[0]->getUuid());
    }

    public function test_service_get_by_reference(): void
    {
        $service = ServiceFactory::getContractGeneratorService();
        $generator = $service->getByReference(self::GENERATOR_UUID);

        self::assertNotNull($generator);
        self::assertSame('TestCFP', $generator->getClassName());
    }

    public function test_service_get_by_reference_returns_null_for_unknown(): void
    {
        $service = ServiceFactory::getContractGeneratorService();
        self::assertNull($service->getByReference('nonexistent-uuid'));
        self::assertNull($service->getByReference(null));
    }

    public function test_no_legacy_or_pvp_contracts_in_career_handler(): void
    {
        $document = $this->loadGenerator();
        $handler = $document->getHandlers()[0];

        self::assertSame([], $handler->getLegacyContracts());
        self::assertSame([], $handler->getPVPBountyContracts());
    }
}
