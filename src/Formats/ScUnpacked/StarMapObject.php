<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Str;
use Octfx\ScDataDumper\DocumentTypes\Radar\RadarContactTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\DocumentTypes\Starmap\Jurisdiction;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapAmenityTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapAsteroidRing;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject as StarMapObjectDocument;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObjectType;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class StarMapObject extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->item instanceof StarMapObjectDocument) {
            return null;
        }

        $object = $this->item;
        $type = $object->getTypeDocument();
        $jurisdiction = $object->getJurisdiction();
        $affiliation = $object->getAffiliation();
        $radarContactType = $object->getRadarContactType();
        $asteroidRing = $object->getAsteroidRing();

        $name = ServiceFactory::getLocalizationService()->translateValue($object->getName());
        if (empty($name)) {
            $name = Str::headline($object->getClassName());
        }

        $parentUuid = $object->getParentReference();
        if ($parentUuid === null) {
            $parentUuid = ServiceFactory::getStarmapParentResolver()->resolveParentUuid($object->getClassName());
        }

        return $this->transformArrayKeysToPascalCase([
            'uuid' => $object->getUuid(),
            'name' => $name,
            'description' => ServiceFactory::getLocalizationService()->translateValue($object->getDescription()),
            'parentUuid' => $parentUuid,
            'navIcon' => $object->getNavIcon(),
            'respawnLocationType' => $object->getRespawnLocationType(),
            'isScannable' => $object->getIsScannable(),
            'hideInStarmap' => $object->getHideInStarmap(),
            'hideInWorld' => $object->getHideInWorld(),
            'hideWhenInAdoptionRadius' => $object->getHideWhenInAdoptionRadius(),
            'blockTravel' => $object->getBlockTravel(),
            'onlyShowWhenParentSelected' => $object->getOnlyShowWhenParentSelected(),
            'showOrbitLine' => $object->getShowOrbitLine(),
            'noAutoBodyRecovery' => $object->getNoAutoBodyRecovery(),
            'size' => $object->getSize(),
            'minimumDisplaySize' => $object->getMinimumDisplaySize(),
            'quantumTravel' => $this->buildQuantumTravel($object),
            'locationHierarchyTag' => $this->buildLocationHierarchyTag($object),
            'type' => $this->buildType($type),
            'jurisdiction' => $this->buildJurisdiction($jurisdiction),
            'affiliation' => $this->buildAffiliation($affiliation),
            'radarContactType' => $this->buildRadarContactType($radarContactType),
            'asteroidRing' => $this->buildAsteroidRing($asteroidRing),
            'amenities' => array_values(array_filter(
                array_map(fn (StarMapAmenityTypeEntry $amenity): array => $this->buildAmenity($amenity), $object->getAmenities())
            )),
        ]);
    }

    /**
     * @return array{obstructionRadius: ?float, arrivalRadius: ?float, arrivalPointDetectionOffset: ?float, adoptionRadius: ?float, subPointRadiusMultiplier: ?float}|null
     */
    private function buildQuantumTravel(StarMapObjectDocument $object): ?array
    {
        $quantumTravel = [
            'obstructionRadius' => $object->getQuantumTravelObstructionRadius(),
            'arrivalRadius' => $object->getQuantumTravelArrivalRadius(),
            'arrivalPointDetectionOffset' => $object->getQuantumTravelArrivalPointDetectionOffset(),
            'adoptionRadius' => $object->getQuantumTravelAdoptionRadius(),
            'subPointRadiusMultiplier' => $object->getQuantumTravelSubPointRadiusMultiplier(),
        ];

        if (array_any($quantumTravel, fn ($value) => $value !== null)) {
            return $quantumTravel;
        }

        return null;
    }

    /**
     * @return array{uuid: string, name: ?string}|null
     */
    private function buildLocationHierarchyTag(StarMapObjectDocument $object): ?array
    {
        $uuid = $object->getLocationHierarchyTagReference();

        if ($uuid === null) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'name' => $object->getLocationHierarchyTagName(),
        ];
    }

    /**
     * @return array{uuid: string, name: ?string, classification: ?string, spawnNavPoints: bool, validQuantumTravelDestination: bool}|null
     */
    private function buildType(?StarMapObjectType $type): ?array
    {
        if ($type === null) {
            return null;
        }

        return [
            'uuid' => $type->getUuid(),
            'name' => ServiceFactory::getLocalizationService()->translateValue($type->getName()),
            'classification' => ServiceFactory::getLocalizationService()->translateValue($type->getClassification()),
            'spawnNavPoints' => $type->spawnNavPoints(),
            'validQuantumTravelDestination' => $type->validQuantumTravelDestination(),
        ];
    }

    /**
     * @return array{uuid: string, name: ?string, baseFine: ?int, maxStolenGoodsPossessionScu: ?int, isPrison: bool}|null
     */
    private function buildJurisdiction(?Jurisdiction $jurisdiction): ?array
    {
        if ($jurisdiction === null) {
            return null;
        }

        return [
            'uuid' => $jurisdiction->getUuid(),
            'name' => ServiceFactory::getLocalizationService()->translateValue($jurisdiction->getName()),
            'baseFine' => $jurisdiction->getBaseFine(),
            'maxStolenGoodsPossessionScu' => $jurisdiction->getMaxStolenGoodsPossessionScu(),
            'isPrison' => $jurisdiction->getIsPrison(),
        ];
    }

    /**
     * @return array{uuid: string, displayName: ?string}|null
     */
    private function buildAffiliation(?RootDocument $affiliation): ?array
    {
        if ($affiliation === null) {
            return null;
        }

        /** @var string|null $displayName */
        $displayName = $affiliation->get('@displayName');
        /** @var string|null $name */
        $name = $affiliation->get('@name');

        return [
            'uuid' => $affiliation->getUuid(),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($displayName ?? $name),
        ];
    }

    /**
     * @return array{uuid: string, name: ?string, displayName: ?string, tagUuid: ?string, tagName: ?string, isObjectOfInterest: bool}|null
     */
    private function buildRadarContactType(?RadarContactTypeEntry $radarContactType): ?array
    {
        if ($radarContactType === null) {
            return null;
        }

        return [
            'uuid' => $radarContactType->getUuid(),
            'name' => ServiceFactory::getLocalizationService()->translateValue($radarContactType->getName()),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($radarContactType->getDisplayName()),
            'tagUuid' => $radarContactType->getTagReference(),
            'tagName' => $radarContactType->getTagName(),
            'isObjectOfInterest' => $radarContactType->isObjectOfInterest(),
        ];
    }

    /**
     * @return array{densityScale: ?float, sizeScale: ?float, innerRadius: ?float, outerRadius: ?float, depth: ?float}|null
     */
    private function buildAsteroidRing(?StarMapAsteroidRing $asteroidRing): ?array
    {
        if ($asteroidRing === null) {
            return null;
        }

        return [
            'densityScale' => $asteroidRing->getDensityScale(),
            'sizeScale' => $asteroidRing->getSizeScale(),
            'innerRadius' => $asteroidRing->getInnerRadius(),
            'outerRadius' => $asteroidRing->getOuterRadius(),
            'depth' => $asteroidRing->getDepth(),
        ];
    }

    /**
     * @return array{uuid: string, name: ?string, displayName: ?string}
     */
    private function buildAmenity(StarMapAmenityTypeEntry $amenity): array
    {
        return [
            'uuid' => $amenity->getUuid(),
            'name' => ServiceFactory::getLocalizationService()->translateValue($amenity->getName()),
            'displayName' => ServiceFactory::getLocalizationService()->translateValue($amenity->getDisplayName()),
        ];
    }
}
