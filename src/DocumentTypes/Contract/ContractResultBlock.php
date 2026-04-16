<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

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
     * @return list<array{factionReputation: ?string, reputationScope: ?string, reward: ?string}>
     */
    public function getLegacyReputationRewards(): array
    {
        $results = [];
        $nodes = $this->getAll('contractResults/ContractResult_LegacyReputation/contractResultReputationAmounts');

        foreach ($nodes as $node) {
            $results[] = [
                'factionReputation' => $node->get('@factionReputation'),
                'reputationScope' => $node->get('@reputationScope'),
                'reward' => $node->get('@reward'),
            ];
        }

        return $results;
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

    public function getBlueprintRewards(): ?array
    {
        $node = $this->get('contractResults/BlueprintRewards');
        if ($node === null) {
            return null;
        }

        return [
            'chance' => (float) ($node->get('@chance') ?? 0),
            'blueprintPool' => $node->get('@blueprintPool'),
        ];
    }

    /**
     * @return list<array{entityClass: ?string, amount: int, sendToPlayerHomeLocation: bool}>
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
            ];
        }

        return $results;
    }

    public function hasCompletionBounty(): bool
    {
        return $this->get('contractResults/ContractResult_CompletionBounty') !== null;
    }

    public function getItemsWeighting(): ?bool
    {
        $node = $this->get('contractResults/ContractResult_ItemsWeighting');
        if ($node === null) {
            return null;
        }

        return (int) ($node->get('@awardOnlyToMissionOwner') ?? 0) === 1;
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
