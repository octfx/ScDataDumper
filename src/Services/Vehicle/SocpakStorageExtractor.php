<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;
use Octfx\ScDataDumper\Services\ItemService;

/**
 * Extract weapon rack and suit locker entities placed inside vehicle interior socpak files.
 *
 * Some ships (e.g. Origin M80) have weapon racks that exist as standalone entities
 * placed inside interior socpak files, but are NOT referenced as Part/ItemPort entries
 * in the vehicle definition XML. This extractor finds those entities by scanning the
 * socpak editor XML for known type prefixes.
 */
final class SocpakStorageExtractor
{
    /** @var list<string> Entity type prefixes for weapon racks */
    private const array WEAPON_RACK_PREFIXES = ['Weapon_Rack_', 'weapon_rack_'];

    /** @var list<string> Entity type prefixes for suit lockers */
    private const array SUIT_LOCKER_PREFIXES = ['Locker_Suit_', 'Useable_suit_locker_', 'SuitLocker_'];

    /** @var array<string, list<array{ClassName: string, InstanceName: string, Section: string}>> */
    private array $socpakCache = [];

    public function __construct(
        private readonly SocpakReader $reader,
        private readonly ?ItemService $itemService = null,
    ) {}

    /**
     * Extract all weapon rack and suit locker storage entities from socpak files
     * referenced by a vehicle entity.
     *
     * @return array{
     *     weapon_racks: list<array{class_name: string, instance_name: string, section: string, slots_total: int, slots_rifle: int, slots_pistol: int}>,
     *     suit_lockers: list<array{class_name: string, instance_name: string, section: string, slots_total: int}>
     * }
     */
    public function extractStorage(VehicleDefinition $entity): array
    {
        $ocRefs = $entity->getAll('Components/VehicleComponentParams/objectContainers/SVehicleObjectContainerParams');

        if ($ocRefs === []) {
            return ['weapon_racks' => [], 'suit_lockers' => []];
        }

        $weaponRacks = [];
        $suitLockers = [];

        foreach ($ocRefs as $ocRef) {
            if (! ($ocRef instanceof Element)) {
                continue;
            }

            $fileName = $ocRef->get('@fileName');
            $boneName = $ocRef->get('@boneName');

            if ($fileName === null || $boneName === null) {
                continue;
            }

            $socpakPath = $this->reader->resolveSocpakPath((string) $fileName);

            if ($socpakPath === null) {
                continue;
            }

            $objects = $this->extractStorageObjectsFromSocpak($socpakPath, (string) $boneName);

            foreach ($objects as $obj) {
                if ($this->isWeaponRack($obj['ClassName'])) {
                    $slots = $this->countWeaponSlots($obj['ClassName']);
                    $weaponRacks[] = [
                        'class_name' => strtolower($obj['ClassName']),
                        'instance_name' => $obj['InstanceName'],
                        'section' => $obj['Section'],
                        ...$slots,
                    ];
                }

                if ($this->isSuitLocker($obj['ClassName'])) {
                    $slots = $this->countSuitSlots($obj['ClassName']);
                    $suitLockers[] = [
                        'class_name' => strtolower($obj['ClassName']),
                        'instance_name' => $obj['InstanceName'],
                        'section' => $obj['Section'],
                        'slots_total' => $slots,
                    ];
                }
            }
        }

        return ['weapon_racks' => $weaponRacks, 'suit_lockers' => $suitLockers];
    }

