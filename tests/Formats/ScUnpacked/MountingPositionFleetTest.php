<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use PHPUnit\Framework\TestCase;

final class MountingPositionFleetTest extends TestCase
{
    public function test_regenerated_fleet_preserves_legacy_exports_and_links_positions(): void
    {
        $baseline = $this->fleetDirectory('SC_MOUNTING_BASELINE');
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $files = static fn (string $dir): array => array_map(basename(...), glob("$dir/*.json"));
        $manifest = $files($baseline);
        self::assertNotEmpty($manifest, 'Baseline fleet is empty');
        self::assertSame($manifest, $files($candidate), 'Fleet manifests differ');

        foreach ($manifest as $file) {
            $expected = $this->readDump("$baseline/$file");
            $actual = $this->readDump("$candidate/$file");
            $positions = $actual['MountingPositions'];
            self::assertTrue(array_is_list($positions), "$file: expected a position list");
            $diagnostics = $actual['MountingPositionIssues'] ?? [];
            self::assertSame([], $diagnostics, "$file: attribution issues");
            unset($actual['MountingPositions'], $actual['MountingPositionIssues']);
            $loadout = [];

            if (isset($actual['Loadout'])) {
                $this->indexLoadout($actual['Loadout'], $loadout);
            }

            self::assertSame($expected, $actual, "$file: legacy export changed");

            $seen = [];
            $positionsByIdentity = [];
            $legacyMembers = [];

            foreach ($positions as $position) {
                $id = $position['PortId'];
                self::assertTrue(! isset($seen[$id]) && isset($loadout[$id]), "$file: broken or duplicate position link $id");
                $seen[$id] = true;

                foreach (['HardpointName', 'MountType', 'TurretRole', 'MinSize', 'MaxSize'] as $key) {
                    self::assertTrue(($position[$key] ?? null) === ($loadout[$id][$key] ?? null), "$file: $id has mismatched $key");
                }

                self::assertTrue($position['Occupied'] || ! isset($position['MountType']), "$file: classified a vacant position");
                self::assertArrayNotHasKey('ControlRoutes', $position, "$file: technical routes leaked at $id");

                foreach ($position['OperatingStations'] ?? [] as $station) {
                    self::assertTrue(isset($loadout[$station['SeatPortId']]), "$file: detached seat at $id");
                    self::assertContains($station['Kind'], ['Pilot', 'Crew', 'InTurret']);
                    self::assertNotEmpty($station['Name']);

                    if ($station['Kind'] === 'InTurret') {
                        self::assertSame($id, $station['SeatPortId'], "$file: invalid integrated seat at $id");
                    }
                }

                foreach ($position['Payload'] as $payload) {
                    $payloadId = $payload['PortId'];
                    self::assertTrue(isset($loadout[$payloadId]) && ($payloadId === $id || str_starts_with($payloadId, "$id.loadout.")), "$file: detached payload $payloadId");
                }

                self::assertArrayNotHasKey('LegacyTurret', $position, "$file: copied a legacy summary into $id");
                $identity = $this->turretIdentity($position['HardpointName'] ?? null, $loadout[$id]['ClassName'] ?? null);
                $positionsByIdentity[$identity][] = [$position, $loadout[$id]];
            }

            foreach ($positions as $position) {
                foreach ($position['Payload'] as $payload) {
                    self::assertTrue($payload['PortId'] === $position['PortId'] || ! isset($seen[$payload['PortId']]), "$file: counted a child payload as a position");
                }
            }

            foreach (['MannedTurrets', 'RemoteTurrets', 'PdcTurrets'] as $bucket) {
                foreach ($expected[$bucket] ?? [] as $ordinal => $turret) {
                    $identity = $this->turretIdentity($turret['HardpointName'] ?? null, $turret['TurretClassName'] ?? null);
                    $matches = $positionsByIdentity[$identity] ?? [];
                    self::assertCount(1, $matches, "$file: $bucket turret {$turret['HardpointName']} has no unique position");
                    [[$position, $entry]] = $matches;
                    $id = $position['PortId'];
                    self::assertTrue(($entry['Type'] ?? null) === ($turret['TurretType'] ?? null), "$file: $id lost its installed type");
                    // Installed-payload totals can differ from legacy summaries that count duplicate source entries.
                    foreach (['DpsTotal' => 'Dps', 'SustainedDpsTotal' => 'SustainedDps', 'AlphaTotal' => 'Alpha'] as $legacyKey => $currentKey) {
                        if (($turret[$legacyKey] ?? null) === null) {
                            continue;
                        }

                        self::assertArrayHasKey($currentKey, $position['CombatStats'] ?? [], "$file: $id loses legacy $legacyKey");
                        self::assertIsNumeric($position['CombatStats'][$currentKey], "$file: $id has non-numeric $currentKey");
                    }

                    self::assertTrue(($position['IsPilotSlaveable'] ?? null) === ($turret['IsPilotSlaveable'] ?? null), "$file: $id mismatches legacy slaveability");

                    // Legacy membership is carried explicitly and stays separate from the current classification.
                    self::assertSame($bucket, $position['LegacyBucket'] ?? null, "$file: $id lost its $bucket membership");
                    self::assertSame($ordinal, $position['LegacyOrdinal'] ?? null, "$file: $id has a shifted $bucket ordinal");
                    self::assertSame(
                        $turret['PartName'] ?? null,
                        $entry['PartName'] ?? null,
                        "$file: $id part identity diverges from the legacy summary"
                    );

                    $derivable = 0;

                    foreach ([$entry['InstalledSize'] ?? null, $entry['MaxSize'] ?? null, $entry['MinSize'] ?? null] as $sizeSource) {
                        if (is_numeric($sizeSource) && (int) $sizeSource > 0) {
                            $derivable = (int) $sizeSource;
                            break;
                        }
                    }

                    $legacySize = $turret['Size'] ?? 0;
                    self::assertSame($legacySize, $position['LegacySize'] ?? $derivable, "$file: $id loses the legacy size");
                    self::assertSame($legacySize !== $derivable, array_key_exists('LegacySize', $position), "$file: $id has a redundant legacy size");

                    // Legacy display name must be recoverable from the linked entry's localized labels.
                    self::assertSame(
                        $turret['DisplayName'] ?? null,
                        array_key_exists('PartDisplayName', $entry) ? $entry['PartDisplayName'] : ($entry['PortDisplayName'] ?? null),
                        "$file: $id loses the legacy display name"
                    );
                    $legacyMembers["$bucket.$ordinal"] = $id;
                }
            }

            // Every carried legacy membership maps back to exactly one legacy summary row.
            $carried = [];

            foreach ($positions as $position) {
                if (isset($position['LegacyBucket'])) {
                    $carried[$position['LegacyBucket'].'.'.$position['LegacyOrdinal']] = $position['PortId'];
                }
            }

            ksort($legacyMembers);
            ksort($carried);
            self::assertSame($legacyMembers, $carried, "$file: carried legacy membership diverges from the legacy summaries");
        }
    }

