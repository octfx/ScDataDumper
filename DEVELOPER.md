# Developer Guide

## What This Codebase Does

ScDataDumper reads extracted Star Citizen XML, turns those records into typed PHP documents,
formats them into export structures, and writes JSON to `export/`.

```text
CLI command -> ServiceFactory -> Service -> RootDocument -> Format -> JSON file
```

## Layer Map

| Layer          | Directory                                         | Responsibility                                                  |
|----------------|---------------------------------------------------|-----------------------------------------------------------------|
| Commands       | `src/Commands/`                                   | Symfony Console entry points that orchestrate exports           |
| Services       | `src/Services/`                                   | Find records, load documents, resolve references, manage caches |
| Document types | `src/DocumentTypes/`                              | Typed wrappers around XML documents and related-record helpers  |
| Definitions    | `src/Definitions/`                                | Optional DOM mutation during document load (see below)          |
| Formats        | `src/Formats/ScUnpacked/`                         | Convert documents into export arrays                            |
| Loader/factory | `src/Loader/`, `src/ElementDefinitionFactory.php` | Walk the DOM and attach matching definitions                    |

Key entry points:

- `cli.php` registers all commands.
- `src/Commands/AbstractDataCommand.php`: shared command behavior (paths, JSON writing, lazy-hydration toggle via the `GeneratesCache` trait).
- `src/Services/ServiceFactory.php`: static singleton registry; initializes the active SC data path and boots services on demand.
- `src/Services/BaseService.php`: loads cache maps and instantiates typed documents.
- `src/DocumentTypes/RootDocument.php`: base class for XML-backed documents.
- `src/Formats/BaseFormat.php`: base class for export mappers.

## Commands

`load:data` is the umbrella that runs everything. The individual commands
(registered in `cli.php`, sequenced in `LoadData::subcommands()`):

`generate:cache`, `load:items`, `load:vehicles`, `load:blueprints`, `load:commodities`, `load:commodity-trade-locations`, `load:contracts`, `load:factions`, `load:starmap`, `load:translations`, `load:manufacturers`, `load:tags`, `load:resources`

`schema:diff` is a separate diagnostic command (compares XML schema snapshots between two import directories), not part of the export pipeline.

Commands own orchestration only: prepare services, make output dirs, iterate records, write JSON. Export logic belongs in **formats and document types**.

## Services

Services are the boundary between cache/index lookups and typed documents.
`ServiceFactory` is a static singleton registry; commands talk to services through accessors like `ServiceFactory::getItemService()`.

`BaseService` loads the shared cache maps (UUID-to-path, class-to-path, etc.) into static arrays, resolves references, and instantiates `RootDocument` subclasses.
`ServiceFactory::reset()` clears all static state; call it in tests.

Top-level services (one per export domain):

`ItemService`, `VehicleService`, `BlueprintService`, `ContractGeneratorService`,
`MissionBrokerService`, `ResourceService`, `ManufacturerService`,
`FoundryLookupService`, `LoadoutFileService`, `InventoryContainerService`,
`TagDatabaseService`, `LocalizationService`, `ItemClassifierService`,
`AmmoParamsService`, `StarmapParentResolver`, `ContractLocationResolver`,
`MissionLocationStarmapResolver`.

Two large sub-namespaces are worth knowing about:

- **`Services/Vehicle/`** (~33 classes); the vehicle aggregation pipeline.
  `VehicleDataOrchestrator` builds a `VehicleDataContext` from resolvers and
  aggregators: health, emissions, propulsion, resources, weapon DPS, flight
  and quantum-travel characteristics, cargo grids (strategy-based), loadouts,
  seating, turrets, and ground-vehicle drive characteristics.
- **`Services/Resource/`** (~9 classes); mining, harvestable, commodity, and
  trade-location resolution: `ResourceIndexBuilder`, quality tier/range
  resolvers, cave-harvestable and harvestable-provider starmap resolvers,
  `CommodityTradeLocationResolver`, `SocpakMappingGenerator`.

`Services/DataDumper/` (`Game2ExtractorService`, `SocpakReader`) handles `.socpak` extraction concerns.

## Document Types

Each `RootDocument` subclass wraps one XML document type and exposes methods that hide raw XML traversal from the rest of the codebase.
Documents are grouped by SC schema under `src/DocumentTypes/`:

`EntityClassDefinition` (most item/vehicle entities), `Vehicle`/`VehicleDefinition`,
`Loadout` + `Loadout/LoadoutEntry`, `Crafting/`, `Contract/`, `Mission/`,
`Mining/`, `Harvestable/`, `Loot/`, `Recovery/`, `Reputation/`, `Radar/`,
`Starmap/`, `Faction/`, `AreaServices/`.

`RootDocument` (a `DOMDocument` subclass) provides, as the **subclass API**:

- identity: `getClassName()`, `getType()`, `getPath()`, `getUuid()`
- typed scalars: `getString()`, `getInt()`, `getFloat()`, `getBool()`, `getNullableBool()`
- relations: `getHydratedDocument()`, `resolveRelatedDocument()`, `resolveRelatedDocuments()`
- output: `toArray()`, `toJson()`

XML access itself comes from the `XmlAccess` trait (`get()`, `has()`, `getAll()`), shared with `BaseFormat`.

## Definitions (Deprecated)

Definitions mutate the DOM during document load. They are **not** the primary API; most contributors never touch them.

