<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Starmap;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Faction\Faction;
use Octfx\ScDataDumper\DocumentTypes\Faction\Faction_LEGACY;
use Octfx\ScDataDumper\DocumentTypes\Radar\RadarContactTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

class StarMapObject extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getDescription(): ?string
    {
        return $this->getString('@description');
    }

    public function getLocationHierarchyTagReference(): ?string
    {
        return $this->getString('@locationHierarchyTag');
    }

    public function getLocationHierarchyTagName(): ?string
    {
        $reference = $this->getLocationHierarchyTagReference();

        return $reference ? ServiceFactory::getTagDatabaseService()->getTagName($reference) : null;
    }

    public function getParentReference(): ?string
    {
        return $this->getString('@parent');
    }

    public function getJurisdictionReference(): ?string
    {
        return $this->getString('@jurisdiction');
    }

    public function getAffiliationReference(): ?string
    {
        return $this->getString('@affiliation');
    }

    public function getRadarContactTypeReference(): ?string
    {
        return $this->getString('radarProperties/SSCRadarContactProperites@contactType');
    }

    public function getJurisdiction(): ?Jurisdiction
    {
        $resolved = $this->resolveRelatedDocument(
            'Jurisdiction',
            Jurisdiction::class,
            $this->getJurisdictionReference(),
            static fn (string $reference): ?Jurisdiction => ServiceFactory::getFoundryLookupService()
                ->getJurisdictionByReference($reference)
        );

        return $resolved instanceof Jurisdiction ? $resolved : null;
    }

    public function getRadarContactType(): ?RadarContactTypeEntry
    {
        $resolved = $this->resolveRelatedDocument(
            'RadarContactType',
            RadarContactTypeEntry::class,
            $this->getRadarContactTypeReference(),
            static fn (string $reference): ?RadarContactTypeEntry => ServiceFactory::getFoundryLookupService()
                ->getRadarContactTypeByReference($reference)
        );

        return $resolved instanceof RadarContactTypeEntry ? $resolved : null;
    }

    public function getTypeDocument(): ?StarMapObjectType
    {
        $resolved = $this->resolveRelatedDocument(
            'Type',
            StarMapObjectType::class,
            $this->getTypeReference(),
            static fn (string $reference): ?StarMapObjectType => ServiceFactory::getFoundryLookupService()
                ->getStarMapObjectTypeByReference($reference)
        );

        return $resolved instanceof StarMapObjectType ? $resolved : null;
    }

    public function getAffiliation(): ?RootDocument
    {
        $affiliation = $this->get('Affiliation');

        if ($affiliation instanceof Element) {
            $documentType = $affiliation->get('@__type');

            return match ($documentType) {
                'Faction' => $this->getHydratedDocument('Affiliation', Faction::class),
                'Faction_LEGACY' => $this->getHydratedDocument('Affiliation', Faction_LEGACY::class),
                default => null,
            };
        }

        $resolved = $this->getAffiliationReference() !== null && $this->getAffiliationReference() !== ''
            ? ServiceFactory::getFoundryLookupService()->getFactionByReference($this->getAffiliationReference())
            : null;

        return $resolved instanceof RootDocument ? $resolved : null;
    }

    /**
     * @return list<string>
     */
    public function getAmenityReferences(): array
    {
        return $this->queryAttributeValues('amenities/Reference', 'value');
    }

    /**
     * @return list<StarMapAmenityTypeEntry>
     */
    public function getAmenities(): array
    {
        $amenities = [];

        foreach ($this->getAll('amenities/Reference/Amenity') as $amenityNode) {
            if (! $amenityNode instanceof Element) {
                continue;
            }

            $amenity = StarMapAmenityTypeEntry::fromNode($amenityNode->getNode());

            if ($amenity instanceof StarMapAmenityTypeEntry) {
                $amenities[] = $amenity;
            }
        }

        return $this->resolveRelatedDocuments(
            $amenities,
            $this->getAmenityReferences(),
            static fn (string $reference): ?StarMapAmenityTypeEntry => ServiceFactory::getFoundryLookupService()
                ->getStarMapAmenityTypeByReference($reference)
        );
    }

    public function getAsteroidRing(): ?StarMapAsteroidRing
    {
        $ringNode = $this->get('asteroidRings/StarMapAsteroidRing');

        if (! $ringNode instanceof Element) {
            return null;
        }

        $ring = StarMapAsteroidRing::fromNode($ringNode->getNode());

        return $ring instanceof StarMapAsteroidRing ? $ring : null;
    }

    public function getTypeReference(): ?string
    {
        return $this->getString('@type');
    }

    public function getNavIcon(): ?string
    {
        return $this->getString('@navIcon');
    }

    public function getRespawnLocationType(): ?string
    {
        return $this->getString('@respawnLocationType');
    }

    public function getOverrideShowInAllZones(): ?string
    {
        return $this->getString('@overrideShowInAllZones');
    }

    public function getOverridePermanent(): ?string
    {
        return $this->getString('@overridePermanent');
    }

    public function getStarMapGeomPath(): ?string
    {
        return $this->getString('@starMapGeomPath');
    }

    public function getStarMapMaterialPath(): ?string
    {
        return $this->getString('@starMapMaterialPath');
    }

    public function getStarMapShapePath(): ?string
    {
        return $this->getString('@starMapShapePath');
    }

    public function getLocationImagePath(): ?string
    {
        return $this->getString('@locationImagePath');
    }

    public function getIsScannable(): bool
    {
        return $this->getBool('@isScannable');
    }

    public function getHideInStarmap(): bool
    {
        return $this->getBool('@hideInStarmap');
    }

    public function getHideInWorld(): bool
    {
        return $this->getBool('@hideInWorld');
    }

    public function getHideWhenInAdoptionRadius(): bool
    {
        return $this->getBool('@hideWhenInAdoptionRadius');
    }

    public function getBlockTravel(): bool
    {
        return $this->getBool('@blockTravel');
    }

    public function getOnlyShowWhenParentSelected(): bool
    {
        return $this->getBool('@onlyShowWhenParentSelected');
    }

    public function getShowOrbitLine(): bool
    {
        return $this->getBool('@showOrbitLine');
    }

    public function getUseHoloMaterial(): bool
    {
        return $this->getBool('@useHoloMaterial');
    }

    public function getNoAutoBodyRecovery(): bool
    {
        return $this->getBool('@noAutoBodyRecovery');
    }

    public function getOverrideRotationSpeed(): bool
    {
        return $this->getBool('@overrideRotationSpeed');
    }

    public function getSize(): ?float
    {
        return $this->getFloat('@size');
    }

    public function getMinimumDisplaySize(): ?float
    {
        return $this->getFloat('@minimumDisplaySize');
    }

    public function getOverrideRotationSpeedValue(): ?float
    {
        return $this->getFloat('@overrideRotationSpeedValue');
    }

    public function getQuantumTravelObstructionRadius(): ?float
    {
        return $this->getFloat('quantumTravelData/StarMapQuantumTravelDataParams@obstructionRadius');
    }

    public function getQuantumTravelArrivalRadius(): ?float
    {
        return $this->getFloat('quantumTravelData/StarMapQuantumTravelDataParams@arrivalRadius');
    }

    public function getQuantumTravelArrivalPointDetectionOffset(): ?float
    {
        return $this->getFloat('quantumTravelData/StarMapQuantumTravelDataParams@arrivalPointDetectionOffset');
    }

    public function getQuantumTravelAdoptionRadius(): ?float
    {
        return $this->getFloat('quantumTravelData/StarMapQuantumTravelDataParams@adoptionRadius');
    }

    public function getQuantumTravelSubPointRadiusMultiplier(): ?float
    {
        return $this->getFloat('quantumTravelData/StarMapQuantumTravelDataParams@subPointRadiusMultiplier');
    }

    public function getLocationSetEntityLocationOnEnter(): ?bool
    {
        return $this->getNullableBool('locationParams/StarMapObjectLocationParams@setEntityLocationOnEnter');
    }

    public function getLocationExposeForPlayerCreatedMissions(): ?bool
    {
        return $this->getNullableBool('locationParams/StarMapObjectLocationParams@exposeForPlayerCreatedMissions');
    }

    public function getLocationExcludeFromLevelLoad(): ?bool
    {
        return $this->getNullableBool('locationParams/StarMapObjectLocationParams@excludeFromLevelLoad');
    }
}
