<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Faction\Faction as FactionDocument;
use Octfx\ScDataDumper\DocumentTypes\Faction\FactionReputation;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationContextUI;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationScopeParams;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationStandingParams;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Faction extends BaseFormat
{
    public function toArray(): ?array
    {
        if ($this->item instanceof FactionDocument) {
            return $this->fromFactionDocument();
        }

        if ($this->item instanceof FactionReputation) {
            return $this->fromOrphanReputation();
        }

        return null;
    }

    private function fromFactionDocument(): ?array
    {
        return $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $this->item->getUuid(),
            'name' => ServiceFactory::getLocalizationService()->translateValue($this->item->getName(), true)
                ?? ServiceFactory::getLocalizationService()->translateValue($this->item->getFactionReputation()?->getDisplayName(), true),
            'description' => ServiceFactory::getLocalizationService()->translateValue($this->item->getDescription(), true),
            'defaultReaction' => $this->item->getDefaultReaction(),
            'factionType' => $this->item->getFactionType(),
            'ableToArrest' => $this->item->getAbleToArrest(),
            'policesLawfulTrespass' => $this->item->getPolicesLawfulTrespass(),
            'policesCriminality' => $this->item->getPolicesCriminality(),
            'noLegalRights' => $this->item->getNoLegalRight(),
            'reputation' => $this->buildReputation($this->item->getFactionReputation()),
        ]));
    }

    private function fromOrphanReputation(): ?array
    {
        $reputation = $this->item;
        assert($reputation instanceof FactionReputation);

        $localization = ServiceFactory::getLocalizationService();

        $name = $localization->translateValue($reputation->getDisplayName(), true);
        $properties = $this->buildProperties($reputation);
        $factionType = $this->resolveFactionType($reputation, $properties);

        return $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $reputation->getUuid(),
            'name' => $name,
            'description' => $properties['description'] ?? null,
            'defaultReaction' => 'Neutral',
            'factionType' => $factionType,
            'ableToArrest' => false,
            'policesLawfulTrespass' => false,
            'policesCriminality' => false,
            'noLegalRights' => ($properties['lawful'] ?? true) === false,
            'reputation' => $this->buildReputation($reputation),
        ]));
    }

    /**
     * @param  array<string, bool|string>  $properties
     */
    private function resolveFactionType(FactionReputation $reputation, array $properties): ?string
    {
        if (isset($properties['lawful']) && is_bool($properties['lawful'])) {
            return $properties['lawful'] ? 'Lawful' : 'Unlawful';
        }

        $className = $reputation->getClassName();

        if (str_contains($className, '_Unlawful_')) {
            return 'Unlawful';
        }

        return str_contains($className, '_Lawful_') ? 'Lawful' : null;
    }

    private function buildReputation(?FactionReputation $reputation): ?array
    {
        if ($reputation === null) {
            return null;
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $reputation->getUuid(),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($reputation->getDisplayName(), true),
            'isNpc' => $reputation->isNpc(),
            'hideInDelphiApp' => $reputation->isHiddenInDelphiApp(),
            'context' => $this->buildContext($reputation->getReputationContext(), $reputation->getAlliedScopeReference()),
            'hostility' => $this->buildEdge(
                $reputation->getHostilityScopeReference(),
                $reputation->getHostilityStandingReference(),
                $reputation->getHostilityScope(),
                $reputation->getHostilityStanding(),
                ServiceFactory::getLocalizationService()->translateValue($reputation->get('hostilityParams/markerParams@description'), true)
            ),
            'allied' => $this->buildEdge(
                $reputation->getAlliedScopeReference(),
                $reputation->getAlliedStandingReference(),
                $reputation->getAlliedScope(),
                $reputation->getAlliedStanding(),
                ServiceFactory::getLocalizationService()->translateValue($reputation->get('alliedParams/markerParams@description'), true)
            ),
            'properties' => $this->buildProperties($reputation),
        ]);
    }

    private function buildContext(?SReputationContextUI $context, ?string $alliedScopeUuid): ?array
    {
        if ($context === null) {
            return null;
        }

        $scopes = $context->getLadderScopes($alliedScopeUuid);

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $context->getUuid(),
            'sortOrderScope' => $context->getSortOrderScope(),
            'primaryScope' => $scopes !== [] ? $this->buildScope($scopes[0]) : null,
            'additionalScopes' => array_slice($scopes, 1)
                    |> (fn ($x) => array_map(fn (SReputationScopeParams $scope): array => $this->buildScope($scope), $x))
                    |> array_values(...),
        ]);
    }

    private function buildScope(?SReputationScopeParams $scope): ?array
    {
        if ($scope === null) {
            return null;
        }

        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $scope->getUuid(),
            'scopeName' => $scope->getScopeName(),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($scope->getDisplayName(), true),
            'description' => ServiceFactory::getLocalizationService()->translateValue($scope->getDescription(), true),
            'reputationCeiling' => $scope->getReputationCeiling(),
            'initialReputation' => $scope->getInitialReputation(),
            'standings' => array_values(array_map(
                fn (SReputationStandingParams $standing): array => $this->buildStanding($standing),
                $scope->getStandings()
            )),
        ]);
    }

    /**
     * @return array{uuid: string, name: ?string, displayName: ?string, description: ?string, perkDescription: ?string, minReputation: ?int, driftReputation: ?int, driftTimeHours: ?int, gated: bool}
     */
    private function buildStanding(SReputationStandingParams $standing): array
    {
        return $this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $standing->getUuid(),
            'name' => $standing->getName(),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($standing->getDisplayName(), true),
            'description' => $this->translateStandingDescription($standing->getDescription()),
            'perkDescription' => ServiceFactory::getLocalizationService()->translateValue($standing->getPerkDescription(), true),
            'minReputation' => $standing->getMinReputation(),
            'driftReputation' => $standing->getDriftReputation(),
            'driftTimeHours' => $standing->getDriftTimeHours(),
            'gated' => $standing->isGated(),
        ]);
    }

    private function buildEdge(
        ?string $scopeUuid,
        ?string $standingUuid,
        ?SReputationScopeParams $scope,
        ?SReputationStandingParams $standing,
        ?string $markerDescription,
    ): ?array {
        return $this->removeNullValuesPreservingEmptyArrays([
            'scopeUuid' => $scopeUuid,
            'standingUuid' => $standingUuid,
            'scope' => $this->buildScope($scope),
            'standing' => $standing !== null ? $this->buildStanding($standing) : null,
            'markerDescription' => $markerDescription,
        ]);
    }

    /**
     * @return array<string, bool|string>
     */
    private function buildProperties(FactionReputation $reputation): array
    {
        $properties = [];

        foreach ($reputation->getAll('propertiesBB/SReputationContextBBPropertyParams') as $propertyNode) {
            if (! $propertyNode instanceof Element) {
                continue;
            }

            $name = $propertyNode->get('@name');
            if (! is_string($name) || $name === '') {
                continue;
            }

            $normalizedName = $this->normalizePropertyName($name);
            if ($normalizedName === null) {
                continue;
            }

            $value = $this->resolvePropertyValue($propertyNode);
            if ($value === null) {
                continue;
            }

            $properties[$normalizedName] = $value;
        }

        return $properties;
    }

    private function normalizePropertyName(string $name): ?string
    {
        if (! str_starts_with($name, 'entity')) {
            return null;
        }

        $trimmed = substr($name, strlen('entity'));
        if ($trimmed === '') {
            return null;
        }

        return lcfirst($trimmed);
    }

    private function resolvePropertyValue(Element $propertyNode): string|bool|null
    {
        $locString = $propertyNode->get('dynamicProperty/SBBDynamicPropertyLocString@value');
        if (is_string($locString) && $locString !== '') {
            return ServiceFactory::getLocalizationService()->translateValue($locString, true) ?? $locString;
        }

        $boolValue = $propertyNode->get('dynamicProperty/SBBDynamicPropertyBool@value');
        if (is_numeric($boolValue)) {
            return (int) $boolValue === 1;
        }

        return null;
    }

    private function translateStandingDescription(mixed $value): ?string
    {
        if ($value === 'desc') {
            return null;
        }

        return ServiceFactory::getLocalizationService()->translateValue($value, true);
    }
}
