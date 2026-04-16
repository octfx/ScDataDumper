<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class ContractHandler extends RootDocument
{
    public function getHandlerType(): string
    {
        return $this->documentElement->nodeName;
    }

    public function getDebugName(): ?string
    {
        return $this->getString('@debugName');
    }

    public function isNotForRelease(): bool
    {
        return $this->getBool('@notForRelease');
    }

    public function getFactionReputationReference(): ?string
    {
        return $this->getString('@factionReputation');
    }

    public function getReputationScopeReference(): ?string
    {
        return $this->getString('@reputationScope');
    }

    public function hasEscapedConvicts(): bool
    {
        return $this->getBool('@escapedConvicts');
    }

    public function isOnceOnly(): bool
    {
        return $this->getBool('defaultAvailability@onceOnly');
    }

    public function getMaxPlayersPerInstance(): ?int
    {
        return $this->getInt('defaultAvailability@maxPlayersPerInstance');
    }

    public function isAvailableInPrison(): bool
    {
        return $this->getBool('defaultAvailability@availableInPrison');
    }

    public function canReacceptAfterAbandoning(): bool
    {
        return $this->getBool('defaultAvailability@canReacceptAfterAbandoning');
    }

    public function getAbandonedCooldownTime(): ?float
    {
        return $this->getFloat('defaultAvailability@abandonedCooldownTime');
    }

    public function getAbandonedCooldownTimeVariation(): ?float
    {
        return $this->getFloat('defaultAvailability@abandonedCooldownTimeVariation');
    }

    public function canReacceptAfterFailing(): bool
    {
        return $this->getBool('defaultAvailability@canReacceptAfterFailing');
    }

    public function hasPersonalCooldown(): bool
    {
        return $this->getBool('defaultAvailability@hasPersonalCooldown');
    }

    public function getPersonalCooldownTime(): ?float
    {
        return $this->getFloat('defaultAvailability@personalCooldownTime');
    }

    public function getPersonalCooldownTimeVariation(): ?float
    {
        return $this->getFloat('defaultAvailability@personalCooldownTimeVariation');
    }

    public function isHideInMobiGlas(): bool
    {
        return $this->getBool('defaultAvailability@hideInMobiGlas');
    }

    public function notifyOnAvailable(): bool
    {
        return $this->getBool('defaultAvailability@notifyOnAvailable');
    }

    /**
     * @return list<array{type: string, locationAvailable: ?string, localityAvailable: ?string, minCrimeStat: ?int, maxCrimeStat: ?int, factionReputation: ?string, scope: ?string, minStanding: ?string, maxStanding: ?string, propertyVariableName: ?string, locationLevelType: ?string}>
     */
    public function getDefaultPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('defaultAvailability/prerequisites/*');

        foreach ($nodes as $node) {
            $type = $node->nodeName;

            $results[] = [
                'type' => $type,
                'locationAvailable' => $node->get('@locationAvailable'),
                'localityAvailable' => $node->get('@localityAvailable'),
                'minCrimeStat' => $node->get('@minCrimeStat') !== null ? (int) $node->get('@minCrimeStat') : null,
                'maxCrimeStat' => $node->get('@maxCrimeStat') !== null ? (int) $node->get('@maxCrimeStat') : null,
                'factionReputation' => $node->get('@factionReputation'),
                'scope' => $node->get('@scope'),
                'minStanding' => $node->get('@minStanding'),
                'maxStanding' => $node->get('@maxStanding'),
                'propertyVariableName' => $node->get('@propertyVariableName'),
                'locationLevelType' => $node->get('@locationLevelType'),
            ];
        }

        return $results;
    }

    public function getContractor(): ?string
    {
        return $this->getString('contractParams/stringParamOverrides/ContractStringParam[@param="Contractor"]@value');
    }

    public function isShareable(): bool
    {
        return $this->getBool('contractParams/boolParamOverrides/ContractBoolParam[@param="CanBeShared"]@value');
    }

    public function getMissionTypeOverride(): ?string
    {
        return $this->getString('contractParams@missionTypeOverride');
    }

    /**
     * @return list<MissionPropertyOverride>
     */
    public function getContractParamPropertyOverrides(): array
    {
        $results = [];
        $nodes = $this->getAll('contractParams/propertyOverrides/MissionProperty');

        foreach ($nodes as $node) {
            $doc = MissionPropertyOverride::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());
            if ($doc instanceof MissionPropertyOverride) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    /**
     * @return list<ContractEntry>
     */
    public function getContracts(): array
    {
        return $this->extractContractEntries('contracts');
    }

    /**
     * @return list<ContractEntry>
     */
    public function getIntroContracts(): array
    {
        return $this->extractContractEntries('introContracts');
    }

    /**
     * @return list<ContractEntry>
     */
    public function getLegacyContracts(): array
    {
        return $this->extractContractEntries('legacyContracts');
    }

    /**
     * @return list<ContractEntry>
     */
    public function getPVPBountyContracts(): array
    {
        return $this->extractContractEntries('PVPBountyContract');
    }

    /**
     * @return list<ContractEntry>
     */
    private function extractContractEntries(string $containerPath): array
    {
        $container = $this->get($containerPath);
        if ($container === null) {
            return [];
        }

        $entries = [];

        foreach ($container->children() as $child) {
            $doc = ContractEntry::fromNode($child->getNode(), $this->isReferenceHydrationEnabled());
            if ($doc instanceof ContractEntry) {
                $entries[] = $doc;
            }
        }

        return $entries;
    }
}
