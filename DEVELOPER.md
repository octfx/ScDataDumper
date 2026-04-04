# Developer Guide

## What This Codebase Does

ScDataDumper reads extracted Star Citizen XML, turns those records into typed PHP documents, formats them into export structures, and writes JSON files to `export/`.

The main runtime path is:

```text
CLI command
  -> ServiceFactory
  -> Service
  -> RootDocument
  -> Format
  -> JSON file
```

## High-Level Architecture

| Layer          | Directory                                         | Responsibility                                                  |
|----------------|---------------------------------------------------|-----------------------------------------------------------------|
| Commands       | `src/Commands/`                                   | Symfony Console entry points that orchestrate exports           |
| Services       | `src/Services/`                                   | Find records, load documents, resolve references, manage caches |
| Document types | `src/DocumentTypes/`                              | Typed wrappers around XML documents and related-record helpers  |
| Definitions    | `src/Definitions/`                                | Optional DOM mutation during document load                      |
| Formats        | `src/Formats/ScUnpacked/`                         | Convert documents into export arrays                            |
| Loader/factory | `src/Loader/`, `src/ElementDefinitionFactory.php` | Walk the DOM and attach matching definitions                    |

Supporting entry points:

- `cli.php` registers all console commands.
- `src/Commands/AbstractDataCommand.php` contains shared command behavior.
- `src/Services/ServiceFactory.php` initializes and exposes shared services.
- `src/Services/BaseService.php` loads cache maps and creates typed documents.
- `src/DocumentTypes/RootDocument.php` is the base class for XML-backed documents.
- `src/Formats/BaseFormat.php` is the base class for export mappers.

## Runtime Flow

### 1. Commands orchestrate the export

Commands prepare services, create output directories, iterate records, and write JSON files.

Typical examples:

- `load:data` runs multiple exports.
- `load:items` writes `items.json`, filtered index files, and per-item JSON files.
- `load:vehicles`, `load:blueprints`, `load:starmap`, and similar commands each drive one export pipeline.

`AbstractDataCommand` provides the shared pieces:

- cache generation/bootstrap
- input/output path helpers
- directory creation
- JSON file writing
- temporary lazy-reference mode for selected services

### 2. Services locate and load records

Services are the boundary between cache/index lookups and typed documents.

`ServiceFactory` initializes the active Star Citizen data path and boots shared service instances on demand. Commands generally talk to services through `ServiceFactory`.

`BaseService` provides the common mechanics:

- load cache maps such as UUID-to-path and class-to-path
- validate cache path normalization
- resolve references to XML file paths
- instantiate `RootDocument` subclasses
- propagate the current hydration mode into loaded documents

Service classes then add domain-specific lookup logic, iteration, and caching. Examples:

- `ItemService` iterates `EntityClassDefinition` records for item exports
- `VehicleService` loads vehicle records and vehicle-specific derived data
- `BlueprintService` resolves crafting blueprints
- `FoundryLookupService` provides typed record lookups by reference
- `LoadoutFileService` resolves XML-backed loadout files

### 3. Document types provide typed XML access

Each `RootDocument` subclass wraps one XML document type and exposes methods that hide raw XML traversal from the rest of the codebase.

Typical document responsibilities:

- expose scalar getters for local values
- expose typed helpers for related records
- expose normalized helpers for repeated XML shapes
- keep format code from growing into a second lookup layer

Important base functionality lives in `RootDocument`:

- `getClassName()`, `getType()`, `getPath()`, `getUuid()`
- typed scalar helpers such as `getString()`, `getInt()`, `getFloat()`, `getBool()`
- relation helpers such as `getHydratedDocument()`, `resolveRelatedDocument()`, and `resolveRelatedDocuments()`

Representative current documents:

- `EntityClassDefinition` for most item and vehicle entity exports
- `Loadout` and `Loadout\LoadoutEntry` for loadout traversal
- `Crafting\CraftingBlueprintRecord` for blueprint exports
- `Starmap\StarMapObject` for starmap exports
- `Faction\Faction` and `Faction\FactionReputation` for faction exports

### 4. Definitions mutate the DOM when needed

Definitions are not the primary API for most contributors. They exist for cases where the XML tree needs to be extended during load.

`ElementLoader` walks the DOM after a document is loaded. `ElementDefinitionFactory` maps XML element paths to matching classes in `src/Definitions/`. A definition can then resolve related XML and append it into the current DOM tree.

Use a definition when:

- a related record must appear in the DOM as part of document initialization
- multiple consumers rely on that nested structure being present
- the behavior is truly a document-load concern, not just a convenient lookup

Do not use a definition just to avoid adding a typed helper. If a format needs a related record, prefer a document method first.

### 5. Formats shape export output

Formats should read like output mappers. They receive a typed document and return the array structure that will become JSON.

`BaseFormat` provides:

- `get()` and `has()` wrappers for local XML reads
- localization helpers
- JSON conversion helpers
- array post-processing helpers

Current format conventions:

- use typed document helpers for cross-document relations
- keep direct XPath-like access for local scalar values
- avoid embedding service lookup logic directly in format classes unless there is no document-level helper yet

Examples:

- `Formats\ScUnpacked\Item` builds the item export from `EntityClassDefinition`
- `Formats\ScUnpacked\Ship` builds vehicle output from typed vehicle helpers
- `Formats\ScUnpacked\Blueprint` reads blueprint-specific typed relations

