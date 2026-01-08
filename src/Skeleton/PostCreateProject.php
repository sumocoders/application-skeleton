<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostCreateProject
{
    public static function run(Event $event): void
    {
        self::createAssets($event);
        self::reconfigureApplication($event);
        self::fixFiles($event);
        self::cleanupFiles($event);
        self::cleanup($event);
        self::dumpInitialTranslations($event);
        self::importAssets($event);
        self::runSass($event);
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

        $io->notice('→ Copy app.js');
        self::copyDirectoryContent(
            $projectDir . '/scripts/assets/js',
            $projectDir . '/assets'
        );
    }

    private static function reconfigureApplication(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Reconfigure application');

        self::reconfigureSymfonycastsSass($event);
        self::reconfigureAssetMapper($event);
        self::reconfigureTwig($event);
        self::reconfigureServices($event);
        self::reconfigureAnnotations($event);
        self::reconfigureRouting($event);
        self::reconfigureFramework($event);
        self::reconfigureSentry($event);
        self::reconfigureDefaultLocale($event);
        self::reconfigureDoctrine($event);
        self::reconfigureValidator($event);
        self::reconfigureMonolog($event);
        self::reconfigureMessenger($event);
        self::reconfigureMailer($event);
        self::reconfigureEnv($event);
        self::reconfigureDockerCompose($event);
        self::reconfigureNelmioSecurityBundle($event);
    }

    private static function reconfigureSymfonycastsSass(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Configure symfonycasts/sass');
        $content = <<<EOF
symfonycasts_sass:
  root_sass:
    - '%kernel.project_dir%/assets/styles/style.scss'
    - '%kernel.project_dir%/assets/styles/mail.scss'
    - '%kernel.project_dir%/assets/styles/error.scss'
EOF;
        file_put_contents($projectDir . '/config/packages/symfonycasts_sass.yaml', $content);
    }

    private static function reconfigureAssetMapper(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Set up asset mapper with framework-core-bundle');
        $content = file_get_contents($projectDir . '/config/packages/asset_mapper.yaml');
        $content = preg_replace(
            '/(paths:(\r\n|\r|\n) +- assets\/(\r\n|\r|\n))/',
            '$1            - vendor/sumocoders/framework-core-bundle/assets-public/' . PHP_EOL
            . '            - vendor/twbs/bootstrap-icons/font/' . PHP_EOL,
            $content
        );
        file_put_contents($projectDir . '/config/packages/asset_mapper.yaml', $content);
    }

    private static function reconfigureTwig(Event $event): void
    {
        $io = $event->getIO();
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
    }

    private static function reconfigureServices(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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
            '  locales_regex: \'%locale%\' # separate with |, for example: nl|fr|en',
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
    }

    private static function reconfigureAnnotations(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure annotations');
        $routesFile = $projectDir . '/config/routes.yaml';

        $content = implode(PHP_EOL, [
            'controllers:',
            '  resource:',
            '    path: ../src/Controller/',
            '    namespace: App\Controller',
            '  type: attribute',
            '  prefix: /{_locale}',
            '  requirements:',
            '    _locale: \'%locales_regex%\'',
            '  trailing_slash_on_root: false',
        ]);

        file_put_contents($routesFile, $content);
    }

    private static function reconfigureRouting(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure routing');
        $content = file_get_contents($projectDir . '/config/packages/routing.yaml');
        $content = preg_replace(
            '/#default_uri: http:\/\/localhost/smU',
            'default_uri: \'%env(DEFAULT_URI)%\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/routing.yaml', $content);
    }

    private static function reconfigureFramework(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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
    }

    private static function reconfigureSentry(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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
    }

    private static function reconfigureDefaultLocale(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure default locale');
        $content = file_get_contents($projectDir . '/config/packages/translation.yaml');
        $content = str_replace(
            ' en',
            ' \'%locale%\'',
            $content
        );
        file_put_contents($projectDir . '/config/packages/translation.yaml', $content);
    }

    private static function reconfigureDoctrine(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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
    }

    private static function reconfigureValidator(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure validator');
        $file = $projectDir . '/config/packages/validator.yaml';
        $lines = file($file, FILE_IGNORE_NEW_LINES);

        $newLines = [];
        foreach ($lines as $line) {
            $newLines[] = $line;
            if (preg_match('/^\s*validation:/', $line)) {
                $newLines[] = '         email_validation_mode: strict';
            }
        }

        file_put_contents($file, implode(PHP_EOL, $newLines));
    }

    private static function reconfigureMonolog(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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
    }

    private static function reconfigureMessenger(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure messenger');
        $content = file_get_contents($projectDir . '/config/packages/messenger.yaml');
        # https://symfony.com/doc/current/mailer.html#sending-messages-async
        $content = str_replace(
            [
                '# failure_transport: failed',
                '            # async: \'%env(MESSENGER_TRANSPORT_DSN)%\'',
                '# failed: \'doctrine://default?queue_name=failed\'',
                '# when@test:',
            ],
            [
                'failure_transport: failed',
                <<<'EOA'
                            async:
                                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                                retry_strategy:
                                    max_retries: 0
                EOA,
                'failed: \'doctrine://default?queue_name=failed\'',
                <<<'EOR'
                when@prod:
                    framework:
                        messenger:
                            routing:
                                'Symfony\Component\Mailer\Messenger\SendEmailMessage': async

                # when@test:
                EOR,
            ],
            $content
        );
        file_put_contents($projectDir . '/config/packages/messenger.yaml', $content);
    }

    private static function reconfigureMailer(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure mailer');
        file_put_contents($projectDir . '/config/packages/mailer.yaml',
            <<<'EODEV'

        when@dev:
            framework:
                mailer:
                    envelope:
                        # needs at least one recipient, otherwise allowed_recipients is ignored
                        recipients: ['mail@localhost']
                        allowed_recipients:
                            - '.*@sumocoders.be'
                            - '.*@tesuta.be'
        EODEV, FILE_APPEND);
    }

    private static function reconfigureEnv(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Reconfigure .env');
        $content = file_get_contents($projectDir . '/.env');
        // Set the default env to prod
        $content = str_replace(
            'APP_ENV=dev',
            'APP_ENV=prod',
            $content
        );
        $content = str_replace(
            'MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0',
            'MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1',
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

        $insert = [
            '###> symfony/mailer ###',
            'MAILER_DEFAULT_SENDER_NAME="Your application"',
            'MAILER_DEFAULT_SENDER_EMAIL="mailer_default_sender_email_is_misconfigured@tesuta.be"',
            'MAILER_DEFAULT_TO_NAME="Your application"',
            'MAILER_DEFAULT_TO_EMAIL="mailer_default_to_email_is_misconfigured@tesuta.be"',
            '###< symfony/mailer ###',
        ];
        $offset = strpos($content, '###< symfony/mailer ###');
        if ($offset !== false) {
            // remove symfony/mailer wrapper as it is already present
            array_shift($insert);
            array_pop($insert);
        } else {
            $offset = mb_strlen($content);
        }
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            implode(PHP_EOL, $insert) . PHP_EOL
        );
        file_put_contents($projectDir . '/.env', $content);

        $io->notice('→ Setup .env.local');
        $secret = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $content = <<<EOF
APP_ENV=dev
APP_SECRET="$secret"

###> symfony/mailer ###
MAILER_DSN=smtp://127.0.0.1:1025
###< symfony/mailer ###

DATABASE_URL="mysql://root:root@127.0.0.1:3306/db_name_replace_me"

EOF;
        file_put_contents($projectDir . '/.env.local', $content);
    }

    private static function reconfigureDockerCompose(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

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

    private static function reconfigureNelmioSecurityBundle(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        // reconfigure nelmio/security-bundle
        $io->notice('→ Reconfigure nelmio/security-bundle');
        $content = <<<EOF
nelmio_security:
  # prevents framing of the entire site
  clickjacking:
    paths:
      '^/.*': DENY

  # disables content type sniffing for script resources
  content_type:
    nosniff: true

  # Content Security Policy (CSP) configuration
  # (the specific configuration needs to be done below per environment)
  csp:
    enabled: true
    report_logger_service: monolog.logger.security
    request_matcher: null
    hosts: [ ]
    content_types: [ ]

  # forces HTTPS handling, don't combine with flexible mode
  # and make sure you have SSL working on your site before enabling this
  forced_ssl: ~

  # Send a full URL in the `Referer` header when performing a same-origin request,
  # only send the origin of the document to secure destination (HTTPS->HTTPS),
  # and send no header to a less secure destination (HTTPS->HTTP).
  # If `strict-origin-when-cross-origin` is not supported, use `no-referrer` policy,
  # no referrer information is sent along with requests.
  referrer_policy:
    enabled: true
    policies:
      - 'no-referrer'
      - 'strict-origin-when-cross-origin'

when@prod:
  nelmio_security:
    csp:
      enforce:
        level1_fallback: false
        browser_adaptive:
          enabled: false
        default-src:
          - 'self'
        child-src:
          - 'none'
        font-src:
          - 'self'
        frame-src: [ ]
        img-src:
          - 'self'
          - 'data:'
        script-src:
          - 'self'
          - 'strict-dynamic'
        style-src:
          - 'self'
        block-all-mixed-content: true
        upgrade-insecure-requests: true

when@dev:
  nelmio_security:
    csp:
      report:
        level1_fallback: false
        browser_adaptive:
          enabled: false
        default-src:
          - 'self'
        child-src:
          - 'none'
        font-src:
          - 'self'
        frame-src: [ ]
        img-src:
          - 'self'
          - 'data:'
        script-src:
          - 'self'
          - 'strict-dynamic'
        style-src:
          - 'self'
        block-all-mixed-content: true # defaults to false, blocks HTTP content over HTTPS transport
        upgrade-insecure-requests: true # defaults to false, upgrades HTTP requests to HTTPS transport

EOF;
        file_put_contents($projectDir . '/config/packages/nelmio_security.yaml', $content);
    }

    private static function fixFiles(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Fix files');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Fix standardjs for csrf_protection_controller.js');
        $file = 'assets/controllers/csrf_protection_controller.js';
        $path = $projectDir . '/' . $file;
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $content = str_replace(
                'Object.keys(h).map(function (k) {',
                implode(
                    "\n",
                    [
                        '// eslint-disable-next-line array-callback-return',
                        'Object.keys(h).map(function (k) {'
                    ]
                ),
                $content
            );
            file_put_contents($path, $content);

            $output = shell_exec(
                sprintf(
                    'docker run --volume ./:/code sumocoders/standardjs:latest --fix %1$s',
                    $file
                )
            );
            if ($io->isVerbose()) {
                $io->write($output);
            }
        }
    }

    private static function cleanupFiles(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Cleanup files');
        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $io->notice('→ Remove app.css');
        $path = $projectDir . '/assets/styles/app.css';
        if (file_exists($path)) {
            unlink($path);
        }

        $io->notice('→ Remove reference to app.css');
        $content = file_get_contents($projectDir . '/assets/app.js');
        $content = preg_replace('|import \'\./styles/app.css\';\n|', '', $content);

        file_put_contents($projectDir . '/assets/app.js', $content);

        $io->notice('→ Remove hello_controller.js');
        $path = $projectDir . '/assets/controllers/hello_controller.js';
        if (file_exists($path)) {
            unlink($path);
        }
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

    private static function importAssets(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Run `bin/console importmap:require`');

        // Turbo 8
        $output = shell_exec('symfony console importmap:require @hotwired/turbo@8');
        if ($io->isVerbose()) {
            $io->write($output);
        }

        $assets = [
            'bootstrap@^5.3',
            'tom-select/dist/css/tom-select.default.css@^2.3',
            'tom-select/dist/css/tom-select.bootstrap5.css@^2.3',
            'flatpickr@^4.6',
            'flatpickr/dist/flatpickr.css@^4.6',
            'flatpickr/dist/themes/airbnb.css@^4.6',
            'flatpickr/dist/l10n/at.js@^4.6',
            'flatpickr/dist/l10n/cs.js@^4.6',
            'flatpickr/dist/l10n/da.js@^4.6',
            'flatpickr/dist/l10n/nl.js@^4.6',
            'flatpickr/dist/l10n/et.js@^4.6',
            'flatpickr/dist/l10n/fi.js@^4.6',
            'flatpickr/dist/l10n/fr.js@^4.6',
            'flatpickr/dist/l10n/de.js@^4.6',
            'flatpickr/dist/l10n/gr.js@^4.6',
            'flatpickr/dist/l10n/lv.js@^4.6',
            'flatpickr/dist/l10n/lt.js@^4.6',
            'flatpickr/dist/l10n/it.js@^4.6',
            'flatpickr/dist/l10n/no.js@^4.6',
            'flatpickr/dist/l10n/pl.js@^4.6',
            'flatpickr/dist/l10n/pt.js@^4.6',
            'flatpickr/dist/l10n/sk.js@^4.6',
            'flatpickr/dist/l10n/sv.js@^4.6',
            'flatpickr/dist/l10n/es.js@^4.6',
            'flatpickr/dist/l10n/sl.js@^4.6',
            'sortablejs@^1.15',
            'axios@^1.7',
            '@stimulus-components/clipboard@^5.0',
        ];

        // Add all packages
        $output = shell_exec(sprintf('symfony console importmap:require %1$s', implode(' ', $assets)));
        if ($io->isVerbose()) {
            $io->write($output);
        }

        // Add Framework JS and stimulus controllers, needs to be separate because of --path parameter
        $packages = [
            'Clipboard' => 'controllers/clipboard_controller.js',
            'SidebarCollapsable' => 'controllers/sidebar_collapsable_controller.js',
            'Toast' => 'controllers/toast_controller.js',
            'addToast' => 'js/toast.js',
            'cookie' => 'js/cookie.js',
            'Theme' => 'controllers/theme_controller.js',
            'Tooltip' => 'controllers/tooltip_controller.js',
            'DateTimePicker' => 'controllers/date_time_picker_controller.js',
            'Tabs' => 'controllers/tabs_controller.js',
            'PasswordStrengthChecker' => 'controllers/password_strength_checker_controller.js',
            'TogglePassword' => 'controllers/toggle_password_controller.js',
            'FormCollection' => 'controllers/form_collection_controller.js',
            'debounce' => 'js/debounce.js',
            'ScrollToTop' => 'controllers/scroll_to_top_controller.js',
            'Popover' => 'controllers/popover_controller.js',
            'ajax_client' => 'js/ajax_client.js',
            'Confirm' => 'controllers/confirm_controller.js',
        ];
        foreach ($packages as $name => $path) {
            $output = shell_exec(
                sprintf(
                    'symfony console importmap:require sumocoders/%1$s ' .
                    '--path "./vendor/sumocoders/framework-core-bundle/assets-public/%2$s"',
                    $name,
                    $path
                )
            );
            if ($io->isVerbose()) {
                $io->write($output);
            }
        }
    }

    private static function runSass(Event $event): void
    {
        $io = $event->getIO();
        $io->info('Run `bin/console sass:build`');

        $output = shell_exec('symfony console sass:build');

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
