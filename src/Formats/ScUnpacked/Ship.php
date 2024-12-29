<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): array
    {
        $attach = $this->get();
        $vehicleComponent = $this->get('Components/VehicleComponentParams');

        $manufacturer = $vehicleComponent->get('Manufacturer');
        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturer);

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
        ];

//        $this->processArray($data);

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
}
