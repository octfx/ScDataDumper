<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ServiceFactory;

trait FormatsMissionBrokerEntries
{
    protected function translateMbeText(?string $value): string
    {
        return $value !== null ? $this->translateLocalizationValue($value) : '';
    }

    protected function resolveMbeMissionTypeInfo(?string $uuid): ?array
    {
        if ($uuid === null) {
            return null;
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $typeRecord = $lookup->getMissionTypeByReference($uuid);

        return [
            'uuid' => $uuid,
            'name' => $typeRecord !== null
                ? ServiceFactory::getLocalizationService()->translateValue($typeRecord->getStringValue('@LocalisedTypeName'), true)
                : null,
        ];
    }

    protected function resolveMbeMissionGiver(MissionBrokerEntry $mbe): ?string
    {
        $missionGiver = $this->translateMbeText($mbe->getMissionGiver());

        if ($missionGiver !== '' && ! str_contains($missionGiver, '~mission(')) {
            return $missionGiver;
        }

        $recordUuid = $mbe->getMissionGiverRecord();

        if ($recordUuid === null) {
            return null;
        }

        $record = ServiceFactory::getFoundryLookupService()->getMissionGiverByReference($recordUuid);

        if ($record === null) {
            return $recordUuid;
        }

        foreach (['@displayName', '@DisplayName', '@name', '@Name', '@LocalisedName'] as $path) {
            $raw = $record->getStringValue($path);

            if ($raw === null || $raw === '@LOC_UNINITIALIZED' || $raw === '@LOC_EMPTY') {
                continue;
            }

            $translated = ServiceFactory::getLocalizationService()->translateValue($raw, true);

            if ($translated !== null && $translated !== '' && ! str_contains($translated, 'UNINITIALIZED')) {
                return $translated;
            }
        }

        return $record->getClassName();
    }

    protected function buildMbeLocationPools(MissionBrokerEntry $mbe): array
    {
        $pools = [];
        $tagService = ServiceFactory::getTagDatabaseService();
        $resolver = ServiceFactory::getContractLocationResolver();

        foreach ($mbe->getLocationProperties() as $property) {
            $key = $property['missionVariableName'] ?? $property['extendedTextToken'] ?? 'default';
            $terms = $property['tagSearchTerms'];

            if ($terms === []) {
                continue;
            }

            $resourceTags = $property['resourceTags'];

            $resolvedLocations = array_map(
                static fn (array $loc): array => [
                    'uuid' => $loc['uuid'],
                    'location_template_uuid' => $loc['location_template_uuid'],
                    'name' => $loc['name'],
                ],
                $resolver->resolveLocations($terms, $resourceTags),
            );

            $purpose = $property['extendedTextToken'];

            if ($purpose === 'location') {
                $purpose = 'Location';
            }

            $pool = [
                'purpose' => $purpose,
                'resolved_locations' => $resolvedLocations,
            ];

            if ($resourceTags !== []) {
                $pool['resource_tags'] = $tagService->resolveUuidsToNameObjects($resourceTags);
            }

            $pools[$key] = $pool;
        }

        return $pools;
    }

    protected function buildMbeHaulingOrders(MissionBrokerEntry $mbe): array
    {
        $orders = [];
        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        foreach ($mbe->getHaulingOrders() as $order) {
            $uuid = $order['resource'];
            $name = null;

            if ($uuid !== null) {
                $resource = $lookup->getResourceTypeByReference($uuid);
                $name = $resource !== null
                    ? $localization->translateValue($resource->getDisplayName(), true)
                    : null;
            }

            $orders[] = [
                'kind' => $order['type'] === 'HaulingOrder_Resource' ? 'Resource' : $order['type'],
                'uuid' => $uuid,
                'name' => $name,
                'min_amount' => $order['minAmount'],
                'max_amount' => $order['maxAmount'],
                'max_container_size' => $order['maxContainerSize'],
                'min_scu' => $order['minSCU'],
                'max_scu' => $order['maxSCU'],
            ];
        }

        return $orders;
    }

    protected function buildMbeMissionTokens(MissionBrokerEntry $mbe, string $title, string $description, array $locationPools): ?array
    {
        $text = $title.' '.$description;

        if (! preg_match_all('/~mission\(([^)]+)\)/', $text, $matches)) {
            return null;
        }

        $requestedTokens = [];
        $locationRequestedTokens = [];

        foreach ($matches[1] as $tokenContent) {
            $requestedTokens[$this->missionTokenOutputKey($mbe, $tokenContent)] = $tokenContent;
            $locationRequestedTokens[$this->missionTokenKey($tokenContent)] = true;
        }

        $tokenMap = $this->resolveLocationBasedTokens($locationPools, $locationRequestedTokens);

        foreach ($requestedTokens as $outputKey => $tokenContent) {
            $token = $this->missionTokenKey($tokenContent);

            if (isset($tokenMap[$outputKey]) || isset($tokenMap[$token])) {
                continue;
            }

            $resolved = $this->resolveMbePropertyTokenValues($mbe, $tokenContent);

            if ($resolved !== null && $resolved !== []) {
                $tokenMap[$outputKey] = $resolved;
            }
        }

        foreach ($mbe->getProperties() as $property) {
            $token = $property['extendedTextToken'];

            if ($token === null || isset($tokenMap[$token])) {
                continue;
            }

            $resolved = $this->resolveMbePropertyTokenValues($mbe, $token);

            if ($resolved !== null && $resolved !== []) {
                $tokenMap[$token] = $resolved;
            }
        }

        $tokenMap = collect($tokenMap)
            ->mapWithKeys(fn ($value, $key) => [$key => collect((array) $value)->unique()->values()])
            ->toArray();

        return $tokenMap !== [] ? $tokenMap : null;
    }

    protected function buildDisplayTextFromMissionTokens(string $text, ?array $missionTokens): ?string
    {
        if ($missionTokens === null || ! str_contains($text, '~mission(')) {
            return null;
        }

        $displayText = $text;
        $replaced = false;

        for ($i = 0; $i < 5 && str_contains($displayText, '~mission('); $i++) {
            $passReplaced = false;
            $next = preg_replace_callback(
                '/~mission\(([^)]+)\)/',
                function (array $matches) use ($missionTokens, &$passReplaced): string {
                    $tokenContent = $matches[1];
                    $token = $this->missionTokenKey($tokenContent);
                    $replacement = $this->missionTokenVariant($tokenContent) !== null
                        ? $this->singleMissionTokenValue($missionTokens[$tokenContent] ?? null)
                        : $this->singleMissionTokenValue($missionTokens[$token] ?? null);

                    if ($replacement === null) {
                        return $matches[0];
                    }

                    $passReplaced = true;

                    return $replacement;
                },
                $displayText,
            );

            if (! $passReplaced || $next === null || $next === $displayText) {
                break;
            }

            $displayText = $next;
            $replaced = true;
        }

        return $replaced && $displayText !== $text ? $displayText : null;
    }

    protected function missionTokenKey(string $tokenContent): string
    {
        return explode('|', $tokenContent, 2)[0];
    }

    protected function missionTokenVariant(string $tokenContent): ?string
    {
        $parts = explode('|', $tokenContent, 2);

        return isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
    }

    protected function missionTokenOutputKey(MissionBrokerEntry $mbe, string $tokenContent): string
    {
        $token = $this->missionTokenKey($tokenContent);
        $variant = $this->missionTokenVariant($tokenContent);
        $property = $mbe->getPropertyByToken($token);

        return $variant !== null && ($property['valueTypeName'] ?? null) === 'MissionPropertyValue_Organization'
            ? $tokenContent
            : $token;
    }

    /**
     * @param  mixed  $value
     */
    protected function singleMissionTokenValue(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $values = [];

        foreach ($value as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                $values[$candidate] = true;
            }
        }

        if (count($values) !== 1) {
            return null;
        }

        return array_key_first($values);
    }

