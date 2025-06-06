#!/usr/bin/env php
<?php

// application.php

require __DIR__.'/vendor/autoload.php';

use Octfx\ScDataDumper\Commands\GenerateCache;
use Octfx\ScDataDumper\Commands\LoadData;
use Octfx\ScDataDumper\Commands\LoadFactions;
use Octfx\ScDataDumper\Commands\LoadItems;
use Octfx\ScDataDumper\Commands\LoadManufacturers;
use Octfx\ScDataDumper\Commands\LoadTranslations;
use Octfx\ScDataDumper\Commands\LoadVehicles;
use Symfony\Component\Console\Application;

$application = new Application('ScDataDumper', '1.0.0');

$application->add(new GenerateCache);
$application->add(new LoadItems);
$application->add(new LoadVehicles);
$application->add(new LoadFactions);
$application->add(new LoadData);
$application->add(new LoadTranslations);
$application->add(new LoadManufacturers);

$application->run();
