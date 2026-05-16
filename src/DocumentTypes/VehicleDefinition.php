<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class VehicleDefinition extends EntityClassDefinition
{
    /**
     * Cached VehicleComponentParams element.
     */
    private ?Element $vehicleComponentParams = null;

    private bool $vehicleComponentParamsResolved = false;

    /**
     * Resolve the VehicleComponentParams element once and cache it.
     * Falls back to the AttachDef element when VehicleComponentParams is absent
     * (e.g. some actor-based vehicles like power suits).
     */
    public function getVehicleComponentParams(): ?Element
    {
        if ($this->vehicleComponentParamsResolved) {
            return $this->vehicleComponentParams;
        }

        $vcp = $this->get('Components/VehicleComponentParams');

        if ($vcp instanceof Element) {
            $this->vehicleComponentParams = new Element($vcp->getNode());
        } else {
            $this->vehicleComponentParams = $this->getAttachDef();
        }

        $this->vehicleComponentParamsResolved = true;

        return $this->vehicleComponentParams;
    }

    /**
     * Override: fall back to SEntityInsuranceProperties/displayParams@manufacturer
     * for actor-based vehicles (e.g. power suits) that lack a valid AttachDef manufacturer.
     */
    public function getManufacturer(): ?SCItemManufacturer
    {
        $manufacturer = parent::getManufacturer();

        if ($manufacturer !== null) {
            return $manufacturer;
        }

        $manufacturerRef = $this->get('StaticEntityClassData/SEntityInsuranceProperties/displayParams@manufacturer');

        if ($manufacturerRef !== null && $manufacturerRef !== '') {
            $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturerRef);
        }

        return $manufacturer instanceof SCItemManufacturer ? $manufacturer : null;
    }

    public function isGroundVehicle(): bool
    {
        $subType = $this->get('Components/SAttachableComponentParams/AttachDef@SubType');
        $movementClass = $this->getVehicleComponentParams()?->get('@movementClass');
        $vehicleCareer = $this->getVehicleComponentParams()?->get('@vehicleCareer', '');

        return $subType === 'Vehicle_GroundVehicle'
            || $movementClass === 'ArcadeWheeled'
            || str_contains($vehicleCareer ?? '', 'ground');
    }

    public function isGravlev(): bool
    {
        $value = $this->getVehicleComponentParams()?->get('@isGravlevVehicle');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN)
            || (is_numeric($value) && (float) $value > 0);
    }

    public function isSpaceship(): bool
    {
        return ! ($this->isGroundVehicle() || $this->isGravlev());
    }

    /**
     * Normalized dimensions: Length >= Width >= Height.
     * Uses maxBoundingBoxSize with fallback to inventoryOccupancyLocalBounds.
     *
     * @return array{0: float, 1: float, 2: float} [Length, Width, Height]
     */
    public function getDimensions(): array
    {
        $vcp = $this->getVehicleComponentParams();

        $dimensions = [
            (float) ($vcp?->get('maxBoundingBoxSize@x', 0) ?? 0),
            (float) ($vcp?->get('maxBoundingBoxSize@y', 0) ?? 0),
            (float) ($vcp?->get('maxBoundingBoxSize@z', 0) ?? 0),
        ];
        rsort($dimensions, SORT_NUMERIC);

        if ($dimensions === [0.0, 0.0, 0.0]) {
            $min = $vcp?->get('inventoryOccupancyLocalBoundsMin');
            $max = $vcp?->get('inventoryOccupancyLocalBoundsMax');

            if ($min !== null && $max !== null) {
                $dimensions = [
                    abs((float) $max->get('@x') - (float) $min->get('@x')),
                    abs((float) $max->get('@y') - (float) $min->get('@y')),
                    abs((float) $max->get('@z') - (float) $min->get('@z')),
                ];
                rsort($dimensions, SORT_NUMERIC);
            }
        }

        return $dimensions;
    }

    public function getVehicleNameKey(): ?string
    {
        $vcp = $this->getVehicleComponentParams();

        $name = $vcp?->get('@vehicleName');

        if ($name !== null && $name !== '') {
            return (string) $name;
        }

        return null;
    }

    public function getVehicleDescriptionKey(): ?string
    {
        $desc = $this->getVehicleComponentParams()?->get('@vehicleDescription');

        return $desc !== null && $desc !== '' ? (string) $desc : null;
    }

    public function getCareerKey(): ?string
    {
        $career = $this->getVehicleComponentParams()?->get('@vehicleCareer');

        return $career !== null && $career !== '' ? (string) $career : null;
    }

    public function getRoleKey(): ?string
    {
        $role = $this->getVehicleComponentParams()?->get('@vehicleRole');

        return $role !== null && $role !== '' ? (string) $role : null;
    }

    public function getCrewSize(): int
    {
        return (int) ($this->getVehicleComponentParams()?->get('@crewSize', 1) ?? 1);
    }

    public function getFusePenetrationMultiplier(): mixed
    {
        return $this->getVehicleComponentParams()?->get('@fusePenetrationDamageMultiplier');
    }

    public function getComponentPenetrationMultiplier(): mixed
    {
        return $this->getVehicleComponentParams()?->get('@componentPenetrationDamageMultiplier', []);
    }

    /**
     * @return array{ExpeditedCost: float, ExpeditedClaimTime: float, StandardClaimTime: float}
     */
    public function getInsuranceParams(): array
    {
        return [
            'ExpeditedCost' => (float) $this->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseExpeditingFee', 0),
            'ExpeditedClaimTime' => (float) $this->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@mandatoryWaitTimeMinutes', 0),
            'StandardClaimTime' => (float) $this->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseWaitTimeMinutes', 0),
        ];
    }

    /**
     * Maximum number of shields that can be active simultaneously,
     * read from the entity's resource network power pool configuration.
     */
    public function getShieldPoolMaxCount(): int
    {
        $pools = $this->get('Components/SItemPortContainerComponentParams/resourceNetworkPowerPools/itemPools');

        foreach ($pools?->children() ?? [] as $pool) {
            if ($pool->get('@itemType') === 'Shield') {
                return (int) ($pool->get('@maxItemCount') ?? 0);
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Ship-level PortTags from SItemPortContainerComponentParams.
     *
     * @return list<string>
     */
    public function getPortTags(): array
    {
        $raw = trim((string) ($this->get('Components/SItemPortContainerComponentParams@PortTags') ?? ''));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $raw)));
    }

    /**
     * Raw cross-section parameters from SSCSignatureSystemParams.
     *
     * @return array<string, float>|null
     */
    public function getCrossSectionParams(): ?array
    {
        $signatureParams = $this->get('Components/SSCSignatureSystemParams');

        if ($signatureParams === null) {
            return null;
        }

        $crossSection = $signatureParams
            ->get('radarProperties/SSCRadarContactProperites/crossSectionParams/SSCSignatureSystemManualCrossSectionParams/crossSection');

        if ($crossSection === null) {
            return null;
        }

        return $crossSection->attributesToArray();
    }

    /**
     * Actor physics parameters (ATLS, power suits, etc.).
     * Returns null for standard vehicles.
     */
    public function getPhysicsParams(): ?Element
    {
        return $this->get('Components/SSCActorPhysicsControllerComponentParams/physType/SEntityActorPhysicsControllerParams');
    }

    public function isActorVehicle(): bool
    {
        return $this->getPhysicsParams() !== null;
    }

    /**
     * Resolve the engineering boost modifier entity from the `engineeringBuff` port.
     *
     * @return EntityClassDefinition|null The modifier entity, or null if no engineering buff port exists.
     */
    public function getEngineeringBoostItem(): ?EntityClassDefinition
    {
        $ports = $this->get('Components/SItemPortContainerComponentParams/Ports');

        if ($ports === null) {
            return null;
        }

        $engineeringBuffPort = null;

        foreach ($ports->children() as $portDef) {
            if ($portDef->get('@Name') === 'engineeringBuff') {
                $engineeringBuffPort = $portDef;
                break;
            }
        }

        if ($engineeringBuffPort === null) {
            return null;
        }

        $uuid = $engineeringBuffPort->get('defaultItem/@entityClass');

        if ($uuid === null || $uuid === '') {
            return null;
        }

        $modifierEntity = ServiceFactory::getItemService()->getByReference($uuid);

        return $modifierEntity instanceof EntityClassDefinition ? $modifierEntity : null;
    }
}
