<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Extracts turret movement characteristics (per-axis speeds, acceleration, limits, etc).
 */
final class Turret extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemTurretParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $turret = $this->get();

        $data = $turret?->attributesToArray(['toggleTurretPositionInteraction',
            'defaultMovementTag',
            'recenterIfUnused',
            'healthModifierRecord',
            'switchToCachedOperatorModeOnExit',
            'operatorModeOnEnter',
            'jointConvergence',
            'intoxicationModifierRef',
            'hudParamsOverride',
            'autoDeployHelmetTargetingMode', ], true) ?? [];

        $movements = [];

        foreach ($turret?->get('/movementList')?->children() ?? [] as $movement) {
            $movements[] = $this->parseMovement($movement);
        }

        if ($movements !== []) {
            $data['MovementList'] = $movements;
        }

        return $this->removeNullValues(
            $this->transformArrayKeysToPascalCase($data)
        );
    }

    private function parseMovement(Element $movement): array
    {
        $data = $movement->attributesToArray([
            'movementTag',

        ], true);

        $pitch = $this->parseAxis($movement->get('/pitchAxis'));
        $yaw = $this->parseAxis($movement->get('/yawAxis'));
        $roll = $this->parseAxis($movement->get('/rollAxis'));

        if ($pitch !== null) {
            $data['PitchAxis'] = $pitch;
        }

        if ($yaw !== null) {
            $data['YawAxis'] = $yaw;
        }

        if ($roll !== null) {
            $data['RollAxis'] = $roll;
        }

        return $this->removeNullValues($data);
    }

    private function parseAxis(?Element $axis): ?array
    {
        if (! $axis instanceof Element) {
            return null;
        }

        $axisParams = null;

        foreach ($axis->children() as $child) {
            if ($child->nodeName === 'SCItemTurretJointMovementAxisParams') {
                $axisParams = $child;
                break;
            }
        }

        if (! $axisParams instanceof Element) {
            return null;
        }

        $data = $axisParams->attributesToArray([
            'enableIKRotationalSpeed',
            'rotateStartedAudioCooldown',
            'rotateStoppedAudioCooldown',
            'rotationDirectionChangedAudioCooldown',
            'rotationSpeedAudioAveragingFrames',
            'minMovementAngleForAudio',
        ], true);

        $angleLimits = [];

        foreach ($axisParams->get('/angleLimits')?->children() ?? [] as $limit) {
            $angleLimits[] = $limit->attributesToArray([], true);
        }

        if ($angleLimits !== []) {
            $data['AngleLimits'] = $angleLimits;
        }

        return $this->removeNullValues($data);
    }
}
