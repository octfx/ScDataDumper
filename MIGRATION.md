# Migration Guide

## Breaking Changes (since `7c780b38`)

The following breaking changes were introduced after commit `7c780b38`. Each is annotated with the commit that introduced it.

---

### 1. PascalCase JSON Output (`7ac202f`, `280f569`)

All JSON output keys are now **PascalCase**. This affects every output type: Blueprint, Faction, ResourceLocation, StarMapObject, WeaponRegenPool, Manufacturers, Resources, Commodities, DriveCharacteristics, and all nested XML-sourced keys.

**How to migrate:** Update any code that reads JSON keys to use the new PascalCase names. See the [detailed PascalCase reference](#what-changed) below for a complete mapping of old → new keys and search/replace patterns.

---

### 2. Blueprint Modifier Keys Renamed (`7ac202f`)

In Blueprint stat modifier output, two keys were renamed:

| Old Key          | New Key  |
|------------------|----------|
| `PropertyUuid`   | `UUID`   | 
| `PropertyKey`    | `Key`    |

**How to migrate:**

```
.PropertyUuid   -> .UUID
.PropertyKey    -> .Key
['PropertyUuid'] -> ['UUID']
['PropertyKey']  -> ['Key']
```

---

### 3. Resource Types → Commodities (`555e998`)

The resource types command and output file were renamed:

| What          | Old                    | New                          |
|---------------|------------------------|------------------------------|
| Output file   | `resource-types.json`  | `resources/commodities.json` |
| CLI command   | `load:resource-types`  | `load:commodities`           |
| PHP class     | `LoadResourceTypes`    | `LoadCommodities`            |

**How to migrate:**
- Update any scripts that reference `resource-types.json` to read `resources/commodities.json` instead.
- Update CLI invocations from `load:resource-types` to `load:commodities`.
- Update any class references from `LoadResourceTypes` to `LoadCommodities`.

## PascalCase Standardization (Detail Reference)

All JSON output keys have been standardized to PascalCase. This is a **breaking change** that requires updates to any code consuming the ScDataDumper JSON output.

---

## What Changed

### Naming Convention

All JSON output keys now consistently use **PascalCase**:
- snake_case -> PascalCase: `user_name` -> `UserName`
- camelCase -> PascalCase: `itemType` -> `ItemType`
- Acronyms preserved: `uuid` -> `UUID`


### Key Changes by Category

**ItemPort.php** - CompatibleTypes structure:
- `'type'` -> `'Type'`
- `'sub_types'` -> `'SubTypes'`

**InventoryContainer.php**:
- `'unit'` -> `'Unit'`
- `'unitName'` -> `'UnitName'`
- `'minSize'` -> `'MinSize'`
- `'maxSize'` -> `'MaxSize'`
- `'isOpenContainer'` -> `'IsOpenContainer'`
- `'isExternalContainer'` -> `'IsExternalContainer'`
- `'isClosedContainer'` -> `'IsClosedContainer'`
- `'uuid'` -> `'UUID'`

**Helmet.php** - Suit helmet parameters:
- `'atmosphere_capacity'` -> `'AtmosphereCapacity'`
- `'puncture_max_area'` -> `'PunctureMaxArea'`
- `'puncture_max_number'` -> `'PunctureMaxNumber'`

---

## Before/After Examples

### InventoryContainer Output

```json
// Before
{
  "SCU": 2.5,
  "unit": "cSCU",
  "unitName": "Centi SCU",
  "minSize": 0,
  "maxSize": 4,
  "isOpenContainer": false,
  "isExternalContainer": false,
  "isClosedContainer": true,
  "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}

// After
{
  "SCU": 2.5,
  "Unit": "cSCU",
  "UnitName": "Centi SCU",
  "MinSize": 0,
  "MaxSize": 4,
  "IsOpenContainer": false,
  "IsExternalContainer": false,
  "IsClosedContainer": true,
  "UUID": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

### ItemPort CompatibleTypes

```json
// Before
{
  "CompatibleTypes": [
    {
      "type": "WeaponGun",
      "sub_types": ["Ballistic", "Energy"]
    },
    {
      "type": "WeaponDefensive",
      "sub_types": []
    }
  ]
}

// After
{
  "CompatibleTypes": [
    {
      "Type": "WeaponGun",
      "SubTypes": ["Ballistic", "Energy"]
    },
    {
      "Type": "WeaponDefensive",
      "SubTypes": []
    }
  ]
}
```

### Helmet Output

```json
// Before
{
  "atmosphere_capacity": 300,
  "puncture_max_area": 0.5,
  "puncture_max_number": 3
}

// After
{
  "AtmosphereCapacity": 300,
  "PunctureMaxArea": 0.5,
  "PunctureMaxNumber": 3
}
```

### Nested XML Attributes (TractorBeam, Shield, etc.)

All nested object keys from XML attributes are now PascalCase:

```json
// Before (Shield StunParams)
{
  "StunParams": {
    "stunDuration": 5.0,
    "stunImpulse": 100.0
  }
}

// After
{
  "StunParams": {
    "StunDuration": 5.0,
    "StunImpulse": 100.0
  }
}
```

---

## Complete Change Reference

| File | Old Key | New Key | Context |
|------|---------|---------|---------|
| **InventoryContainer.php** | `unit` | `Unit` | Container unit type |
| | `unitName` | `UnitName` | Human-readable unit name |
| | `minSize` | `MinSize` | Minimum item size |
| | `maxSize` | `MaxSize` | Maximum item size |
| | `isOpenContainer` | `IsOpenContainer` | Open container flag |
| | `isExternalContainer` | `IsExternalContainer` | External container flag |
| | `isClosedContainer` | `IsClosedContainer` | Closed container flag |
| | `uuid` | `UUID` | Container UUID |
| **ItemPort.php** | `type` | `Type` | Compatible type major category |
| | `sub_types` | `SubTypes` | Compatible type subcategories |
| **Helmet.php** | `atmosphere_capacity` | `AtmosphereCapacity` | Helmet oxygen capacity |
| | `puncture_max_area` | `PunctureMaxArea` | Max puncture area |
| | `puncture_max_number` | `PunctureMaxNumber` | Max number of punctures |
| **All XML attributes** | *varies* | PascalCase | All XML-sourced keys now PascalCase |

---

## Search and Replace Patterns

### Exact Replacements

Use these search/replace patterns in your codebase:

**InventoryContainer:**
```
.unit           -> .Unit
.unitName       -> .UnitName
.minSize        -> .MinSize
.maxSize        -> .MaxSize
.isOpenContainer -> .IsOpenContainer
.isExternalContainer -> .IsExternalContainer
.isClosedContainer -> .IsClosedContainer
['unit']        -> ['Unit']
['unitName']    -> ['UnitName']
['minSize']     -> ['MinSize']
['maxSize']     -> ['MaxSize']
```

**Helmet:**
```
.atmosphere_capacity  -> .AtmosphereCapacity
.puncture_max_area    -> .PunctureMaxArea
.puncture_max_number  -> .PunctureMaxNumber
['atmosphere_capacity'] -> ['AtmosphereCapacity']
['puncture_max_area']   -> ['PunctureMaxArea']
['puncture_max_number'] -> ['PunctureMaxNumber']
```

**ItemPort CompatibleTypes:**
```
.type        -> .Type
.sub_types   -> .SubTypes
['type']     -> ['Type']
['sub_types'] -> ['SubTypes']
```

**UUID fields:**
```
.uuid   -> .UUID
['uuid'] -> ['UUID']
->uuid  -> ->UUID
```