## Working In The Codebase

### Adding or changing an export

When you need to change exported JSON:

1. Find the command that owns the export.
2. Find the service that supplies the source documents.
3. Check whether the source document already exposes the data as a typed helper.
4. Add or update the format class that shapes the output.
5. Add tests close to the changed behavior.

In most cases, export work belongs in formats and document types, not in commands.

### Adding a typed document helper

If a format or service needs related data:

1. Add a `getXReference()` method if the XML stores a reference.
2. Add a `getX()` method returning the typed related document.
3. Use `resolveRelatedDocument()` or `resolveRelatedDocuments()` to support both hydrated and non-hydrated access.

This keeps relation logic in one place and makes formats simpler to maintain.

### Adding a definition

Add a definition only when DOM mutation is required during document load.

A typical definition should:

1. guard against running twice
2. call `parent::initialize()`
3. resolve the related record
4. append it only when it is not already present

If no DOM mutation is needed, a typed helper on the document is usually the better abstraction.

### Adding a service-level cache

If a service caches documents and those documents differ depending on hydration mode, include hydration mode in the cache key. Lazy and eager documents must not alias each other in the same cache entry.

This matters for services such as `BlueprintService` and `LoadoutFileService`.

## Reference Hydration

Reference hydration is part of the current architecture, but it is an internal loading strategy, not the main mental model for the repo.

### What it means

When reference hydration is enabled, definitions can append related XML records into the current DOM during document load.

When it is disabled, documents stay lighter and related records are expected to be resolved on demand through typed helpers and services.

`BaseService::loadDocument()` passes the service's hydration mode into each created `RootDocument`.

### Important current behavior

- `RootDocument::fromNode()` defaults `referenceHydrationEnabled` to `false`
- `RootDocument::load()` and `RootDocument::loadXML()` both run `ElementLoader::load()`
- typed relation helpers should work whether related data is already hydrated or must be resolved lazily

### When to use lazy reference mode

Some export commands intentionally disable reference hydration while building output because they only need a small subset of related data.

Shared support lives in:

```php
AbstractDataCommand::withLazyReferenceHydration(array $services, callable $callback): mixed
```

Use this when:

- eager hydration would append large subtrees that the export does not need
- the document layer already exposes the needed related records through typed helpers

Current commands using this pattern include `load:blueprints`, `load:factions`, `load:mineables`, and `load:starmap`.

## Loadouts

Loadouts have a dedicated typed pipeline and should not be treated as ad hoc XML fragments.

Current structure:

- `LoadoutFileService` resolves `Scripts/Loadouts/...` XML files
- `Loadout` is a `RootDocument` that can wrap a bare loadout root, an empty loadout, or a parent document containing a nested loadout
- `Loadout\LoadoutEntry` normalizes both manual and XML-backed loadout entry shapes
- `EntityClassDefinition::getDefaultLoadoutEntries()` is the canonical way to read default loadouts

Contributor guidance:

- use `LoadoutEntry` helpers instead of manual child traversal
- prefer `getPortName()`, `getEntityClassName()`, `getEntityClassReference()`, `getInstalledItem()`, and `getNestedEntries()`
- keep loadout normalization in the typed layer rather than in formats

Current consumers include:

- `Formats\ScUnpacked\Item`
- `Formats\ScUnpacked\Loadout`
- `Formats\ScUnpacked\WeaponAttachment`
- `Services\Vehicle\LoadoutBuilder`

## Cache Files

Cache generation is required before most export work. The cache files are generated by `generate:cache` and then consumed by services.

Important cache maps:

| File                          | Purpose                                              |
|-------------------------------|------------------------------------------------------|
| `uuidToPathMap-{OS}.json`     | Resolve UUID references to XML paths                 |
| `uuidToClassMap-{OS}.json`    | Resolve UUID references to class names               |
| `classToPathMap-{OS}.json`    | Iterate all records for a document class             |
| `classToUuidMap-{OS}.json`    | Reverse class-name lookup                            |
| `classToTypeMap-{OS}.json`    | Entity type/classification lookup                    |
| `entityMetadataMap-{OS}.json` | Compact item metadata for subtype and entity queries |

Important runtime details:

- cache paths must use normalized forward slashes
- if cache files contain Windows backslashes, regenerate them
- services rely on shared static cache state, so `ServiceFactory::reset()` is important in tests and isolated runs

## Testing Guidance

Tests are organized by subsystem:

- `tests/Commands/` for CLI/export behavior
- `tests/Services/` for lookup, caching, and orchestration
- `tests/DocumentTypes/` for typed XML access and relations
- `tests/Formats/` for export mapping behavior
- `tests/Helper/` for low-level XML helper behavior

When changing behavior:

- add or update the narrowest test that proves the behavior
- prefer document or format tests for typed helper/output changes
- use command tests when the change affects export orchestration or file-writing behavior

## Contributor Checklist

- Start from the owning command, then trace into service, document, and format layers.
- Put cross-document lookup logic on `RootDocument` subclasses when possible.
- Keep format classes focused on shaping output, not resolving the world.
- Use definitions only for real DOM-mutation concerns.
- Regenerate cache when working with fresh SC data or cache-shape changes.
- Include hydration mode in cache keys when cached documents can differ between lazy and eager loading.
- Add tests next to the subsystem you changed.
