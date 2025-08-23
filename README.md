# ScDataDumper

## Quickstart
Unpack game data using [unp4k](https://github.com/dolkensp/unp4k):
```shell
./unp4k.exe 'C:\Program Files\Roberts Space Industries\StarCitizen\LIVE\Data.p4k' *.xml
./unp4k.exe 'C:\Program Files\Roberts Space Industries\StarCitizen\LIVE\Data.p4k' *.ini
./unforge.exe .
```

Put the unforged SC XML files (`Data` and `Engine` folders) in the `import` directory.

Start the container with `docker compose up -d --build --force-recreate`

Dumping all files is done using:
```shell
docker compose exec scdatadumper php cli.php load:data --scUnpackedFormat import export
```

Alternatively, when running commands for the first time separately, generate the cache files:
```shell
docker compose exec scdatadumper php cli.php generate:cache import
```

Dump items:
```shell
docker compose exec scdatadumper php cli.php load:items --scUnpackedFormat import export
```

Dump vehicles:
```shell
docker compose exec scdatadumper php cli.php load:vehicles import export
```

## Advanced usage
### Non-Docker installation
Install dependency with Composer
```shell
composer install --no-dev
```

## Commands
### Generate cache
This is needed when you are running for the first time.
```shell
php cli.php generate:cache Path/To/ScDataDir
```

## Dumping items
```shell
php cli.php load:items --scUnpackedFormat Path/To/ScDataDir Path/To/Output
```