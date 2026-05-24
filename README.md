# ScDataDumper

A PHP CLI tool for extracting and converting Star Citizen XML data into structured JSON.

## Quickstart

Unpack game data using [unp4k](https://github.com/dolkensp/unp4k):
```shell
./unp4k.exe 'C:\Program Files\Roberts Space Industries\StarCitizen\LIVE\Data.p4k' *.xml
./unp4k.exe 'C:\Program Files\Roberts Space Industries\StarCitizen\LIVE\Data.p4k' *.ini
./unp4k.exe 'C:\Program Files\Roberts Space Industries\StarCitizen\LIVE\Data.p4k' *.socpak
./unforge.exe .
```

Put the unforged SC XML files (`Data` and `Engine` folders) in the `import` directory.

Start the container:
```shell
docker compose up -d --build --force-recreate
```

Dump everything in one go:
```shell
docker compose exec scdatadumper php cli.php load:data import export
```

Or run individual commands (see [Commands](#commands) below).

## Non-Docker installation

```shell
composer install --no-dev
```

## Commands

All commands take `scDataPath` as the path to the unpacked SC data directory (e.g. `import`). Most also take a `jsonOutPath` for output (e.g. `export`).

### `load:data`

Umbrella command that runs all load commands in sequence.

```shell
php cli.php load:data <scDataPath> <jsonOutPath> [--overwrite]
```

### `generate:cache`

When calling commands individually, this MUST run first.  
Generates cache files (class/path/type/UUID maps, entity metadata) and `socpak_mappings.json`. Automatically skipped if all cache files already exist.

```shell
php cli.php generate:cache <scDataPath> [--overwrite]
```

### `load:items`

Dumps SC items to individual JSON files plus index files (`items.json`, `fps-items.json`, `ship-items.json`).

```shell
php cli.php load:items <scDataPath> <jsonOutPath> [--overwrite] [-f|--filter FILTER] [-t|--typeFilter TYPE]
```

- `--filter` / `-f`: Only include items whose name contains this substring
- `--typeFilter` / `-t`: Exclude items of the given type (repeatable)

### `load:vehicles`

Dumps ships to a `ships/` directory plus `ships.json` index.

```shell
php cli.php load:vehicles <scDataPath> <jsonOutPath> [--overwrite] [-f|--filter FILTER] [--with-raw]
```

- `--filter` / `-f`: Only include vehicles whose name contains this substring
- `--with-raw`: Include raw XML->JSON data in output

### `load:blueprints`

Dumps crafting blueprints to individual files plus `blueprints.json` index.

```shell
php cli.php load:blueprints <scDataPath> <jsonOutPath> [--overwrite] [-f|--filter FILTER]
```

### `load:commodities`

Dumps commodities (ResourceType) with mineable element data, quality tiers, cargo containers, etc. to `resources/commodities.json`.

```shell
php cli.php load:commodities <scDataPath> <jsonOutPath> [--overwrite]
```

### `load:commodity-trade-locations`

WIP

Links commodities to trade locations via tag hierarchy matching. Outputs `resources/commodity_trade_locations.json`.

```shell
php cli.php load:commodity-trade-locations <scDataPath> <jsonOutPath> [--overwrite]
```

### `load:contracts`

Dumps contract generator data (contracts, intro, legacy, PVP bounty) with mission chain index to `contracts/` directory.

```shell
php cli.php load:contracts <scDataPath> <jsonOutPath> [--overwrite]
```

### `load:factions`

Dumps factions to a `factions/` directory.

```shell
php cli.php load:factions <scDataPath> <jsonOutPath> [--overwrite]
```

### `load:starmap`

Dumps starmap objects (`starmap.json`) and trade locations (`trade_locations.json`).

```shell
php cli.php load:starmap <scDataPath> <jsonOutPath> [--overwrite] [-f|--filter FILTER]
```

### `load:translations`

Dumps English translations to `labels.json`.

```shell
php cli.php load:translations <scDataPath> <jsonOutPath>
```

### `load:manufacturers`

Dumps manufacturers to `manufacturers.json`.

```shell
php cli.php load:manufacturers <scDataPath> <jsonOutPath>
```

### `load:tags`

Dumps tag database to `tags.json`.

```shell
php cli.php load:tags <scDataPath> <jsonOutPath> [--overwrite]
```

### `load:resources`

Dumps mineables, harvestables, and salvage resources (`resources/resources.json`) and mining/gathering locations (`resources/locations.json`).

```shell
php cli.php load:resources <scDataPath> <jsonOutPath> [--overwrite]
```

### `schema:diff`

Compares XML schema snapshots between two import directories or pre-built `.json` snapshot files.  
Reports new/removed paths, cardinality changes, new/removed attributes, and cross-references against implemented DocumentTypes for possible breakages.  
Exits with code 1 if changes are detected.

```shell
php cli.php schema:diff <old> <new> [--type TYPE] [-o|--output FILE] [-q|--quiet]
```

- `--type` / `-t`: Filter to specific document types
- `--output` / `-o`: Write the report to a file
- `--quiet` / `-q`: Only show changes (skip unchanged types)

## Common options

| Option                | Description                                                  |
|-----------------------|--------------------------------------------------------------|
| `--overwrite`         | Overwrite existing output files (most commands)              |
| `--filter` / `-f`     | Name substring filter (items, vehicles, blueprints, starmap) |
| `--typeFilter` / `-t` | Exclude types (items only)                                   |
