<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;

final class ContractLocationResolver
{
    private bool $initialized = false;

    /**
     * @var list<array{uuid: string, className: string, displayName: ?string, expandedTags: array<string, true>, limitTags: array<string, true>, generalTags: list<string>}>
     */
    private array $templates = [];

    private ?MissionLocationStarmapResolver $starmapResolver = null;

    public function __construct() {}

    /**
     * @param  list<array{positiveTags: list<string>, negativeTags: list<string>}>  $searchTerms
     * @param  list<string>  $resourceTags
     * @return list<array{uuid: string, location_template_uuid: string, name: ?string, class_name: string}>
     */
    public function resolveLocations(array $searchTerms, array $resourceTags = []): array
    {
        $this->ensureInitialized();

        $tagService = ServiceFactory::getTagDatabaseService();

        $seen = [];
        $results = [];

        foreach ($searchTerms as $term) {
            $expandedPositive = array_map(
                'strtolower',
                $tagService->expandTagsWithAncestors($term['positiveTags']),
            );
            $expandedNegative = array_map(
                'strtolower',
                $tagService->expandTagsWithDescendants($term['negativeTags']),
            );

            $positiveSet = array_flip($expandedPositive);

            foreach ($this->templates as $template) {
                $uuidLower = strtolower($template['uuid']);
                if (isset($seen[$uuidLower])) {
                    continue;
                }

                if ($resourceTags !== []) {
                    $hasMatchingLimit = false;
                    foreach ($resourceTags as $rt) {
                        if (isset($template['limitTags'][strtolower($rt)])) {
                            $hasMatchingLimit = true;
                            break;
                        }
                    }
                    if (! $hasMatchingLimit) {
                        continue;
                    }
                }

                if ($positiveSet !== []) {
                    $allMatch = true;
                    foreach ($expandedPositive as $posTag) {
                        if (! isset($template['expandedTags'][$posTag])) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if (! $allMatch) {
                        continue;
                    }
                }

                $hasNegative = false;
                foreach ($expandedNegative as $negTag) {
                    if (isset($template['expandedTags'][$negTag])) {
                        $hasNegative = true;
                        break;
                    }
                }
                if ($hasNegative) {
                    continue;
                }

                $seen[$uuidLower] = true;
                $starmapUuid = $this->starmapResolver?->getStarmapUuid($template['uuid']);
                $results[] = [
                    'uuid' => $starmapUuid ?? $template['uuid'],
                    'location_template_uuid' => $template['uuid'],
                    'name' => $template['displayName'],
                    'class_name' => $template['className'],
                ];
            }
        }

        return $results;
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $lookup = ServiceFactory::getFoundryLookupService();
        $tagService = ServiceFactory::getTagDatabaseService();
        $localization = ServiceFactory::getLocalizationService();

        foreach ($lookup->getDocumentType('MissionLocationTemplate', MissionLocationTemplate::class) as $template) {
            $generalTags = $template->getGeneralTagReferences();
            $expanded = $tagService->expandTagsWithAncestors($generalTags);

            $expandedTags = [];
            foreach ($expanded as $tag) {
                $expandedTags[strtolower($tag)] = true;
            }

            $limitTags = [];
            foreach ($template->getMissionLimitTags() as $limitTag) {
                $limitTags[strtolower($limitTag)] = true;
            }

            $displayName = $template->getDisplayName();
            if ($displayName !== null) {
                $displayName = $localization->translateValue($displayName, true);
            }

            $this->templates[] = [
                'uuid' => $template->getUuid(),
                'className' => $template->getClassName(),
                'displayName' => $displayName,
                'expandedTags' => $expandedTags,
                'limitTags' => $limitTags,
                'generalTags' => $generalTags,
            ];
        }

        $this->starmapResolver = ServiceFactory::getMissionLocationStarmapResolver();
        $this->starmapResolver->resolveAll(array_map(static fn (array $t): array => [
            'uuid' => $t['uuid'],
            'className' => $t['className'],
            'displayName' => $t['displayName'],
            'generalTags' => $t['generalTags'],
        ], $this->templates));
    }
}
