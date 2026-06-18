<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Enriches turret root entries in the annotated loadout with DPS data.
 *
 * Matches Weaponry.Turrets (from WeaponDpsAggregator) by HardpointName onto
 * top-level loadout entries (ParentPortId === null) and merges DPS fields.
 */
final class TurretDpsEnricher
{
    /**
     * @param  list<array<string, mixed>>  $annotatedLoadout
     * @param  array<string, mixed>  $calculatedData  Contains Weaponry.Turrets from WeaponDpsAggregator
     * @return list<array<string, mixed>>
     */
    public function enrich(array $annotatedLoadout, array $calculatedData): array
    {
        $turretDpsMap = $this->buildTurretDpsMap($calculatedData);

        if ($turretDpsMap === []) {
            return $annotatedLoadout;
        }

        return $this->enrichEntries($annotatedLoadout, $turretDpsMap);
    }

    /** @return array<string, array<string, mixed>> */
    private function buildTurretDpsMap(array $calculatedData): array
    {
        $turrets = $calculatedData['Weaponry']['Turrets'] ?? [];

        if (! is_array($turrets) || $turrets === []) {
            return [];
        }

        $map = [];
        foreach ($turrets as $turret) {
            $hardpointName = $turret['HardpointName'] ?? null;
            if ($hardpointName !== null) {
                $map[$hardpointName] = $turret;
            }
        }

        return $map;
    }

    /**
     * Only top-level entries (ParentPortId === null) receive turret-root DPS;
     * nested entries (gimbals, weapons) are never enriched.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, array<string, mixed>>  $turretDpsMap
     * @return list<array<string, mixed>>
     */
    private function enrichEntries(array $entries, array $turretDpsMap): array
    {
        $result = [];

        foreach ($entries as $entry) {
            $enriched = $entry;

            if (($entry['ParentPortId'] ?? null) === null) {
                $hardpointName = $entry['HardpointName'] ?? null;

                if ($hardpointName !== null && isset($turretDpsMap[$hardpointName])) {
                    $dps = $turretDpsMap[$hardpointName];

                    $enriched['DpsTotal'] = $dps['DpsTotal'] ?? null;
                    $enriched['SustainedDpsTotal'] = $dps['SustainedDpsTotal'] ?? null;
                    $enriched['AlphaTotal'] = $dps['AlphaTotal'] ?? null;
                    $enriched['Weapons'] = $dps['Weapons'] ?? [];
                    $enriched['IsPilotSlaveable'] = $dps['IsPilotSlaveable'] ?? false;
                }
            }

            if (isset($enriched['Loadout']) && is_array($enriched['Loadout'])) {
                $enriched['Loadout'] = $this->enrichEntries($enriched['Loadout'], $turretDpsMap);
            }

            $result[] = $enriched;
        }

        return $result;
    }
}
