<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Resolve actor stance speed from the 3-hop reference chain:
 * SActorComponentParams@stancesDataRecord -> ActorStanceConfig -> stanceSpeeds/Reference -> ActorStanceSpeedsInfo
 *
 * Only applies to actor-based vehicles (like ATLS powersuits).
 */
final class StanceSpeedExtractor
{
    private const M_TO_KPH_FACTOR = 3.6;

    /**
     * Extract stance speed from an actor-based vehicle entity.
     *
     * @param  RootDocument  $entity  The VehicleDefinition entity (actor XML)
     * @return array<string, mixed>|null Stance speed data, or null if not an actor vehicle
     */
    public function extract(RootDocument $entity): ?array
    {
        $actorComponent = $entity->get('Components/SActorComponentParams');
        if ($actorComponent === null) {
            return null;
        }

        $stancesDataRecordRef = $actorComponent->get('@stancesDataRecord');
        if (! is_string($stancesDataRecordRef) || $stancesDataRecordRef === '') {
            return null;
        }

        $lookupService = ServiceFactory::getFoundryLookupService();

        $stanceConfig = $lookupService->getByReference($stancesDataRecordRef);
        if ($stanceConfig === null) {
            return null;
        }

        $speedRef = $stanceConfig->get('stanceSpeeds/Reference@value');
        if (! is_string($speedRef) || $speedRef === '') {
            return null;
        }

        $speedDoc = $lookupService->getByReference($speedRef);
        if ($speedDoc === null) {
            return null;
        }

        $speeds = $speedDoc->get('speeds');
        if ($speeds === null) {
            return null;
        }

        $walkSpeedMs = (float) ($speeds->get('@defaultSpeed') ?? 0);
        $sprintSpeedMs = (float) ($speeds->get('@sprintSpeed') ?? 0);

        return [
            'WalkSpeedMs' => $walkSpeedMs,
            'WalkSpeedKph' => $walkSpeedMs * self::M_TO_KPH_FACTOR,
            'SprintSpeedMs' => $sprintSpeedMs,
            'SprintSpeedKph' => $sprintSpeedMs * self::M_TO_KPH_FACTOR,
            'Acceleration' => (float) ($speeds->get('@defaultLinearAcceleration') ?? 0),
            'RotationSpeed' => (float) ($speeds->get('@defaultRotationSpeed') ?? 0),
        ];
    }
}
