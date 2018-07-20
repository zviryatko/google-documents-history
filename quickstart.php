<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use zviryatko\GDocHist\Command\SelectFile;
use zviryatko\GDocHist\GoogleServiceProvider;

$application = new Application();
$application->add(new SelectFile('select-file', (new GoogleServiceProvider)->create()));
$application->run();
