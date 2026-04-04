<?php

namespace Octfx\ScDataDumper\DocumentTypes\Faction;

use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationContextUI;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationScopeParams;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationStandingParams;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

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
        $resolved = $this->resolveRelatedDocument(
            'ReputationContextUI',
            SReputationContextUI::class,
            $this->getReputationContextReference(),
            static fn (string $reference): ?SReputationContextUI => ServiceFactory::getFoundryLookupService()
                ->getReputationContextByReference($reference)
        );

        return $resolved instanceof SReputationContextUI ? $resolved : null;
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
        $resolved = $this->resolveRelatedDocument(
            $path,
            SReputationScopeParams::class,
            $this->getString(str_replace('/Scope', '@scope', $path)),
            static fn (string $reference): ?SReputationScopeParams => ServiceFactory::getFoundryLookupService()
                ->getReputationScopeByReference($reference)
        );

        return $resolved instanceof SReputationScopeParams ? $resolved : null;
    }

    private function resolveStanding(string $path): ?SReputationStandingParams
    {
        $resolved = $this->resolveRelatedDocument(
            $path,
            SReputationStandingParams::class,
            $this->getString(str_replace('/Standing', '@standing', $path)),
            static fn (string $reference): ?SReputationStandingParams => ServiceFactory::getFoundryLookupService()
                ->getReputationStandingByReference($reference)
        );

        return $resolved instanceof SReputationStandingParams ? $resolved : null;
    }
}
