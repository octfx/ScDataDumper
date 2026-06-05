<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mission;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;

final class MissionBrokerEntry extends RootDocument
{
    public function getTitle(): ?string
    {
        return $this->getString('@title');
    }

    public function getDescription(): ?string
    {
        return $this->getString('@description');
    }

    public function getTitleKey(): ?string
    {
        $value = $this->get('@title', raw: true);

        return is_string($value) && str_starts_with($value, '@') ? ltrim($value, '@') : null;
    }

    public function getDescriptionKey(): ?string
    {
        $value = $this->get('@description', raw: true);

        return is_string($value) && str_starts_with($value, '@') ? ltrim($value, '@') : null;
    }

    public function getMissionGiver(): ?string
    {
        return $this->getString('@missionGiver');
    }

    public function getMissionTypeUuid(): ?string
    {
        return $this->getString('@type');
    }

    public function getMissionGiverRecord(): ?string
    {
        return $this->getString('@missionGiverRecord');
    }

    public function getLocationAvailableUuid(): ?string
    {
        return $this->getString('@locationMissionAvailable');
    }

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
            'min_wanted_level' => $min,
            'max_wanted_level' => $max,
        ];
    }

    /**
     * @return list<array{faction_reputation: ?string, reputation_scope: ?string, comparison: ?string, standing: ?string}>
     */
    public function getReputationRequirements(): array
    {
        $results = [];
        $nodes = $this->getAll(
            'reputationRequirements/SReputationMissionRequirementsParams/expression/SReputationMissionGiverRequirementParams'
        );

        foreach ($nodes as $node) {
            $results[] = [
                'faction_reputation' => $node->get('@factionReputation'),
                'reputation_scope' => $node->get('@reputationScope'),
                'comparison' => $node->get('@comparison'),
                'standing' => $node->get('@standing'),
            ];
        }

        return $results;
    }

    /**
     * @return list<array{missionVariableName: ?string, extendedTextToken: ?string, valueTypeName: ?string, value: mixed, textId: ?string, options: list<array{textId: ?string, value: mixed, weighting: int|float|null}>, organizations: list<string>, tagSearchTerms: list<array{positiveTags: list<string>, negativeTags: list<string>}>, resourceTags: list<string>}>
     */
    public function getProperties(): array
    {
        $results = [];

        foreach ($this->getAll('properties/MissionProperty') as $node) {
            $valueNode = $node->get('value');
            $valueElement = $this->firstElementChild($valueNode);
            $valueTypeName = $valueElement?->nodeName;
            $options = $this->parsePropertyOptions($valueElement);

            $results[] = [
                'missionVariableName' => $node->get('@missionVariableName'),
                'extendedTextToken' => $node->get('@extendedTextToken'),
                'valueTypeName' => $valueTypeName,
                'value' => $this->propertyValue($valueElement, $options),
                'textId' => $options[0]['textId'] ?? $valueElement?->get('@textId'),
                'options' => $options,
                'organizations' => $this->referenceValues($valueElement, 'matchConditions/DataSetMatchCondition_SpecificOrganizationsDef/organizations/Reference@value'),
                'tagSearchTerms' => $this->tagSearchTerms($valueElement),
                'resourceTags' => $this->referenceValues($valueElement, 'resourceTags/Reference@value'),
            ];
        }

        return $results;
    }

    public function getPropertyByToken(string $token): ?array
    {
        return array_find($this->getProperties(), fn ($property) => $property['extendedTextToken'] === $token);
    }

    /**
     * @return list<array{missionVariableName: ?string, extendedTextToken: ?string, valueTypeName: ?string, value: mixed, textId: ?string, options: list<array{textId: ?string, value: mixed, weighting: int|float|null}>, organizations: list<string>, tagSearchTerms: list<array{positiveTags: list<string>, negativeTags: list<string>}>, resourceTags: list<string>}>
     */
    public function getLocationProperties(): array
    {
        return array_values(array_filter(
            $this->getProperties(),
            static fn (array $property): bool => in_array($property['valueTypeName'], [
                'MissionPropertyValue_Location',
                'MissionPropertyValue_Locations',
            ], true)
        ));
    }

    /**
     * @return list<array{type: string, resource: ?string, maxContainerSize: int, minSCU: int, maxSCU: int, minAmount: int, maxAmount: int}>
     */
    public function getHaulingOrders(): array
    {
        $results = [];
        $nodes = $this->getAll('objectiveTokens/ObjectiveToken/objectiveHandler/ObjectiveHandler_Hauling/haulingOrders/*');

        foreach ($nodes as $node) {
            $results[] = [
                'type' => $node->nodeName,
                'resource' => $node->get('@resource'),
                'maxContainerSize' => (int) ($node->get('@maxContainerSize') ?? -1),
                'minSCU' => (int) ($node->get('@minSCU') ?? 0),
                'maxSCU' => (int) ($node->get('@maxSCU') ?? 0),
                'minAmount' => (int) ($node->get('@minAmount') ?? 0),
                'maxAmount' => (int) ($node->get('@maxAmount') ?? 0),
            ];
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function getCompletionTagUuids(): array
    {
        return $this->referenceValues($this, 'completionTags/tags/Reference@value');
    }

    /**
     * @return list<string>
     */
    public function getRequiredMissionUuids(): array
    {
        return $this->referenceValues($this, 'requiredMissions/Reference@value');
    }

    public function canBeShared(): bool
    {
        return $this->getBool('@canBeShared');
    }

    public function isLawful(): bool
    {
        return $this->getBool('@lawfulMission');
    }

    public function isOnceOnly(): bool
    {
        return $this->getBool('@onceOnly');
    }

    public function isAvailableInPrison(): bool
    {
        return $this->getBool('@availableInPrison');
    }

    public function failIfBecameCriminal(): bool
    {
        return $this->getBool('@failIfBecameCriminal');
    }

    public function isNotForRelease(): bool
    {
        return $this->getBool('@notForRelease');
    }

    public function isWorkInProgress(): bool
    {
        return $this->getBool('@workInProgress');
    }

    public function getInstanceLifeTime(): ?float
    {
        return $this->getFloat('@instanceLifeTime');
    }

    public function getInstanceLifeTimeVariation(): ?float
    {
        return $this->getFloat('@instanceLifeTimeVariation');
    }

    public function getRespawnTime(): ?float
    {
        return $this->getFloat('@respawnTime');
    }

    public function getRespawnTimeVariation(): ?float
    {
        return $this->getFloat('@respawnTimeVariation');
    }

    public function getMaxInstances(): ?int
    {
        return $this->getInt('@maxInstances');
    }

    public function getMaxInstancesPerPlayer(): ?int
    {
        return $this->getInt('@maxInstancesPerPlayer');
    }

    public function getPersonalCooldownTime(): ?float
    {
        return $this->getFloat('@personalCooldownTime');
    }

    public function getPersonalCooldownTimeVariation(): ?float
    {
        return $this->getFloat('@personalCooldownTimeVariation');
    }

    public function getAbandonedCooldownTime(): ?float
    {
        return $this->getFloat('@abandonedCooldownTime');
    }

    public function getAbandonedCooldownTimeVariation(): ?float
    {
        return $this->getFloat('@abandonedCooldownTimeVariation');
    }

    public function canReacceptAfterAbandoning(): bool
    {
        return $this->getBool('@canReacceptAfterAbandoning');
    }

    public function canReacceptAfterFailing(): bool
    {
        return $this->getBool('@canReacceptAfterFailing');
    }

    public function getMaxPlayersPerInstance(): ?int
    {
        return $this->getInt('@maxPlayersPerInstance');
    }

    private function firstElementChild(mixed $node): ?Element
    {
        if (! $node instanceof Element) {
            return null;
        }

        foreach ($node->children() as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<array{textId: ?string, value: mixed, weighting: int|float|null}>
     */
    private function parsePropertyOptions(?Element $valueElement): array
    {
        if ($valueElement === null) {
            return [];
        }

        $options = [];

        foreach ($valueElement->getAll('options/*') as $option) {
            $options[] = [
                'textId' => $option->get('@textId'),
                'value' => $option->get('@value'),
                'weighting' => $option->get('@weighting'),
            ];
        }

        return $options;
    }

    private function propertyValue(?Element $valueElement, array $options): mixed
    {
        if ($valueElement === null) {
            return null;
        }

        $directValue = $valueElement->get('@value');

        if ($directValue !== null) {
            return $directValue;
        }

        $debugOrg = $valueElement->get('@debugForceChosenOrganization');

        if ($debugOrg !== null) {
            return $debugOrg;
        }

        if ($options === []) {
            return null;
        }

        $values = [];

        foreach ($options as $option) {
            $values[] = $option['value'] ?? $option['textId'];
        }

        $values = array_values(array_filter($values, static fn ($value): bool => $value !== null && $value !== ''));

        return count($values) === 1 ? $values[0] : $values;
    }

    /**
     * @return list<array{positiveTags: list<string>, negativeTags: list<string>}>
     */
    private function tagSearchTerms(?Element $valueElement): array
    {
        if ($valueElement === null) {
            return [];
        }

        $results = [];

        foreach ($valueElement->getAll('matchConditions/DataSetMatchCondition_TagSearch/tagSearch/TagSearchTerm') as $term) {
            $results[] = [
                'positiveTags' => $this->referenceValues($term, 'positiveTags/Reference@value'),
                'negativeTags' => $this->referenceValues($term, 'negativeTags/Reference@value'),
            ];
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function referenceValues(?object $node, string $path): array
    {
        if ($node === null) {
            return [];
        }

        $values = [];

        foreach ($node->getAll($path, raw: true) as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
