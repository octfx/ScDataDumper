# ScDataDumper

## Installation
```shell
composer install --no-dev
```

## Usage
When running for the first time, cache files must be generated.

All commands require unforged SC XML files in a dedicated folder.

```shell
php cli.php generate:cache Path/To/ScDataDir
```

## Dumping items

```shell
php cli.php load:items --scUnpackedFormat Path/To/ScDataDir Path/To/Output
```
