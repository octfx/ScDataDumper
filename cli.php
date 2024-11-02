#!/usr/bin/env php
<?php

// application.php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Octfx\ScDataDumper\Commands\GenerateCache;

$application = new Application('ScDataDumper', '1.0.0');

$application->add(new GenerateCache());
$application->add(new \Octfx\ScDataDumper\Commands\LoadItems());

//$application->add(new \Commands\GenerateCache);
//$application->all('Octfx\\ScDataDumper\\Commands');

$application->run();
