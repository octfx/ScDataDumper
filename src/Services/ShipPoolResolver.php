<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;

/**
 * Spawn XML references ships by TAG, not directly: the live poolId is a runtime
 * spawn-director hash, unbuildable from static data. Resolution = AI ship entities
 * whose top-level tags are a superset of the spawn's selection tags, minus negatives.
 *
 * Positive tags are intersected with the entity tag universe first, which auto-drops
 * behavior tags (Target, HumanPilot40, ...) since they live on no entity. Validated
 * in issues/combat-ship-pool-validation.md.
 */
final class ShipPoolResolver extends BaseService
{
    private bool $indexBuilt = false;

    /**
     * @var list<array{tags: array<string, true>, name: string, className: ?string}>
     */
    private array $entities = [];

    /**
     * @var array<string, true>
     */
    private array $tagUniverse = [];

    public function initialize(): void
    {
        if ($this->indexBuilt) {
            return;
        }
        $this->indexBuilt = true;

        $localization = ServiceFactory::getLocalizationService();

        foreach (self::$classToPathMap['EntityClassDefinition'] ?? [] as $path) {
            // AI ship spawn entities only; the _ai_ suffix excludes player hulls and
            // their components, which share the entities/spaceships/ directory.
            if (! str_contains($path, 'entities/spaceships/') || ! str_contains(basename($path), '_ai_')) {
                continue;
            }

            $entity = $this->loadDocument($path, EntityClassDefinition::class);

            $tags = [];
            foreach ($entity->getEntityTagReferences() as $uuid) {
                $lower = strtolower($uuid);
                $tags[$lower] = true;
                $this->tagUniverse[$lower] = true;
            }

            $name = $localization->translateValue($entity->get('Components/VehicleComponentParams@vehicleName'), true);
            // shipEntityClassName is the base-hull link key (e.g. AEGS_Avenger_Stalker). NPC-only
            // hulls (Vanduul, capitals) carry none; they display by name but aren't linkable.
            $className = $entity->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@shipEntityClassName');
            if (($name === null || $name === '') && $className !== null) {
                $name = $className;
            }
            if ($name === null || $name === '') {
                continue;
            }

            $this->entities[] = ['tags' => $tags, 'name' => $name, 'className' => $className];
        }
    }

    /**
     * @param  list<string>  $positiveTags
     * @param  list<string>  $negativeTags
     * @return list<array{name: string, className: ?string}> deduped by className, sorted by name
     */
    public function resolve(array $positiveTags, array $negativeTags): array
    {
        $this->initialize();

        // Drop behavior tags: keep only positive tags some entity actually carries.
        $selection = [];
        foreach ($positiveTags as $uuid) {
            $lower = strtolower($uuid);
            if (isset($this->tagUniverse[$lower])) {
                $selection[$lower] = true;
            }
        }

        // Empty selection (all positive tags behavioral) is unresolvable: don't match every entity.
        if ($selection === []) {
            return [];
        }

        $negative = [];
        foreach ($negativeTags as $uuid) {
            $negative[strtolower($uuid)] = true;
        }

        // Dedupe by (className, name): _crim/_civ/_sec variants of one hull share both and
        // collapse to one entry, while distinct-name hulls (F7A vs F7C Hornet) both show
        // and link to the same show page. className-less hulls dedupe by name alone.
        $results = [];
        foreach ($this->entities as $entity) {
            if (count(array_diff_key($selection, $entity['tags'])) > 0) {
                continue;
            }
            if (count(array_intersect_key($negative, $entity['tags'])) > 0) {
                continue;
            }
            $key = ($entity['className'] ?? '')."\0".$entity['name'];
            $results[$key] = ['className' => $entity['className'], 'name' => $entity['name']];
        }

        usort($results, static fn ($a, $b) => $a['name'] <=> $b['name']);

        return $results;
    }
}
