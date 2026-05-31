<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use Octfx\ScDataDumper\DocumentTypes\ResourceTypeGroup as ResourceTypeGroupDocument;
use Octfx\ScDataDumper\Services\FoundryLookupService;

/**
 * Maps resource UUIDs to their ResourceTypeGroup path.
 */
final class ResourceTypeGroupResolver
{
    private FoundryLookupService $lookup;

    /** @var array<string, list<string>>|null */
    private ?array $map = null;

    public function __construct(FoundryLookupService $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * @return list<string>
     */
    public function getGroupsForResource(string $resourceUuid): array
    {
        if ($this->map === null) {
            $this->map = $this->buildMap();
        }

        return $this->map[$resourceUuid] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildMap(): array
    {
        /** @var array<string, ResourceTypeGroupDocument> $groups uuid -> group document */
        $groups = [];

        foreach ($this->lookup->getDocumentType('ResourceTypeGroup', ResourceTypeGroupDocument::class) as $group) {
            $groups[$group->getUuid()] = $group;
        }

        $childToParent = [];

        foreach ($groups as $groupUuid => $group) {
            foreach ($group->getChildGroupReferences() as $childRef) {
                $childToParent[$childRef] = $groupUuid;
            }
        }

        $result = [];

        foreach ($groups as $groupUuid => $group) {
            $names = $this->collectGroupPathNames($groupUuid, $groups, $childToParent);

            foreach ($group->getResourceReferences() as $resourceUuid) {
                $result[$resourceUuid] = $names;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, ResourceTypeGroupDocument>  $groups
     * @param  array<string, string>  $childToParent
     * @return list<string>
     */
    private function collectGroupPathNames(string $groupUuid, array $groups, array $childToParent): array
    {
        $names = [];
        $visited = [];
        $current = $groupUuid;

        while ($current !== null && ! isset($visited[$current])) {
            $visited[$current] = true;
            $names[] = $groups[$current]->getClassName();
            $current = $childToParent[$current] ?? null;
        }

        return array_reverse($names);
    }
}