    public function test_regenerated_hulls_export_expected_mounts_and_stations(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $fixtures = [
            'aegs_idris_m' => ['hardpoint_front_left_turret' => ['Turret', 'Autonomous', 'WeaponGun.Gun']],
            'aegs_vanguard' => array_fill_keys([
                'hardpoint_weapon_gun_nose', 'hardpoint_weapon_gun_nose_fixed_001', 'hardpoint_weapon_gun_nose_fixed_002',
                'hardpoint_weapon_gun_nose_fixed_003', 'hardpoint_weapon_gun_nose_fixed_004',
            ], ['Gimbal', null, 'WeaponGun.Gun']) + ['hardpoint_weapon_missilerack_right_inner' => ['Rack', null, 'Missile.Missile']],
            'cnou_nomad' => ['hardpoint_turret' => ['Turret', 'Remote', 'TractorBeam.UNDEFINED']],
            'rsi_constellation_taurus' => ['hardpoint_turret_base_lower' => ['Turret', 'Manned', 'TractorBeam.UNDEFINED']],
            'aegs_reclaimer' => [
                'hardpoint_remote_turret_salvage_left' => ['Turret', 'Remote', 'SalvageHead.UNDEFINED'],
                'hardpoint_remote_turret_salvage_right' => ['Turret', 'Remote', 'SalvageHead.UNDEFINED'],
                'hardpoint_claw' => ['Tool', null, 'ToolArm.UNDEFINED'],
            ],
            'argo_mole' => ['hardpoint_mining_cab_front' => ['Turret', 'Manned', 'WeaponMining.Gun']],
            'misc_prospector' => ['hardpoint_mining_arm' => ['Tool', null, 'WeaponMining.Gun']],
            'rsi_scorpius_antares' => [
                'hardpoint_EMP' => ['Device', null, 'EMP.UNDEFINED'],
            ],
            'grin_mdc' => ['hardpoint_mdc_pdc' => ['Turret', 'Pdc', 'WeaponGun.Gun']],
            'misc_starfarer' => ['hardpoint_refuel_arm' => ['Tool', null, 'ToolArm.UNDEFINED']],
        ];

        foreach ($fixtures as $hull => $checks) {
            $actual = $this->readDump("$candidate/$hull.json");
            $positions = array_column($actual['MountingPositions'], null, 'HardpointName');

            foreach ($checks as $hardpoint => [$mount, $role, $payloadType]) {
                $position = $positions[$hardpoint] ?? [];
                self::assertTrue(($position['MountType'] ?? null) === $mount && ($position['TurretRole'] ?? null) === $role, "$hull: unexpected classification at $hardpoint");
                self::assertTrue(in_array($payloadType, array_column($position['Payload'], 'Type'), true), "$hull: missing payload at $hardpoint");
            }
        }

        foreach ([
            'aegs_vanguard' => ['hardpoint_weapon_missilelauncher'],
            'argo_mpuv' => ['hardpoint_arm'],
            'rsi_scorpius_antares' => ['hardpoint_spine'],
            'aegs_avenger_stalker' => ['hardpoint_shield_generator_left'],
            'banu_defender' => ['hardpoint_weapon_rack'],
            'drak_cutlass_steel' => ['BAR1'],
        ] as $hull => $excluded) {
            $names = array_column($this->readDump("$candidate/$hull.json")['MountingPositions'], 'HardpointName');
            self::assertSame([], array_values(array_intersect($excluded, $names)), "$hull: included an ineligible position");
        }

        $harbinger = array_column($this->readDump("$candidate/aegs_vanguard_harbinger.json")['MountingPositions'], null, 'HardpointName');
        self::assertSame('Rack', $harbinger['hardpoint_weapon_missilelauncher']['MountType']);
        $mustang = array_column($this->readDump("$candidate/cnou_mustang_alpha.json")['MountingPositions'], null, 'HardpointName');

        foreach (['hardpoint_weapon_wing_left', 'hardpoint_weapon_wing_right'] as $name) {
            self::assertFalse($mustang[$name]['Occupied']);
            self::assertArrayNotHasKey('MountType', $mustang[$name]);
        }

        $reclaimer = array_column($this->readDump("$candidate/aegs_reclaimer.json")['MountingPositions'], null, 'HardpointName');
        self::assertContains('SalvageFieldEmitter.UNDEFINED', array_column($reclaimer['hardpoint_claw']['Payload'], 'Type'));

        $stations = [
            'aegs_reclaimer' => ['hardpoint_remote_turret_salvage_left', 'Crew', 'hardpoint_seat_tractorbeam_left'],
        ];

        foreach ($stations as $hull => [$hardpoint, $kind, $seat]) {
            $actual = $this->readDump("$candidate/$hull.json");
            $position = array_column($actual['MountingPositions'], null, 'HardpointName')[$hardpoint];
            $ports = array_column($actual['Loadout'], 'PortId', 'HardpointName');
            self::assertSame([$ports[$seat]], array_column($position['OperatingStations'] ?? [], 'SeatPortId'), "$hull: missing station at $hardpoint");
            self::assertSame([$kind], array_column($position['OperatingStations'], 'Kind'));
        }

        foreach (['argo_mole' => 'hardpoint_mining_cab_front', 'rsi_constellation_taurus' => 'hardpoint_turret_base_lower'] as $hull => $hardpoint) {
            $actual = $this->readDump("$candidate/$hull.json");
            $position = array_column($actual['MountingPositions'], null, 'HardpointName')[$hardpoint];
            self::assertContains([
                'Kind' => 'InTurret', 'SeatPortId' => $position['PortId'], 'Name' => 'Seat inside turret',
            ], $position['OperatingStations'] ?? [], "$hull: missing explicit integrated tool-use station at $hardpoint");
        }

        $tana = $this->readDump("$candidate/misc_reliant_tana.json");
        $tanaPositions = array_column($tana['MountingPositions'], null, 'PortId');

        foreach (['loadout.3', 'loadout.26'] as $id) {
            self::assertSame('Gimbal', $tanaPositions[$id]['MountType']);
            self::assertArrayNotHasKey('TurretRole', $tanaPositions[$id]);
        }

        $starlancer = $this->readDump("$candidate/misc_starlancer_tac.json");
        $positions = array_column($starlancer['MountingPositions'], null, 'HardpointName');

        foreach (['hardpoint_weapon_left', 'hardpoint_weapon_right'] as $hardpoint) {
            $mount = $positions[$hardpoint];
            self::assertTrue(isset($mount['CombatStats']['Dps']), "Starlancer TAC: child gimbal sharing $hardpoint hid the root turret's stats");
        }
    }

