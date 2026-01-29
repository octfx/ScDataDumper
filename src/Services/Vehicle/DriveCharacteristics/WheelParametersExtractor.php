<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Extract raw SubPartWheel parameters from vehicle definition
 *
 * This extractor finds all SubPartWheel elements in vehicle XML
 * and extracts their parameters for use in drive characteristics calculations.
 */
final readonly class WheelParametersExtractor
{
    /**
     * Extract wheel parameters from vehicle
     *
     * @param  Vehicle|null  $vehicle  The vehicle document
     * @return array<string, mixed>|null Wheel data array or null if no vehicle
     */
    public function extract(?Vehicle $vehicle): ?array
    {
        if (! $vehicle) {
            return null;
        }

        $wheelData = $this->extractAllWheels($vehicle);
        if (empty($wheelData)) {
            return null;
        }

        return [
            'RawWheelData' => $wheelData,
        ];
    }

    /**
     * Extract all SubPartWheel elements from vehicle
     *
     * @param  Vehicle  $vehicle  The vehicle document
     * @return array<int, array<string, float|int|bool>> Array of wheel data
     */
    private function extractAllWheels(Vehicle $vehicle): array
    {
        $wheels = [];

        $parts = $vehicle->get('//Parts');

        if (! $parts instanceof Element) {
            return $wheels;
        }

        // Traverse through all Part elements and find those with class="SubPartWheel"
        $this->findSubPartWheels($parts, $wheels);

        return $wheels;
    }

    /**
     * Recursively find SubPartWheel elements in Parts tree
     *
     * @param  Element  $element  Current element to search
     * @param  array<int, array<string, float|int|bool>>  $wheels  Array to collect wheel data
     */
    private function findSubPartWheels(Element $element, array &$wheels): void
    {
        // Check if this is a Part with class="SubPartWheel"
        $partClass = $element->get('@class');
        if ($partClass === 'SubPartWheel') {
            // Find the SubPartWheel child element
            $subPartWheel = $element->get('SubPartWheel');
            if ($subPartWheel instanceof Element) {
                $wheelData = $this->extractWheelData($subPartWheel);
                if ($wheelData !== null) {
                    $wheels[] = $wheelData;
                }
            }
        }

        // Recursively search children
        foreach ($element->children() as $child) {
            if ($child instanceof Element) {
                $this->findSubPartWheels($child, $wheels);
            }
        }
    }

    /**
     * Extract wheel data from a single SubPartWheel element
     *
     * @param  Element  $wheelElement  The SubPartWheel element
     * @return array<string, float|int|bool|null>|null Wheel data or null if invalid
     */
    private function extractWheelData(Element $wheelElement): ?array
    {
        // Extract required attributes from SubPartWheel element
        $axle = $wheelElement->get('@axle');
        $driving = $wheelElement->get('@driving');
        $canSteer = $wheelElement->get('@canSteer');
        $rimRadius = $wheelElement->get('@rimRadius');
        $torqueScale = $wheelElement->get('@torqueScale');
        $maxFriction = $wheelElement->get('@maxFriction');

        // Extract optional attributes (may be null if not present)
        $minFriction = $wheelElement->get('@minFriction');
        $damping = $wheelElement->get('@damping');
        $stiffness = $wheelElement->get('@stiffness');
        $suspLength = $wheelElement->get('@suspLength');
        $lenMax = $wheelElement->get('@lenMax');
        $slipFrictionMod = $wheelElement->get('@slipFrictionMod');
        $density = $wheelElement->get('@density');

        // Validate that at least one required field is present (allow partial data)
        if ($axle === null && $driving === null && $canSteer === null &&
            $rimRadius === null && $torqueScale === null && $maxFriction === null) {
            return null;
        }

        return [
            'Axle' => $axle !== null ? (int) $axle : null,
            'Driving' => $driving !== null ? (int) $driving : null,
            'CanSteer' => $canSteer !== null ? (bool) $canSteer : null,
            'RimRadius' => $rimRadius !== null ? (float) $rimRadius : null,
            'TorqueScale' => $torqueScale !== null ? (float) $torqueScale : null,
            'MaxFriction' => $maxFriction !== null ? (float) $maxFriction : null,
            // Optional fields:
            'MinFriction' => $minFriction !== null ? (float) $minFriction : null,
            'Damping' => $damping !== null ? (float) $damping : null,
            'Stiffness' => $stiffness !== null ? (float) $stiffness : null,
            'SuspensionLength' => $suspLength !== null ? (float) $suspLength : null,
            'MaxExtension' => $lenMax !== null ? (float) $lenMax : null,
            'SlipFrictionMod' => $slipFrictionMod !== null ? (float) $slipFrictionMod : null,
            'Density' => $density !== null ? (float) $density : null,
        ];
    }
}