    /**
     * Resolve location-based mission tokens (Location, Destination, GoToLocation, Address)
     * from location pools. Shared between Contract and MissionBroker token resolution.
     *
     * @param  array<string, true>  $requestedTokens  Token names that appeared in the text.
     * @return array<string, array>  Resolved token values keyed by token name.
     */
    private function resolveLocationBasedTokens(array $locationPools, array $requestedTokens): array
    {
        $purposeToNames = [];

        foreach ($locationPools as $pool) {
            $purpose = $pool['purpose'] ?? null;

            if ($purpose === null) {
                continue;
            }

            $names = [];

            foreach ($pool['resolved_locations'] ?? [] as $loc) {
                if (($loc['name'] ?? null) !== null && $loc['name'] !== '') {
                    $names[] = $loc['name'];
                }
            }

            if ($names !== []) {
                $purposeToNames[$purpose] = $names;
            }
        }

        $tokenMap = [];

        foreach (array_keys($requestedTokens) as $token) {
            $resolved = match ($token) {
                'Location' => $purposeToNames['Location'] ?? null,
                'Destination' => $purposeToNames['Destination'] ?? null,
                'GoToLocation' => $purposeToNames['GoToLocation'] ?? null,
                'Address' => $purposeToNames['Location'] ?? $purposeToNames['Destination'] ?? null,
                default => null,
            };

            if ($resolved !== null && $resolved !== []) {
                $tokenMap[$token] = $resolved;
            }
        }

        return $tokenMap;
    }