    public function test_mauler_duplicate_source_entries_do_not_double_legacy_stats(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $mauler = $this->readDump("$candidate/vncl_mauler.json");
        $matches = static fn (array $rows): array => array_values(array_filter($rows,
            static fn (array $row): bool => ($row['HardpointName'] ?? null) === 'hardpoint_pdc_top_010'));
        $loadout = $matches($mauler['Loadout']);
        $legacy = $matches($mauler['PdcTurrets']);
        $positions = $matches($mauler['MountingPositions']);
        self::assertCount(1, $loadout);
        self::assertCount(1, $legacy);
        self::assertCount(1, $positions);
        self::assertSame($loadout[0]['PortId'], $positions[0]['PortId']);
        self::assertEquals(85, $legacy[0]['DpsTotal']);
        self::assertSame($legacy[0]['DpsTotal'], $positions[0]['CombatStats']['Dps']);
    }

    public function test_regenerated_tractor_turrets_keep_their_housing_and_linked_payload(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            'crus_spirit_c1' => [51],
            'crus_spirit_c1_civilian' => [51],
            'drak_caterpillar' => [13],
            'drak_caterpillar_boarded' => [13],
            'drak_caterpillar_boarded_shatteredblade' => [13],
            'drak_caterpillar_pirate' => [13],
            'drak_cutlass_black' => [105],
            'drak_cutlass_black_bis2950' => [105],
            'drak_cutlass_black_exec_military' => [105],
            'drak_cutlass_black_exec_stealth' => [105],
            'drak_golem_ox' => [27],
            'gama_railen' => [55, 84],
            'gama_tyilui' => [80],
            'misc_hull_b' => [98, 99],
            'misc_reliant' => [26],
            'rsi_polaris' => [142],
            'rsi_polaris_collector_military' => [142],
        ];

