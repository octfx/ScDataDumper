#!/usr/bin/env php
<?php

// application.php

require __DIR__.'/vendor/autoload.php';

use Octfx\ScDataDumper\Commands\GenerateCache;
use Octfx\ScDataDumper\Commands\LoadItems;
use Octfx\ScDataDumper\Commands\LoadVehicles;
use Symfony\Component\Console\Application;

$application = new Application('ScDataDumper', '1.0.0');

$application->add(new GenerateCache);
$application->add(new LoadItems);
$application->add(new LoadVehicles);

$application->run();
