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

        $manufacturer = $attach->get('@Manufacturer');
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

        $entityTagsXml = $this->item->get('tags')?->children();
        $entityTags = [];

        if ($entityTagsXml !== null) {
            foreach ($entityTagsXml as $tag) {
                if ($tag->getNode()->nodeName === 'Reference') {
                    $entityTags[] = strtolower($tag->get('@value'));
                }
            }
        }

        $entityTagNames = ServiceFactory::getTagDatabaseService();

        $data = [
            'className' => $this->item->getClassName(),
            'reference' => $this->item->getUuid(),
            'itemName' => strtolower($this->item->getClassName()),
            'type' => $attach->get('@Type'),
            'subType' => $attach->get('@SubType'),
            'size' => $attach->get('@Size'),
            'grade' => $attach->get('@Grade'),
            'name' => $attach->get('Localization/English@Name'),
            'tags' => $attach->get('@Tags'),
            'required_tags' => $attach->get('@RequiredTags'),
            'entity_tags' => $entityTags,
            'entity_tag_map' => array_map(static fn ($tag) => [
                'tag' => $tag,
                'name' => $entityTagNames->getTagName($tag),
            ], $entityTags),
            'manufacturer' => $manufacturer?->getCode(),
            'classification' => $this->item->getClassification(),
            'stdItem' => [
                'UUID' => $this->item->getUuid(),
                'ClassName' => $this->item->getClassName(),
                'Size' => $attach->get('@Size', 0),
                'Grade' => $attach->get('@Grade', 0),
                // @deprecated use InventoryOccupancy.GridDimensions.Width, Height, Length
                'Width' => $attach->get('inventoryOccupancyDimensions@x', 0),
                'Height' => $attach->get('inventoryOccupancyDimensions@z', 0),
                'Length' => $attach->get('inventoryOccupancyDimensions@y', 0),
                // @deprecated use InventoryOccupancy.Volume.SCU
                'Volume' => self::convertToScu($attach->get('@inventoryOccupancyVolume')),
                'InventoryOccupancy' => new InventoryOccupancy($this->item),
                'Mass' => $this->item->get('Components/SEntityPhysicsControllerParams/PhysType/SEntityRigidPhysicsControllerParams@Mass', 0),
                'Type' => self::buildTypeName($attach->get('@Type', 'UNKNOWN'), $attach->get('@SubType', 'UNKNOWN')),
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
                'RequiredTags' => $this->item->getRequiredTagList(),
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
                'Emission' => $this->extractEmission(),
                'Food' => new Food($this->item),
                'Medical' => new Medical($this->item),
                'HackingChip' => new HackingChip($this->item),
                'Grenade' => new Grenade($this->item),
                'FuelIntake' => new FuelIntake($this->item),
                'FuelTank' => new FuelTank($this->item, 'FuelTank'),
                'FlightController' => new FlightController($this->item),
                'JumpDrive' => new JumpDrive($this->item),
                'MiningLaser' => new MiningLaser($this->item),
                'MiningModule' => new MiningModule($this->item),
                'HeatConnection' => new HeatConnection($this->item),
                'Temperature' => new Temperature($this->item),
                'Helmet' => new Helmet($this->item),
                'Ifcs' => new Ifcs($this->item),
                'Interactions' => new Interactions($this->item),
                'InventoryContainer' => new InventoryContainer($this->item),
                'ResourceContainer' => new ResourceContainer($this->item),
                'Seat' => new Seat($this->item),
                'MeleeWeapon' => new MeleeWeapon($this->item),
                'Missile' => new Missile($this->item),
                'MissileRack' => new MissileRack($this->item),
                'PowerConnection' => new PowerConnection($this->item),
                'PowerPlant' => new PowerPlant($this->item),
                'ResourceNetwork' => new ResourceNetwork($this->item),
                'QuantumDrive' => new QuantumDrive($this->item),
                'QuantumFuelTank' => new FuelTank($this->item, 'QuantumFuelTank'),
                'QuantumInterdictionGenerator' => new QuantumInterdictionGenerator($this->item),
                'Radar' => new Radar($this->item),
                'SelfDestruct' => new SelfDestruct($this->item),
                'SensorMine' => new SensorMine($this->item),
                'Shield' => new Shield($this->item),
                'ShieldController' => new ShieldController($this->item),
                'SalvageModifier' => new SalvageModifier($this->item),
                'SuitArmor' => new SuitArmor($this->item),
                'TemperatureResistance' => new TemperatureResistance($this->item),
                'RadiationResistance' => new RadiationResistance($this->item),
                'Thruster' => new Thruster($this->item),
                'TractorBeam' => new TractorBeam($this->item),
                'Turret' => new Turret($this->item),
                'Weapon' => new Weapon($this->item),
                'WeaponAttachment' => new WeaponAttachment($this->item),
                'WeaponModifier' => new WeaponModifier($this->item),
                'WeaponRegenPool' => new WeaponRegenPool($this->item),
                'WeaponDefensive' => new WeaponDefensive($this->item),
            ],
        ];

        $this->processArray($data);

        if ($data['type'] === 'PowerPlant' && ! empty($data['stdItem']['Emission']) && ! empty($data['stdItem']['PowerPlant'])) {
            $data['stdItem']['Emission']['Em']['PerSegment'] = round($data['stdItem']['Emission']['Em']['Maximum'] / $data['stdItem']['PowerPlant']['Generation']);
        }

        if ($data['type'] === 'QuantumDrive' && ! empty($data['stdItem']['ResourceNetwork'])) {
            // $data['stdItem']['ResourceNetwork']['Usage']['Power']['Minimum'] = 0;
            $data['stdItem']['ResourceNetwork']['Usage']['Coolant']['Minimum'] = 0;
        }

        return $this->removeNullValues($data);
    }

    /**
     * Extract nominal EM/IR signatures for the item so consumers can access them
     * directly without re-parsing components.
     */
    private function extractEmission(): ?array
    {
        $onlineState = null;

        foreach (($this->item->get('Components/ItemResourceComponentParams/states')?->children() ?? []) as $state) {
            if ($state->get('@name') === 'Online') {
                $onlineState = $state;
                break;
            }
        }

        if ($onlineState === null) {
            return null;
        }

        $em = (float) ($onlineState->get('signatureParams/EMSignature@nominalSignature', 0));
        $emDecay = (float) ($onlineState->get('signatureParams/EMSignature@decayRate', 0));

        $powerDelta = null;

        foreach ($onlineState->get('deltas')?->children() as $delta) {
            if ($delta->get('consumption@resource') === 'Power') {
                $powerDelta = $delta;
                break;
            }
        }

        $minConsumptionFraction = (float) ($powerDelta?->get('@minimumConsumptionFraction', 1) ?? 1);

        $lowPowerRange = null;

        foreach ($onlineState->get('powerRanges')?->children() as $range) {
            if ($range->getNode()->nodeName === 'low' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }

            if ($range->getNode()->nodeName === 'medium' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }

            if ($range->getNode()->nodeName === 'high' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }
        }

        $ir = (float) ($onlineState->get('signatureParams/IRSignature@nominalSignature', 0));
        $startIr = (float) ($this->item->get('Components/HeatController/Signature@StartIREmission', 0));

        $irTotal = $ir + $startIr;

        return [
            'Em' => [
                'Maximum' => $em,
                'Minimum' => round($em * $minConsumptionFraction * $lowPowerRange),
                'Decay' => $emDecay,
            ],
            'Ir' => $irTotal,
        ];
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

        if ($minor === null || trim($minor) === '' || $minor === 'UNKNOWN') {
            return $major;
        }

        return "{$major}.{$minor}";
    }

    private function buildPortsList(): array
    {
        $ports = [];

        $loadoutMap = $this->buildLoadoutMap();

        foreach ($this->item->get('Components/SItemPortContainerComponentParams[not(ancestor::InstalledItem)]/Ports')?->childNodes ?? [] as $port) {
            $port = new ItemPort($port, $loadoutMap)->toArray();

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
            $itemUuid = null;

            $entityClassName = $entry->get('@entityClassName');
            if (! empty($entityClassName)) {
                $itemUuid = $itemService->getUuidByClassName($entityClassName);
            }

            if ($itemUuid === null) {
                $entityClassReference = $entry->get('@entityClassReference');
                if (! empty($entityClassReference) && $entityClassReference !== '00000000-0000-0000-0000-000000000000') {
                    $itemUuid = $entityClassReference;
                }
            }

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
}
