# Mounting positions dump contract

`MountingPositions` is a top-level list in new ship dumps, including `[]` for a ship with no eligible ports. A missing field means the dump predates this contract. Do not synthesize it from `Systems.WeaponMounts` or the legacy turret lists. The build version is supplied by the snapshot/import, not by each position.

## Position schema

| Field | Type | Meaning |
| --- | --- | --- |
| `PortId` | string | Unique link to a `Loadout` entry in this snapshot. Do not persist as a cross-build ID. |
| `HardpointName` | string, optional | Raw port name. Can repeat. |
| `Occupied` | boolean | The snapshot has a resolved installed item at the port. |
| `MinSize`, `MaxSize` | integer | Port capacity. Zero uses the loadout's existing unknown/default convention. |
| `InstalledSize` | integer, optional | Installed item's declared size, distinct from port capacity and legacy `Size`. |
| `MountType` | enum, optional | `Fixed`, `Gimbal`, `Turret`, `Rack`, `Missile`, `Tool`, or `Device`. Omitted for a vacant position or unknown installed mount type. |
| `TurretRole` | enum, optional | `Manned`, `Remote`, `Pdc`, or `Autonomous`. Omitted for non-turrets or unknown roles. |
| `Payload` | list | Installed payload in loadout traversal order. Every entry includes `PortId` and the known fields from `HardpointName`, `UUID`, `ClassName`, `Name`, `Type`, `Size`. A direct installation can use the row's own ID. Child gun or missile ports are not separate positions. |
| `ControlRoutes` | list, optional | Known installed routes. Each has `Kind` (`Controller` or `InTurret`), `Operator` (`Pilot` or `Crew`), and `SeatPortId`. `Controller` rows also link `ControllerPortId`. An integrated seat uses the turret position's own ID. Optional `Control` distinguishes `Aim` from `Payload` where known; omission leaves the distinction unspecified. The list does not establish primary control or slaving; absence means unknown, and a present list need not exhaust all routes. |
| `IsPilotSlaveable` | boolean, optional | Existing flag where uniquely attributable. Not evidence of a pilot route. |
| `CombatStats` | object, optional | Existing DPS aggregator projection without `HardpointName`: `DpsTotal`, `SustainedDpsTotal`, `AlphaTotal`, `Weapons`, `IsPilotSlaveable` where provided. This is not recalculated and may differ from the exact legacy summary. |
| `LegacyBucket`, `LegacyOrdinal` | string and integer, optional | Legacy bucket and zero-based row order when attribution is unique. Migration inputs, not public API fields. |
| `LegacySize` | integer, optional | Legacy size override when installed size and port capacity cannot recover it. |

The linked loadout entry carries the same optional `MountType` and `TurretRole`. Get the installed assembly's identity and compatible types from that entry. Eligibility needs installed weapon/utility equipment or compatible types. Hull attachments can host independent ports (for example, the Grin MDC rear module PDC); ordinary nested attachment ports and personal weapon lockers are not mounting positions.

**EMP qualifies** as a `Device`; quantum interdiction equipment does not. **The Reclaimer claw qualifies** as a `Tool` and carries its salvage emitter on the arm row. The Prospector mining arm and Starfarer refueling arm are also tools, not turrets.

A locked empty port is not evidence of a usable position. Keep it when its identity matches a legacy summary so attribution can be checked separately. This excludes the Warden's inherited S5 launcher slot while keeping the Harbinger's installed launcher. The base MPUV's inactive arm is not a vacant utility position. The vacant rack and utility integration cases use modified loadouts, not stock configurations.

`MountingPositionIssues`, when present, is a list of diagnostics. Each has `Reason`, optional `HardpointName`, and candidate `PortIds`; legacy identity failures also have `Bucket` and `Ordinal`. `UnresolvedLegacyTurret`, `UnresolvedCombatStats`, `AmbiguousCombatStats`, and `AmbiguousSlaveability` block legacy parity. The old DPS aggregator groups by hardpoint name and may already have merged contributions before export. Do not attribute that result when multiple DPS source ports share the name. A child gimbal that cannot source turret stats is not a candidate. Old summaries, loadout stats, and totals stay unchanged even on a collision.

## Reconstructing the legacy API projection

