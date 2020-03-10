<?php

namespace App\Skeleton;

use Composer\Script\Event;
use Composer\IO\IOInterface;

class PostCreateProject
{
    public static function run(Event $event)
    {
        $io = $event->getIO();

        self::fixSecurityChecker($io);
        self::runNpmInstall($io);
        self::installNpmPackages($io);
        self::addJsAndSass($io);
        self::addConfigurationToWebpack($io);
        self::cleanup($io);
    }

    private static function fixSecurityChecker(IOInterface $io)
    {
        $io->info('Fix Security Checker');

        echo "Fixing security-checker\n";
        exec(
            'sed -i \'\' \'s/"security-checker security:check": "script"/"[ $COMPOSER_DEV_MODE -eq 0 ] || security-checker security:check": "script"/g\' composer.json'
        );
    }

    private static function runNpmInstall(IOInterface $io)
    {

        $io->info('Run NPM install');
    }

    private static function installNpmPackages(IOInterface $io)
    {
        $io->info('Install NPM packages');
    }

    private static function addJsAndSass(IOInterface $io)
    {
        $io->info('Add JS and SASS');
    }

    private static function addConfigurationToWebpack(IOInterface $io)
    {
        $io->info('Add Configuration to webpack');
    }

    private static function cleanup(IOInterface $io)
    {
        $io->info('Cleanup');
    }
}