`ElementLoader` walks the DOM after a document is loaded and
`ElementDefinitionFactory` maps XML element paths to classes in
`src/Definitions/`, which can resolve related XML and append it into the tree.

Use a definition only when a related record must appear in the DOM as part of document initialization and multiple consumers rely on that nested structure.
For ordinary cross-document lookups, prefer a typed helper on the document.

## Formats

Formats are output mappers. They receive a typed document and return the array that becomes JSON.
Extend `BaseFormat`, implement `toArray(): ?array`.

`BaseFormat` provides `get()`/`has()` wrappers, localization helpers, JSON/array
post-processing (`removeNullValues()`), and the resistance-key ordering
(`Physical, Energy, Distortion, Thermal, Biochemical, Stun`).

Conventions:

- use typed document helpers for cross-document relations
- keep direct XML access for local scalar values
- avoid embedding service lookup logic in formats unless no document helper exists yet
- compose sub-formats via `LazyFormat` to defer construction until `canTransform()` passes

Examples: `Item` (item export from `EntityClassDefinition`),
`Ship` (aggregates many sub-formatters from vehicle helpers), `Blueprint`, `Contract`,
`MissionBroker`, `TradeLocation`, `Mineable`.

## Reference Hydration

**Lazy is the default and the forward path. Eager hydration is deprecated.**

`RootDocument::fromNode()` defaults `referenceHydrationEnabled` to `false`.
When eager hydration is on, `ElementLoader` appends related XML records into the DOM during load.
When it is off (the normal case), documents stay light and related records are resolved on demand through typed helpers and services.

`fromNode()`, `setReferenceHydrationEnabled()`, `isReferenceHydrationEnabled()`,
and `ElementLoader` are all marked `@deprecated`; keep new code lazy.

A handful of export commands explicitly force lazy mode while building output,
via `AbstractDataCommand::withLazyReferenceHydration(array $services, callable $callback)`,
because they only need a small subset of related data:

`load:blueprints`, `load:factions`, `load:starmap`, `load:resources`,
`load:contracts`, `load:commodity-trade-locations`.

## Loadouts

Loadouts have a dedicated typed pipeline; do not treat them as ad hoc XML.

- `LoadoutFileService` resolves `Scripts/Loadouts/...` XML files.
- `Loadout` wraps a bare loadout root, an empty loadout, or a parent document containing a nested loadout.
- `Loadout\LoadoutEntry` normalizes both manual and XML-backed entry shapes.
- `EntityClassDefinition::getDefaultLoadoutEntries()` is the canonical way to read default loadouts.

Prefer `LoadoutEntry` helpers (`getPortName()`, `getEntityClassName()`,
`getEntityClassReference()`, `getInstalledItem()`, `getNestedEntries()`) over
manual child traversal. Current consumers: `Formats\ScUnpacked\Ship` and
`Loadout`, plus `Services\Vehicle\LoadoutBuilder`, `StandardisedPartBuilder`,
`VehicleDataContext`, and `FlightCharacteristicsCalculator`.

## Cache Files

Cache generation (`generate:cache`) is required before most export work.
The files live in the SC data directory and are OS-specific (`PHP_OS_FAMILY` suffix).

| File                          | Purpose                                              |
|-------------------------------|------------------------------------------------------|
| `uuidToPathMap-{OS}.json`     | Resolve UUID references to XML paths                 |
| `uuidToClassMap-{OS}.json`    | Resolve UUID references to class names               |
| `classToPathMap-{OS}.json`    | Iterate all records for a document class             |
| `classToUuidMap-{OS}.json`    | Reverse class-name lookup                            |
| `classToTypeMap-{OS}.json`    | Entity type/classification lookup                    |
| `entityMetadataMap-{OS}.json` | Compact item metadata for subtype and entity queries |

Runtime details:

- cache paths must use normalized forward slashes; regenerate if a file contains Windows backslashes.
- services rely on shared static cache state, so `ServiceFactory::reset()` matters in tests and isolated runs.
- when a service caches documents that differ by hydration mode, include the mode in the cache key.

## Working In The Codebase

**Changing exported JSON:** find the owning command, then the service that supplies documents, check whether the document already exposes the data as a typed helper, add/update the format, and add a test.

**Adding a typed document helper:** add a `getXReference()` method if the XML stores a reference; add a `getX()` returning the typed document;
use `resolveRelatedDocument()` / `resolveRelatedDocuments()` so it works whether the related data is hydrated or lazy.

**Adding a service cache:** if cached documents differ by hydration mode, include the mode in the cache key; lazy and eager documents must not alias.

## Testing

Tests mirror `src/`: `tests/Commands/`, `tests/Services/`,
`tests/DocumentTypes/`, `tests/Formats/`, `tests/Helper/`. Fixtures live in
`tests/Fixtures/xml/` and `tests/Fixtures/exports/`.

```shell
composer test            # PHPUnit
composer test:coverage   # with coverage
```

Add or update the narrowest test that proves the behavior: document/format tests for typed helper or output changes, command tests for export orchestration or file-writing.

## Contributor Checklist

- Start from the owning command, then trace into service, document, and format layers.
- Put cross-document lookup logic on `RootDocument` subclasses when possible.
- Keep formats focused on shaping output, not resolving the world.
- Use definitions only for real DOM-mutation concerns.
- Regenerate cache when working with fresh SC data or cache-shape changes.
- Include hydration mode in cache keys when cached documents can differ.
- Add tests next to the subsystem you changed.
