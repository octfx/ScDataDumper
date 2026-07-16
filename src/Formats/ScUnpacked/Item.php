<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Formats\LazyFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\ResolvesEventSource;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @extends BaseFormat<EntityClassDefinition>
 */
final class Item extends BaseFormat
{
    use ResolvesEventSource;

    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): array
    {
        $attach = $this->get();

        /** @var SCItemManufacturer|null $manufacturer */
        $manufacturer = $this->item->getManufacturer();

        $defaultManufacturer = [
            'Name' => 'Unknown Manufacturer',
            'Code' => 'UNKN',
            'UUID' => '00000000-0000-0000-0000-000000000000',
        ];

        $wikiFacts = ServiceFactory::getDataOverrideService()->factsFor($this->item->getUuid());
        $resolvedManufacturer = ServiceFactory::getManufacturerService()
            ->resolveForEntity($manufacturer, $wikiFacts['manufacturer'] ?? null);

        $name = $wikiFacts['name'] ?? $attach->get('Localization@Name');
        $description = $attach->get('Localization@Description') ?? '';
        $descriptionData = ItemDescriptionParser::parse($description);

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

        $rarity = null;
        $rarityOrder = ['Legendary', 'Epic', 'Rare', 'Uncommon', 'Common'];
        $resolvedTagNames = array_map(
            static fn ($uuid) => $entityTagNames->getTagName($uuid),
            $entityTags,
        );
        foreach ($rarityOrder as $rarityTag) {
            if (in_array($rarityTag, $resolvedTagNames, true)) {
                $rarity = $rarityTag;
                break;
            }
        }

        $data = [
            'className' => $this->item->getClassName(),
            'reference' => $this->item->getUuid(),
            'itemName' => strtolower($this->item->getClassName()),
            'type' => $attach->get('@Type'),
            'subType' => $attach->get('@SubType'),
            'size' => $attach->get('@Size'),
            'grade' => $attach->get('@Grade'),
            'name' => $name !== '' ? $name : null,
            'tags' => $attach->get('@Tags'),
            'required_tags' => $attach->get('@RequiredTags'),
            'entity_tags' => $entityTags,
            'entity_tag_map' => array_map(static fn ($tag) => [
                'tag' => $tag,
                'name' => $entityTagNames->getTagName($tag),
            ], $entityTags),
            'manufacturer' => $resolvedManufacturer['code'] ?? null,
            'classification' => $this->item->getClassification(),
            'event_source' => self::resolveEventSource($this->item->getClassName(), $this->item->getTagList(), $this->item->getUuid()),
            'ammo_feed' => $this->buildAmmoFeed(),
            'stdItem' => [
                'UUID' => $this->item->getUuid(),
                'ClassName' => $this->item->getClassName(),
                'Size' => $attach->get('@Size', 0),
                'Grade' => $attach->get('@Grade', 0),
                // @deprecated use InventoryOccupancy.CargoGrid.Width, Length, Height
                'Width' => $attach->get('inventoryOccupancyDimensions@x', 0),
                'Height' => $attach->get('inventoryOccupancyDimensions@z', 0),
                'Length' => $attach->get('inventoryOccupancyDimensions@y', 0),
                // @deprecated use InventoryOccupancy.Volume.SCU
                'Volume' => self::convertToScu($attach->get('@inventoryOccupancyVolume')),
                'InventoryOccupancy' => new LazyFormat(fn () => new InventoryOccupancy($this->item)),
                'Mass' => $this->item->getMass(),
                'Type' => self::buildTypeName($attach->get('@Type', 'UNKNOWN'), $attach->get('@SubType', 'UNKNOWN')),
                'Name' => $name,
                'Description' => $description,
                'DescriptionData' => $descriptionData['data'] ?? null,
                'DescriptionText' => $descriptionData['description'] ?? null,
                'Manufacturer' => $resolvedManufacturer !== null
                    ? [
                        'Code' => $resolvedManufacturer['code'],
                        'Name' => $resolvedManufacturer['name'],
                        'UUID' => $resolvedManufacturer['uuid'],
                    ]
                    : $defaultManufacturer,
                'Tags' => $this->item->getTagList(),
                'RequiredTags' => $this->item->getRequiredTagList(),
                'Rarity' => $rarity,
                'Ports' => $this->buildPortsList(),

                'Ammunition' => new LazyFormat(fn () => new Ammunition($this->item)),
                'Armor' => new LazyFormat(fn () => new Armor($this->item)),
                'Bomb' => new LazyFormat(fn () => new Bomb($this->item)),
                'CargoGrid' => new LazyFormat(fn () => new CargoGrid($this->item)),
                'Cooler' => new LazyFormat(fn () => new Cooler($this->item)),
                'DimensionOverrides' => new LazyFormat(fn () => new DimensionOverrides($this->item)),
                'Distortion' => new LazyFormat(fn () => new Distortion($this->item)),
                'Durability' => new LazyFormat(fn () => new Durability($this->item)),
                'Emp' => new LazyFormat(fn () => new EMP($this->item)),
                'Emission' => $this->extractEmission(),
                'Food' => new LazyFormat(fn () => new Food($this->item)),
                'Medical' => new LazyFormat(fn () => new Medical($this->item)),
                'HackingChip' => new LazyFormat(fn () => new HackingChip($this->item)),
                'Grenade' => new LazyFormat(fn () => new Grenade($this->item)),
                'FuelIntake' => new LazyFormat(fn () => new FuelIntake($this->item)),
                'FuelTank' => new LazyFormat(fn () => new FuelTank($this->item, 'FuelTank')),
                'FlightController' => new LazyFormat(fn () => new FlightController($this->item)),
                'JumpDrive' => new LazyFormat(fn () => new JumpDrive($this->item)),
                'MiningLaser' => new LazyFormat(fn () => new MiningLaser($this->item)),
                'Mineable' => new LazyFormat(fn () => new Mineable($this->item)),
                'MiningModule' => new LazyFormat(fn () => new MiningModule($this->item)),
                'HeatConnection' => new LazyFormat(fn () => new HeatConnection($this->item)),
                'Temperature' => new LazyFormat(fn () => new Temperature($this->item)),
                'Helmet' => new LazyFormat(fn () => new Helmet($this->item)),
                'Ifcs' => new LazyFormat(fn () => new Ifcs($this->item)),
                'Interactions' => new LazyFormat(fn () => new Interactions($this->item)),
                'InventoryContainer' => new LazyFormat(fn () => new InventoryContainer($this->item)),
                'ResourceContainer' => new LazyFormat(fn () => new ResourceContainer($this->item)),
                'Seat' => new LazyFormat(fn () => new Seat($this->item)),
                'MeleeWeapon' => new LazyFormat(fn () => new MeleeWeapon($this->item)),
                'Missile' => new LazyFormat(fn () => new Missile($this->item)),
                'MissileRack' => new LazyFormat(fn () => new MissileRack($this->item)),
                'PowerConnection' => new LazyFormat(fn () => new PowerConnection($this->item)),
                'PowerPlant' => new LazyFormat(fn () => new PowerPlant($this->item)),
                'ResourceNetwork' => new LazyFormat(fn () => new ResourceNetwork($this->item)),
                'QuantumDrive' => new LazyFormat(fn () => new QuantumDrive($this->item)),
                'QuantumFuelTank' => new LazyFormat(fn () => new FuelTank($this->item, 'QuantumFuelTank')),
                'QuantumInterdictionGenerator' => new LazyFormat(fn () => new QuantumInterdictionGenerator($this->item)),
                'Radar' => new LazyFormat(fn () => new Radar($this->item)),
                'SelfDestruct' => new LazyFormat(fn () => new SelfDestruct($this->item)),
                'SensorMine' => new LazyFormat(fn () => new SensorMine($this->item)),
                'Shield' => new LazyFormat(fn () => new Shield($this->item)),
                'ShieldController' => new LazyFormat(fn () => new ShieldController($this->item)),
                'SalvageModifier' => new LazyFormat(fn () => new SalvageModifier($this->item)),
                'SuitArmor' => new LazyFormat(fn () => new SuitArmor($this->item)),
                'TemperatureResistance' => new LazyFormat(fn () => new TemperatureResistance($this->item)),
                'RadiationResistance' => new LazyFormat(fn () => new RadiationResistance($this->item)),
                'GForceResistance' => new LazyFormat(fn () => new GForceResistance($this->item)),
                'Thruster' => new LazyFormat(fn () => new Thruster($this->item)),
                'TractorBeam' => new LazyFormat(fn () => new TractorBeam($this->item)),
                'Turret' => new LazyFormat(fn () => new Turret($this->item)),
                'Weapon' => new LazyFormat(fn () => new Weapon($this->item)),
                'WeaponAttachment' => new LazyFormat(fn () => new WeaponAttachment($this->item)),
                'WeaponModifier' => new LazyFormat(fn () => new WeaponModifier($this->item)),
                'WeaponRegenPool' => new LazyFormat(fn () => new WeaponRegenPool($this->item)),
                'WeaponDefensive' => new LazyFormat(fn () => new WeaponDefensive($this->item)),
            ],
        ];

        $this->processArray($data);

        $generation = Arr::get($data, 'stdItem.ResourceNetwork.Generation.Power');
        if ($data['type'] === 'PowerPlant' && ! empty($data['stdItem']['Emission']) && $generation > 0) {
            $data['stdItem']['Emission']['Em']['PerSegment'] = round($data['stdItem']['Emission']['Em']['Maximum'] / $generation);
        }

        if ($data['type'] === 'QuantumDrive' && ! empty($data['stdItem']['ResourceNetwork'])) {
            // $data['stdItem']['ResourceNetwork']['Usage']['Power']['Minimum'] = 0;
            $data['stdItem']['ResourceNetwork']['Usage']['Coolant']['Minimum'] = 0;
        }

        return $this->removeNullValues($data);
    }

    /**
     * Backpack with ammo feed like cds_combat_superheavy_backpack
     *
     * @return array{magazine_reference: ?string, magazine_class_name: ?string}|null
     */
    private function buildAmmoFeed(): ?array
    {
        if (! $this->item->hasAmmoContainerFeeder()) {
            return null;
        }

        return [
            'magazine_reference' => $this->item->getAmmoFeedMagazineReference(),
            'magazine_class_name' => $this->item->getAmmoFeedMagazine()?->getClassName(),
        ];
    }

    /**
     * Extract nominal EM/IR signatures for the item so consumers can access them
     * directly without re-parsing components.
     */
    private function extractEmission(): ?array
    {
        $onlineState = $this->item->getResourceOnlineState();

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
        $itemService = ServiceFactory::getItemService();

        foreach ($this->item->getDefaultLoadoutEntries() as $entry) {
            $portName = $entry->getPortName();
            if ($portName === null || $portName === '') {
                continue;
            }

            $itemUuid = $entry->getInstalledItem()?->getUuid()
                ?? $entry->getEntityClassReference()
                ?? (($className = $entry->getEntityClassName()) ? $itemService->getUuidByClassName($className) : null);

            if ($itemUuid === null || $itemUuid === '') {
                continue;
            }

            $loadoutMap[strtolower($portName)] = $itemUuid;
        }

        return $loadoutMap;
    }

    private function processArray(&$array): void
    {
        foreach ($array as &$value) {
            if ($value instanceof LazyFormat) {
                $value = $value->toArray();

                if (is_array($value)) {
                    $this->processArray($value);
                }
            } elseif (is_array($value)) {
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
