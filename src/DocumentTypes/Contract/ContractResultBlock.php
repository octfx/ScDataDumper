<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\ItemAwardWeightingsRecord;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class ContractResultBlock extends RootDocument
{
    public function getContractBuyInAmount(): int
    {
        return $this->getInt('@contractBuyInAmount') ?? 0;
    }

    public function getTimeToComplete(): ?float
    {
        return $this->getFloat('@timeToComplete');
    }

    public function getCalculatedReward(): bool
    {
        return $this->get('contractResults/ContractResult_CalculatedReward') !== null;
    }

    /**
     * @return list<bool>
     */
    public function getMissionResultConditions(): array
    {
        $nodes = $this->getAll('contractResults/ContractResult_Reward/missionResults/Bool');
        if ($nodes === []) {
            $nodes = $this->getAll('contractResults/ContractResult_CalculatedReward/missionResults/Bool');
        }

        return array_map(static fn ($n): bool => (int) ($n->get('@value') ?? 0) === 1, $nodes);
    }

    public function getFixedReward(): ?array
    {
        $node = $this->get('contractResults/ContractResult_Reward/contractReward');
        if ($node === null) {
            return null;
        }

        return [
            'reward' => (int) ($node->get('@reward') ?? 0),
            'max' => (int) ($node->get('@max') ?? 0),
            'plusBonuses' => (int) ($node->get('@plusBonuses') ?? 0) === 1,
            'currencyType' => (string) ($node->get('@currencyType') ?? 'UEC'),
        ];
    }

    /**
     * @return list<array{factionReputation: ?string, reputationScope: ?string, reward: ?string, outcome: ?string}>
     */
    public function getLegacyReputationRewards(): array
    {
        $results = [];
        $nodes = $this->getAll('contractResults/ContractResult_LegacyReputation');

        foreach ($nodes as $node) {
            $outcome = self::deriveOutcomeLabel($this->readOutcomeVector($node));

            foreach ($node->getAll('contractResultReputationAmounts') as $amount) {
                $results[] = [
                    'factionReputation' => $amount->get('@factionReputation'),
                    'reputationScope' => $amount->get('@reputationScope'),
                    'reward' => $amount->get('@reward'),
                    'outcome' => $outcome,
                ];
            }
        }

        return $results;
    }

    /**
     * Read the 5-bool <missionResults> vector gating which mission outcome fires a result.
     *
     * @return list<bool>
     */
    private function readOutcomeVector(Element $node): array
    {
        return array_map(
            static fn (Element $b): bool => (int) ($b->get('@value') ?? 0) === 1,
            $node->getAll('missionResults/Bool'),
        );
    }

    /**
     * Derive a human outcome label from a missionResults vector.
     *
     * Slot 0 = Success
     * slot 2 = Failure
     *
     * @param  list<bool>  $vector
     */
    public static function deriveOutcomeLabel(array $vector): ?string
    {
        if ($vector === []) {
            return null;
        }

        $set = count(array_filter($vector));

        if ($set === 0) {
            return 'unconditional';
        }

        $success = $vector[0] ?? false;
        $failure = $vector[2] ?? false;

        if ($success && $set === 1) {
            return null;
        }

        if ($failure) {
            return 'failure';
        }

        return 'other';
    }

    public function getCalculatedReputation(): ?array
    {
        $node = $this->get('contractResults/ContractResult_CalculatedReputation');
        if ($node === null) {
            return null;
        }

        return [
            'factionReputation' => $node->get('@factionReputation'),
            'reputationScope' => $node->get('@reputationScope'),
        ];
    }

    /**
     * @return list<string>
     */
    public function getCompletionTags(): array
    {
        $results = [];
        $nodes = $this->getAll('contractResults/ContractResult_CompletionTags/completionTags/ContractResult_CompletionTag');

        foreach ($nodes as $node) {
            $tag = $node->get('@tag');
            if ($tag !== null) {
                $results[] = $tag;
            }
        }

        return $results;
    }

    /**
     * @return list<array{chance: float, blueprintPool: ?string}>
     */
    public function getAllBlueprintRewards(): array
    {
        $nodes = $this->getAll('contractResults/BlueprintRewards');
        if ($nodes === []) {
            return [];
        }

        $results = [];
        foreach ($nodes as $node) {
            $results[] = [
                'chance' => (float) ($node->get('@chance') ?? 0),
                'blueprintPool' => $node->get('@blueprintPool'),
            ];
        }

        return $results;
    }

    /**
     * @return list<array{entityClass: ?string, amount: int, sendToPlayerHomeLocation: bool, awardOnlyToMissionOwner: bool}>
     */
    public function getItemResults(): array
    {
        $results = [];
        $nodes = $this->getAll('contractResults/ContractResult_Item');

        foreach ($nodes as $node) {
            $results[] = [
                'entityClass' => $node->get('@entityClass'),
                'amount' => (int) ($node->get('@amount') ?? 0),
                'sendToPlayerHomeLocation' => (int) ($node->get('@sendToPlayerHomeLocation') ?? 0) === 1,
                'awardOnlyToMissionOwner' => (int) ($node->get('@awardOnlyToMissionOwner') ?? 0) === 1,
            ];
        }

        return $results;
    }

    public function hasCompletionBounty(): bool
    {
        return $this->get('contractResults/ContractResult_CompletionBounty') !== null;
    }

    /**
     * Weighted item award sets from ContractResult_ItemsWeighting blocks.
     *
     * Each ContractResult_ItemsWeighting carries an itemAwardStructure holding one or more
     * ItemAwardWeightings options. Every entry under an option's awards is granted together;
     * the options themselves are weighted alternatives.
     *
     * The award structure is defined either inline (<ItemAwardWeightings>) or by reference
     * (<ItemAwardWeightingsParams awardsRecord="<uuid>"> pointing at an iawr_*.xml pool);
     * the two shapes are mutually exclusive per block. Both parse through
     * {@see ItemAwardWeightingsRecord::getAwardSets()}, which owns the shared structure.
     *
     * @return list<array{
     *     weight: int,
     *     awardOnlyToMissionOwner: bool,
     *     items: list<array{entityClass: ?string, amount: int}>
     * }>
     */
    public function getItemAwardSets(): array
    {
        $sets = [];
        $nodes = $this->getAll('contractResults/ContractResult_ItemsWeighting');

        foreach ($nodes as $node) {
            $awardOnlyToMissionOwner = (int) ($node->get('@awardOnlyToMissionOwner') ?? 0) === 1;

            foreach ($this->resolveItemAwardWeighting($node) as $set) {
                $sets[] = [
                    'weight' => $set['weight'],
                    'awardOnlyToMissionOwner' => $awardOnlyToMissionOwner,
                    'items' => $set['items'],
                ];
            }
        }

        return $sets;
    }

    /**
     * Resolve the {weight, items} award sets for a single ContractResult_ItemsWeighting node,
     * transparently handling both the inline and by-reference award shapes.
     *
     * @return list<array{weight: int, items: list<array{entityClass: ?string, amount: int}>}>
     */
    private function resolveItemAwardWeighting(Element $node): array
    {
        // Inline awards: the ContractResult_ItemsWeighting node
        $inline = ItemAwardWeightingsRecord::fromNode($node->getNode())?->getAwardSets() ?? [];
        if ($inline !== []) {
            return $inline;
        }

        // By-reference awards: <ItemAwardWeightingsParams awardsRecord="<uuid>"/>.
        $sets = [];
        foreach ($node->getAll('itemAwardStructure/ItemAwardWeightingsParams') as $params) {
            $ref = $params->get('@awardsRecord');
            if ($ref !== null && ServiceFactory::isInitialized()) {
                $pool = ServiceFactory::getFoundryLookupService()->getItemAwardWeightingsByReference($ref);
                foreach ($pool?->getAwardSets() ?? [] as $set) {
                    $sets[] = $set;
                }
            }
        }

        return $sets;
    }

    public function getRefundBuyIn(): ?float
    {
        $node = $this->get('contractResults/ContractResult_RefundBuyIn');
        if ($node === null) {
            return null;
        }

        return (float) ($node->get('@refundMultiplier') ?? 0);
    }

    public function getScenarioProgress(): ?int
    {
        $node = $this->get('contractResults/ContractResult_ScenarioProgress');
        if ($node === null) {
            return null;
        }

        return (int) ($node->get('@PointsToAward') ?? 0);
    }

    /**
     * @return list<string>
     */
    public function getJournalEntryReferences(): array
    {
        return $this->queryAttributeValues(
            'contractResults/ContractResult_JournalEntry/journalEntriesToAdd/Reference',
            'value'
        );
    }

    public function getBadgeAward(): ?string
    {
        $node = $this->get('contractResults/ContractResult_BadgeAward');
        if ($node === null) {
            return null;
        }

        return $node->get('@badgeToAward');
    }

    /**
     * @return array{mechanicalSkill: ?string, mentalLoad: ?string, riskOfLoss: ?string, gameKnowledge: ?string, difficultyProfile: ?string}
     */
    public function getDifficulty(): array
    {
        $node = $this->get('difficulty/ContractDifficulty');
        if ($node === null) {
            return [
                'mechanicalSkill' => null,
                'mentalLoad' => null,
                'riskOfLoss' => null,
                'gameKnowledge' => null,
                'difficultyProfile' => null,
            ];
        }

        return [
            'mechanicalSkill' => $node->get('@mechanicalSkill'),
            'mentalLoad' => $node->get('@mentalLoad'),
            'riskOfLoss' => $node->get('@riskOfLoss'),
            'gameKnowledge' => $node->get('@gameKnowledge'),
            'difficultyProfile' => $node->get('@difficultyProfile'),
        ];
    }
}
