<?php

namespace RedberryProducts\Zephyr;

use RedberryProducts\Zephyr\Commands\GenerateCommand;
use RedberryProducts\Zephyr\Commands\RunTestCycle;
use RedberryProducts\Zephyr\Commands\SendResults;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ZephyrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-zephyr')
            ->hasConfigFile('zephyr')
            ->hasCommands([
                GenerateCommand::class,
                SendResults::class,
                RunTestCycle::class,
            ]);
    }
}