    protected function resolveMbePropertyTokenValues(MissionBrokerEntry $mbe, string $tokenContent): ?array
    {
        $token = $this->missionTokenKey($tokenContent);
        $property = $mbe->getPropertyByToken($token);

        if ($property === null) {
            return null;
        }

        if (($property['valueTypeName'] ?? null) === 'MissionPropertyValue_Organization') {
            return $this->resolveMbeOrganizationTokenValues($property, $this->missionTokenVariant($tokenContent));
        }

        $values = [];

        foreach ($property['options'] as $option) {
            $raw = $option['textId'] ?? $option['value'] ?? null;

            if (! is_string($raw) || $raw === '' || $raw === '@LOC_UNINITIALIZED' || $raw === '@LOC_EMPTY') {
                continue;
            }

            $translated = str_starts_with($raw, '@')
                ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                : $raw;

            if ($translated !== null && $translated !== '') {
                $values[] = $translated;
            }
        }

        return $values !== [] ? $values : null;
    }

    protected function resolveMbeOrganizationTokenValues(array $property, ?string $variant): ?array
    {
        $organizationUuids = $property['organizations'];

        if ($organizationUuids === [] && is_string($property['value'])) {
            $organizationUuids = [$property['value']];
        }

        if ($organizationUuids === []) {
            return null;
        }

        $variantTagUuid = $variant !== null ? $this->resolveTagUuidByName($variant) : null;
        if ($variant !== null && $variantTagUuid === null) {
            return null;
        }

        $values = [];

        foreach ($organizationUuids as $organizationUuid) {
            $organization = ServiceFactory::getFoundryLookupService()->getMissionOrganizationByReference($organizationUuid);

            if ($organization === null) {
                continue;
            }

            if ($variantTagUuid !== null) {
                foreach ($organization->getAll(sprintf('stringVariants/variants/MissionStringVariant[@tag="%s"]@string', $variantTagUuid), raw: true) as $raw) {
                    if (! is_string($raw) || $raw === '') {
                        continue;
                    }

                    $translated = str_starts_with($raw, '@')
                        ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                        : $raw;

                    if ($translated !== null && $translated !== '') {
                        $values[] = $translated;
                    }
                }

                continue;
            }

            $raw = $organization->getStringValue('stringVariants/variants/MissionStringVariant@string');

            if ($raw !== null && $raw !== '') {
                $translated = str_starts_with($raw, '@')
                    ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                    : $raw;

                if ($translated !== null && $translated !== '') {
                    $values[] = $translated;
                }
            }
        }

        return $values !== [] ? $values : null;
    }

    protected function resolveTagUuidByName(string $name): ?string
    {
        return ServiceFactory::getTagDatabaseService()->getTagUuidByName($name);
    }

