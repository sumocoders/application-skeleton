<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostCreateProject
{
    public static function run(Event $event)
    {
        self::fixSecurityChecker($event);
        self::runNpmInstall($event);
        self::installNpmPackages($event);
        self::installFrameworkStylePackage($event);
        self::reconfigureWebpack($event);
        self::createAssets($event);
        self::cleanupFiles($event);
        self::cleanup($event);
    }

    private static function fixSecurityChecker(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Fix Security Checker');
        if ($io->isVerbose()) {
            $io->write(
                [
                    '   We alter the composer.json file so security-checker security:check is',
                    '   only run when composer is not in dev-mode.',
                    '',
                ]
            );
        }

        $file = $event->getComposer()->getConfig()->get('vendor-dir') . '/../composer.json';
        $content = file_get_contents($file);
        $content = preg_replace(
            '|"security-checker security:check": "script"|',
            '"[ $COMPOSER_DEV_MODE -eq 0 ] || security-checker security:check": "script"',
            $content
        );
        file_put_contents($file, $content);
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
            'stylelint',
            'stylelint-config-standard',
        ];

        if ($io->isVerbose()) {
            $io->write(
                sprintf(
                    '   Install packages (%1$s) that are required for our git hooks.',
                    implode(', ', $packages)
                )
            );
        }

        $output = shell_exec(sprintf('npm install %1$s --save-dev', implode(' ', $packages)));
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
            'frameworkstylepackage@^1.0.0',
            'node-sass@^4.14.1',
            'sass-loader@^8.0.0',
            'webpack-shell-plugin-alt',
        ];
        if ($io->isVerbose()) {
            $io->write(
                sprintf(
                    '   Install packages (%1$s) that are required for our FrameworkStylePackage.',
                    implode(', ', $packages)
                )
            );
        }
        $output = shell_exec(sprintf('npm install %1$s --save-dev', implode(' ', $packages)));
        if ($io->isVerbose()) {
            $io->write($output);
        }


        $io->notice('→ Import our Framework JS');
        if ($io->isVerbose()) {
            $io->write('   Import the frameworkstylepackage index file');
        }
        $content = file_get_contents($projectDir . '/assets/js/app.js');
        $insert = [
            'import { Framework } from \'frameworkstylepackage/src/js/Index\'',
        ];
        $matches = [];
        preg_match('|import\s.*css\/app\.(s)?css|', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = mb_strpos($content, "\n", $matches[0][1]);
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            "\n" . implode("\n", $insert)
        );


        $io->notice('→ Initialize Framework JS');
        if ($io->isVerbose()) {
            $io->write('   Create new instance of the Framework object');
        }
        $insert = [
            'new Framework()',
        ];
        $content = self::insertStringAtPosition(
            $content,
            mb_strlen($content),
            "\n" . implode("\n", $insert) . "\n"
        );


        $io->notice('→ Replace app.css with app.scss');
        $content = preg_replace('|app\.css|', 'app.scss', $content);


        // store the file
        file_put_contents($projectDir . '/assets/js/app.js', $content);

        // fix code styling, as the default
        if ($io->isVerbose()) {
            $io->write(
                '   Apply StandardJS as the default app.js is not following these standards.'
            );
        }
        shell_exec(' node_modules/.bin/standard assets/js/app.js --quiet --fix');


        if (file_exists($projectDir . '/assets/css/app.css') && !file_exists($projectDir . '/assets/css/app.scss')) {
            $io->notice('→ Rename app.css to app.scss');
            if ($io->isVerbose()) {
                $io->write(
                    '   We use SCSS instead of CSS files, so we rename the original file.'
                );
            }
            rename($projectDir . '/assets/css/app.css', $projectDir . '/assets/css/app.scss');
        }


        $io->notice('→ Import sccs-files');
        if ($io->isVerbose()) {
            $io->write(
                '   Import bootstrap variables and our base scss-file.'
            );
        }
        $content = file_get_contents($projectDir . '/assets/css/app.scss');
        $insert = [
            '@import \'~bootstrap/scss/functions\';',
            '@import \'~bootstrap/scss/variables\';',
            '@import \'~frameworkstylepackage/src/sass/style\';',
        ];
        $content = self::insertStringAtPosition(
            $content,
            0,
            implode("\n", $insert)
        );
        file_put_contents($projectDir . '/assets/css/app.scss', $content);
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
        $content = preg_replace('|// .addEntry\(.*|', '', $content);

        $io->notice('→ add extra entrypoints');
        $insert = [
            '  .addEntry(\'mail\', \'./assets/css/mail.scss\')',
            '  .addEntry(\'style\', \'./assets/css/style.scss\')',
            '  .addEntry(\'style-dark\', \'./assets/css/style-dark.scss\')',
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfEncoreEntries($content),
            implode("\n", $insert) . "\n"
        );


        $io->notice('→ enable Sass/SCSS support');
        $content = preg_replace('|//.enableSassLoader\(\)|', '.enableSassLoader()', $content);


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
            '.addPlugin(new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/))',
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
            '      \'bin/console bazinga:js-translation:dump public/build --format=json --merge-domains\',',
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

        file_put_contents($projectDir . '/webpack.config.js', $content);

        // fix code styling
        shell_exec(' node_modules/.bin/standard webpack.config.js --quiet --fix');
    }

    private static function cleanupFiles(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Cleanup files');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Remove app.scss');
        $path = $projectDir . '/assets/css/app.scss';
        if (file_exists($path)) {
            unlink($projectDir . '/assets/css/app.scss');
        }

        $io->notice('→ Remove reference to app.scss');
        $content = file_get_contents($projectDir . '/assets/js/app.js');
        $content = preg_replace('|// any CSS you import will output into a single css file.*\n|', '', $content);
        $content = preg_replace('|import \'../css/app.scss\'\n|', '', $content);

        file_put_contents($projectDir . '/assets/js/app.js', $content);
    }

    private static function createAssets(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Create assets');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Copy scss-files');
        self::copyDirectoryContent(
            $projectDir . '/scripts/assets/css',
            $projectDir . '/assets/css'
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
        preg_match('|module.exports|', $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches[0][1] - 1;
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
            // skip current and previous virtual folders
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            copy($source . '/' . $file, $destination . '/' . $file);
        }
    }
}
