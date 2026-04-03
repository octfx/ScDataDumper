<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Starmap;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class StarMapObject extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();

        $radarContactType = $lookup->getRadarContactTypeByReference($this->get('radarProperties/SSCRadarContactProperites@contactType'));
        if ($radarContactType !== null && $this->get('RadarContactType@__ref') !== $this->get('radarProperties/SSCRadarContactProperites@contactType')) {
            $this->appendNode($document, $radarContactType, 'RadarContactType');
        }

        $jurisdiction = $lookup->getJurisdictionByReference($this->get('@jurisdiction'));
        if ($jurisdiction !== null && $this->get('Jurisdiction@__ref') !== $this->get('@jurisdiction')) {
            $this->appendNode($document, $jurisdiction, 'Jurisdiction');
        }

        $type = $lookup->getStarMapObjectTypeByReference($this->get('@type'));
        if ($type !== null && $this->get('Type@__ref') !== $this->get('@type')) {
            $this->appendNode($document, $type, 'Type');
        }

        $affiliationReference = $this->get('@affiliation');
        if (is_string($affiliationReference) && $affiliationReference !== '') {
            $affiliation = $lookup->getFactionByReference($affiliationReference);

            if ($affiliation !== null && $this->get('Affiliation@__ref') !== $affiliationReference) {
                $this->appendNode($document, $affiliation, 'Affiliation');
            }
        }

        foreach ($this->getAll('amenities/Reference') as $reference) {
            if (! $reference instanceof Element) {
                continue;
            }

            $uuid = $reference->get('@value');
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }

            if ($reference->get('Amenity@__ref') === $uuid) {
                continue;
            }

            $amenity = $lookup->getStarMapAmenityTypeByReference($uuid);
            if ($amenity !== null) {
                $reference->appendNode($document, $amenity, 'Amenity');
            }
        }
    }
}
