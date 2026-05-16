<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Tests\Fixtures\BuildsTestItems;
use PHPUnit\Framework\TestCase;

final class ItemClassifierServiceTest extends TestCase
{
    use BuildsTestItems;

    private ItemClassifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ItemClassifierService::resetCache();
        $this->service = new ItemClassifierService;
    }

    protected function tearDown(): void
    {
        ItemClassifierService::resetCache();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ //
    //  Null / catch-all                                                   //
    // ------------------------------------------------------------------ //

    public function test_classify_returns_null_for_null_input(): void
    {
        self::assertNull($this->service->classify(null));
    }

    public function test_catch_all_matcher_returns_null_classification(): void
    {
        // A type that doesn't match any specific matcher falls through to *.* which returns null
        $result = $this->service->classify($this->makeClassifiable('SomeRandomType', 'Whatever'));

        self::assertNull($result);
    }

    public function test_classify_returns_cached_result_on_second_call(): void
    {
        $item = $this->makeClassifiable('Cooler', 'Cooler');

        $result1 = $this->service->classify($item);
        $result2 = $this->service->classify($item);

        self::assertSame($result1, $result2);
        self::assertSame('Ship.Cooler.Cooler', $result1);
    }

    public function test_cache_reset_clears_cache(): void
    {
        $this->service->classify($this->makeClassifiable('Radar', 'Radar'));
        ItemClassifierService::resetCache();

        // Verify static cache is cleared by checking the internal state
        $ref = new \ReflectionClass(ItemClassifierService::class);
        self::assertEmpty($ref->getProperty('cache')->getValue());
    }

    public function test_already_classified_array_returns_stored_classification(): void
    {
        $item = [
            'stdItem' => ['ClassName' => 'ALREADY_DONE'],
            'classification' => 'Ship.Weapon.Gun',
        ];

        self::assertSame('Ship.Weapon.Gun', $this->service->classify($item));
    }

    // ------------------------------------------------------------------ //
    //  Ship Weapons                                                       //
    // ------------------------------------------------------------------ //

    public function test_classify_ship_countermeasure(): void
    {
        self::assertSame(
            'Ship.WeaponDefensive.CountermeasureLauncher',
            $this->service->classify($this->makeClassifiable('WeaponDefensive', 'CountermeasureLauncher')),
        );
    }

    public function test_classify_ship_weapon_gun(): void
    {
        self::assertSame(
            'Ship.Weapon.Gun',
            $this->service->classify($this->makeClassifiable('WeaponGun', 'Gun')),
        );
    }

    public function test_classify_ship_weapon_gun_without_sub_type(): void
    {
        // cleanClassification strips trailing .UNDEFINED
        self::assertSame(
            'Ship.Weapon',
            $this->service->classify($this->makeClassifiable('WeaponGun', 'UNDEFINED')),
        );
    }

    public function test_classify_ship_mining_weapon(): void
    {
        // WeaponMining.* classifier: fn($t, $s) => "Ship.Mining.$s"
        self::assertSame(
            'Ship.Mining.Gun',
            $this->service->classify($this->makeClassifiable('WeaponMining', 'Gun')),
        );
    }

    public function test_classify_ship_missile_launcher(): void
    {
        self::assertSame(
            'Ship.MissileLauncher.MissileRack',
            $this->service->classify($this->makeClassifiable('MissileLauncher', 'MissileRack')),
        );
    }

    public function test_classify_ship_missile(): void
    {
        self::assertSame(
            'Ship.Missile.Missile',
            $this->service->classify($this->makeClassifiable('Missile', 'Missile')),
        );
    }

    public function test_classify_ship_turret(): void
    {
        self::assertSame(
            'Ship.Turret.GunTurret',
            $this->service->classify($this->makeClassifiable('Turret', 'GunTurret')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Ship Components                                                    //
    // ------------------------------------------------------------------ //

    public function test_classify_ship_armor(): void
    {
        self::assertSame(
            'Ship.Armor.Armor',
            $this->service->classify($this->makeClassifiable('Armor', 'Armor')),
        );
    }

    public function test_classify_ship_cooler(): void
    {
        self::assertSame(
            'Ship.Cooler.Cooler',
            $this->service->classify($this->makeClassifiable('Cooler', 'Cooler')),
        );
    }

    public function test_classify_ship_emp(): void
    {
        self::assertSame(
            'Ship.EMP.EMP',
            $this->service->classify($this->makeClassifiable('EMP', 'EMP')),
        );
    }

    public function test_classify_ship_power_plant(): void
    {
        self::assertSame(
            'Ship.PowerPlant.PowerPlant',
            $this->service->classify($this->makeClassifiable('PowerPlant', 'PowerPlant')),
        );
    }

    public function test_classify_ship_quantum_drive(): void
    {
        self::assertSame(
            'Ship.QuantumDrive.QuantumDrive',
            $this->service->classify($this->makeClassifiable('QuantumDrive', 'QuantumDrive')),
        );
    }

    public function test_classify_ship_shield(): void
    {
        self::assertSame(
            'Ship.Shield.Shield',
            $this->service->classify($this->makeClassifiable('Shield', 'Shield')),
        );
    }

    public function test_classify_ship_radar(): void
    {
        self::assertSame(
            'Ship.Radar.Radar',
            $this->service->classify($this->makeClassifiable('Radar', 'Radar')),
        );
    }

    public function test_classify_ship_scanner(): void
    {
        self::assertSame(
            'Ship.Scanner.Scanner',
            $this->service->classify($this->makeClassifiable('Scanner', 'Scanner')),
        );
    }

    public function test_classify_ship_ping(): void
    {
        self::assertSame(
            'Ship.Ping.Ping',
            $this->service->classify($this->makeClassifiable('Ping', 'Ping')),
        );
    }

    public function test_classify_ship_transponder(): void
    {
        self::assertSame(
            'Ship.Transponder.Transponder',
            $this->service->classify($this->makeClassifiable('Transponder', 'Transponder')),
        );
    }

    public function test_classify_ship_fuel_intake(): void
    {
        self::assertSame(
            'Ship.FuelIntake.FuelIntake',
            $this->service->classify($this->makeClassifiable('FuelIntake', 'FuelIntake')),
        );
    }

    public function test_classify_ship_fuel_tank(): void
    {
        self::assertSame(
            'Ship.FuelTank.FuelTank',
            $this->service->classify($this->makeClassifiable('FuelTank', 'FuelTank')),
        );
    }

    public function test_classify_ship_quantum_fuel_tank(): void
    {
        self::assertSame(
            'Ship.QuantumFuelTank.QuantumFuelTank',
            $this->service->classify($this->makeClassifiable('QuantumFuelTank', 'QuantumFuelTank')),
        );
    }

    public function test_classify_ship_cargo_grid_ignores_sub_type(): void
    {
        // CargoGrid.* classifier returns fn($t, $s) => "Ship.$t" - no sub type
        self::assertSame(
            'Ship.CargoGrid',
            $this->service->classify($this->makeClassifiable('CargoGrid', 'UNDEFINED')),
        );
    }

    public function test_classify_ship_self_destruct_ignores_sub_type(): void
    {
        self::assertSame(
            'Ship.SelfDestruct',
            $this->service->classify($this->makeClassifiable('SelfDestruct', 'UNDEFINED')),
        );
    }

    public function test_classify_ship_life_support(): void
    {
        self::assertSame(
            'Ship.LifeSupportGenerator',
            $this->service->classify($this->makeClassifiable('LifeSupportGenerator', 'UNDEFINED')),
        );
    }

    public function test_classify_ship_weapon_regen_pool(): void
    {
        self::assertSame(
            'Ship.WeaponRegenPool.WeaponRegenPool',
            $this->service->classify($this->makeClassifiable('WeaponRegenPool', 'WeaponRegenPool')),
        );
    }

    public function test_classify_ship_thruster_main(): void
    {
        self::assertSame(
            'Ship.MainThruster.MainThruster',
            $this->service->classify($this->makeClassifiable('MainThruster', 'MainThruster')),
        );
    }

    public function test_classify_ship_thruster_maneuver(): void
    {
        self::assertSame(
            'Ship.ManneuverThruster.ManneuverThruster',
            $this->service->classify($this->makeClassifiable('ManneuverThruster', 'ManneuverThruster')),
        );
    }

    public function test_classify_ship_container_cargo(): void
    {
        self::assertSame(
            'Ship.Container.Cargo',
            $this->service->classify($this->makeClassifiable('Container', 'Cargo')),
        );
    }

    public function test_classify_ship_flair_wall(): void
    {
        // Flair_Wall.* classifier: fn($t, $s) => "Ship.Flair.$t.$s"
        // cleanClassification strips trailing .UNDEFINED
        self::assertSame(
            'Ship.Flair.Flair_Wall',
            $this->service->classify($this->makeClassifiable('Flair_Wall', 'UNDEFINED')),
        );
    }

    public function test_classify_ship_flight_controller(): void
    {
        self::assertSame(
            'Ship.FlightController.FlightController',
            $this->service->classify($this->makeClassifiable('FlightController', 'FlightController')),
        );
    }

    public function test_classify_ship_quantum_interdiction_generator(): void
    {
        self::assertSame(
            'Ship.QuantumInterdictionGenerator.QuantumInterdictionGenerator',
            $this->service->classify($this->makeClassifiable('QuantumInterdictionGenerator', 'QuantumInterdictionGenerator')),
        );
    }

    // ------------------------------------------------------------------ //
    //  FPS Weapons                                                        //
    // ------------------------------------------------------------------ //

    public function test_classify_fps_weapon(): void
    {
        self::assertSame(
            'FPS.Weapon.AssaultRifle',
            $this->service->classify($this->makeClassifiable('WeaponPersonal', 'AssaultRifle')),
        );
    }

    public function test_classify_fps_iron_sight(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.IronSight',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'IronSight')),
        );
    }

    public function test_classify_fps_magazine(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.Magazine',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'Magazine')),
        );
    }

    public function test_classify_fps_utility_attachment(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.Utility',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'Utility')),
        );
    }

    public function test_classify_fps_bottom_attachment(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.BottomAttachment',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'BottomAttachment')),
        );
    }

    public function test_classify_fps_missile_attachment(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.Missile',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'Missile')),
        );
    }

    public function test_classify_fps_light_weapon(): void
    {
        self::assertSame(
            'FPS.WeaponAttachment.Light',
            $this->service->classify($this->makeClassifiable('Light', 'Weapon')),
        );
    }

    public function test_classify_ship_barrel_on_ship_path(): void
    {
        $item = $this->makeClassifiable('WeaponAttachment', 'Barrel', path: 'objects/spaceships/weapons/gun.xml');

        self::assertSame('Ship.WeaponAttachment.Barrel', $this->service->classify($item));
    }

    public function test_classify_fps_barrel_with_fps_tag(): void
    {
        $item = $this->makeClassifiable('WeaponAttachment', 'Barrel', tags: 'FPS_Barrel weapon');

        self::assertSame('FPS.WeaponAttachment.BarrelAttachment', $this->service->classify($item));
    }

    public function test_classify_barrel_without_ship_path_and_no_fps_tag_falls_through(): void
    {
        // WeaponAttachment.Barrel without ship path and without FPS tag
        // falls through the remaining matchers, eventually hitting the catch-all *.*
        $item = $this->makeClassifiable('WeaponAttachment', 'Barrel', path: 'objects/characters/weapons/rifle.xml');

        // It will match the WeaponAttachment.FiringMechanism? No, that's a different sub-type.
        // It will NOT match any specific barrel matcher (no ship path, no FPS tag).
        // It falls to the catch-all matcher which returns null.
        self::assertNull($this->service->classify($item));
    }

    public function test_classify_ship_firing_mechanism(): void
    {
        self::assertSame(
            'Ship.WeaponAttachment.FiringMechanism',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'FiringMechanism')),
        );
    }

    public function test_classify_ship_power_array(): void
    {
        self::assertSame(
            'Ship.WeaponAttachment.PowerArray',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'PowerArray')),
        );
    }

    public function test_classify_ship_ventilation(): void
    {
        self::assertSame(
            'Ship.WeaponAttachment.Ventilation',
            $this->service->classify($this->makeClassifiable('WeaponAttachment', 'Ventilation')),
        );
    }

    // ------------------------------------------------------------------ //
    //  FPS Armor                                                          //
    // ------------------------------------------------------------------ //

    public function test_classify_fps_armor_arms(): void
    {
        self::assertSame(
            'FPS.Armor.Arms',
            $this->service->classify($this->makeClassifiable('Char_Armor_Arms', 'UNDEFINED')),
        );
    }

    public function test_classify_fps_armor_helmet(): void
    {
        self::assertSame(
            'FPS.Armor.Helmet',
            $this->service->classify($this->makeClassifiable('Char_Armor_Helmet', 'UNDEFINED')),
        );
    }

    public function test_classify_fps_armor_legs(): void
    {
        self::assertSame(
            'FPS.Armor.Legs',
            $this->service->classify($this->makeClassifiable('Char_Armor_Legs', 'UNDEFINED')),
        );
    }

    public function test_classify_fps_armor_torso(): void
    {
        self::assertSame(
            'FPS.Armor.Torso',
            $this->service->classify($this->makeClassifiable('Char_Armor_Torso', 'UNDEFINED')),
        );
    }

    public function test_classify_fps_armor_undersuit(): void
    {
        self::assertSame(
            'FPS.Armor.Undersuit',
            $this->service->classify($this->makeClassifiable('Char_Armor_Undersuit', 'UNDEFINED')),
        );
    }

    public function test_classify_fps_armor_backpack(): void
    {
        self::assertSame(
            'FPS.Armor.Backpack',
            $this->service->classify($this->makeClassifiable('Char_Armor_Backpack', 'UNDEFINED')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Clothing                                                           //
    // ------------------------------------------------------------------ //

    public function test_classify_clothing_torso_0(): void
    {
        self::assertSame(
            'FPS.Clothing.Torso',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Torso_0', 'UNDEFINED')),
        );
    }

    public function test_classify_clothing_torso_1(): void
    {
        self::assertSame(
            'FPS.Clothing.Torso',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Torso_1', 'UNDEFINED')),
        );
    }

    public function test_classify_clothing_hat(): void
    {
        self::assertSame(
            'FPS.Clothing.Hat',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Hat', 'UNDEFINED')),
        );
    }

    public function test_classify_clothing_legs(): void
    {
        self::assertSame(
            'FPS.Clothing.Legs',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Legs', 'UNDEFINED')),
        );
    }

    public function test_classify_clothing_feet(): void
    {
        self::assertSame(
            'FPS.Clothing.Shoes',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Feet', 'UNDEFINED')),
        );
    }

    public function test_classify_clothing_hands(): void
    {
        self::assertSame(
            'FPS.Clothing.Gloves',
            $this->service->classify($this->makeClassifiable('Char_Clothing_Hands', 'UNDEFINED')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Consumables                                                        //
    // ------------------------------------------------------------------ //

    public function test_classify_consumable_medical(): void
    {
        self::assertSame(
            'FPS.Consumable.Medical',
            $this->service->classify($this->makeClassifiable('FPS_Consumable', 'Medical')),
        );
    }

    public function test_classify_consumable_medpack(): void
    {
        self::assertSame(
            'FPS.Consumable.Medical',
            $this->service->classify($this->makeClassifiable('FPS_Consumable', 'MedPack')),
        );
    }

    public function test_classify_consumable_hacking(): void
    {
        self::assertSame(
            'FPS.Consumable.Hacking',
            $this->service->classify($this->makeClassifiable('FPS_Consumable', 'Hacking')),
        );
    }

    public function test_classify_food(): void
    {
        self::assertSame(
            'FPS.Consumable.Food.Food',
            $this->service->classify($this->makeClassifiable('Food', 'UNDEFINED')),
        );
    }

    public function test_classify_drink(): void
    {
        self::assertSame(
            'FPS.Consumable.Food.Drink',
            $this->service->classify($this->makeClassifiable('Drink', 'UNDEFINED')),
        );
    }

    public function test_classify_bottle(): void
    {
        self::assertSame(
            'FPS.Consumable.Food.Bottle',
            $this->service->classify($this->makeClassifiable('Bottle', 'UNDEFINED')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Mining                                                             //
    // ------------------------------------------------------------------ //

    public function test_classify_mining_gadget_with_tag(): void
    {
        $item = $this->makeClassifiable('Gadget', 'Gadget', tags: 'mining_gadget');

        self::assertSame('Mining.Gadget', $this->service->classify($item));
    }

    public function test_classify_mining_gadget_without_tag_falls_through(): void
    {
        $item = $this->makeClassifiable('Gadget', 'Gadget', tags: 'other_tag');

        self::assertNull($this->service->classify($item));
    }

    public function test_classify_mining_module(): void
    {
        self::assertSame(
            'Mining.Module',
            $this->service->classify($this->makeClassifiable('MiningModifier', 'UNDEFINED')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Clean classification                                               //
    // ------------------------------------------------------------------ //

    public function test_clean_classification_strips_undefined_suffix(): void
    {
        // CargoGrid classifier: fn($t, $s) => "Ship.$t" - with UNDEFINED sub-type
        // But cleanClassification strips .UNDEFINED from the result
        $result = $this->service->classify($this->makeClassifiable('CargoGrid', 'UNDEFINED'));

        self::assertSame('Ship.CargoGrid', $result);
    }

    public function test_clean_classification_keeps_non_undefined(): void
    {
        self::assertSame(
            'Ship.Weapon.Gun',
            $this->service->classify($this->makeClassifiable('WeaponGun', 'Gun')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Type matching is case-insensitive                                  //
    // ------------------------------------------------------------------ //

    public function test_type_matching_is_case_insensitive(): void
    {
        // typeMatch uses strcasecmp (case-insensitive), but the classifier passes through
        // the raw type/sub-type strings from input, so case is preserved in output
        self::assertSame(
            'Ship.cooler.cooler',
            $this->service->classify($this->makeClassifiable('cooler', 'cooler')),
        );
    }

    // ------------------------------------------------------------------ //
    //  Paints                                                             //
    // ------------------------------------------------------------------ //

    public function test_classify_ship_paints(): void
    {
        // Paints.* classifier returns fn($t, $s) => "Ship.$t.$s"
        // but cleanClassification strips the trailing .UNDEFINED
        self::assertSame(
            'Ship.Paints',
            $this->service->classify($this->makeClassifiable('Paints', 'UNDEFINED')),
        );
    }
}
