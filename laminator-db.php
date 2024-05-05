#!/usr/bin/env php
<?php

namespace DbTableInstigator;

use Laminas\ConfigAggregator\{
    ConfigAggregator,
    PhpFileProvider,
    LaminasConfigProvider
};
use Laminas\Db\Adapter\Adapter as DbAdapter;
use Laminas\Db\Metadata;

use Symfony\Component\Console\Application;

require __DIR__ . '/vendor/autoload.php';

$wDir = getcwd();

// Config
$mvcConfigPattern = $wDir . '/config/autoload/*{global,local,.global,.local}.{php}';
$customConfigFile = __DIR__ . '/app.ini';
$configAggregator = new ConfigAggregator([
    new PhpFileProvider($mvcConfigPattern),
    new LaminasConfigProvider($customConfigFile),
]);
$config = $configAggregator->getMergedConfig();

// Db Adapter
if (!isset($config['db'])) {
    die("\e[1;37;41mCannot instantiate DbAdapter: Missing db-config\e[0m\n");
}

$dbAdapter = new DbAdapter($config['db']);

$dbMetadata = Metadata\Source\Factory::createSourceFromAdapter($dbAdapter);

// Symfony console application
$application = new Application(App::APP_NAME, App::APP_VERSION);

$formCommand = new Command\FormCommand();
$formCommand
    ->setAppDir($wDir)
    ->setDbMetadata($dbMetadata);
$application->add($formCommand);

$entityCommand = new Command\EntityCommand();
$entityCommand
    ->setAppDir($wDir)
    ->setDbMetadata($dbMetadata);
$application->add($entityCommand);

#$application->add(new Command\TableToFormCommand($dbAdapter, $wdir, $config));
#$application->add(new Command\TableToTableClassCommand($dbAdapter, $wdir, $config));

$application->run();
