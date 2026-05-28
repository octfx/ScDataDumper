<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Represents an entity with triggerable device behavior (grenades, mines, etc.).
 */
class TriggerableDevice extends EntityClassDefinition
{
    /**
     * The raw triggerable device params element.
     */
    public function getTriggerableParams(): ?Element
    {
        $params = $this->get('Components/EntityComponentTriggerableDevicesParams');

        return $params instanceof Element ? $params : null;
    }

    /**
     * Collect all explosion params from player triggers and aiTriggers.
     *
     * @return Element[]
     */
    public function getExplosions(): array
    {
        $triggerable = $this->getTriggerableParams();

        if ($triggerable === null) {
            return [];
        }

        return [
            ...$this->extractExplosions($triggerable->get('triggers')),
            ...$this->extractExplosions($triggerable->get('aiTriggers')),
        ];
    }

    /**
     * Find the first explosion params block (player triggers preferred, then aiTriggers).
     */
    public function getFirstExplosion(): ?Element
    {
        $triggerable = $this->getTriggerableParams();

        if ($triggerable === null) {
            return null;
        }

        $explosions = $this->extractExplosions($triggerable->get('triggers'));

        if ($explosions !== []) {
            return $explosions[0];
        }

        $explosions = $this->extractExplosions($triggerable->get('aiTriggers'));

        if ($explosions !== []) {
            return $explosions[0];
        }

        return null;
    }

    /**
     * Get the fallback explosion params from the health component's death explosion.
     */
    public function getDeathExplosion(): ?Element
    {
        $fallback = $this->get('Components/SHealthComponentParams/DeathExplosionParams/ExplosionParams');

        return $fallback instanceof Element ? $fallback : null;
    }

    /**
     * Search player triggers and aiTriggers for a spawn-entity behavior UUID.
     */
    public function getSpawnEntityUuid(): ?string
    {
        $triggerable = $this->getTriggerableParams();

        if ($triggerable === null) {
            return null;
        }

        $section = 'triggers';
        $uuid = $this->extractSpawnEntityUuid($triggerable->get($section));

        return $uuid ?? null;
    }

    /**
     * Resolve the spawned entity as a HazardZone document.
     *
     * Returns null if no spawn entity exists or it cannot be resolved.
     */
    public function getSpawnedHazardZone(): ?HazardZone
    {
        $uuid = $this->getSpawnEntityUuid();

        if ($uuid === null) {
            return null;
        }

        $entity = ServiceFactory::getItemService()->getByReference($uuid);

        if (! $entity instanceof EntityClassDefinition) {
            return null;
        }

        // Re-wrap as HazardZone to get typed accessors
        $hazardZone = HazardZone::fromNode($entity->documentElement);

        return $hazardZone instanceof HazardZone ? $hazardZone : null;
    }

    /**
     * Collect explosion params from a trigger section element.
     *
     * @return Element[]
     */
    private function extractExplosions(?Element $section): array
    {
        if (! $section instanceof Element) {
            return [];
        }

        $explosions = [];

        foreach ($section->children() as $trigger) {
            $behavior = $trigger->get('behavior');

            if (! $behavior instanceof Element) {
                continue;
            }

            foreach ($behavior->children() as $behaviorChild) {
                if ($behaviorChild->nodeName !== 'STriggerableDevicesBehaviorExplosionParams') {
                    continue;
                }

                $explosion = $behaviorChild->get('explosionParams');

                if ($explosion instanceof Element) {
                    $explosions[] = $explosion;
                }
            }
        }

        return $explosions;
    }

    /**
     * Scan a trigger section for the first spawn-entity behavior UUID.
     */
    private function extractSpawnEntityUuid(?Element $section): ?string
    {
        if (! $section instanceof Element) {
            return null;
        }

        foreach ($section->children() as $trigger) {
            $behavior = $trigger->get('behavior');

            if (! $behavior instanceof Element) {
                continue;
            }

            foreach ($behavior->children() as $behaviorChild) {
                if ($behaviorChild->nodeName !== 'STriggerableDevicesBehaviorSpawnEntityParams') {
                    continue;
                }

                $uuid = $behaviorChild->get('@entityToSpawn');

                if (is_string($uuid) && $uuid !== '') {
                    return $uuid;
                }
            }
        }

        return null;
    }
}
