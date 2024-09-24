<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostCreateProject
{
    public static function run(Event $event): void
    {
        self::pinVolta();
        self::runNpmInstall($event);
        self::installNpmPackages($event);
        self::installFrameworkStylePackage($event);
        self::reconfigureWebpack($event);
        self::createAssets($event);
        self::reconfigureApplication($event);
        self::cleanupFiles($event);
        self::cleanup($event);
        self::runNpmBuild($event);
        self::dumpInitialTranslations($event);
    }

    private static function runNpmInstall(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Run `npm install`');

        $output = shell_exec('npm install');
        if ($io->isVerbose()) {
            $io->write($output);
        }
    }

    private static function installNpmPackages(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Install required NPM packages');

        $packages = [
            'standard',
            'standard-gitlab',
            'stylelint',
            'stylelint-config-standard',
            'stylelint-config-standard-scss',
            'stylelint-formatter-gitlab-code-quality-report',
            '@dshbuilds/gitlab-npm-audit-parser',
        ];

        if ($io->isVerbose()) {
            $io->write(
                sprintf(
                    '   Install packages (%1$s) that are required for our git hooks.',
                    implode(', ', $packages)
                )
            );
        }

        $command = sprintf('npm install %1$s --save-dev', implode(' ', $packages));
        $output = shell_exec($command);

        if ($io->isVerbose()) {
            $io->write($output);
        }
    }

    private static function installFrameworkStylePackage(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Install sumocoders/FrameworkStylePackage');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Install required NPM packages for FrameworkStylePackage');
        $packages = [
            'sass-embedded',
            'frameworkstylepackage@^4',
        ];
        if ($io->isVerbose()) {
            $io->write(
                sprintf(
                    '   Install packages (%1$s) that are required for our FrameworkStylePackage.',
                    implode(', ', $packages)
                )
            );
        }

        $command = sprintf('npm install %1$s --save-dev', implode(' ', $packages));
        $output = shell_exec($command);

        if ($io->isVerbose()) {
            $io->write($output);
        }

        $io->notice('→ Copy the imports');
        if ($io->isVerbose()) {
            $io->write('   Copy the Index.js file so we can manipulate the import specifically for this project.');
        }
        $assetsJsPath = $projectDir . '/assets/js';
        if (!is_dir($assetsJsPath)) {
            mkdir($assetsJsPath);
        }
        $content = file_get_contents($projectDir . '/node_modules/frameworkstylepackage/src/js/Index.js');
        $content = preg_replace('|from \'./Framework/|', 'from \'frameworkstylepackage/src/js/Framework/', $content);
        file_put_contents($assetsJsPath . '/imports.js', $content);
        shell_exec(' node_modules/.bin/standard assets/js/imports.js --quiet --fix');

        $io->notice('→ Import our Framework JS');
        if ($io->isVerbose()) {
            $io->write('   Import the frameworkstylepackage index file');
        }
        $content = file_get_contents($projectDir . '/assets/app.js');
        $insert = [
            'import { Framework } from \'./js/imports\'',
        ];
        $matches = [];
        preg_match('|import\s.*styles\/app\.(s)?css|', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = mb_strpos($content, PHP_EOL, $matches[0][1]);
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            PHP_EOL . implode(PHP_EOL, $insert)
        );

        if ($io->isVerbose()) {
            $io->write('   Fix the app.js file');
        }
        $content = str_replace("\nimport './styles/app.css';", '', $content);


        $io->notice('→ Initialize Framework JS');
        if ($io->isVerbose()) {
            $io->write('   Create new instance of the Framework object');
        }
        $insert = [
            'Framework()',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content),
            PHP_EOL . implode(PHP_EOL, $insert) . PHP_EOL
        );

        // store the file
        file_put_contents($projectDir . '/assets/app.js', $content);

        // fix code styling, as the default
        if ($io->isVerbose()) {
            $io->write(
                '   Apply StandardJS as the default app.js is not following these standards.'
            );
        }
        shell_exec(' node_modules/.bin/standard assets/app.js --quiet --fix');

        if (file_exists($projectDir . '/assets/bootstrap.js')) {
            shell_exec(' node_modules/.bin/standard assets/bootstrap.js --quiet --fix');
        }

        /*
         * Remove the Symfony default Stimulus controller. We don't use it
         * and it doesn't pass our StandardJS CI checks.
         */
        if (file_exists($projectDir . '/assets/controllers/hello_controller.js')) {
            shell_exec(sprintf('rm -rf %1$s', $projectDir . '/assets/controllers/hello_controller.js'));
        }
    }

    private static function reconfigureWebpack(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Reconfigure webpack');

        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');
        $content = file_get_contents($projectDir . '/webpack.config.js');

        $io->notice('→ add require statements');
        $insert = [
            'var webpack = require(\'webpack\')',
            'var WebpackShellPlugin = require(\'webpack-shell-plugin-alt\')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            0,
            implode(PHP_EOL, $insert) . PHP_EOL
        );

        $io->notice('→ remove useless entries');
        $content = preg_replace('|//.addEntry\(.*|', '', $content);

        $io->notice('→ add extra entrypoints');
        $insert = [
            '  .addEntry(\'mail\', \'./assets/styles/mail.scss\')',
            '  .addEntry(\'style\', \'./assets/styles/style.scss\')',
            '  .addEntry(\'style-dark\', \'./assets/styles/style-dark.scss\')',
            '  .addEntry(\'error\', \'./assets/styles/error.scss\')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreEntries($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );

        $io->notice('→ enable Sass/SCSS support');
        $content = preg_replace(
            '|//.enableSassLoader\(\)|',
            '.enableSassLoader(options => { ' . "\n" .
            '  options.implementation = require(\'sass-embedded\')' . "\n" .
            '  options.sassOptions = {' . "\n" .
            '    quietDeps: true' . "\n" .
            '  }' . "\n" .
            '})',
            $content
        );

        $io->notice('→ enable autoProvideVariables');
        $insert = [
            '.autoProvideVariables({',
            '  moment: \'moment\'',
            '})',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );


        $io->notice('→ add IgnorePlugin configuration');
        $insert = [
            '.addPlugin(new webpack.IgnorePlugin({',
            '   resourceRegExp: /^\.\/locale$/,',
            '   contextRegExp: /moment$/,',
            '}))',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );


        $io->notice('→ add WebpackShellPlugin configuration');
        $insert = [
            '.addPlugin(',
            '  new WebpackShellPlugin({',
            '    onBuildStart: [',
            '      \'bin/console fos:js-routing:dump --format=json --locale=nl --target=public/build/routes/fos_js_routes.json\'',
            '    ],',
            '  })',
            ')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );


        $io->notice('→ add CopyFiles configuration');
        $insert = [
            '.copyFiles(',
            '  {',
            '    from: \'./assets/images\',',
            '    to: \'images/[path][name].[hash:8].[ext]\',',
            '  }',
            ')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );

        $io->notice('→ add Vue loader');
        $insert = [
            '.enableVueLoader(() => {}, { runtimeCompilerBuild: false })',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode(PHP_EOL, $insert) . PHP_EOL
        );


        $io->notice('→ insert configureBabel');
        $insert = [
            '.configureBabel(() => {}, {',
            '  useBuiltIns: \'usage\',',
            '  corejs: 3,',
            '  includeNodeModules: [\'frameworkstylepackage\']',
            '})',
        ];
        $matches = [];
        preg_match('|\.configureBabelPresetEnv|', $content, $matches, PREG_OFFSET_CAPTURE);
        $content = self::insertStringAtPosition(
            $content,
            $matches[0][1],
            implode(PHP_EOL, $insert) . PHP_EOL
        );


        $io->notice('→ do not use configureBabelPresetEnv');
        $content = preg_replace('|\.configureBabelPresetEnv.*\}\)|smU', '', $content);

        $io->notice('→ disable enableBuildNotifications');
        $content = preg_replace('|\.enableBuildNotifications\(\)|smU', '//.enableBuildNotifications()', $content);

        file_put_contents($projectDir . '/webpack.config.js', $content);

        // fix code styling
        shell_exec(' node_modules/.bin/standard webpack.config.js --quiet --fix');
    }

    private static function cleanupFiles(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Cleanup files');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Remove app.css');
        $path = $projectDir . '/assets/styles/app.css';
        if (file_exists($path)) {
            unlink($projectDir . '/assets/styles/app.css');
        }

        $io->notice('→ Remove reference to app.css');
        $content = file_get_contents($projectDir . '/assets/app.js');
        $content = preg_replace('|// any CSS you import will output into a single css file.*\n|', '', $content);
        $content = preg_replace('|import \'./styles/app.css\'\n|', '', $content);

        file_put_contents($projectDir . '/assets/app.js', $content);
    }

    private static function createAssets(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Create assets');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Copy scss-files');
        self::copyDirectoryContent(
            $projectDir . '/scripts/assets/css',
            $projectDir . '/assets/styles'
        );


        $io->notice('→ Copy image-files');
        self::copyDirectoryContent(
            $projectDir . '/scripts/assets/images',
            $projectDir . '/assets/images'
        );

        $io->notice('→ Copy templates');
        self::copyDirectoryContent(
            $projectDir . '/scripts/templates',
            $projectDir . '/templates'
        );
    }

    private static function reconfigureApplication(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Reconfigure application');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure Twig');
        $content = file_get_contents($projectDir . '/config/packages/twig.yaml');
        $matches = [];
        preg_match('|twig:|smU', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = $matches[0][1] + mb_strlen($matches[0][0]);
        $insert = [
            '    globals:',
            '        fallbacks: "@framework.fallbacks"',
            '        locales: "%locales%"',
            '        breadcrumbs: \'@SumoCoders\FrameworkCoreBundle\Service\BreadcrumbTrail\'',
            '        page_title: \'@SumoCoders\FrameworkCoreBundle\Service\PageTitle\'',
            '    form_themes:',
            '        - "bootstrap_5_layout.html.twig"',
            '        - "@SumoCodersFrameworkCore/Form/fields.html.twig"',
            '        - "blocks.html.twig"',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            PHP_EOL . implode(PHP_EOL, $insert) . PHP_EOL
        );
        file_put_contents($projectDir . '/config/packages/twig.yaml', $content);


        $io->notice('→ Reconfigure services');
        $content = file_get_contents($projectDir . '/config/services.yaml');
        $matches = [];
        preg_match('|parameters:|', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = mb_strpos($content, PHP_EOL, $matches[0][1]) + 1;
        $insert = [
            '  # configuration of the locale, used for url and allowed locales',
            '  locale: \'nl\'',
            '  locales:',
            '    - \'%locale%\'',
            '',
            '  # configuration of some fallback variables',
            '  fallbacks:',
            '    site_title: \'%env(resolve:SITE_TITLE)%\'',
            '',
            '  # Mailer configuration',
            '  mailer.default_sender_name: \'%env(resolve:MAILER_DEFAULT_SENDER_NAME)%\'',
            '  mailer.default_sender_email: \'%env(resolve:MAILER_DEFAULT_SENDER_EMAIL)%\'',
            '  mailer.default_to_name: \'%env(resolve:MAILER_DEFAULT_TO_NAME)%\'',
            '  mailer.default_to_email: \'%env(resolve:MAILER_DEFAULT_TO_EMAIL)%\'',
            '  mailer.default_reply_to_name: \'%mailer.default_sender_name%\'',
            '  mailer.default_reply_to_email: \'%mailer.default_sender_email%\'',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            implode(PHP_EOL, $insert) . PHP_EOL
        );
        file_put_contents($projectDir . '/config/services.yaml', $content);


        $io->notice('→ Reconfigure annotations');
        $content = file_get_contents($projectDir . '/config/routes.yaml');
        $matches = [];
        preg_match('|controllers:.*attribute|smU', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = $matches[0][1] + mb_strlen($matches[0][0]);
        $insert = [
            '    prefix:',
            '        nl: \'\'',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            PHP_EOL . implode(PHP_EOL, $insert)
        );
        file_put_contents($projectDir . '/config/routes.yaml', $content);

        $io->notice('→ Reconfigure routing');
        $content = file_get_contents($projectDir . '/config/packages/routing.yaml');
        $content = preg_replace(
            '/#default_uri: http:\/\/localhost/smU',
            'default_uri: \'%env(DEFAULT_URI)%\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/routing.yaml', $content);

        $io->notice('→ Reconfigure framework');
        $content = file_get_contents($projectDir . '/config/packages/framework.yaml');
        $matches = [];
        preg_match('|framework:|smU', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = $matches[0][1] + mb_strlen($matches[0][0]);
        $insert = [
            '    trusted_proxies: \'127.0.0.1,REMOTE_ADDR\'',
            '    trusted_headers: [ \'x-forwarded-for\', \'x-forwarded-host\', \'x-forwarded-proto\', \'x-forwarded-port\' ]',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            PHP_EOL . implode(PHP_EOL, $insert) . PHP_EOL
        );
        file_put_contents($projectDir . '/config/packages/framework.yaml', $content);

        $io->notice('→ Reconfigure sentry');
        $content = file_get_contents($projectDir . '/config/packages/sentry.yaml');
        $content = preg_replace(
            '/ +- \'Symfony\\\Component\\\ErrorHandler\\\Error\\\FatalError\'(\r\n|\r|\n)' .
            ' +- \'Symfony\\\Component\\\Debug\\\Exception\\\FatalErrorException\'/',
            '                - \'Symfony\Component\HttpKernel\Exception\NotFoundHttpException\'' . PHP_EOL .
            '                - \'Symfony\Component\Security\Core\Exception\AccessDeniedException\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/sentry.yaml', $content);

        $io->notice('→ Reconfigure default locale');
        $content = file_get_contents($projectDir . '/config/packages/translation.yaml');
        $content = str_replace(
            ' en',
            ' \'%locale%\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/translation.yaml', $content);

        $io->notice('→ Reconfigure doctrine test environment');
        $content = file_get_contents($projectDir . '/config/packages/doctrine.yaml');
        $content = preg_replace(
            '/dbname_suffix: \'.*?\'/smU',
            'dbname_suffix: \'%env(string:default::TEST_TOKEN)%\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/doctrine.yaml', $content);

        $io->notice('→ Reconfigure doctrine migrations');
        $content = file_get_contents($projectDir . '/config/packages/doctrine_migrations.yaml');
        $matches = [];
        preg_match('|doctrine_migrations:|smU', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = $matches[0][1] + mb_strlen($matches[0][0]);
        $insert = [
            '    transactional: false',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            PHP_EOL . implode(PHP_EOL, $insert) . PHP_EOL
        );
        file_put_contents($projectDir . '/config/packages/doctrine_migrations.yaml', $content);

        $io->notice('→ Reconfigure validator');
        $content = file_get_contents($projectDir . '/config/packages/validator.yaml');
        $content = preg_replace(
            '/email_validation_mode: .+/',
            'email_validation_mode: strict',
            $content
        );
        file_put_contents($projectDir . '/config/packages/validator.yaml', $content);

        $io->notice('→ Reconfigure monolog');
        $content = file_get_contents($projectDir . '/config/packages/monolog.yaml');
        // Default log file
        $content = preg_replace(
            '/(nested:(\r\n|\r|\n) +type: stream(\r\n|\r|\n) +path: )php:\/\/stderr/',
            '$1"%kernel.logs_dir%/%kernel.environment%.log"',
            $content
        );
        // Audit trail channel
        $content = preg_replace(
            '/(monolog:(\r\n|\r|\n) +channels:(\r\n|\r|\n) +(- .*(\r\n|\r|\n))+)/',
            '$1        - audit_trail' . PHP_EOL,
            $content
        );
        // Audit trail log file
        $content = preg_replace(
            '/(when@prod:(\r\n|\r|\n) +monolog:(\r\n|\r|\n) +handlers:(\r\n|\r|\n)(.*(\r\n|\r|\n))+ +nested:(\r\n|\r|\n)( {16}.*(\r\n|\r|\n))+)/',
            '$1' .
            '            audit_trail:' . PHP_EOL .
            '                type: stream' . PHP_EOL .
            '                path: "%kernel.logs_dir%/audit.log"' . PHP_EOL .
            '                level: info' . PHP_EOL .
            '                channels: [\'audit_trail\']' . PHP_EOL,
            $content
        );
        file_put_contents($projectDir . '/config/packages/monolog.yaml', $content);

        $io->notice('→ Reconfigure .env');
        $content = file_get_contents($projectDir . '/.env');
        // Set the default env to prod
        $content = str_replace(
            'APP_ENV=dev',
            'APP_ENV=prod',
            $content
        );
        $encryptionKey = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $insert = [
            '###> sumocoders/framework-core-bundle ###',
            'SITE_TITLE="Your application"',
            'ENCRYPTION_KEY="' . $encryptionKey . '"',
            'DEFAULT_URI="/"',
            '###< sumocoders/framework-core-bundle ###',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content),
            PHP_EOL . implode(PHP_EOL, $insert)
        );
        file_put_contents($projectDir . '/.env', $content);

        $io->notice('→ Reconfigure docker-compose.yml');
        $content = file_get_contents($projectDir . '/docker-compose.yml');
        // remove doctrine/doctrine-bundle configuration
        $content = preg_replace(
            '|###> doctrine/doctrine-bundle ###.*###< doctrine/doctrine-bundle ###|mUs',
            '',
            $content
        );
        // remove empty volumes element
        $content = preg_replace(
            '|volumes:\n\n|mUs',
            '',
            $content
        );
        $content = trim($content) . PHP_EOL;
        file_put_contents($projectDir . '/docker-compose.yml', $content);

        $io->notice('→ Reconfigure docker-compose.override.yml');
        $content = file_get_contents($projectDir . '/docker-compose.override.yml');
        // remove doctrine/doctrine-bundle configuration
        $content = preg_replace(
            '|###> doctrine/doctrine-bundle ###.*###< doctrine/doctrine-bundle ###|mUs',
            '',
            $content
        );
        // remove symfony/mailer configuration
        $content = preg_replace(
            '|###> symfony/mailer ###.*###< symfony/mailer ###|mUs',
            '',
            $content
        );
        // remove empty volumes element
        $content = preg_replace(
            '|services:\n|mUs',
            '',
            $content
        );
        $content = trim($content) . PHP_EOL;
        file_put_contents($projectDir . '/docker-compose.override.yml', $content);
    }

    private static function cleanup(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Cleanup');

        if ($io->isVerbose()) {
            $io->warning('  WARNING: this will not happen as you are in verbose mode.');

            return;
        }

        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Remove the post-create-project-cmd.');
        $content = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        unset($content['scripts']['post-create-project-cmd']);

        file_put_contents(
            $projectDir . '/composer.json',
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $io->notice('→ Remove the PostCreateProject file.');
        shell_exec(sprintf('rm %1$s', $projectDir . '/src/Skeleton/PostCreateProject.php'));
        if (count(scandir($projectDir . '/src/Skeleton')) === 2) {
            shell_exec(sprintf('rm -rf %1$s', $projectDir . '/src/Skeleton'));
        }

        $io->notice('→ Remove scripts folder');
        shell_exec(sprintf('rm -rf %1$s', $projectDir . '/scripts'));

        $io->notice('→ Remove the Github action config');
        shell_exec(sprintf('rm -rf %1$s', $projectDir . '/.github'));
    }

    private static function runNpmBuild(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Run `npm run build`');

        $output = shell_exec('npm run build');

        if ($io->isVerbose()) {
            $io->write($output);
        }
    }

    private static function dumpInitialTranslations(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Generating the initial translations`');

        if (!self::testCommandLocally('symfony')) {
            $io->notice('Could\'nt find symfony binary, skipping translations dump.');

            return;
        }

        $output = shell_exec('symfony console translation:extract nl --force --format yaml');
        if ($io->isVerbose()) {
            $io->write($output);
        }
    }

    // some helper methods
    private static function insertStringAtPosition(string $content, int $position, string $insert): string
    {
        if ($position < 0) {
            return $content;
        }

        $before = mb_substr($content, 0, $position);
        $after = mb_substr($content, $position);

        return $before . $insert . $after;
    }

    private static function findEndOfEncoreConfiguration(string $content): int
    {
        $matches = [];
        preg_match('|Encore\n(.*)\n;|ms', $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches[0][1] + mb_strlen($matches[0][0]) - 1;
    }

    private static function findEndOfEncoreEntries(string $content): int
    {
        $matches = [];
        preg_match('|.addEntry\(.*|', $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches[0][1] + mb_strlen($matches[0][0]) + 1;
    }

    private static function copyDirectoryContent(string $source, string $destination): void
    {
        $files = scandir($source);

        if (!file_exists($destination)) {
            mkdir($destination);
        }

        foreach ($files as $file) {
            $fullSource = $source . '/' . $file;
            $fullDestination = $destination . '/' . $file;

            // skip current and previous virtual folders
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            if (is_dir($fullSource)) {
                mkdir($destination . '/' . $file);
                self::copyDirectoryContent($fullSource, $fullDestination);
            } else {
                copy($fullSource, $fullDestination);
            }
        }
    }

    private static function testCommandLocally(string $command): bool
    {
        return shell_exec(sprintf("which %s", escapeshellcmd($command))) !== null;
    }

    private static function pinVolta(): void
    {
        shell_exec('volta pin node@lts');
    }
}
