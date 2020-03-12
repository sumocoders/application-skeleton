<?php

namespace App\Skeleton;

use Composer\Script\Event;

class PostUpdate
{
    public static function run(Event $event)
    {
//        self::configureParameters($event);
        self::configureI18nPrefixes($event);
//        self::cleanup($event);
    }

    public static function configureParameters(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Configure the parameters');
        if ($io->isVerbose()) {
            $io->write(
                [
                    '   Our Framework needs some parameters to work correctly',
                    '',
                ]
            );
        }

        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $content = file_get_contents($projectDir . '/config/services.yaml');
        $insert = [
            '  locale: \'nl\'',
            '  locales:',
            '    - \'%locale%\'',
            '  mailer.default_sender_name: \'%env(resolve:MAILER_DEFAULT_SENDER_NAME)%\'',
            '  mailer.default_sender_email: \'%env(resolve:MAILER_DEFAULT_SENDER_EMAIL)%\'',
            '  mailer.default_to_name: \'%env(resolve:MAILER_DEFAULT_TO_NAME)%\'',
            '  mailer.default_to_email: \'%env(resolve:MAILER_DEFAULT_TO_EMAIL)%\'',
            '  mailer.default_reply_to_name: \'%mailer.default_sender_name%\'',
            '  mailer.default_reply_to_email: \'%mailer.default_sender_email%\'',
            '  fallbacks:',
            '    site_title: \'%env(resolve:SITE_TITLE)%\''
        ];
        $content = self::insertStringAtPosition(
            $content,
            self::findEndOfParameters($content),
            "\n" . implode("\n", $insert)
        );

        file_put_contents($projectDir . '/config/services.yaml', $content);
    }

    private static function configureI18nPrefixes(Event $event): void
    {
        $io = $event->getIO();
        $io->notice('Configure the i18n prefix');
        if ($io->isVerbose()) {
            $io->write(
                [
                    '   The language should be prefixed in each url.',
                    '',
                ]
            );
        }

        $projectDir = realpath($event->getComposer()->getConfig()->get('vendor-dir') . '/..');

        $content = file_get_contents($projectDir . '/config/routes/annotations.yaml');
        $matches = [];
        preg_match('|controllers:.*annotation|Ums', $content, $matches, PREG_OFFSET_CAPTURE);
        $offset = mb_strlen($matches[0][0]) + $matches[0][1];

        $insert = [
            '    prefix:',
            '        \'%locale%\': \'%locale%\'',
        ];
        $content = self::insertStringAtPosition(
            $content,
            $offset,
            "\n" . implode("\n", $insert)
        );

        file_put_contents($projectDir . '/config/routes/annotations.yaml', $content);
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

        $io->notice('→ Remove the post-update-cmd.');
        $content = json_decode(file_get_contents($projectDir . '/composer.json'), true);

        $index = array_search(self::class . '::run', $content['scripts']['post-update-cmd']);
        if ($index !== false) {
            unset($content['scripts']['post-update-cmd'][$index]);
            $content['scripts']['post-update-cmd'] = array_values($content['scripts']['post-update-cmd']);
        }

        file_put_contents(
            $projectDir . '/composer.json',
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $io->notice('→ Remove the PostCreateProject file.');
        shell_exec(sprintf('rm %1$s', $projectDir . '/src/Skeleton/PostUpdate.php'));
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

    private static function findEndOfParameters(string $content): int
    {
        $search = 'parameters:';
        $offset = mb_strpos($content, $search);

        return $offset + strlen($search);
    }
}
