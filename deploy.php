<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'contrib/cachetool.php';
require __DIR__ . '/vendor/tijsverkoyen/deployer-sumo/sumo.php';

// Define some variables
set('client', '$client');
set('project', '$project');
set('repository', '$repository');
set('production_url', '$productionUrl');
set('production_user', '$productionUser');
set('php_version', '8.2');

// Define staging
host('dev02.sumocoders.eu')
    ->setRemoteUser('sites')
    ->set('labels', ['stage' => 'staging'])
    ->set('deploy_path', '~/apps/{{client}}/{{project}}')
    ->set('branch', 'staging')
    ->set('bin/php', '{{php_binary}}')
    ->set('cachetool', '/var/run/php_{{php_version_numeric}}_fpm_sites.sock')
    ->set('document_root', '~/php{{php_version_numeric}}/{{client}}/{{project}}')
    ->set('keep_releases', 2);

// Define production
//host('$host')
//    ->setRemoteUser('{{production_user}}')
//    ->set('labels', ['stage' => 'production'])
//    ->setPort(2244)
//    ->set('deploy_path', '~/wwwroot')
//    ->set('branch', 'master')
//    ->set('bin/php', '{{php_binary}}')
//    ->set('cachetool', '/data/vhosts/{{production_user}}/.sock/{{production_user}}-{{php_binary}}.sock --tmp-dir=/data/vhosts/{{production_user}}/.temp')
//    ->set('document_root', '~/wwwroot/www')
//    ->set('http_user', '{{production_user}}')
//    ->set('writable_mode', 'chmod') // Cloudstar only
//    ->set('keep_releases', 3);

/*************************
 * No need to edit below *
 *************************/

set('php_binary', function () {
    return 'php' . get('php_version');
});

set('php_version_numeric', function () {
    return (int) filter_var(get('bin/php'), FILTER_SANITIZE_NUMBER_INT);
});

set('use_relative_symlink', false);

// Shared files/dirs between deploys
add('shared_files', ['.env.local']);
add('shared_dirs', []);

// Writable dirs by web server
add('writable_dirs', []);

// Disallow stats
set('allow_anonymous_stats', false);

/*
 * Composer
 * Deployer also has this download&run snippet in its core, but they only use it
 * when a global `composer` option isn't available. We always want to use the
 * phar version from shared so we overwrite the default behaviour here.
 */
set('shared_folder', '{{deploy_path}}/shared');
set('bin/composer', function () {
    if (!test('[ -f {{shared_folder}}/composer.phar ]')) {
        run("cd {{shared_folder}} && curl -sLO https://getcomposer.org/download/latest-stable/composer.phar");
    }
    return '{{bin/php}} {{shared_folder}}/composer.phar';
});

set('keep_releases', 3);

/**********************
 * Flow configuration *
 **********************/
// Clear the Opcache
after('deploy:symlink', 'cachetool:clear:opcache');
// Unlock the deploy when it failed
after('deploy:failed', 'deploy:unlock');
// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');