    /**
     * @return list<array{ClassName: string, InstanceName: string, Section: string}>
     */
    private function extractStorageObjectsFromSocpak(string $socpakPath, string $section): array
    {
        if (isset($this->socpakCache[$socpakPath])) {
            $cached = $this->socpakCache[$socpakPath];

            return array_map(static fn (array $item) => [...$item, 'Section' => $section], $cached);
        }

        $editorXml = $this->reader->extractEditorXml($socpakPath);

        if ($editorXml === null) {
            return $this->socpakCache[$socpakPath] = [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($editorXml);
        $xpath = new DOMXPath($dom);

        $allObjects = $xpath->query('//Object');
        $items = [];

        if ($allObjects === false || $allObjects->length === 0) {
            return $this->socpakCache[$socpakPath] = [];
        }

        foreach ($allObjects as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }

            $type = $node->getAttribute('type');
            $name = $node->getAttribute('name');

            if ($type === '' || ! $this->isStorageType($type)) {
                continue;
            }

            $items[] = [
                'ClassName' => $type,
                'InstanceName' => $name,
                'Section' => $section,
            ];
        }

        $this->socpakCache[$socpakPath] = $items;

        return array_map(static fn (array $item) => [...$item, 'Section' => $section], $items);
    }

    private function isStorageType(string $type): bool
    {
        return $this->isWeaponRack($type) || $this->isSuitLocker($type);
    }

    private function isWeaponRack(string $type): bool
    {
        foreach (self::WEAPON_RACK_PREFIXES as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isSuitLocker(string $type): bool
    {
        foreach (self::SUIT_LOCKER_PREFIXES as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count weapon storage slots by looking up the entity's SItemPortContainerComponentParams.
     *
     * @return array{slots_total: int, slots_rifle: int, slots_pistol: int}
     */
    private function countWeaponSlots(string $className): array
    {
        $defaults = ['slots_total' => 0, 'slots_rifle' => 0, 'slots_pistol' => 0];

        if ($this->itemService === null) {
            return $defaults;
        }

        $entity = $this->itemService->getByClassName($className);

        if ($entity === null) {
            return $defaults;
        }

        $ports = $entity->get('Components/SItemPortContainerComponentParams/Ports');

        if ($ports === null) {
            return $defaults;
        }

        $slotsTotal = 0;
        $slotsRifle = 0;
        $slotsPistol = 0;

        foreach ($ports->children() as $portDef) {
            $types = $this->extractPortTypesFromEntity($portDef);

            if (! $this->isWeaponPersonalPort($types)) {
                continue;
            }

            $maxSize = (float) ($portDef->get('@MaxSize') ?? $portDef->get('@maxSize') ?? 0);

            $slotsTotal++;

            if ($maxSize <= 2) {
                $slotsPistol++;
            } else {
                $slotsRifle++;
            }
        }

        return ['slots_total' => $slotsTotal, 'slots_rifle' => $slotsRifle, 'slots_pistol' => $slotsPistol];
    }

    /**
     * Count suit storage slots by looking up the entity's SItemPortContainerComponentParams.
     */
    private function countSuitSlots(string $className): int
    {
        if ($this->itemService === null) {
            return 0;
        }

        $entity = $this->itemService->getByClassName($className);

        if ($entity === null) {
            return 0;
        }

        $ports = $entity->get('Components/SItemPortContainerComponentParams/Ports');

        if ($ports === null) {
            return 0;
        }

        $count = 0;

        foreach ($ports->children() as $portDef) {
            $types = $this->extractPortTypesFromEntity($portDef);

            if ($this->isSuitStoragePort($types)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Extract port type strings from a SItemPortDef element in an entity class definition.
     *
     * @return list<string>
     */
    private function extractPortTypesFromEntity(Element $portDef): array
    {
        $types = [];

        foreach ($portDef->get('./Types')?->children() ?? [] as $portType) {
            $major = $portType->get('@Type') ?? $portType->get('@type');

            if (empty($major)) {
                continue;
            }

            $subTypesElement = $portType->get('./SubTypes');

            if ($subTypesElement !== null && $subTypesElement->getNode()->childNodes->count() > 0) {
                foreach ($subTypesElement->children() as $subType) {
                    $minor = $subType->get('@value');
                    if (! empty($minor)) {
                        $types[] = "{$major}.{$minor}";
                    }
                }
            } else {
                $types[] = $major;
            }
        }

        return $types;
    }

    /**
     * Detect whether a port accepts personal weapons (any subtype).
     */
    private function isWeaponPersonalPort(array $types): bool
    {
        return array_any($types, fn (string $type) => str_starts_with(strtolower($type), 'weaponpersonal'));
    }

    /**
     * Detect whether a port accepts suit/armor items.
     */
    private function isSuitStoragePort(array $types): bool
    {
        return array_any($types, function (string $type) {
            $lower = strtolower($type);

            return str_starts_with($lower, 'char_armor')
                || str_starts_with($lower, 'suit.');
        });
    }
}
