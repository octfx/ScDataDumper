<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Item extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): array
    {
        $attach = $this->get();

        $manufacturer = $attach->get('Manufacturer');
        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturer);

        $data = [
            'className' => $this->item->getClassName(),
            'reference' => $this->item->getUuid(),
            'itemName' => strtolower($this->item->getClassName()),
            'type' => $attach->get('Type'),
            'subType' => $attach->get('SubType'),
            'size' => $attach->get('Size'),
            'grade' => $attach->get('Grade'),
            'name' => $attach->get('Localization/English@Name'),
            'tags' => $attach->get('Tags'),
            'manufacturer' => $manufacturer?->getCode(),
            'classification' => $this->item->getClassification(),
            'stdItem' => [
                'UUID' => $this->item->getUuid(),
                'ClassName' => $this->item->getClassName(),
                'Size' => $attach->get('Size', 0),
                'Grade' => $attach->get('Grade', 0),
                'Width' => $attach->get('inventoryOccupancyDimensions@x', 0),
                'Height' => $attach->get('inventoryOccupancyDimensions@z', 0),
                'Length' => $attach->get('inventoryOccupancyDimensions@y', 0),
                'Volume' => self::convertToScu($attach->get('inventoryOccupancyVolume')),
                'Type' => self::buildTypeName($attach->get('Type', 'UNKNOWN'), $attach->get('SubType', 'UNKNOWN')),
                'Name' => $attach->get('Localization/English@Name', ''),
                'Description' => $attach->get('Localization/English@Description', ''),
                'Manufacturer' => $manufacturer ? [
                    'Code' => $manufacturer->getCode(),
                    'Name' => $manufacturer->get('Localization/English@Name'),
                ] : [],
                'Tags' => $this->item->getTagList(),
                'Ports' => $this->buildPortsList(),

                'Ammunition' => new Ammunition($this->item),
                'Armor' => new Armor($this->item),
                'Bomb' => new Bomb($this->item),
                'CargoGrid' => new CargoGrid($this->item),
                'Cooler' => new Cooler($this->item),
                'DimensionOverrides' => new DimensionOverrides($this->item),
                'Durability' => new Durability($this->item),
                'Emp' => new EMP($this->item),
                'Food' => new Food($this->item),
                'FuelIntake' => new HydrogenFuelIntake($this->item),
                'FuelTank' => new FuelTank($this->item, 'FuelTank'),
                'HeatConnection' => new HeatConnection($this->item),
                'Ifcs' => new Ifcs($this->item),
                'InventoryContainer' => new InventoryContainer($this->item),
                'MeleeWeapon' => new MeleeWeapon($this->item),
                'Missile' => new Missile($this->item),
                'MissileRack' => new MissileRack($this->item),
                'PowerConnection' => new PowerConnection($this->item),
                'PowerPlant' => new PowerPlant($this->item),
                'QuantumDrive' => new QuantumDrive($this->item),
                'QuantumFuelTank' => new FuelTank($this->item, 'QuantumFuelTank'),
                'QuantumInterdiction' => new QuantumInterdictionGenerator($this->item),
                'Radar' => new Radar($this->item),
                'Shield' => new Shield($this->item),
                'SuitArmor' => new SuitArmor($this->item),
                'TemperatureResistance' => new TemperatureResistance($this->item),
                'RadiationResistance' => new RadiationResistance($this->item),
                'Thruster' => new Thruster($this->item),
                'Weapon' => new Weapon($this->item),
                'WeaponRegenPool' => new WeaponRegenPool($this->item),
            ],
        ];

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
}
