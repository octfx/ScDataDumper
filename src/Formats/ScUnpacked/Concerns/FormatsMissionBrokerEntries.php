<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ServiceFactory;

trait FormatsMissionBrokerEntries
{
    /**
     * Location-token aliases ordered by specificity, then fallback.
     *
     * @var array<string, list<string>>
     */
    private const LOCATION_TOKEN_CANDIDATES = [
        'location' => ['Location'],
        'destination' => ['Destination'],
        'gotolocation' => ['GoToLocation'],
        'address' => ['Location', 'Destination'],
        'dropofflocation' => ['DropOffLocation', 'DropoffLocation', 'Destination'],
        'drop1' => ['Drop1', 'Destination'],
        'defendlocationwrapperlocation' => ['DefendLocationWrapperLocation', 'Location'],
        'lagrangelocation' => ['LagrangeLocation', 'Location'],
        'missioncluster' => ['MissionCluster', 'Location'],
        'initiatorlocation' => ['InitiatorLocation', 'Location'],
        'startlocation' => ['StartLocation', 'Destination'],
        'selecteddestination' => ['SelectedDestination', 'Destination'],
    ];

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
        $requestedTokenReferences = $this->collectMissionTokenReferences($title, $description);

        if ($requestedTokenReferences === []) {
            return null;
        }

        $resolvePropertyToken = function (string $tokenContent) use ($mbe): ?array {
            $property = $mbe->getPropertyByToken($this->missionTokenKey($tokenContent));
            $values = $this->resolveMbePropertyTokenValues($mbe, $tokenContent);

            if ($values === null || $values === []) {
                return null;
            }

            return [
                'map_key' => $this->missionTokenMapKey($tokenContent, $property['valueTypeName'] ?? null),
                'values' => $values,
            ];
        };

        $tokenMap = $this->resolveLocationBasedTokens($locationPools, $requestedTokenReferences);

        foreach (array_keys($requestedTokenReferences) as $tokenContent) {
            $token = $this->missionTokenKey($tokenContent);
            $resolved = $resolvePropertyToken($tokenContent);

            if ($resolved === null || isset($tokenMap[$resolved['map_key']]) || isset($tokenMap[$token])) {
                continue;
            }

            $tokenMap[$resolved['map_key']] = $resolved['values'];
        }

        foreach ($mbe->getProperties() as $property) {
            $token = $property['extendedTextToken'];

            if ($token === null) {
                continue;
            }

            $mapKey = $this->missionTokenMapKey($token, $property['valueTypeName'] ?? null);

            if (isset($tokenMap[$mapKey])) {
                continue;
            }

            $resolved = $this->resolveMbePropertyTokenValues($mbe, $token);

            if ($resolved !== null && $resolved !== []) {
                $tokenMap[$mapKey] = $resolved;
            }
        }

        $tokenMap = $this->expandNestedMissionTokens($tokenMap, $locationPools, $resolvePropertyToken);

        $tokenMap = $this->normalizeMissionTokenMap($tokenMap);

