<?php

namespace Octfx\ScDataDumper\DocumentTypes\Faction;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationContextUI;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationScopeParams;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationStandingParams;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class FactionReputation extends RootDocument
{
    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }

    public function getLogo(): ?string
    {
        return $this->getString('@logo');
    }

    public function isNpc(): bool
    {
        return $this->getBool('@isNPC');
    }

    public function isHiddenInDelphiApp(): bool
    {
        return $this->getBool('@hideInDelpihApp');
    }

    public function getReputationContextReference(): ?string
    {
        return $this->getString('@reputationContextPropertiesUI');
    }

    public function getReputationContext(): ?SReputationContextUI
    {
        $contextNode = $this->get('ReputationContextUI');

        if (! $contextNode instanceof Element) {
            return null;
        }

        $context = SReputationContextUI::fromNode($contextNode->getNode());

        return $context instanceof SReputationContextUI ? $context : null;
    }

    public function getHostilityScopeReference(): ?string
    {
        return $this->getString('hostilityParams@scope');
    }

    public function getHostilityStandingReference(): ?string
    {
        return $this->getString('hostilityParams@standing');
    }

    public function getAlliedScopeReference(): ?string
    {
        return $this->getString('alliedParams@scope');
    }

    public function getAlliedStandingReference(): ?string
    {
        return $this->getString('alliedParams@standing');
    }

    public function getHostilityScope(): ?SReputationScopeParams
    {
        return $this->resolveScope('hostilityParams/Scope');
    }

    public function getHostilityStanding(): ?SReputationStandingParams
    {
        return $this->resolveStanding('hostilityParams/Standing');
    }

    public function getAlliedScope(): ?SReputationScopeParams
    {
        return $this->resolveScope('alliedParams/Scope');
    }

    public function getAlliedStanding(): ?SReputationStandingParams
    {
        return $this->resolveStanding('alliedParams/Standing');
    }

    private function resolveScope(string $path): ?SReputationScopeParams
    {
        $scopeNode = $this->get($path);

        if (! $scopeNode instanceof Element) {
            return null;
        }

        $scope = SReputationScopeParams::fromNode($scopeNode->getNode());

        return $scope instanceof SReputationScopeParams ? $scope : null;
    }

    private function resolveStanding(string $path): ?SReputationStandingParams
    {
        $standingNode = $this->get($path);

        if (! $standingNode instanceof Element) {
            return null;
        }

        $standing = SReputationStandingParams::fromNode($standingNode->getNode());

        return $standing instanceof SReputationStandingParams ? $standing : null;
    }
}
