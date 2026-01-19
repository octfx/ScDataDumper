<?php

namespace Octfx\ScDataDumper\Services;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;

final class ItemClassifierService
{
    private readonly array $matchers;

    private static array $cache = [];

    public function __construct()
    {
        $this->matchers = [
            // Ship weapons
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponDefensive.CountermeasureLauncher'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponGun.*'),
                'Classifier' => fn ($t, $s) => "Ship.Weapon.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponMining.*'),
                'Classifier' => fn ($t, $s) => "Ship.Mining.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Barrel') && self::pathMatch($item, 'ships/weapons'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.FiringMechanism'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.PowerArray'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Ventilation'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'MissileLauncher.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Missile.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Turret.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],

            // Ship components
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Armor.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Cooler.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'EMP.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FlightController.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Flair_Wall.*'),
                'Classifier' => fn ($t, $s) => "Ship.Flair.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Flair_Floor.*'),
                'Classifier' => fn ($t, $s) => "Ship.Flair.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Flair_Surface.*'),
                'Classifier' => fn ($t, $s) => "Ship.Flair.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Flair_Cockpit.*'),
                'Classifier' => fn ($t, $s) => "Ship.Flair.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'PowerPlant.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'QuantumDrive.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'QuantumInterdictionGenerator.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Radar.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Scanner.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Ping.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Transponder.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Shield.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Paints.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponRegenPool.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'ManneuverThruster.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'MainThruster.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'SelfDestruct.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FuelIntake.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FuelTank.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'QuantumFuelTank.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'CargoGrid.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t",
            ],
            //            [
            //                'Matcher' => fn ($item) => self::typeMatch($item, 'Cargo.*'),
            //                'Classifier' => fn ($t, $s) => 'Ship.CargoGrid',
            //            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Container.Cargo'),
                'Classifier' => fn ($t, $s) => "Ship.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'LifeSupportGenerator.*'),
                'Classifier' => fn ($t, $s) => "Ship.$t",
            ],

            // FPS weapons
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponPersonal.*'),
                'Classifier' => fn ($t, $s) => "FPS.Weapon.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Barrel') && self::tagMatch($item, 'FPS_Barrel'),
                'Classifier' => fn ($t, $s) => 'FPS.WeaponAttachment.BarrelAttachment',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.IronSight'),
                'Classifier' => fn ($t, $s) => "FPS.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Magazine'),
                'Classifier' => fn ($t, $s) => "FPS.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Utility'),
                'Classifier' => fn ($t, $s) => "FPS.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.BottomAttachment'),
                'Classifier' => fn ($t, $s) => "FPS.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'WeaponAttachment.Missile'),
                'Classifier' => fn ($t, $s) => "FPS.$t.$s",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Light.Weapon'),
                'Classifier' => fn ($t, $s) => 'FPS.WeaponAttachment.Light',
            ],

            // FPS armor
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Arms.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Arms',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Helmet.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Helmet',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Legs.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Legs',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Torso.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Torso',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Undersuit.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Undersuit',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Armor_Backpack.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Armor.Backpack',
            ],

            // Clothing
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Torso_0.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Torso',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Torso_1.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Torso',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Hat.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Hat',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Legs.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Legs',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Feet.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Shoes',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Char_Clothing_Hands.*'),
                'Classifier' => fn ($t, $s) => 'FPS.Clothing.Gloves',
            ],

            // Consumables
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FPS_Consumable.Medical'),
                'Classifier' => fn ($t, $s) => 'FPS.Consumable.Medical',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FPS_Consumable.MedPack'),
                'Classifier' => fn ($t, $s) => 'FPS.Consumable.Medical',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'FPS_Consumable.Hacking'),
                'Classifier' => fn ($t, $s) => 'FPS.Consumable.Hacking',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Food.*'),
                'Classifier' => fn ($t, $s) => "FPS.Consumable.Food.$t",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Drink.*'),
                'Classifier' => fn ($t, $s) => "FPS.Consumable.Food.$t",
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Bottle.*'),
                'Classifier' => fn ($t, $s) => "FPS.Consumable.Food.$t",
            ],

            // Mining
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'Gadget.Gadget') && self::tagMatch($item, 'mining_gadget'),
                'Classifier' => fn ($t, $s) => 'Mining.Gadget',
            ],
            [
                'Matcher' => fn ($item) => self::typeMatch($item, 'MiningModifier.*'),
                'Classifier' => fn ($t, $s) => 'Mining.Module',
            ],

            // Default catch all
            [
                'Matcher' => fn ($item) => self::typeMatch($item, '*.*'),
                'Classifier' => fn ($t, $s) => null,
            ],
        ];
    }

    private static function typeMatch($entity, $typePattern): bool
    {
        [$entityType, $entitySubType] = self::getTypeAndSubType($entity);

        $patternSplit = explode('.', $typePattern, 2);
        $type = $patternSplit[0];
        if ($type === '*') {
            $type = null;
        }

        $subType = $patternSplit[1] ?? null;
        if ($subType === '*') {
            $subType = null;
        }

        if (! empty($type)) {
            if ($entityType === null || strcasecmp($type, (string) $entityType) !== 0) {
                return false;
            }
        }

        if (! empty($subType)) {
            if ($entitySubType === null || strcasecmp($subType, (string) $entitySubType) !== 0) {
                return false;
            }
        }

        return true;
    }

    private static function tagMatch($entity, $tag): bool
    {
        if (is_array($entity)) {
            $tagList = Arr::get($entity, 'Components.SAttachableComponentParams.AttachDef.Tags', '');
            if ($tagList === '' || $tagList === null) {
                $tagList = Arr::get($entity, 'tags', '');
            }
        } else {
            if (method_exists($entity, 'getTagList')) {
                return in_array($tag, $entity->getTagList(), true);
            }

            $tagList = $entity->get('Components/SAttachableComponentParams/AttachDef@Tags');
            $tagList = $tagList ? (string) $tagList : '';
        }

        $split = is_array($tagList) ? $tagList : explode(' ', $tagList);

        return in_array($tag, $split, true);
    }

    private static function pathMatch($entity, $search): bool
    {
        if (is_array($entity)) {
            $fullPath = Arr::get($entity, '__path', '');
        } else {
            $fullPath = $entity->getPath();
        }

        return str_contains($fullPath, $search);
    }

    private static function getTypeAndSubType(EntityClassDefinition|array $entity): array
    {
        if (is_array($entity)) {
            $type = Arr::get($entity, 'Components.SAttachableComponentParams.AttachDef.Type');
            $subType = Arr::get($entity, 'Components.SAttachableComponentParams.AttachDef.SubType');
        } else {
            $type = $entity->getAttachType();
            $subType = $entity->getAttachSubType();
        }

        return [$type, $subType];
    }

    private static function buildCacheKey(EntityClassDefinition|array $entity, ?string $type, ?string $subType): string
    {
        $base = (string) $type.'.'.(string) $subType;

        // Some matchers depend on tags or paths, so the cache key must include that context.
        if (strcasecmp((string) $type, 'WeaponAttachment') === 0 && strcasecmp((string) $subType, 'Barrel') === 0) {
            $isShipPath = self::pathMatch($entity, 'ships/') ? 'ship' : 'nonship';

            return $base.'|'.$isShipPath;
        }

        return $base;
    }

    public function classify(null|EntityClassDefinition|array $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        // Already classified entity
        if (is_array($entity) && Arr::has($entity, 'stdItem')) {
            return Arr::get($entity, 'classification');
        }

        [$type, $subType] = self::getTypeAndSubType($entity);
        $cacheIndex = self::buildCacheKey($entity, $type, $subType);

        if (isset(self::$cache[$cacheIndex])) {
            $matcher = $this->matchers[self::$cache[$cacheIndex]];

            return $this->cleanClassification($matcher['Classifier']($type, $subType));
        }

        foreach ($this->matchers as $key => $match) {
            if (! $match['Matcher']($entity)) {
                continue;
            }

            $classification = $this->cleanClassification($match['Classifier']($type, $subType));

            self::$cache[$cacheIndex] = $key;

            return $classification;
        }

        return null;
    }

    private function cleanClassification(?string $classification): ?string
    {
        if ($classification === null) {
            return null;
        }

        if (str_ends_with($classification, '.UNDEFINED')) {
            $classification = substr($classification, 0, -10);
        }

        return $classification;
    }
}
