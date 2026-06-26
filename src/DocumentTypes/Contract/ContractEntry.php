<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class ContractEntry extends RootDocument
{
    /** @var array{0: ?string, 1: ?string, 2: ?string}|null Cached template display strings [title, description, contractor], null = not yet resolved */
    private ?array $templateDisplayStrings = null;

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
        $value = $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Title"]@value');
        if ($value !== null) {
            return $value;
        }

        return $this->resolveTemplateDisplayStrings()[0];
    }

    public function getDescription(): ?string
    {
        $value = $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Description"]@value');
        if ($value !== null) {
            return $value;
        }

        return $this->resolveTemplateDisplayStrings()[1];
    }

    public function getTitleKey(): ?string
    {
        $value = $this->get('paramOverrides/stringParamOverrides/ContractStringParam[@param="Title"]@value', raw: true);

        if (is_string($value) && str_starts_with($value, '@')) {
            return ltrim($value, '@');
        }

        $template = $this->resolveTemplateDisplayStrings()[0];

        return is_string($template) && str_starts_with($template, '@') ? ltrim($template, '@') : null;
    }

    public function getDescriptionKey(): ?string
    {
        $value = $this->get('paramOverrides/stringParamOverrides/ContractStringParam[@param="Description"]@value', raw: true);

        if (is_string($value) && str_starts_with($value, '@')) {
            return ltrim($value, '@');
        }

        $template = $this->resolveTemplateDisplayStrings()[1];

        return is_string($template) && str_starts_with($template, '@') ? ltrim($template, '@') : null;
    }

    public function getContractor(): ?string
    {
        $value = $this->getString('paramOverrides/stringParamOverrides/ContractStringParam[@param="Contractor"]@value');
        if ($value !== null) {
            return $value;
        }

        return $this->resolveTemplateDisplayStrings()[2];
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

    public function isNotifyOnAvailable(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="NotifyOnAvailable"]@value');
    }

    public function getMissionTypeOverride(): ?string
    {
        return $this->getString('paramOverrides@missionTypeOverride');
    }

    /**
     * @return list<array{factionReputation: ?string, scope: ?string, minStanding: ?string, maxStanding: ?string}>
     */
    public function getReputationPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('additionalPrerequisites/ContractPrerequisite_Reputation');

        foreach ($nodes as $node) {
            $results[] = [
                'factionReputation' => $node->get('@factionReputation'),
                'scope' => $node->get('@scope'),
                'minStanding' => $node->get('@minStanding'),
                'maxStanding' => $node->get('@maxStanding'),
            ];
        }

        return $results;
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
     * Locality gates carried by SubContract variants.
     * A subcontract is a location-specific overlay on the parent (one per planet/region it's offered at),
     * so its localities are additional places the contract appears
     *
     * @return list<array{localityAvailable: ?string}>
     */
    public function getSubContractLocalityPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('subContracts/SubContract/additionalPrerequisites/ContractPrerequisite_Locality');

        foreach ($nodes as $node) {
            $results[] = ['localityAvailable' => $node->get('@localityAvailable')];
        }

        return $results;
    }

    /**
     * Per-entry location gates (a SPECIFIC POI/system the player must be at, as opposed to a Locality region).
     *
     * @return list<array{locationAvailable: ?string}>
     */
    public function getLocationPrerequisites(): array
    {
        $results = [];
        $nodes = $this->getAll('additionalPrerequisites/ContractPrerequisite_Location');

        foreach ($nodes as $node) {
            $results[] = ['locationAvailable' => $node->get('@locationAvailable')];
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

    /**
     * Resolves title, description, and contractor from the referenced ContractTemplate's displayInfo.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [title, description, contractor]
     */
    private function resolveTemplateDisplayStrings(): array
    {
        if ($this->templateDisplayStrings !== null) {
            return $this->templateDisplayStrings;
        }

        $templateRef = $this->getTemplateReference();
        if ($templateRef === null) {
            return $this->templateDisplayStrings = [null, null, null];
        }

        $template = ServiceFactory::getFoundryLookupService()
            ->getContractTemplateByReference($templateRef);

        if ($template === null) {
            return $this->templateDisplayStrings = [null, null, null];
        }

        $locIds = $template->getAll(
            'contractDisplayInfo/ContractDisplayInfo/displayString/LocID@value',
            raw: true
        );

        // LocID order: [0]=Title, [1]=Title(dup), [2]=Description, [3]=Contractor, [4]=placeholder
        $title = isset($locIds[0]) && $locIds[0] !== '@LOC_UNINITIALIZED' ? $locIds[0] : null;
        $desc = isset($locIds[2]) && $locIds[2] !== '@LOC_UNINITIALIZED' ? $locIds[2] : null;
        $contractor = isset($locIds[3]) && $locIds[3] !== '@LOC_UNINITIALIZED' ? $locIds[3] : null;

        return $this->templateDisplayStrings = [$title, $desc, $contractor];
    }

    public function canReacceptAfterFailing(): ?bool
    {
        return $this->getNullableBool('paramOverrides/boolParamOverrides/ContractBoolParam[@param="CanReacceptAfterFailing"]@value');
    }
}
