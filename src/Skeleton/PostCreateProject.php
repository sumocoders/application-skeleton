<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostCreateProject
{
    public static function run(Event $event): void
    {
        self::createAssets($event);
        self::reconfigureApplication($event);
        self::cleanupFiles($event);
        self::cleanup($event);
        self::dumpInitialTranslations($event);
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
            '/(when@test:(\r\n|\r|\n) +doctrine:(\r\n|\r|\n) +dbal:(\r\n|\r|\n)( +#.*(\r\n|\r|\n)) +dbname_suffix: ).*(\r\n|\r|\n)/',
            '$1\'%env(string:default::TEST_TOKEN)%\'$7',
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
}