    /**
     * @param  iterable<array{factionReputation: ?string, reputationScope: ?string, reward: ?string}>  $rewards
     * @return array{gained: list<array>, lost: list<array>}
     */
    protected function collectMbeReputationRewards(FoundryLookupService $lookup, LocalizationService $localization, iterable $rewards): array
    {
        $gained = [];
        $lost = [];

        foreach ($rewards as $rep) {
            $resolved = $this->resolveMbeReputationEntry(
                $lookup,
                $localization,
                $rep['factionReputation'],
                $rep['reputationScope'],
            );

            $amount = null;
            $tier = null;

            if ($rep['reward'] !== null) {
                $rewardRecord = $lookup->getReputationRewardByReference($rep['reward']);
                if ($rewardRecord !== null) {
                    $amount = $rewardRecord->getIntValue('@reputationAmount');
                    $tier = $rewardRecord->getStringValue('@editorName');
                }
            }

            $entry = array_filter([
                ...$resolved,
                'amount' => $amount,
                'tier' => $tier,
            ], static fn ($v) => $v !== null);

            if ($entry !== []) {
                if ($amount !== null && $amount < 0) {
                    $lost[] = $entry;
                } else {
                    $gained[] = $entry;
                }
            }
        }

        return ['gained' => $gained, 'lost' => $lost];
    }

    protected function buildMbeRewardFields(MissionBrokerEntry $mbe): array
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $rep = $this->collectMbeReputationRewards($lookup, ServiceFactory::getLocalizationService(), $mbe->getReputationRewards());
        $buyIn = $mbe->getBuyInAmount();

