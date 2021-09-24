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

// Define staging
host('dev02.sumocoders.eu')
    ->setRemoteUser('sites')
    ->set('labels', ['stage' => 'staging'])
    ->set('deploy_path', '~/apps/{{client}}/{{project}}')
    ->set('branch', 'staging')
    ->set('bin/php', 'php8.0')
    ->set('document_root', '~/php74/{{client}}/{{project}}');

// Define production
//host('$host')
//    ->setRemoteUser(get('production_user'))
//    ->set('labels', ['stage' => 'production'])
//    ->port(2244)
//    ->set('deploy_path', '~/wwwroot')
//    ->set('branch', 'master')
//    ->set('bin/php', 'php8.0')
//    ->set('document_root', '~/wwwroot/www')
//    ->set('http_user', get('production_user'))
//    ->set('writable_mode', 'chmod'); Cloudstar only

/*************************
 * No need to edit below *
 *************************/

set('use_relative_symlink', false);

// Shared files/dirs between deploys
add('shared_files', ['.env.local']);
add('shared_dirs', []);

// Writable dirs by web server
add('writable_dirs', []);

// Disallow stats
set('allow_anonymous_stats', false);

// Composer
set('shared_folder', '{{deploy_path}}/shared');
set('bin/composer', function () {
    if (!test('[ -f {{shared_folder}}/composer.phar ]')) {
        run("cd {{shared_folder}} && curl -sLO https://getcomposer.org/download/latest-stable/composer.phar");
    }
    return '{{bin/php}} {{shared_folder}}/composer.phar';
});

set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');

/*****************
 * Task sections *
 *****************/
// Build tasks
task(
    'build:assets:npm',
    function () {
        $nvmPath = trim(shell_exec('echo $HOME/.nvm/nvm.sh'));

        if (file_exists($nvmPath)) {
            run('. ' . $nvmPath . ' && nvm use && nvm exec npm run build');
        } else {
            run('npm run build');
        }
    }
)
    ->desc('Run the build script which will build our needed assets.')
    ->local();

// Upload tasks
task(
    'upload:assets',
    function () {
        upload(__DIR__ . '/public/build', '{{release_path}}/public');
    }
)
    ->desc('Uploads the assets')
    ->addBefore('build:assets:npm');
after('deploy:update_code', 'upload:assets');

/**********************
 * Flow configuration *
 **********************/
// Clear the Opcache
after('deploy:symlink', 'cachetool:clear:opcache');
// Unlock the deploy when it failed
after('deploy:failed', 'deploy:unlock');
// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');
