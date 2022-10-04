<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostCreateProject
{
    public static function run(Event $event)
    {
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

        $output = self::runWithNvm('npm install');
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
            'stylelint',
            'stylelint-config-standard',
            'stylelint-config-standard-scss',
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
        $output = self::runWithNvm($command);

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
            'frameworkstylepackage@^2',
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
        $output = self::runWithNvm($command);

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
        $content = file_get_contents($projectDir .'/node_modules/frameworkstylepackage/src/js/Index.js');
        $content = preg_replace('|from \'./Framework/|', 'from \'frameworkstylepackage/src/js/Framework/', $content);
        file_put_contents($assetsJsPath .'/imports.js', $content);
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
        $offset = mb_strpos($content, "\n", $matches[0][1]);
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            "\n" . implode("\n", $insert)
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
            'window.framework = new Framework()',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content),
            "\n" . implode("\n", $insert) . "\n"
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
            implode("\n", $insert) . "\n"
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
            implode("\n", $insert) . "\n"
        );


        $io->notice('→ enable Sass/SCSS support');
        $content = preg_replace('|//.enableSassLoader\(\)|', '.enableSassLoader(options => { options.implementation = require(\'sass\') })', $content);

        $io->notice('→ enable autoProvidejQuery');
        $content = preg_replace('|//.autoProvidejQuery\(\)|', '.autoProvidejQuery()', $content);


        $io->notice('→ enable autoProvideVariables');
        $insert = [
            '.autoProvideVariables({',
            '  moment: \'moment\'',
            '})',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode("\n", $insert) . "\n"
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
            implode("\n", $insert) . "\n"
        );


        $io->notice('→ add WebpackShellPlugin configuration');
        $insert = [
            '.addPlugin(',
            '  new WebpackShellPlugin({',
            '    onBuildStart: [',
            '      //\'bin/console bazinga:js-translation:dump public/build --format=json --merge-domains\',',
            '      \'bin/console fos:js-routing:dump --format=json --locale=nl --target=public/build/routes/fos_js_routes.json\'',
            '    ],',
            '  })',
            ')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode("\n", $insert) . "\n"
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
            implode("\n", $insert) . "\n"
        );

        $io->notice('→ add Vue loader');
        $insert = [
            '.enableVueLoader()',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreConfiguration($content),
            implode("\n", $insert) . "\n"
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
            implode("\n", $insert) . "\n"
        );


        $io->notice('→ do not use configureBabelPresetEnv');
        $content = preg_replace('|\.configureBabelPresetEnv.*\}\)|smU', '', $content);


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
            '    form_themes:',
            '        - "bootstrap_5_layout.html.twig"',
            '        - "@SumoCodersFrameworkCore/Form/fields.html.twig"',
            '        - "blocks.html.twig"',
            '    paths:',
            '        # Add the public folder to the default twig path so we can access the',
            '        # mail stylesheet through the inline_css method in the base email template.',
            '        \'%kernel.project_dir%/public/\': ~',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            "\n" . implode("\n", $insert) . "\n"
        );
        file_put_contents($projectDir . '/config/packages/twig.yaml', $content);


        $io->notice('→ Reconfigure services');
        $content = file_get_contents($projectDir . '/config/services.yaml');
        $matches = [];
        preg_match('|parameters:|', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = mb_strpos($content, "\n", $matches[0][1]) + 1;
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
            implode("\n", $insert) . "\n"
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
            "\n" . implode("\n", $insert)
        );
        file_put_contents($projectDir . '/config/routes.yaml', $content);

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
            "\n" . implode("\n", $insert) . "\n"
        );
        file_put_contents($projectDir . '/config/packages/framework.yaml', $content);

        $io->notice('→ Reconfigure sentry');
        $content = file_get_contents($projectDir . '/config/packages/sentry.yaml');
        $insert = [
            '        options:',
            '           integrations:',
            '               - \'Sentry\Integration\IgnoreErrorsIntegration\'',
            '',
            'services:',
            '    Sentry\Integration\IgnoreErrorsIntegration:',
            '        arguments:',
            '            $options:',
            '                ignore_exceptions:',
            '                    - \'Symfony\Component\HttpKernel\Exception\NotFoundHttpException\'',
            '                    - \'Symfony\Component\Security\Core\Exception\AccessDeniedException\'',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content) + 1,
            implode("\n", $insert) . "\n"
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
            "\n" . implode("\n", $insert) . "\n"
        );
        file_put_contents($projectDir . '/config/packages/doctrine_migrations.yaml', $content);

        $io->notice('→ Reconfigure .env');
        $content = file_get_contents($projectDir . '/.env');
        $matches = [];
        $encryptionKey = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $insert = [
            '###> sumocoders/framework-core-bundle ###',
            'SITE_TITLE="Your application"',
            'ENCRYPTION_KEY="' . $encryptionKey . '"',
            '###< sumocoders/framework-core-bundle ###',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content),
            "\n" . implode("\n", $insert)
        );
        file_put_contents($projectDir . '/.env', $content);
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
    }

    private static function runNpmBuild(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Run `npm run build`');

        $output = self::runWithNvm('npm run build');

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

    private static function runWithNvm(string $command): string
    {
        /*
         * If we use env variables like $HOME directly in a path,
         * it won't resolve. But if we echo it out first, we can
         * use the absolute path from the output just fine.
         */
        $nvmPath = trim(shell_exec('echo $HOME/.nvm/nvm.sh'));

        if (file_exists($nvmPath)) {
            $command = sprintf(
                '. ' . $nvmPath . ' && nvm use && nvm exec %s',
                $command
            );
        }

        return shell_exec($command);
    }
}
