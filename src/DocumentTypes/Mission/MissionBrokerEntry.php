<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mission;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MissionBrokerEntry extends RootDocument
{
    public function getRewardAmount(): ?int
    {
        return $this->getInt('missionReward@reward');
    }

    public function getRewardMax(): ?int
    {
        return $this->getInt('missionReward@max');
    }

    public function isBonusEligible(): bool
    {
        return $this->getBool('missionReward@plusBonuses');
    }

    public function getCurrencyType(): ?string
    {
        return $this->getString('missionReward@currencyType');
    }

    public function getBuyInAmount(): int
    {
        return $this->getInt('@missionBuyInAmount') ?? 0;
    }

    public function shouldRefundBuyInOnWithdraw(): bool
    {
        return $this->getBool('@refundBuyInOnWithdraw');
    }

    /**
     * @return list<array{factionReputation: ?string, reputationScope: ?string, reward: ?string}>
     */
    public function getReputationRewards(): array
    {
        $results = [];
        $nodes = $this->getAll('missionResultReputationRewards/SReputationAmountListParams/reputationAmounts/SReputationAmountParams');

        foreach ($nodes as $node) {
            $results[] = [
                'factionReputation' => $node->get('@factionReputation'),
                'reputationScope' => $node->get('@reputationScope'),
                'reward' => $node->get('@reward'),
            ];
        }

        return $results;
    }

    public function getReputationBonusReference(): ?string
    {
        return $this->getString('missionReward@reputationBonus');
    }

    public function getReward(): ?array
    {
        $amount = $this->getRewardAmount();
        if ($amount === null) {
            return null;
        }

        return [
            'amount' => $amount,
            'max' => $this->getRewardMax() ?? 0,
            'bonus_eligible' => $this->isBonusEligible(),
            'currency' => $this->getCurrencyType() ?? 'UEC',
            'reputation_bonus' => $this->getReputationBonusReference(),
        ];
    }

    public function getDeadline(): ?array
    {
        $node = $this->get('missionDeadline');
        if ($node === null) {
            return null;
        }

        $completionTime = (int) ($node->get('@missionCompletionTime') ?? 0);

        return array_filter([
            'completion_time' => $completionTime > 0 ? $completionTime : null,
            'auto_end' => (int) ($node->get('@missionAutoEnd') ?? 0) === 1 ?: null,
            'result_after_timer' => $node->get('@missionResultAfterTimerEnd'),
            'show_timer' => (int) ($node->get('@remainingTimeToShowTimer') ?? 0) === 1 ?: null,
            'end_reason' => $node->get('@missionEndReason'),
        ], static fn ($v) => $v !== null) ?: null;
    }

    public function getReputationPrerequisites(): ?array
    {
        $node = $this->get('reputationPrerequisites/wantedLevel');
        if ($node === null) {
            return null;
        }

        $min = $node->get('@minValue');
        $max = $node->get('@maxValue');
        if ($min === null && $max === null) {
            return null;
        }

        return [
            'min_wanted_level' => $min !== null ? (int) $min : null,
            'max_wanted_level' => $max !== null ? (int) $max : null,
        ];
    }

    public function getReputationRequirements(): array
    {
        $results = [];
        $nodes = $this->getAll('reputationRequirements/SReputationMissionRequirementsParams');

        foreach ($nodes as $node) {
            $results[] = array_filter([
                'faction_reputation' => $node->get('@factionReputation'),
                'reputation_scope' => $node->get('@reputationScope'),
                'min_standing' => $node->get('@minStanding'),
                'max_standing' => $node->get('@maxStanding'),
            ], static fn ($v) => $v !== null);
        }

        return $results;
    }
}
