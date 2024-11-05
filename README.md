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

## Docker usage

Copy `data.p4k` to the `import` directory

Start the container with `docker compse up -d --build`

```shell
docker compose exec scdatadumper php cli.php generate:cache import
```

```shell
docker compose exec scdatadumper php cli.php load:items --scUnpackedFormat import export
```