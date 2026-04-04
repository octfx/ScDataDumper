<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MiningGlobalParams extends RootDocument
{
    public function getPowerCapacityPerMass(): ?float
    {
        return $this->getFloat('@powerCapacityPerMass');
    }

    public function getDecayPerMass(): ?float
    {
        return $this->getFloat('@decayPerMass');
    }

    public function getOptimalWindowSize(): ?float
    {
        return $this->getFloat('@optimalWindowSize');
    }

    public function getOptimalWindowFactor(): ?float
    {
        return $this->getFloat('@optimalWindowFactor');
    }

    public function getOptimalWindowMaxSize(): ?float
    {
        return $this->getFloat('@optimalWindowMaxSize');
    }

    public function getResistanceCurveFactor(): ?float
    {
        return $this->getFloat('@resistanceCurveFactor');
    }

    public function getOptimalWindowThinnessCurveFactor(): ?float
    {
        return $this->getFloat('@optimalWindowThinnessCurveFactor');
    }

    public function getCScuPerVolume(): ?float
    {
        return $this->getFloat('@cSCUPerVolume');
    }

    public function getDefaultMass(): ?float
    {
        return $this->getFloat('@defaultMass');
    }

    public function getWasteResourceTypeReference(): ?string
    {
        return $this->getString('@wasteResourceType');
    }

    public function getWasteResourceType(): ?ResourceType
    {
        $resourceType = $this->getHydratedDocument('ResourceType', ResourceType::class);

        if ($resourceType instanceof ResourceType) {
            return $resourceType;
        }

        $reference = $this->getWasteResourceTypeReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference);

        return $resolved instanceof ResourceType ? $resolved : null;
    }

    public function getInstabilityWavePeriod(): ?float
    {
        return $this->getFloat('mineableInstabilityParams@instabilityWavePeriod');
    }

    public function getInstabilityWaveVariance(): ?float
    {
        return $this->getFloat('mineableInstabilityParams@instabilityWaveVariance');
    }

    public function getInstabilityCurveFactor(): ?float
    {
        return $this->getFloat('mineableInstabilityParams@instabilityCurveFactor');
    }

    public function getDangerPoolFactor(): ?float
    {
        return $this->getFloat('mineableExplosionParams@dangerPoolFactor');
    }

    public function getDefaultExplosionVolume(): ?float
    {
        return $this->getFloat('mineableExplosionParams@defaultVolume');
    }
}