        return array_filter([
            'fixed_reward' => $mbe->getReward(),
            'cost' => $buyIn > 0 ? $buyIn : null,
            'reputation_gained' => $rep['gained'] !== [] ? $rep['gained'] : null,
            'reputation_lost' => $rep['lost'] !== [] ? $rep['lost'] : null,
            'refund_buy_in_on_withdraw' => $mbe->shouldRefundBuyInOnWithdraw() ?: null,
            'deadline' => $mbe->getDeadline(),
            'broker_reputation_prerequisites' => $mbe->getReputationPrerequisites(),
            'broker_reputation_requirements' => ($reqs = $mbe->getReputationRequirements()) !== [] ? $reqs : null,
        ], static fn ($v) => $v !== null);
    }

    protected function resolveMbeStanding(FoundryLookupService $lookup, ?string $ref): ?array
    {
        if ($ref === null) {
            return null;
        }

        $standing = $lookup->getReputationStandingByReference($ref);

        return $standing !== null ? [
            'name' => $this->translateLocalizationValue($standing->getDisplayName() ?? $standing->getName()),
            'min_reputation' => $standing->getMinReputation(),
        ] : null;
    }

    protected function buildMbeStandingRequirements(MissionBrokerEntry $mbe): array
    {
        $requirements = $mbe->getReputationRequirements();
        $primary = $requirements[0] ?? null;

        if ($primary === null) {
            return ['min' => null, 'max' => null, 'rank_index' => null];
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $standing = $this->resolveMbeStanding($lookup, $primary['standing']);
        $comparison = $primary['comparison'];

        return [
            'min' => in_array($comparison, ['GreaterThanOrEqualTo', 'GreaterThan', 'EqualTo'], true) ? $standing : null,
            'max' => in_array($comparison, ['LessThanOrEqualTo', 'EqualTo'], true) ? $standing : null,
            'rank_index' => in_array($comparison, ['GreaterThanOrEqualTo', 'GreaterThan', 'EqualTo'], true)
                ? $this->computeMbeRankIndex($lookup, $primary['reputation_scope'], $primary['standing'])
                : null,
        ];
    }

    protected function computeMbeRankIndex(FoundryLookupService $lookup, ?string $scopeUuid, ?string $standingUuid): ?int
    {
        if ($scopeUuid === null || $standingUuid === null) {
            return null;
        }

        $scope = $lookup->getReputationScopeByReference($scopeUuid);

        if ($scope === null) {
            return null;
        }

        $refs = $scope->getStandingReferences();
        $position = array_search(strtolower($standingUuid), array_map('strtolower', $refs), true);

        if ($position === false) {
            return null;
        }

        $firstStanding = $lookup->getReputationStandingByReference($refs[0] ?? null);
        $offset = ($firstStanding !== null && ($firstStanding->getMinReputation() ?? 0) < 0) ? 1 : 0;
        $adjusted = $position - $offset;

        return $adjusted >= 0 ? $adjusted : null;
    }

    protected function buildMbeFaction(MissionBrokerEntry $mbe): ?array
    {
        $primary = $mbe->getReputationRequirements()[0] ?? null;

        if ($primary === null) {
            return null;
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        $factionData = null;
        $factionRepUuid = $primary['faction_reputation'];

        if ($factionRepUuid !== null) {
            $faction = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
            $name = $faction !== null
                ? $localization->translateValue($faction->getName(), true)
                : null;

            if ($name === null) {
                $factionRep = $lookup->getFactionReputationByReference($factionRepUuid);
                $name = $factionRep !== null
                    ? $localization->translateValue($factionRep->getDisplayName(), true)
                    : null;
            }

            $factionData = [
                'uuid' => $faction !== null ? $faction->getUuid() : $factionRepUuid,
                'name' => $name,
            ];
        }

        $reputationScope = null;
        $scopeUuid = $primary['reputation_scope'];
        if ($scopeUuid !== null) {
            $scope = $lookup->getReputationScopeByReference($scopeUuid);
            $reputationScope = [
                'uuid' => $scopeUuid,
                'scope_name' => $scope?->getScopeName(),
                'display_name' => $scope !== null ? $localization->translateValue($scope->getDisplayName(), true) : null,
                'reputation_ceiling' => $scope?->getReputationCeiling(),
                'initial_reputation' => $scope?->getInitialReputation(),
            ];
        }

        return array_filter([
            'faction_reputation' => $factionData,
            'reputation_scope' => $reputationScope,
        ], static fn ($v) => $v !== null) ?: null;
    }

    protected function buildMbeAvailabilityLocations(MissionBrokerEntry $mbe): array
    {
        $localityUuid = $mbe->getLocationAvailableUuid();
        if ($localityUuid === null) {
            return [];
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $locality = $lookup->getMissionLocalityByReference($localityUuid);

        if ($locality === null) {
            return [[
                'name' => null,
                'resolved_locations' => [[
                    'uuid' => $localityUuid,
                    'name' => null,
                ]],
            ]];
        }

        $resolvedLocations = [];

        foreach ($locality->getAvailableLocationReferences() as $locationUuid) {
            $smo = $lookup->getStarMapObjectByReference($locationUuid);
            $resolvedLocations[] = [
                'uuid' => $locationUuid,
                'name' => $smo !== null
                    ? $this->translateLocalizationValue($smo->getName())
                    : null,
            ];
        }

        return [[
            'name' => $locality->getClassName(),
            'resolved_locations' => $resolvedLocations,
        ]];
    }

    /**
     * @return array{faction: ?string, faction_uuid: ?string, scope: ?string, scope_uuid: ?string}
     */
    protected function resolveMbeReputationEntry(
        FoundryLookupService $lookup,
        LocalizationService $localization,
        ?string $factionRepUuid,
        ?string $scopeRefUuid,
    ): array {
        $faction = null;
        $factionUuid = null;

        if ($factionRepUuid !== null) {
            $factionRecord = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
            $faction = $factionRecord !== null
                ? $localization->translateValue($factionRecord->getName(), true)
                : null;
            $factionUuid = $factionRecord !== null ? $factionRecord->getUuid() : $factionRepUuid;
        }

        $scope = null;
        $resolvedScopeUuid = null;

        if ($scopeRefUuid !== null) {
            $scopeRecord = $lookup->getReputationScopeByReference($scopeRefUuid);
            $scope = $scopeRecord?->getScopeName();
            $resolvedScopeUuid = $scopeRefUuid;
        }

        return array_filter([
            'faction' => $faction,
            'faction_uuid' => $factionUuid,
            'scope' => $scope,
            'scope_uuid' => $resolvedScopeUuid,
        ], static fn ($v) => $v !== null);
    }
}
