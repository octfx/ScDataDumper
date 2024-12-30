<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function __construct(VehicleDefinition $item, private readonly Vehicle $vehicle)
    {
        parent::__construct($item);
    }

    public function toArray(): array
    {
        $attach = $this->get();
        $vehicleComponent = $this->get('Components/VehicleComponentParams');

        $manufacturer = $vehicleComponent->get('Manufacturer');
        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturer);

        $isVehicle = $this->item->get('Components/VehicleComponentParams@vehicleCareer') === '@vehicle_focus_ground';
        $isGravlev = $this->item->get('Components/VehicleComponentParams@isGravlevVehicle') === '1';

        $data = [
            'UUID' => $this->item->getUuid(),
            'ClassName' => $this->item->getClassName(),
            'Name' => $vehicleComponent->get('/vehicleName@Name', $this->item->getClassName()),
            'Description' => $vehicleComponent->get('/English@vehicleDescription', ''),

            'Career' => $vehicleComponent->get('/English@vehicleCareer', ''),
            'Role' => $vehicleComponent->get('/English@vehicleRole', ''),

            'Manufacturer' => $manufacturer ? [
                'Code' => $manufacturer->getCode(),
                'Name' => $manufacturer->get('Localization/English@Name'),
            ] : [],

            'Size' => $attach->get('Size', 0),
            'Width' => $vehicleComponent->get('maxBoundingBoxSize@x', 0),
            'Length' => $vehicleComponent->get('maxBoundingBoxSize@y', 0),
            'Height' => $vehicleComponent->get('maxBoundingBoxSize@z', 0),
            'Crew' => $vehicleComponent->get('crewSize', 0),

            'Parts' => [],

            // WeaponCrew = portSummary.MannedTurrets.Count + portSummary.RemoteTurrets.Count,
            // OperationsCrew = Math.Max(portSummary.MiningTurrets.Count, portSummary.UtilityTurrets.Count),

            'Insurance' => [
                'ExpeditedCost' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseExpeditingFee', 0),
                'ExpeditedClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@mandatoryWaitTimeMinutes', 0),
                'StandardClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseWaitTimeMinutes', 0),
            ],

            'IsVehicle' => $isVehicle,
            'IsGravlev' => $isGravlev,
            'IsSpaceship' => ! ($isVehicle || $isGravlev),
        ];

        foreach ($this->vehicle->get('//Parts')?->children() ?? [] as $part) {
            if ($part->get('skipPart') === '1') {
                continue;
            }

            $data['Parts'][] = (new Part($part))->toArray();
        }

        $loadoutEntries = [];

        foreach ($this->item->get('/SEntityComponentDefaultLoadoutParams/loadout')?->children() ?? [] as $loadout) {
            $loadoutEntries[] = (new Loadout($loadout))->toArray();
        }

        $data['LoadoutEntries'] = $loadoutEntries;

        $mass = 0;
        foreach ($this->partList($data['Parts']) as $part) {
            // TODO fix non-numeric values
            $mass += $part['Mass'] ?? 0;
        }
        $data['Mass'] = $mass > 0 ? $mass : null;

        $this->processArray($data);

        return $this->removeNullValues($data);
    }

    public static function convertToScu(?EntityClassDefinition $item): ?float
    {
        if (! $item) {
            return null;
        }

        $scu = null;

        if ($item->get('SStandardCargoUnit@standardCargoUnits') !== null) {
            $scu = $item->get('SStandardCargoUnit@standardCargoUnits');
        } elseif ($item->get('SCentiCargoUnit@centiSCU') !== null) {
            $scu = $item->get('SCentiCargoUnit@centiSCU') * (10 ** -2);
        } elseif ($item->get('SMicroCargoUnit@microSCU') !== null) {
            $scu = $item->get('SMicroCargoUnit@microSCU') * (10 ** -6);
        }

        return $scu;
    }

    public static function buildTypeName(?string $major, ?string $minor): string
    {
        if (empty($major)) {
            return 'UNKNOWN';
        }

        if (empty(trim($minor)) || $minor === 'UNKNOWN') {
            return $major;
        }

        return "{$major}.{$minor}";
    }

    private function buildPortsList(): array
    {
        $ports = [];

        foreach ($this->item->get('Components/SItemPortContainerComponentParams/Ports')?->childNodes ?? [] as $port) {
            $port = (new ItemPort($port))->toArray();

            if ($port !== null) {
                $ports[] = $port;
            }
        }

        return $ports;
    }

    private function processArray(&$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->processArray($value);
            } elseif ($value instanceof BaseFormat) {
                $value = $value->toArray();

                if (is_array($value)) {
                    $this->processArray($value);
                }
            }
        }
    }

    private function removeNullValues($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);
            }
            if ($value === null || (is_array($value) && empty($value))) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * @param  Part[]  $parts
     */
    private function partList(array $parts): \Generator
    {
        $arrayIterator = new RecursiveArrayIterator($parts);
        $recursiveIterator = new RecursiveIteratorIterator($arrayIterator, RecursiveIteratorIterator::SELF_FIRST);

        /** @var Part $part */
        foreach ($recursiveIterator as $part) {
            yield $part;
        }
    }
}
