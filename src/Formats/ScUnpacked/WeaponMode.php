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

        $mode['Name'] = $fireAction->get('name');
        $mode['LocalisedName'] = ServiceFactory::getLocalizationService()->getTranslation($fireAction->get('localisedName'));

        switch ($fireAction->nodeName) {
            case 'SWeaponActionFireSingleParams':
                $mode['RoundsPerMinute'] = $fireAction->get('fireRate');
                $mode['FireType'] = 'single';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost') ?? 1;
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount') ?? 1;
                break;

            case 'SWeaponActionFireRapidParams':
                $mode['RoundsPerMinute'] = $fireAction->get('fireRate');
                $mode['FireType'] = 'rapid';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost') ?? 1;
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount') ?? 1;
                break;

            case 'SWeaponActionFireBeamParams':
                $mode['FireType'] = 'beam';
                break;

            case 'SWeaponActionFireChargedParams':
                $mode['RoundsPerMinute'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams@fireRate') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams@fireRate');
                $mode['FireType'] = 'charged';
                $mode['AmmoPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher@ammoCost') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams/launchParams/SProjectileLauncher@ammoCost');
                $mode['PelletsPerShot'] = $fireAction->get('weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher@pelletCount') ?? $fireAction->get('weaponAction/SWeaponActionFireBurstParams/launchParams/SProjectileLauncher@pelletCount');
                break;

            case 'SWeaponActionFireHealingBeamParams':
                $mode['FireType'] = $fireAction->get('healingMode');
                break;

            case 'SWeaponActionFireSalvageRepairParams':
                $mode['FireType'] = $fireAction->get('salvageRepairMode');
                break;

            case 'SWeaponActionGatheringBeamParams':
                $mode['FireType'] = 'collectionbeam';
                break;

            case 'SWeaponActionFireTractorBeamParams':
                $mode['FireType'] = 'tractorbeam';
                break;

            case 'SWeaponActionSequenceParams':
                $mode = $this->buildWeaponModeInfo($fireAction->get('sequenceEntries/SWeaponSequenceEntryParams/weaponAction/SWeaponActionFireSingleParams') ?? $fireAction->get('sequenceEntries/SWeaponSequenceEntryParams/weaponAction/SWeaponActionFireBurstParams'));
                $mode['FireType'] = 'sequence';
                break;

            case 'SWeaponActionFireBurstParams':
                $mode['RoundsPerMinute'] = $fireAction->get('fireRate');
                $mode['FireType'] = 'burst';
                $mode['AmmoPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@ammoCost');
                $mode['PelletsPerShot'] = $fireAction->get('launchParams/SProjectileLauncher@pelletCount');
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
