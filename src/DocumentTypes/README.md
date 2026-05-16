# DocumentTypes

Typed wrappers around Star Citizen XML data files.

Each class extends `RootDocument` (which extends `DOMDocument`) and provides typed accessor methods for a specific SC data XML format.
The root element tag name (`EntityClassDefinition.***`, `VehicleDefinition.***`, `ResourceType.***`, etc.) determines which class is instantiated.

## Lifecycle

```
Service loads .xml from disk
  -> $doc->load($path)           // RootDocument::load() parses XML + optionally hydrates refs
  -> $doc->toArray()             // Recursively converts DOM to structured array
  -> Passed to Format classes    // src/Formats/ScUnpacked/ transforms raw data into public output
```

Services (`ItemService`, `VehicleService`, `FoundryLookupService`, etc.) load files from disk.
Commands then pass the resulting `RootDocument` instances to Format classes (`src/Formats/ScUnpacked/`), which call the typed accessors to transform raw XML data into public-facing output.

DocumentTypes have two first-party consumers:
1. **Format classes** (`src/Formats/ScUnpacked/`): call typed accessors and `toArray()` to produce structured output JSON
2. **Services** (`src/Services/`): load documents from disk, cache them, and serve them to both Formats and other services

## Key concepts

- **RootDocument**: base class. Parses XML and provides the shared accessor layer used by all DocumentTypes:
  - Attribute readers: `getString()`, `getFloat()`, `getInt()`, `getBool()`, `getNullableBool()`
  - Document metadata: `getClassName()`, `getType()`, `getPath()`, `getUuid()`
  - XPath-scoped `get()` via the `XmlAccess` trait
  - `toArray()` for recursive DOM-to-array conversion

  Concrete DocumentTypes add domain-specific accessors on top (e.g. `EntityClassDefinition::getAttachDef()`, `ResourceType::getDensity()`, `VehicleDefinition::getVehicleComponentParams()`).
  **All XML reading happens in DocumentTypes**: Format classes and services should never touch the DOM directly.

- **fromNode()**: creates a sub-document from an in-memory DOMNode. Used to parse embedded child documents (e.g. a `ContractEntry` inside a `ContractHandler`).

- (DEPRECATED) **Reference hydration**: when enabled (only during `LoadItems`), the `ElementLoader` walks all DOM nodes and inlines cross-referenced entities
  (magazines into weapons, resource types into mineables, etc.) via the `Definitions\` layer. See `src/Definitions/README.md`.

## Adding a new DocumentType

1. Create `src/DocumentTypes/YourType.php` extending `RootDocument`.
2. Add typed accessors for the XML attributes/elements you need.
3. Override `toArray()` if you need custom serialization; otherwise the default recursive walk handles it.
4. Register the class in the appropriate service so it gets loaded from disk.
