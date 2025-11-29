<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Item extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): array
    {
        $attach = $this->get();

        $manufacturer = $attach->get('Manufacturer');
        /** @var SCItemManufacturer|null $manufacturer */
        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturer);

        $defaultManufacturer = [
            'Name' => 'Unknown Manufacturer',
            'Code' => 'UNKN',
            'UUID' => '00000000-0000-0000-0000-000000000000',
        ];

        $hasManufacturer = $manufacturer && $manufacturer->get('Localization@__Name') !== '@LOC_PLACEHOLDER';

        $descriptionData = ItemDescriptionParser::parse(
            $attach->get('Localization/English@Description', '')
        );

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
            'required_tags' => $attach->get('RequiredTags'),
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
                'Mass' => $this->item->get('SEntityPhysicsControllerParams.PhysType.SEntityRigidPhysicsControllerParams.Mass', 0),
                'Type' => self::buildTypeName($attach->get('Type', 'UNKNOWN'), $attach->get('SubType', 'UNKNOWN')),
                'Name' => $attach->get('Localization/English@Name', ''),
                'Description' => $attach->get('Localization/English@Description', ''),
                'DescriptionData' => $descriptionData['data'] ?? null,
                'DescriptionText' => $descriptionData['description'] ?? null,
                'Manufacturer' => $hasManufacturer ? [
                    'Code' => $manufacturer->getCode(),
                    'Name' => $manufacturer->get('Localization/English@Name'),
                    'UUID' => $manufacturer->getUuid(),
                ] : $defaultManufacturer,
                'Tags' => $this->item->getTagList(),
                'Ports' => $this->buildPortsList(),

                'Ammunition' => new Ammunition($this->item),
                'Armor' => new Armor($this->item),
                'Bomb' => new Bomb($this->item),
                'CargoGrid' => new CargoGrid($this->item),
                'Cooler' => new Cooler($this->item),
                'DimensionOverrides' => new DimensionOverrides($this->item),
                'Distortion' => new Distortion($this->item),
                'Durability' => new Durability($this->item),
                'Emp' => new EMP($this->item),
                'Food' => new Food($this->item),
                'FuelIntake' => new HydrogenFuelIntake($this->item),
                'FuelTank' => new FuelTank($this->item, 'FuelTank'),
                'HeatConnection' => new HeatConnection($this->item),
                'Helmet' => new Helmet($this->item),
                'Ifcs' => new Ifcs($this->item),
                'Interactions' => new Interactions($this->item),
                'InventoryContainer' => new InventoryContainer($this->item),
                'ResourceContainer' => new ResourceContainer($this->item),
                'MeleeWeapon' => new MeleeWeapon($this->item),
                'Missile' => new Missile($this->item),
                'MissileRack' => new MissileRack($this->item),
                'PowerConnection' => new PowerConnection($this->item),
                'PowerPlant' => new PowerPlant($this->item),
//                'ResourceNetwork' => new ResourceNetwork($this->item),
                'ResourceNetwork' => new ResourceNetworkSimple($this->item),
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

    public static function convertToScu(EntityClassDefinition|Element|null $item): ?float
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

        $loadoutMap = $this->buildLoadoutMap();

        foreach ($this->item->get('Components/SItemPortContainerComponentParams/Ports')?->childNodes ?? [] as $port) {
            $port = (new ItemPort($port, $loadoutMap))->toArray();

            if ($port !== null) {
                $ports[] = $port;
            }
        }

        return $ports;
    }

    /**
     * Extract default loadout entries and map port names to equipped item UUIDs
     *
     * @return array<string, string> Map of lowercase port name to item UUID
     */
    private function buildLoadoutMap(): array
    {
        $loadoutMap = [];

        $loadoutEntries = $this->item->get(
            'Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams/entries'
        );

        if (! $loadoutEntries) {
            return $loadoutMap;
        }

        $itemService = ServiceFactory::getItemService();

        foreach ($loadoutEntries->children() ?? [] as $entry) {
            $entityClassName = $entry->get('@entityClassName');

            if (empty($entityClassName)) {
                continue;
            }

            $itemUuid = $itemService->getUuidByClassName($entityClassName);

            if ($itemUuid === null) {
                continue;
            }

            $portName = $entry->get('@itemPortName');
            if (empty($portName)) {
                continue;
            }

            $loadoutMap[strtolower($portName)] = $itemUuid;
        }

        return $loadoutMap;
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