        foreach ($witnesses as $hull => $indices) {
            $actual = $this->readDump("$candidate/$hull.json");
            $positions = array_column($actual['MountingPositions'], null, 'PortId');
            $loadout = [];
            $this->indexLoadoutForRead($actual['Loadout'], $loadout);

            foreach ($indices as $index) {
                $id = "loadout.$index";
                $position = $positions[$id];
                self::assertTrue($position['Occupied'], "$hull $id: vacant housing");

                foreach ([$position, $loadout[$id]] as $linked) {
                    self::assertSame('Turret', $linked['MountType'] ?? null, "$hull $id");
                    self::assertSame('Remote', $linked['TurretRole'] ?? null, "$hull $id");
                }

                self::assertArrayNotHasKey('LegacyBucket', $position);

                if ($hull === 'drak_golem_ox') {
                    self::assertArrayNotHasKey('ControlRoutes', $position, 'Remote articulation alone does not establish a route');
                }

                $beams = array_values(array_filter($position['Payload'], static fn (array $payload): bool => $payload['Type'] === 'TractorBeam.UNDEFINED'));
                self::assertCount(1, $beams, "$hull $id: expected one tractor beam");
                $beamId = $beams[0]['PortId'];
                self::assertStringStartsWith("$id.loadout.", $beamId);
                self::assertSame($beams[0]['UUID'], $loadout[$beamId]['UUID']);
                self::assertSame('TractorBeam.UNDEFINED', $loadout[$beamId]['Type']);
                self::assertArrayNotHasKey($beamId, $positions);
            }
        }
    }

    public function test_regenerated_empty_remote_housings_keep_their_classification(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            'misc_reliant_mako' => 'loadout.26',
            'misc_reliant_sen' => 'loadout.26',
            'rsi_polaris' => 'loadout.204',
            'rsi_polaris_collector_military' => 'loadout.204',
            'rsi_scorpius_antares' => 'loadout.36',
        ];

        foreach ($witnesses as $hull => $id) {
            $actual = $this->readDump("$candidate/$hull.json");
            $positions = array_column($actual['MountingPositions'], null, 'PortId');
            $loadout = [];
            $this->indexLoadoutForRead($actual['Loadout'], $loadout);
            self::assertArrayHasKey($id, $positions, $hull);
            self::assertArrayHasKey($id, $loadout, $hull);
            $position = $positions[$id];
            self::assertTrue($position['Occupied'], "$hull $id: vacant housing");

            foreach ([$position, $loadout[$id]] as $linked) {
                self::assertSame('Turret', $linked['MountType'] ?? null, "$hull $id");
                self::assertSame('Remote', $linked['TurretRole'] ?? null, "$hull $id");
            }

            self::assertSame([], $position['Payload'], "$hull $id");
            self::assertArrayNotHasKey('ControlRoutes', $position, "$hull $id");
            self::assertArrayNotHasKey('OperatingStations', $position, 'Empty remote housing has no verified action');

            foreach (['LegacyBucket', 'LegacyOrdinal'] as $key) {
                self::assertArrayNotHasKey($key, $position, "$hull $id");
            }
        }
    }

    public function test_legacy_membership_and_labels_survive_on_named_fixtures(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');

        // Vacant legacy member: explicit membership, still no installed mount type or role.
        $idris = $this->readDump("$candidate/aegs_idris_m.json");
        $positions = array_column($idris['MountingPositions'], null, 'HardpointName');
        $vacant = $positions['hardpoint_rear_turret_tail'];
        self::assertFalse($vacant['Occupied'], 'Idris-M tail should be vacant');
        self::assertArrayNotHasKey('MountType', $vacant, 'vacant position must stay unclassified');
        self::assertArrayNotHasKey('TurretRole', $vacant, 'vacant position must stay unclassified');
        self::assertSame('MannedTurrets', $vacant['LegacyBucket']);
        self::assertArrayHasKey($vacant['LegacyOrdinal'], $idris['MannedTurrets'], 'legacy ordinal must address a summary row');
        self::assertSame(
            'hardpoint_rear_turret_tail',
            $idris['MannedTurrets'][$vacant['LegacyOrdinal']]['HardpointName'] ?? null,
            'Idris-M tail ordinal must address its legacy summary row'
        );

        // Localized labels ride on the linked loadout entry, not on the position row.
        $loadout = [];
        $this->indexLoadoutForRead($idris['Loadout'], $loadout);
        $entry = $loadout[$vacant['PortId']];
        self::assertSame($idris['MannedTurrets'][$vacant['LegacyOrdinal']]['HardpointName'], $entry['HardpointName']);

        // Nested adapter size: Mount_Gimbal_S3 (size 3) on a size-2 port must stay explicit.
        $vanguard = $this->readDump("$candidate/aegs_vanguard.json");
        $nested = [];
        $this->indexLoadoutForRead($vanguard['Loadout'], $nested);
        $turret = array_values(array_filter(
            $nested,
            fn (array $entry): bool => $entry['HardpointName'] === 'hardpoint_turret' && ! isset($entry['ParentPortId'])
        ));
        self::assertNotEmpty($turret, 'Vanguard turret entry missing');

        foreach ($nested as $entry) {
            if (in_array($entry['HardpointName'], ['turret_left', 'turret_right'], true)
                && str_starts_with((string) $entry['PortId'], ($turret[0]['PortId'] ?? '').'.loadout.')) {
                self::assertSame('Mount_Gimbal_S3', $entry['ClassName']);
                self::assertSame(2, $entry['MaxSize']);
                self::assertSame(3, $entry['InstalledSize'], 'adapter size must be carried despite the smaller port');
                self::assertArrayHasKey('PortDisplayName', $entry, 'nested mount ports must carry a display name');
            }
        }

        $tiburon = $this->readDump("$candidate/aegs_tiburon.json");
        $tiburonPorts = [];
        $this->indexLoadoutForRead($tiburon['Loadout'], $tiburonPorts);
        $remote = array_find($tiburon['MountingPositions'], static fn (array $position): bool => ($position['LegacyBucket'] ?? null) === 'RemoteTurrets' && $position['LegacyOrdinal'] === 0
        );
        self::assertNotNull($remote);
        $mount = $tiburonPorts[$remote['PortId']]['Loadout'][0];
        self::assertSame('', $mount['PortDisplayName']);
        self::assertSame($tiburon['RemoteTurrets'][0]['Mounts'][0]['DisplayName'], $mount['PortDisplayName']);

        // Occupied legacy member keeps its bucket ordinal next to the current classification.
        $hammerhead = $this->readDump("$candidate/aegs_hammerhead_gs.json");

        foreach ($hammerhead['MountingPositions'] as $position) {
            if ($position['HardpointName'] === 'turret_side_back_right') {
                self::assertSame('MannedTurrets', $position['LegacyBucket']);
                self::assertSame(0, $position['LegacyOrdinal']);
                self::assertSame('Turret', $position['MountType']);
            }
        }
    }

    public function test_fleet_covers_independent_loadout_ports_and_owns_payload_once(): void
    {
        $baseline = $this->fleetDirectory('SC_MOUNTING_BASELINE');
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $gaps = [];
        $payloadTypes = ['weapongun', 'weaponmount', 'missile', 'weaponmining', 'miningarm', 'toolarm', 'tractorbeam', 'salvagehead', 'towingbeam', 'emp', 'salvagefieldemitter'];
        $mountTypes = [...$payloadTypes, 'bomblauncher', 'missilelauncher', 'groundvehiclemissilelauncher', 'turret', 'turretbase', 'utilityturret'];
        $baseType = static fn (string $type): string => strtolower(explode('.', $type, 2)[0]);

        foreach (glob("$baseline/*.json") as $path) {
            $file = basename($path);
            $source = [];
            $dump = $this->readDump($path);
            $this->indexLoadoutForRead($dump['Loadout'] ?? [], $source);
            $legacy = [];

            foreach (['MannedTurrets', 'RemoteTurrets', 'PdcTurrets'] as $bucket) {
                foreach ($dump[$bucket] ?? [] as $turret) {
                    $legacy[$this->turretIdentity($turret['HardpointName'] ?? null, $turret['TurretClassName'] ?? null)] = true;
                }
            }

            $positions = [];
            $owners = [];

            foreach ($this->readDump("$candidate/$file")['MountingPositions'] as $position) {
                $id = $position['PortId'];
                $positions[$id][] = $position;

                foreach ($position['Payload'] as $payload) {
                    $owners[$payload['PortId']][] = [$id, $payload['UUID'] ?? null, $payload['Type'] ?? null];
                }
            }

            // A classifier regression cannot erase its own evidence from the legacy loadout.
            $independent = ['' => true];
            $expectedOwners = [];
            $assembly = [];

            foreach ($source as $id => $entry) {
                $parent = $entry['ParentPortId'] ?? '';
                $type = strtolower($entry['Type'] ?? '');
                $base = $baseType($type);
                $compatible = array_map($baseType, array_column($entry['CompatibleTypes'] ?? [], 'Type'));
                $isIndependent = isset($independent[$parent]);
                $isBoundary = $base === 'attachedpart' || (
                    isset($entry['UUID']) && ! in_array($base, $mountTypes, true)
                    && array_intersect($compatible, ['module', 'container']) !== []
                );

                if ($isIndependent && $isBoundary) {
                    $independent[$id] = true;
                }

                $legacyIdentity = $this->turretIdentity($entry['HardpointName'] ?? null, $entry['ClassName'] ?? null);
                $eligible = $isIndependent && $base !== 'quantuminterdictiongenerator'
                    && (isset($entry['UUID']) || ($entry['Editable'] ?? false) || isset($legacy[$legacyIdentity]))
                    && (in_array($base, $mountTypes, true) || array_intersect($compatible, $mountTypes) !== []);
                $rows = $positions[$id] ?? [];

                if (count($rows) !== (int) $eligible) {
                    $gaps[] = "$file $id ($type): expected ".(int) $eligible.' position(s), got '.count($rows);
                }

                $assembly[$id] = $isIndependent && $isBoundary ? null : ($eligible ? $id : ($assembly[$parent] ?? null));

                if ($assembly[$id] !== null && ($type === 'bomb.utility' || in_array($base, $payloadTypes, true))) {
                    $expectedOwners[$id] = [[$assembly[$id], $entry['UUID'] ?? null, $entry['Type']]];
                }

                $classification = match ($base) {
                    'bomblauncher', 'missilelauncher', 'groundvehiclemissilelauncher' => 'Rack',
                    'emp' => 'Device',
                    default => null,
                };

                if ($eligible && $classification !== null && ($rows[0]['MountType'] ?? null) !== $classification) {
                    $gaps[] = "$file $id ($type): expected $classification";
                }

                if ($eligible && ! isset($entry['UUID']) && ($rows[0]['Occupied'] ?? null) !== false) {
                    $gaps[] = "$file $id: expected a vacant position";
                }
            }

            foreach (array_keys($expectedOwners + $owners) as $id) {
                if (($expectedOwners[$id] ?? []) !== ($owners[$id] ?? [])) {
                    $gaps[] = "$file $id: wrong payload ownership, identity or multiplicity";
                }
            }
        }

        self::assertSame([], $gaps, implode("\n", $gaps));
    }

    public function test_regenerated_bomb_racks_and_module_mounts(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            'crus_spirit_a1' => ['loadout.3' => ['Rack', 'Bomb.Utility', 10]],
            'crus_starlifter_a2' => [
                'loadout.130' => ['Rack', 'Bomb.Utility', 2],
                'loadout.131' => ['Rack', 'Bomb.Utility', 2],
            ],
            'crus_starlifter_a2_collector_military' => [
                'loadout.130' => ['Rack', 'Bomb.Utility', 2],
                'loadout.131' => ['Rack', 'Bomb.Utility', 2],
            ],
            'tmbl_cyclone_aa' => [
                'loadout.23.loadout.2' => ['Rack', 'Missile.GroundVehicleMissile', 1],
                'loadout.23.loadout.3' => ['Rack', 'Missile.GroundVehicleMissile', 1],
                'loadout.23.loadout.4' => ['Device', 'EMP.UNDEFINED', 1],
            ],
            'rsi_galaxy_temp_cargo' => [
                'loadout.74.loadout.6' => ['Turret', 'TractorBeam.UNDEFINED', 1],
                'loadout.74.loadout.11' => ['Turret', 'TractorBeam.UNDEFINED', 1],
            ],
        ];

        foreach ($witnesses as $hull => $mounts) {
            $positions = array_column($this->readDump("$candidate/$hull.json")['MountingPositions'], null, 'PortId');

            foreach ($mounts as $id => [$mount, $payloadType, $count]) {
                self::assertArrayHasKey($id, $positions, $hull);
                $position = $positions[$id];
                self::assertTrue($position['Occupied'], "$hull $id");
                self::assertSame($mount, $position['MountType'] ?? null, "$hull $id");
                self::assertSame($mount === 'Turret' ? 'Remote' : null, $position['TurretRole'] ?? null, "$hull $id");
                self::assertSame(array_fill(0, $count, $payloadType), array_column($position['Payload'], 'Type'), "$hull $id");

                if ($hull !== 'rsi_galaxy_temp_cargo') {
                    self::assertArrayNotHasKey('ControlRoutes', $position, "$hull $id");
                }

                self::assertArrayNotHasKey('LegacyBucket', $position, "$hull $id");
            }

            if ($hull === 'tmbl_cyclone_aa') {
                self::assertSame([], $positions['loadout.23']['Payload']);
                self::assertArrayNotHasKey('MountType', $positions['loadout.23']);
                self::assertArrayNotHasKey('TurretRole', $positions['loadout.23']);
            }
        }
    }

    public function test_uuid_matched_articulated_remote_housings(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            ['aegs_idris_m', 'loadout.164', '5b3f8f7b-f645-448b-ab16-642c57c49df3'],
            ['aegs_idris_m', 'loadout.165', 'cd66e83e-d139-4981-ba7a-4faf0e47a382'],
            ['aegs_idris_p', 'loadout.164', '5b3f8f7b-f645-448b-ab16-642c57c49df3'],
            ['aegs_idris_p', 'loadout.165', 'cd66e83e-d139-4981-ba7a-4faf0e47a382'],
            ['aegs_idris_p_collector_military', 'loadout.164', '5b3f8f7b-f645-448b-ab16-642c57c49df3'],
            ['aegs_idris_p_collector_military', 'loadout.165', 'cd66e83e-d139-4981-ba7a-4faf0e47a382'],
            ['aegs_idris_p_fw_25', 'loadout.171', '5b3f8f7b-f645-448b-ab16-642c57c49df3'],
            ['aegs_idris_p_fw_25', 'loadout.172', 'cd66e83e-d139-4981-ba7a-4faf0e47a382'],
            ['aegs_idris_p_tsg', 'loadout.164', '5b3f8f7b-f645-448b-ab16-642c57c49df3'],
            ['aegs_idris_p_tsg', 'loadout.165', 'cd66e83e-d139-4981-ba7a-4faf0e47a382'],
            ['anvl_terrapin', 'loadout.7', '43d95e9f-143c-c8c5-073e-2e4bb6a98cae'],
            ['crus_starlifter_a2', 'loadout.134', '0a7f69a5-be49-4afb-9fd5-9177b541550e'],
            ['crus_starlifter_a2_collector_military', 'loadout.134', '0a7f69a5-be49-4afb-9fd5-9177b541550e'],
            ['drak_cutlass_red', 'loadout.46', 'a1b8ef62-25b0-4ece-a01d-9272219d4a2a'],
            ['drak_cutlass_red_bis2950', 'loadout.46', 'a1b8ef62-25b0-4ece-a01d-9272219d4a2a'],
            ['misc_starlancer_tac', 'loadout.99', 'e24dc01c-60df-4687-8010-c2fe558212ff'],
            ['misc_starlancer_tac_collector_military', 'loadout.99', 'e24dc01c-60df-4687-8010-c2fe558212ff'],
            ['rsi_perseus', 'loadout.24', 'dbd219ff-eca1-4e6b-8426-783f119e102a'],
            ['rsi_polaris', 'loadout.20', 'c765234f-2f12-4027-81cb-32601ac81b7d'],
            ['rsi_polaris_collector_military', 'loadout.20', 'c765234f-2f12-4027-81cb-32601ac81b7d'],
        ];

        foreach ($witnesses as [$hull, $id, $uuid]) {
            $dump = $this->readDump("$candidate/$hull.json");
            $ports = [];
            $this->indexLoadoutForRead($dump['Loadout'], $ports);
            $position = array_column($dump['MountingPositions'], null, 'PortId')[$id];
            self::assertSame($uuid, $ports[$id]['UUID']);

            foreach ([$position, $ports[$id]] as $row) {
                self::assertSame('Turret', $row['MountType'] ?? null, "$hull $id");
                self::assertSame('Remote', $row['TurretRole'] ?? null, "$hull $id");
            }

            self::assertArrayNotHasKey('LegacyBucket', $position, "$hull $id");
        }
    }

    public function test_installed_covers_and_fixed_adapters_are_not_turrets(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            ['cnou_mustang_gamma', 'loadout.0', '058d4903-33ee-4473-a13b-1c7848f78b7a', null, null],
            ['cnou_mustang_omega', 'loadout.0', '058d4903-33ee-4473-a13b-1c7848f78b7a', null, null],
            ['aegs_idris_m', 'loadout.270', '28440bb3-e39e-4d28-a0dd-94ac1331447b', null, 'MannedTurrets'],
            ['anvl_hornet_f7c_wildfire', 'loadout.12', '18895043-42c5-4f1d-a5e8-4cf7c78bd69f', 'Fixed', 'RemoteTurrets', 'loadout.12.loadout.0'],
            ['anvl_hornet_f7cm_heartseeker', 'loadout.53', '18895043-42c5-4f1d-a5e8-4cf7c78bd69f', 'Fixed', 'RemoteTurrets', 'loadout.53.loadout.0.loadout.0'],
        ];

        foreach ($witnesses as $witness) {
            [$hull, $id, $uuid, $mount, $bucket] = $witness;
            $dump = $this->readDump("$candidate/$hull.json");
            $ports = [];
            $this->indexLoadoutForRead($dump['Loadout'], $ports);
            $positions = array_column($dump['MountingPositions'], null, 'PortId');
            self::assertArrayHasKey($id, $positions, $hull);
            $position = $positions[$id];
            self::assertSame($uuid, $ports[$id]['UUID']);

            foreach ([$position, $ports[$id]] as $row) {
                self::assertSame($mount, $row['MountType'] ?? null, "$hull $id");
                self::assertArrayNotHasKey('TurretRole', $row, "$hull $id");
            }

            self::assertSame($bucket, $position['LegacyBucket'] ?? null, "$hull $id");
            self::assertSame(isset($witness[5]) ? [$witness[5]] : [], array_column($position['Payload'], 'PortId'));
            self::assertArrayNotHasKey('ControlRoutes', $position, "$hull $id");
        }
    }

    public function test_galaxy_module_technical_routes_do_not_establish_operation(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $dump = $this->readDump("$candidate/rsi_galaxy_temp_cargo.json");
        $positions = array_column($dump['MountingPositions'], null, 'PortId');

        foreach ([6, 11] as $mount) {
            $prefix = 'loadout.74.loadout.';
            $position = $positions[$prefix.$mount];
            self::assertArrayNotHasKey('IsPilotSlaveable', $position);
            self::assertArrayNotHasKey('ControlRoutes', $position);
            self::assertArrayNotHasKey('OperatingStations', $position);
        }
    }

    public function test_countermeasure_launchers_remain_only_in_loadout(): void
    {
        $candidate = $this->fleetDirectory('SC_MOUNTING_CANDIDATE');
        $witnesses = [
            'tmbl_cyclone_aa' => ['loadout.23.loadout.0' => true, 'loadout.23.loadout.1' => true],
            'aegs_avenger_stalker' => ['loadout.48' => true],
            'crus_starlifter_a2' => ['loadout.2' => false, 'loadout.134.loadout.1' => false],
            'tmbl_cyclone_mt' => ['loadout.23.loadout.4' => true],
            'tmbl_nova' => ['loadout.41.loadout.1' => true],
        ];

        foreach (glob("$candidate/*.json") as $file) {
            $hull = basename($file, '.json');
            $dump = $this->readDump($file);
            $ports = [];
            $this->indexLoadoutForRead($dump['Loadout'] ?? [], $ports);
            $excluded = [];

            foreach ($ports as $id => $entry) {
                $installed = isset($entry['UUID']);
                $launcher = strtolower($entry['Type'] ?? '') === 'weapondefensive.countermeasurelauncher';
                $vacancy = ! $installed && array_any($entry['CompatibleTypes'] ?? [], static fn (array $type): bool => strcasecmp($type['Type'], 'WeaponDefensive') === 0
                    && in_array('countermeasurelauncher', array_map(strtolower(...), $type['SubTypes'] ?? []), true));

                if ($launcher || $vacancy) {
                    $excluded[$id] = $installed;
                }
            }

            foreach ($witnesses[$hull] ?? [] as $id => $installed) {
                self::assertSame($installed, $excluded[$id] ?? null, "$hull $id missing from loadout");
            }

            unset($witnesses[$hull]);

            foreach ($dump['MountingPositions'] as $position) {
                self::assertArrayNotHasKey($position['PortId'], $excluded, "$hull includes a countermeasure position");

                foreach ($position['Payload'] as $payload) {
                    self::assertArrayNotHasKey($payload['PortId'], $excluded, "$hull includes countermeasure payload");
                }
            }
        }

        self::assertSame([], $witnesses);
    }

    private function fleetDirectory(string $variable): string
    {
        $directory = getenv($variable);

        if ($directory === false || $directory === '') {
            self::markTestSkipped("Set $variable to the regenerated ships directory.");
        }

        self::assertDirectoryExists($directory);

        return $directory;
    }

    private function readDump(string $file): array
    {
        return json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    private function indexLoadout(array &$loadout, array &$index): void
    {
        foreach ($loadout as &$entry) {
            $id = $entry['PortId'];
            self::assertTrue(! isset($index[$id]), "Duplicate loadout ID: $id");
            $index[$id] = $entry;
            // Strip the additive mounting-position contract fields before the legacy compare.
            unset(
                $entry['MountType'],
                $entry['TurretRole'],
                $entry['PortDisplayName'],
                $entry['PartDisplayName'],
                $entry['PartName'],
                $entry['InstalledSize'],
            );

            if (isset($entry['Loadout'])) {
                $this->indexLoadout($entry['Loadout'], $index);
            }
        }
    }

    private function indexLoadoutForRead(array $loadout, array &$index): void
    {
        foreach ($loadout as $entry) {
            $index[$entry['PortId']] = $entry;

            if (isset($entry['Loadout'])) {
                $this->indexLoadoutForRead($entry['Loadout'], $index);
            }
        }
    }

    private function turretIdentity(?string $hardpoint, ?string $class): string
    {
        return json_encode([$hardpoint, $class], JSON_THROW_ON_ERROR);
    }
}
