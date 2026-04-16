<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

class EntitySpawnDescriptionsValue extends RootDocument
{
    /**
     * @return list<array{name: ?string, entities: list<array{amount: int, weight: int, tags: list<string>, negativeTags: list<string>, markupTags: list<string>}>}>
     */
    public function getEntityGroups(): array
    {
        $results = [];
        $groups = $this->getAll('spawnDescriptions/SpawnDescription_EntityGroup');

        foreach ($groups as $group) {
            $entities = [];
            $entityNodes = $group->getAll('entities/SpawnDescription_EntityOptions/options/SpawnDescription_Entity');

            foreach ($entityNodes as $entity) {
                $tags = [];
                foreach ($entity->getAll('tags/tags/Reference@value') as $val) {
                    if (is_string($val) && $val !== '') {
                        $tags[] = $val;
                    }
                }

                $negativeTags = [];
                foreach ($entity->getAll('negativeTags/tags/Reference@value') as $val) {
                    if (is_string($val) && $val !== '') {
                        $negativeTags[] = $val;
                    }
                }

                $markupTags = [];
                foreach ($entity->getAll('markupTags/tags/Reference@value') as $val) {
                    if (is_string($val) && $val !== '') {
                        $markupTags[] = $val;
                    }
                }

                $entities[] = [
                    'amount' => (int) ($entity->get('@amount') ?? 1),
                    'weight' => (int) ($entity->get('@weight') ?? 1),
                    'tags' => $tags,
                    'negativeTags' => $negativeTags,
                    'markupTags' => $markupTags,
                ];
            }

            $results[] = [
                'name' => $group->get('@Name'),
                'entities' => $entities,
            ];
        }

        return $results;
    }

    /**
     * @param  string  $variableName  The mission variable name from the override
     * @return list<array{group_name: ?string, amount: int, weight: int, tags: list<array{uuid: string, name: ?string}>, negative_tags: list<array{uuid: string, name: ?string}>, markup_tags: list<array{uuid: string, name: ?string}>}>
     */
    public function toArray(string $variableName = ''): array
    {
        $tagService = ServiceFactory::getTagDatabaseService();
        $rows = [];

        foreach ($this->getEntityGroups() as $group) {
            foreach ($group['entities'] as $entity) {
                $rows[] = [
                    'group_name' => $group['name'],
                    'amount' => $entity['amount'],
                    'weight' => $entity['weight'],
                    'tags' => $tagService->resolveUuidsToNameObjects($entity['tags']),
                    'negative_tags' => $tagService->resolveUuidsToNameObjects($entity['negativeTags'] ?? []),
                    'markup_tags' => $tagService->resolveUuidsToNameObjects($entity['markupTags'] ?? []),
                ];
            }
        }

        return $rows;
    }
}
