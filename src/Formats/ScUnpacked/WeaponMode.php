<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Exception;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class WeaponMode extends BaseFormat
{
    private array $supportedElements = [
        'SWeaponActionFireSingleParams',
        'SWeaponActionFireRapidParams',
        'SWeaponActionFireBeamParams',
        'SWeaponActionFireChargedParams',
        'SWeaponActionFireHealingBeamParams',
        'SWeaponActionFireSalvageRepairParams',
        'SWeaponActionGatheringBeamParams',
        'SWeaponActionFireTractorBeamParams',
        'SWeaponActionSequenceParams',
        'SWeaponActionFireBurstParams',
    ];

    /**
     * @throws Exception
     */
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->buildWeaponModeInfo($this->item);
    }

    /**
     * @throws Exception
     */
    private function buildWeaponModeInfo($fireAction): array
    {
        $mode = [];

        if (! $fireAction) {
            return $mode;
        }

        $mode['Name'] = $fireAction->get('@name');
        $mode['LocalisedName'] = $fireAction->get('@localisedName');

        switch ($fireAction->nodeName) {
            case 'SWeaponActionFireSingleParams':
                $mode['RoundsPerMinute'] = $fireAction->get('@fireRate');
                $mode['FireType'] = 'single';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost') ?? 1;
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount') ?? 1;
                $mode['HeatPerShot'] = $fireAction->get('@heatPerShot');
                $mode['WearPerShot'] = $fireAction->get('@wearPerShot');
                break;

            case 'SWeaponActionFireRapidParams':
                $mode['RoundsPerMinute'] = $fireAction->get('@fireRate');
                $mode['FireType'] = 'rapid';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost') ?? 1;
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount') ?? 1;
                $mode['HeatPerShot'] = $fireAction->get('@heatPerShot');
                $mode['WearPerShot'] = $fireAction->get('@wearPerShot');
                $mode['FireDuringSpinUp'] = $fireAction->get('@fireDuringSpinUp');
                break;

            case 'SWeaponActionFireBeamParams':
                $mode['FireType'] = 'beam';
                $mode['HeatPerSecond'] = $fireAction->get('@heatPerSecond');
                $mode['WearPerSecond'] = $fireAction->get('@wearPerSecond');
                $mode['ChargeUpTime'] = $fireAction->get('@chargeUpTime');
                $mode['ChargeDownTime'] = $fireAction->get('@chargeDownTime');
                $mode['FullDamageRange'] = $fireAction->get('@fullDamageRange');
                $mode['ZeroDamageRange'] = $fireAction->get('@zeroDamageRange');
                $mode['HitType'] = $fireAction->get('@hitType');
                $mode['HitRadius'] = $fireAction->get('@hitRadius');
                $mode['MinEnergyDraw'] = $fireAction->get('@minEnergyDraw');
                $mode['MaxEnergyDraw'] = $fireAction->get('@maxEnergyDraw');
                break;

            case 'SWeaponActionFireChargedParams':
                $mode['RoundsPerMinute'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams@fireRate') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams@fireRate');
                $mode['FireType'] = 'charged';
                $mode['AmmoPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher@ammoCost') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams/launchParams/SProjectileLauncher@ammoCost');
                $mode['PelletsPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher@pelletCount') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams/launchParams/SProjectileLauncher@pelletCount');
                $mode['HeatPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams@heatPerShot') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams@heatPerShot');
                $mode['WearPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams@wearPerShot') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams@wearPerShot');
                break;

            case 'SWeaponActionFireHealingBeamParams':
                $mode['FireType'] = 'healingbeam';
                $mode['HealingMode'] = $fireAction->get('@healingMode');
                $mode['HealingPerSecond'] = $fireAction->get('@mSCUPerSec');
                $mode['AmmoPerMSCU'] = $fireAction->get('@ammoPerMSCU');
                $mode['MedicalAmmoType'] = $fireAction->get('@medicalAmmoType');
                $mode['ExternalHealing'] = $fireAction->get('@externalHealingMode');
                $mode['Toggle'] = $fireAction->get('@toggle');
                $mode['MaxDistance'] = $fireAction->get('@maxDistance');
                $mode['MaxSensorDistance'] = $fireAction->get('@maxSensorDistance');
                $mode['AutoDosageModifier'] = $fireAction->get('@autoDosageTargetBDLModifier');
                $mode['HealingBreakTime'] = $fireAction->get('@healingBreakTime');
                $mode['MaxDoseForAutoAdjustment'] = $fireAction->get('@maxDoseForAutoAdjustment');
                $mode['WearPerSecond'] = $fireAction->get('@wearPerSec');
                $mode['BatteryDrainPerSecond'] = $fireAction->get('@batteryDrainPerSec');
                break;

            case 'SWeaponActionFireSalvageRepairParams':
                $mode['FireType'] = $fireAction->get('@salvageRepairMode') ?? 'salvage';
                $mode['HeatPerSecond'] = $fireAction->get('@heatPerSecond');
                $mode['WearPerSecond'] = $fireAction->get('@wearPerSecond');
                $mode['MaterialEfficiency'] = $fireAction->get('@materialEfficiency');
                $mode['MaxHealthRepairRate'] = $fireAction->get('@maxHealthRepairRate');
                $mode['MaxDamageMapRepairRate'] = $fireAction->get('@maxDamageMapRepairRate');
                $mode['HealthToAmmoRatio'] = $fireAction->get('@healthToAmmoRatio');
                $mode['RampUpTime'] = $fireAction->get('@rampUpTime');
                $mode['RampDownTime'] = $fireAction->get('@rampDownTime');
                $mode['MaxVehicleDamageRatio'] = $fireAction->get('@maxVehicleDamageRatio');
                $mode['RepairedMaterialRatio'] = $fireAction->get('@repairedMaterialRatio');
                $mode['SalvageCanFireOnFull'] = $fireAction->get('@salvageCanFireOnFull');
                $mode['MinEnergyDraw'] = $fireAction->get('@minEnergyDraw');
                $mode['MaxEnergyDraw'] = $fireAction->get('@maxEnergyDraw');
                $mode['DamageThreshold'] = $fireAction->get('@damageThreshold');
                $mode['HitRadius'] = $fireAction->get('@hitRadius');
                break;

            case 'SWeaponActionGatheringBeamParams':
                $mode['FireType'] = 'collectionbeam';
                $mode['MinimumDistance'] = $fireAction->get('@minimumDistance');
                $mode['MaximumDistance'] = $fireAction->get('@maximumDistance');
                $mode['BeamRadius'] = $fireAction->get('@beamRadius');
                $mode['CollectionRate'] = $fireAction->get('@collectionRate');
                $mode['EnergyDraw'] = $fireAction->get('@energyDraw');
                $mode['MiningExtractorTag'] = $fireAction->get('@miningExtractorTag');
                break;

            case 'SWeaponActionFireTractorBeamParams':
                $mode['FireType'] = 'tractorbeam';
                $mode['HeatPerSecond'] = $fireAction->get('@heatPerSecond');
                $mode['WearPerSecond'] = $fireAction->get('@wearPerSecond');
                $mode['ToggleMode'] = $fireAction->get('@toggleMode');
                break;

            case 'SWeaponActionSequenceParams':
                $mode = $this->buildWeaponModeInfo($fireAction->get('sequenceEntries/SWeaponSequenceEntryParams/weaponAction/SWeaponActionFireSingleParams') ?? $fireAction->get('sequenceEntries/SWeaponSequenceEntryParams/weaponAction/SWeaponActionFireBurstParams'));
                $mode['FireType'] = 'sequence';
                $mode['SequenceMode'] = $fireAction->get('@mode');
                break;

            case 'SWeaponActionFireBurstParams':
                $mode['RoundsPerMinute'] = $fireAction->get('@fireRate');
                $mode['FireType'] = 'burst';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost') ?? 1;
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount') ?? 1;
                $mode['HeatPerShot'] = $fireAction->get('@heatPerShot');
                $mode['WearPerShot'] = $fireAction->get('@wearPerShot');
                $mode['ShotCount'] = $fireAction->get('@shotCount');
                $mode['CooldownTime'] = $fireAction->get('@cooldownTime');
                break;

            default:
                throw new RuntimeException('Unknown fireAction');
        }

        return $mode;
    }

    public function canTransform(): bool
    {
        return in_array($this->item?->nodeName, $this->supportedElements, true);
    }
}
