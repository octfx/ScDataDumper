<?php

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\Formats\ScUnpacked\ItemPort;

class PortClassifierService
{
    /**
     * Classifies a port based on its characteristics.
     * The order of checks is very important to handle obscure corner cases.
     *
     * @param  array|null  $port  The port to classify
     * @return array An array with two elements - major category and specific category
     */
    public function classifyPort(?array $port, ?array $installedItem): array
    {
        /*
            The order here is very important to try and catch obscure corner cases
        */

        if ($port === null || (empty($port['Types']) && empty($installedItem))) {
            return ['DISABLED', 'DISABLED'];
        }

        // Tractor beams
        if (self::fuzzyNameMatch($port, 'tractor')) {
            return ['Utility', 'Utility hardpoints'];
        }

        // Utility hardpoints
        if (self::fuzzyNameMatch($port, 'utility')) {
            return ['Utility', 'Utility hardpoints'];
        }

        if ($port['Uneditable'] && ! empty($installedItem)) {
            $guess = $this->guessByInstalledItem($installedItem);
            if ($guess !== null) {
                return $guess;
            }
        }

        // Mining
        if (ItemPort::accepts($port, 'WeaponMining.Gun')) {
            return ['Mining', 'Mining hardpoints'];
        }
        if (ItemPort::accepts($port, 'MiningArm')) {
            return ['Mining', 'Mining arm'];
        }

        if (ItemPort::accepts($port, 'Turret.*')) {
            return ['Weapons', self::fuzzyNameMatch($port, 'remote', $installedItem) ? 'Remote turrets' : 'Weapon hardpoints'];
        }

        if (ItemPort::accepts($port, 'TurretBase.MannedTurret')) {
            if (! empty($port['InstalledItem']['Ports']) &&
                array_filter($port['InstalledItem']['Ports'], static function ($x) {
                    return $x['InstalledItem']['Type'] === 'WeaponMining.Gun';
                })
            ) {
                return ['Mining', 'Mining turrets']; // Argo Mole
            }

            if (self::fuzzyNameMatch($port, 'tractor', $installedItem)) {
                return ['Utility', 'Utility turrets'];
            }

            return ['Weapons', 'Manned turrets'];
        }

        // Weapons
        if (ItemPort::accepts($port, 'MissileLauncher.MissileRack')) {
            return ['Weapons', 'Missile racks'];
        }
        if (ItemPort::accepts($port, 'WeaponGun')) {
            return ['Weapons', 'Weapon hardpoints'];
        }
        if (ItemPort::accepts($port, 'Missile.Missile')) {
            return ['Weapons', 'Missiles'];
        }
        if (ItemPort::accepts($port, 'EMP')) {
            return ['Weapons', 'EMP hardpoints'];
        }
        if (ItemPort::accepts($port, 'WeaponDefensive.CountermeasureLauncher')) {
            return ['Weapons', 'Countermeasures'];
        }
        if (ItemPort::accepts($port, 'QuantumInterdictionGenerator')) {
            return ['Weapons', 'QIG hardpoints'];
        }
        if (ItemPort::accepts($port, 'WeaponDefensive')) {
            return ['Weapons', 'Defensive hardpoints'];
        }

        // Systems
        if (ItemPort::accepts($port, 'PowerPlant')) {
            return ['Systems', 'Power plants'];
        }
        if (ItemPort::accepts($port, 'Cooler')) {
            return ['Systems', 'Coolers'];
        }
        if (ItemPort::accepts($port, 'Shield')) {
            return ['Systems', 'Shield generators'];
        }
        if (ItemPort::accepts($port, 'WeaponRegenPool')) {
            return ['Systems', 'Weapon regen pool'];
        }

        // Propulsion
        if (ItemPort::accepts($port, 'FuelIntake')) {
            return ['Propulsion', 'Fuel intakes'];
        }
        if (ItemPort::accepts($port, 'FuelTank')) {
            return ['Propulsion', 'Fuel tanks'];
        }
        if (ItemPort::accepts($port, 'QuantumFuelTank.QuantumFuel')) {
            return ['Propulsion', 'Quantum fuel tanks'];
        }
        if (ItemPort::accepts($port, 'QuantumFuelTank')) {
            return ['Propulsion', 'Quantum fuel tanks'];
        }
        if (ItemPort::accepts($port, 'QuantumDrive.QDrive')) {
            return ['Propulsion', 'Quantum drives'];
        }
        if (ItemPort::accepts($port, 'QuantumDrive')) {
            return ['Propulsion', 'Quantum drives'];
        }

        // Main Thrusters
        if (ItemPort::accepts($port, 'MainThruster.*')) {
            if (self::fuzzyNameMatch($port, 'retro', $installedItem)) {
                return ['Thrusters', 'Retro thrusters'];
            }

            if (self::fuzzyNameMatch($port, 'vtol', $installedItem)) {
                return ['Thrusters', 'VTOL thrusters'];
            }

            return ['Thrusters', 'Main thrusters'];
        }

        // Maneuvering Thrusters
        if (ItemPort::accepts($port, 'ManneuverThruster.*')) {
            if (self::fuzzyNameMatch($port, 'retro', $installedItem)) {
                return ['Thrusters', 'Retro thrusters'];
            }

            if (self::fuzzyNameMatch($port, 'vtol', $installedItem)) {
                return ['Thrusters', 'VTOL thrusters'];
            }

            return ['Thrusters', 'Maneuvering thrusters'];
        }

        // Avionics
        if (ItemPort::accepts($port, 'Avionics.Motherboard')) {
            return ['Avionics', 'Computers'];
        }
        if (ItemPort::accepts($port, 'Radar')) {
            return ['Avionics', 'Radars'];
        }
        if (ItemPort::accepts($port, 'Radar.ShortRangeRadar')) {
            return ['Avionics', 'Radars'];
        }
        if (ItemPort::accepts($port, 'Radar.MidRangeRadar')) {
            return ['Avionics', 'Radars'];
        }
        if (ItemPort::accepts($port, 'Scanner')) {
            return ['Avionics', 'Scanners'];
        }
        if (ItemPort::accepts($port, 'Scanner.Gun')) {
            return ['Avionics', 'Scanners'];
        }
        if (ItemPort::accepts($port, 'Ping')) {
            return ['Avionics', 'Pings'];
        }
        if (ItemPort::accepts($port, 'Transponder')) {
            return ['Avionics', 'Transponders'];
        }
        if (ItemPort::accepts($port, 'SelfDestruct')) {
            return ['Avionics', 'Self destructs'];
        }
        if (ItemPort::accepts($port, 'FlightController')) {
            return ['Avionics', 'FlightControllers'];
        }

        // Cargo
        if (ItemPort::accepts($port, 'Cargo')) {
            return ['Cargo', 'Cargo grids'];
        }
        if (ItemPort::accepts($port, 'CargoGrid')) {
            return ['Cargo', 'Cargo grids'];
        }
        if (ItemPort::accepts($port, 'Container.Cargo')) {
            return ['Cargo', 'Cargo containers'];
        }

        // Armor
        if (ItemPort::accepts($port, 'Armor')) {
            return ['Armor', 'Armor'];
        }

        // Misc
        if (ItemPort::accepts($port, 'Usable')) {
            return ['Misc', 'Usables'];
        }
        if (ItemPort::accepts($port, 'Room')) {
            return ['Misc', 'Rooms'];
        }
        if (ItemPort::accepts($port, 'Door')) {
            return ['Misc', 'Doors'];
        }
        if (ItemPort::accepts($port, 'Paints')) {
            return ['Misc', 'Paints'];
        }

        // Attachments to larger objects
        if (self::fuzzyNameMatch($port, 'BatteryPort', $installedItem)) {
            return ['Attachments', 'Batteries'];
        }
        if (ItemPort::accepts($port, 'WeaponAttachment.Barrel')) {
            return ['Attachments', 'Weapon attachments'];
        }
        if (ItemPort::accepts($port, 'WeaponAttachment.FiringMechanism')) {
            return ['Attachments', 'Weapon attachments'];
        }
        if (ItemPort::accepts($port, 'WeaponAttachment.PowerArray')) {
            return ['Attachments', 'Weapon attachments'];
        }
        if (ItemPort::accepts($port, 'WeaponAttachment.Ventilation')) {
            return ['Attachments', 'Weapon attachments'];
        }
        if (ItemPort::accepts($port, 'ControlPanel.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }
        if (ItemPort::accepts($port, 'Misc.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }
        if (ItemPort::accepts($port, 'Button.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }
        if (ItemPort::accepts($port, 'Sensor.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }
        if (ItemPort::accepts($port, 'Lightgroup.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }
        if (ItemPort::accepts($port, 'Decal.DoorPart')) {
            return ['Attachments', 'Door attachments'];
        }

        // Seating
        if (ItemPort::accepts($port, 'Seat')) {
            return ['Seating', 'Seats'];
        }
        if (ItemPort::accepts($port, 'SeatAccess')) {
            return ['Seating', 'Seat access'];
        }

        return ['UNKNOWN', 'UNKNOWN'];
    }

    /**
     * Attempts to guess the port type based on the installed item.
     *
     * @return array|null Guessed port classification or null if unable to guess
     */
    private function guessByInstalledItem(array $installedItem): ?array
    {
        $itemClassifier = new ItemClassifierService;

        $type = $itemClassifier->classify($installedItem);

        if ($type === null) {
            return null;
        }

        switch ($type) {
            case 'WeaponGun.Gun':
                return ['Weapons', 'Weapon hardpoints'];

            case 'TurretBase.MannedTurret':
                // Check if any of the ports have a WeaponMining.Gun installed
                $hasMiningGun = false;
                if (! empty($installedItem['Ports'])) {
                    foreach ($installedItem['Ports'] as $subPort) {
                        if (! empty($subPort['InstalledItem']) && $subPort['InstalledItem']['Type'] === 'WeaponMining.Gun') {
                            $hasMiningGun = true;
                            break;
                        }
                    }
                }

                if ($hasMiningGun) {
                    return ['Mining', 'Mining turrets']; // Argo Mole
                }

                return ['Weapons', 'Manned turrets'];

            case 'Container.Cargo':
                return ['Cargo', 'Cargo containers'];
        }

        return null;
    }

    /**
     * Fuzzy matching of a port name against a search pattern.
     *
     * @param  array  $port  The port to check
     * @param  string  $lookFor  The string to look for
     * @return bool True if the port matches the pattern
     */
    public static function fuzzyNameMatch(array $port, string $lookFor, ?array $installedItem = null): bool
    {
        if (stripos($port['PortName'] ?? '', $lookFor) !== false) {
            return true;
        }

        if (! empty($installedItem['ClassName']) &&
            stripos($installedItem['ClassName'], $lookFor) !== false) {
            return true;
        }

        if (! empty($port['Loadout']) &&
            stripos($port['Loadout'], $lookFor) !== false) {
            return true;
        }

        return false;
    }
}