        return $tokenMap !== [] ? $tokenMap : null;
    }

    /**
     * @param  array<string, mixed>  $tokenMap
     * @return array<string, list<string>>
     */
    private function normalizeMissionTokenMap(array $tokenMap): array
    {
        $normalized = [];

        foreach ($tokenMap as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }

            $values = $this->missionTokenValues($value);

            if ($values !== []) {
                $normalized[$key] = $values;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, true>
     */
    private function collectMissionTokenReferences(string ...$texts): array
    {
        $references = [];

        foreach ($texts as $text) {
            if (! preg_match_all('/~mission\(([^)]+)\)/', $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $tokenContent) {
                $references[$tokenContent] = true;
            }
        }

        return $references;
    }

    /**
     * Resolve nested ~mission() references found inside already resolved token values.
     *
     * @param  array<string, mixed>  $tokenMap
     * @param  callable(string): ?array{map_key: string, values: array}  $resolvePropertyToken
     * @return array<string, mixed>
     */
    private function expandNestedMissionTokens(array $tokenMap, array $locationPools, callable $resolvePropertyToken): array
    {
        for ($depth = 0; $depth < 3; $depth++) {
            $nestedTokens = $this->collectNestedMissionTokenReferences($tokenMap);

            if ($nestedTokens === []) {
                break;
            }

            foreach ($this->resolveLocationBasedTokens($locationPools, $nestedTokens) as $key => $values) {
                $tokenMap[$key] = $values;
            }

            foreach (array_keys($nestedTokens) as $tokenContent) {
                $token = $this->missionTokenKey($tokenContent);

                if (isset($tokenMap[$tokenContent]) || isset($tokenMap[$token])) {
                    continue;
                }

                $resolved = $resolvePropertyToken($tokenContent);

                if ($resolved === null || $resolved['values'] === []) {
                    continue;
                }

                $tokenMap[$resolved['map_key']] = $resolved['values'];
            }
        }

        return $tokenMap;
    }

    /**
     * @param  array<string, mixed>  $tokenMap
     * @return array<string, true>
     */
    private function collectNestedMissionTokenReferences(array $tokenMap): array
    {
        $references = [];

        foreach ($tokenMap as $values) {
            foreach ((array) $values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                foreach (array_keys($this->collectMissionTokenReferences($value)) as $tokenContent) {
                    $token = $this->missionTokenKey($tokenContent);

                    if (! isset($tokenMap[$tokenContent]) && ! isset($tokenMap[$token])) {
                        $references[$tokenContent] = true;
                    }
                }
            }
        }

        return $references;
    }

    /**
     * Resolve ~mission() references inside token values.
     *
     * @param  array<string, list<string>>  $tokenMap
     * @return array<string, list<string>>
     */
    private function resolveTokenValueReferences(array $tokenMap): array
    {
        $needsResolution = false;

        foreach ($tokenMap as $values) {
            foreach ($values as $v) {
                if (is_string($v) && str_contains($v, '~mission(')) {
                    $needsResolution = true;
                    break 2;
                }
            }
        }

        if (! $needsResolution) {
            return $tokenMap;
        }

        return array_map(function ($values) use ($tokenMap) {
            return array_map(
                fn ($v) => is_string($v) ? $this->resolveTokenValue($v, $tokenMap) : $v,
                $values,
            );
        }, $tokenMap);
    }

    /**
     * Resolve ~mission() references in a single token value string.
     * <EM4> tags are preserved for the API to style.
     */
    private function resolveTokenValue(string $value, array $tokenMap): string
    {
        for ($i = 0; $i < 5 && str_contains($value, '~mission('); $i++) {
            $next = preg_replace_callback(
                '/~mission\(([^)]+)\)/',
                fn (array $matches): string => $this->renderMissionTokenReference($matches[1], $tokenMap),
                $value,
            );

            if ($next === null || $next === $value) {
                break;
            }

            $value = $next;
        }

        return $value;
    }

    protected function buildDisplayTextFromMissionTokens(string $text, ?array $missionTokens): ?string
    {
        if (! str_contains($text, '~mission(')) {
            return null;
        }

        $displayText = $text;
        $replaced = false;

        for ($i = 0; $i < 5 && str_contains($displayText, '~mission('); $i++) {
            $next = preg_replace_callback(
                '/~mission\(([^)]+)\)/',
                fn (array $matches): string => $this->renderMissionTokenReference($matches[1], $missionTokens),
                $displayText,
            );

            if ($next === null || $next === $displayText) {
                break;
            }

            $displayText = $next;
            $replaced = true;
        }

        return $replaced && $displayText !== $text ? $displayText : null;
    }

    private function renderMissionTokenReference(string $tokenContent, ?array $missionTokens): string
    {
        $lookupKey = $this->missionTokenLookupKey($tokenContent, $missionTokens ?? []);
        $values = $missionTokens !== null
            ? $this->missionTokenValues($missionTokens[$lookupKey] ?? null)
            : [];

        return count($values) === 1 ? $values[0] : $this->tokenBracket($lookupKey);
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

    protected function missionTokenMapKey(string $tokenContent, ?string $valueTypeName = null): string
    {
        $variant = $this->missionTokenVariant($tokenContent);

        if ($this->locationTokenCandidates($this->missionTokenKey($tokenContent)) !== null) {
            return $tokenContent;
        }

        if ($variant !== null && $valueTypeName === 'MissionPropertyValue_Organization') {
            return $tokenContent;
        }

        return $this->missionTokenKey($tokenContent);
    }

    /**
     * Return ordered candidate pool purposes for a location token base key, or null if the token is not a location token.
     */
    private function locationTokenCandidates(string $baseKey): ?array
    {
        $normalized = strtolower($baseKey);

        if (preg_match('/^pickup(\d)$/', $normalized, $m)) {
            return ['Pickup'.$m[1], 'Location'];
        }

        if (preg_match('/^dropoff(\d)$/', $normalized, $m)) {
            return ['DropOff'.$m[1], 'Dropoff'.$m[1], 'Destination'];
        }

        if (preg_match('/^location(\d+)$/', $normalized, $m)) {
            return ['Location'.$m[1], 'Location'];
        }

        if (preg_match('/^destination(\d)$/', $normalized, $m)) {
            return ['Destination'.$m[1], 'Destination'];
        }

        return self::LOCATION_TOKEN_CANDIDATES[$normalized] ?? null;
    }

    /**
     * @return list<string>
     */
    private function missionTokenValues(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $candidate = trim((string) $candidate);

            if ($candidate !== '' && ! str_contains($candidate, 'UNINITIALIZED')) {
                $values[$candidate] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * @param  array<string, mixed>  $missionTokens
     */
    private function missionTokenLookupKey(string $tokenContent, array $missionTokens): string
    {
        $token = $this->missionTokenKey($tokenContent);

        if ($this->missionTokenVariant($tokenContent) !== null && array_key_exists($tokenContent, $missionTokens)) {
            return $tokenContent;
        }

        if (array_key_exists($token, $missionTokens)) {
            return $token;
        }

        return $tokenContent;
    }

    private function tokenBracket(string $lookupKey): string
    {
        return '['.$lookupKey.']';
    }

    /**
     * Resolve location-based mission tokens from location pools using unified candidate-based matching.
     *
     * @param  array<string, true>  $requestedTokens  Full token content that appeared in the text.
     * @return array<string, array> Resolved token values keyed by full token content.
     */
    private function resolveLocationBasedTokens(array $locationPools, array $requestedTokens): array
    {
        $purposeToNames = [];

        foreach ($locationPools as $pool) {
            $purpose = $pool['purpose'] ?? null;

            if ($purpose === null) {
                continue;
            }

            $purpose = strtolower((string) $purpose);
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

        $shiftedDestinationAliases = $this->shiftedDestinationAliases($purposeToNames, $requestedTokens);
        $tokenMap = [];

        foreach (array_keys($requestedTokens) as $tokenContent) {
            $baseKey = $this->missionTokenKey($tokenContent);
            $candidates = $this->locationTokenCandidates($baseKey);

            if ($candidates === null) {
                continue;
            }

            $resolved = null;

            foreach ($candidates as $candidate) {
                $purposeKey = strtolower($candidate);
                $resolved = $shiftedDestinationAliases[$purposeKey]
                    ?? $purposeToNames[$purposeKey]
                    ?? null;

                if ($resolved !== null) {
                    break;
                }
            }

            if ($resolved !== null && $resolved !== []) {
                $tokenMap[$tokenContent] = $resolved;
            }
        }

        return $tokenMap;
    }

    /**
     * Some mission texts use Destination/Destination1 while their pools are
     * numbered Destination1/Destination2. When there is no exact unnumbered or
     * zero-indexed destination pool, expose a small shifted lookup for this
     * mission only while preserving the original token keys.
     *
     * @param  array<string, list<string>>  $purposeToNames
     * @param  array<string, true>  $requestedTokens
     * @return array<string, list<string>>
     */
    private function shiftedDestinationAliases(array $purposeToNames, array $requestedTokens): array
    {
        $requestedDestination = false;

        foreach (array_keys($requestedTokens) as $tokenContent) {
            if (strtolower($this->missionTokenKey($tokenContent)) === 'destination') {
                $requestedDestination = true;
                break;
            }
        }

        if (! $requestedDestination
            || isset($purposeToNames['destination'])
            || isset($purposeToNames['destination0'])
            || ! isset($purposeToNames['destination1'])) {
            return [];
        }

        $aliases = [
            'destination' => $purposeToNames['destination1'],
        ];

        for ($i = 1; $i < 9; $i++) {
            $nextKey = 'destination'.($i + 1);

            if (! isset($purposeToNames[$nextKey])) {
                continue;
            }

            $aliases['destination'.$i] = $purposeToNames[$nextKey];
        }

        return $aliases;
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

            if ($faction === null) {
                $factionRep = $lookup->getFactionReputationByReference($factionRepUuid);
                $faction = $factionRep !== null
                    ? $localization->translateValue($factionRep->getDisplayName(), true)
                    : null;
            }

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