Select positions with `LegacyBucket` and sort within each bucket by `LegacyOrdinal`. Resolve each linked loadout entry by `PortId`. Reconstruct the legacy projection for comparison in tests only; never read the old summary as an input. Keep missing values and empty lists distinct. Do not infer legacy flags from `MountType` or `TurretRole`.

| Legacy API field | Input or rule |
| --- | --- |
| `category` | Decorate from `LegacyBucket` as `manned`, `remote`, or `pdc`. |
| `display_name` | Linked `PartDisplayName` when the key exists, otherwise `PortDisplayName`. An explicit null part label does not fall back to the port. |
| `hardpoint_name`, `part_name` | Linked `HardpointName`, `PartName`. |
| `turret_type`, `class_name` | Linked `Type`, `ClassName`. |
| `size` | `LegacySize` if present, otherwise the first positive linked `InstalledSize`, `MaxSize`, `MinSize`, or zero. |
| `turret`, `gimballed`, `fixed` | Legacy turret bucket rows do not emit these flags. Preserve the resource's defaults. |
| `mount_count` | Number of reconstructed mounts; no count when there are no mounts. |
| `weapon_sizes`, `payload_sizes`, `payload_types`, `payload_class_names` | `WeaponSizes`, `PayloadSizes`, `PayloadTypes`, `PayloadClassNames`; when absent, use the resource's stable, strict unique aggregation across `Mounts`. Do not deduplicate explicit lists. |
| `mounts` | `Mounts`, retaining valid rows and order. Map `display_name` from `DisplayName ?? Name`, `hardpoint_name` from `HardpointName`, `mount_type` from `MountType ?? Type`, `class_name` from `MountClassName ?? ClassName`, `size` from `Size ?? MaxSize ?? MinSize`, and the four payload/weapon lists from their names above without aggregation. |
| `dps_total`, `sustained_dps_total`, `alpha_total`, `is_pilot_slaveable` | `DpsTotal`, `SustainedDpsTotal`, `AlphaTotal`, `IsPilotSlaveable` exactly as exported. |
| `weapons` | `Weapons`, retaining valid rows and order. Map `uuid`, `class_name`, `name`, `dps`, `sustained_dps`, `alpha`, and `is_pilot_slaveable` from `UUID`, `ClassName`, `Name`, `Dps`, `SustainedDps`, `Alpha`, and `IsPilotSlaveable`. Derive `link` and `web_url` from the UUID and API request version; use null without a UUID. |
| `version` | Response metadata for the imported snapshot. |

For the legacy mount fields above, use the linked entry's immediate child ports, in loadout order. A child produces a mount only if its subtree contains a legacy payload. Get display name from `PortDisplayName`, class and type from the installed child, and size from the first positive installed size, max size, or min size (otherwise zero). Walk the child subtree in loadout order. If descendants yield payload descriptors, use those; otherwise use the installed type if it is a legacy payload type, or the first compatible legacy payload type. Keep size duplicates; deduplicate type and class lists in first-seen order. Use `WeaponSystemAnalyzer::buildMountSummary`, `isPrimaryPayloadType`, and `isOffensivePayloadType` as the reference for legacy filtering, not the new `Payload` list. Totals and weapon rows come from `CombatStats`; turret slaveability comes from `IsPilotSlaveable` on the position. Fields without a source use the resource's null/empty defaults.

## Verification and fixtures

Schemas are illustrated in `tests/Fixtures/exports/mounting-positions/`. The populated file is a projection of the regenerated Vanguard dump with a gimbal, a loaded rack, and a manned turret. It retains the selected loadout branches and legacy rows. The empty file shows a new dump with no positions; the old-dump sample omits the field.

1. Run `php cli.php load:vehicles GAME_DATA BASELINE` from `ad899ef`.
2. Run `php cli.php load:vehicles GAME_DATA CANDIDATE` from the candidate revision against the same unpacked input.
3. Run `SC_MOUNTING_BASELINE=BASELINE/ships SC_MOUNTING_CANDIDATE=CANDIDATE/ships vendor/bin/phpunit tests/Formats/ScUnpacked/MountingPositionFleetTest.php` from the candidate checkout. It checks legacy output, position links, legacy membership, diagnostics, and the named `4.10.3-LIVE.13165946` fixtures.
4. Run the API import and v3 parity tests in the API ticket. Dump-level reconstruction does not validate the imported API response or authorize deprecation.
