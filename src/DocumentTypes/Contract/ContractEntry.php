<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class ContractEntry extends RootDocument
{
    public function getId(): ?string
    {
        return $this->getString('@id');
    }

    public function getDebugName(): ?string
    {
        return $this->getString('@debugName');
    }

    public function isNotForRelease(): bool
    {
        return $this->getBool('@notForRelease');
    }

    public function isWorkInProgress(): bool
    {
        return $this->getBool('@workInProgress');
    }

    public function getTemplateReference(): ?string
    {
        return $this->getString('@template');
    }

    public function getMissionBrokerEntryReference(): ?string
    {
        return $this->getString('@missionBrokerEntry');
    }

    public function getMinStandingReference(): ?string
    {
        return $this->getString('@minStanding');
    }

    public function getMaxStandingReference(): ?string
    {
        return $this->getString('@maxStanding');
    }

    public function getTitle(): ?string
    {
        return $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Title"]@value');
    }

    public function getDescription(): ?string
    {
        return $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Description"]@value');
    }

    public function getContractor(): ?string
    {
        return $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Contractor"]@value');
    }

    public function isIllegal(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="Illegal"]@value');
    }

    public function isOnceOnly(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="OnceOnly"]@value');
    }

    public function isShareable(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="CanBeShared"]@value');
    }

    public function failIfBecameCriminal(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="FailIfBecameCriminal"]@value');
    }

    public function isHideInMobiGlas(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="HideInMobiGlas"]@value');
    }

    public function getMissionTypeOverride(): ?string
    {
        return $this->getString('paramOverrides@missionTypeOverride');
    }

    /**
     * @return list<array{localityAvailable: ?string}>
     */
    public function getLocalityPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('additionalPrerequisites/ContractPrerequisite_Locality');

        foreach ($nodes as $node) {
            $results[] = ['localityAvailable' => $node->get('@localityAvailable')];
        }

        return $results;
    }

    /**
     * @return list<array{requiredCountValue: int, excludedCountValue: int, requiredTags: list<string>, excludedTags: list<string>}>
     */
    public function getCompletedContractTagPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('additionalPrerequisites/ContractPrerequisite_CompletedContractTags');

        foreach ($nodes as $node) {
            $requiredTags = [];
            $requiredTagNodes = $node->get('requiredCompletedContractTags/tags');
            if ($requiredTagNodes instanceof Element) {
                foreach ($requiredTagNodes->children() as $tagNode) {
                    $val = $tagNode->get('@value') ?? $tagNode->get('@tag');
                    if (is_string($val)) {
                        $requiredTags[] = $val;
                    }
                }
            }

            $excludedTags = [];
            $excludedTagNodes = $node->get('excludedCompletedContractTags/tags');
            if ($excludedTagNodes instanceof Element) {
                foreach ($excludedTagNodes->children() as $tagNode) {
                    $val = $tagNode->get('@value') ?? $tagNode->get('@tag');
                    if (is_string($val)) {
                        $excludedTags[] = $val;
                    }
                }
            }

            $results[] = [
                'requiredCountValue' => (int) ($node->get('@requiredCountValue') ?? 0),
                'excludedCountValue' => (int) ($node->get('@excludedCountValue') ?? 0),
                'requiredTags' => $requiredTags,
                'excludedTags' => $excludedTags,
            ];
        }

        return $results;
    }

    public function getMaxInstances(): ?int
    {
        return $this->getInt('generationParams/ContractGenerationParams_Legacy@maxInstances');
    }

    public function getMaxInstancesPerPlayer(): ?int
    {
        return $this->getInt('generationParams/ContractGenerationParams_Legacy@maxInstancesPerPlayer');
    }

    public function getRespawnTime(): ?float
    {
        return $this->getFloat('generationParams/ContractGenerationParams_Legacy@respawnTime');
    }

    public function getRespawnTimeVariation(): ?float
    {
        return $this->getFloat('generationParams/ContractGenerationParams_Legacy@respawnTimeVariation');
    }

    public function getInstanceLifeTime(): ?float
    {
        return $this->getFloat('contractLifeTime/ContractLifeTime@instanceLifeTime');
    }

    public function getInstanceLifeTimeVariation(): ?float
    {
        return $this->getFloat('contractLifeTime/ContractLifeTime@instanceLifeTimeVariation');
    }

    /**
     * @return list<MissionPropertyOverride>
     */
    public function getPropertyOverrides(): array
    {
        $results = [];
        $nodes = $this->getAll('paramOverrides/propertyOverrides/MissionProperty');

        foreach ($nodes as $node) {
            $doc = MissionPropertyOverride::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());
            if ($doc instanceof MissionPropertyOverride) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    public function excludesOwnCompletionTag(): bool
    {
        $results = $this->getResults();
        if ($results === null) {
            return false;
        }

        $completionTagUuids = $results->getCompletionTags();

        if ($completionTagUuids === []) {
            return false;
        }

        foreach ($this->getCompletedContractTagPrerequisites() as $prereq) {
            if ($prereq['excludedCountValue'] > 0) {
                $overlap = array_intersect($completionTagUuids, $prereq['excludedTags']);
                if ($overlap !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getResults(): ?ContractResultBlock
    {
        $node = $this->get('contractResults');
        if ($node === null) {
            return null;
        }

        $doc = ContractResultBlock::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

        return $doc instanceof ContractResultBlock ? $doc : null;
    }

    public function getMaxPlayersPerInstance(): ?int
    {
        return $this->getInt('paramOverrides/intParamOverrides/ContractIntParam[@param="MaxPlayersPerInstance"]@value');
    }

    public function getAbandonedCooldownTime(): ?float
    {
        return $this->getFloat('paramOverrides/intParamOverrides/ContractIntParam[@param="AbandonedCooldownTime"]@value');
    }

    public function getAbandonedCooldownTimeVariation(): ?float
    {
        return $this->getFloat('paramOverrides/intParamOverrides/ContractIntParam[@param="AbandonedCooldownTimeVariation"]@value');
    }

    public function getPersonalCooldownTime(): ?float
    {
        return $this->getFloat('paramOverrides/intParamOverrides/ContractIntParam[@param="PersonalCooldownTime"]@value');
    }

    public function getPersonalCooldownTimeVariation(): ?float
    {
        return $this->getFloat('paramOverrides/intParamOverrides/ContractIntParam[@param="PersonalCooldownTimeVariation"]@value');
    }

    public function canReacceptAfterAbandoning(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="CanReacceptAfterAbandoning"]@value');
    }

    public function canReacceptAfterFailing(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="CanReacceptAfterFailing"]@value');
    }
}
