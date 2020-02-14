<?php

function addParameters(): void
{
    echo "Adding default framework parameters to services.yaml\n";
    $filePath =
        __DIR__ . DIRECTORY_SEPARATOR .
        '..' . DIRECTORY_SEPARATOR .
        'config' . DIRECTORY_SEPARATOR .
        'services.yaml';
    $parameters = [
        "    locale: 'nl'\n",
        "    locales:\n",
        "        - '%locale%'\n",
        "    mailer.default_sender_name: '%env(resolve:MAILER_DEFAULT_SENDER_NAME)%'\n",
        "    mailer.default_sender_email: '%env(resolve:MAILER_DEFAULT_SENDER_EMAIL)%'\n",
        "    mailer.default_to_name: '%env(resolve:MAILER_DEFAULT_TO_NAME)%'\n",
        "    mailer.default_to_email: '%env(resolve:MAILER_DEFAULT_TO_EMAIL)%'\n",
        "    mailer.default_reply_to_name: '%mailer.default_sender_name%'\n",
        "    mailer.default_reply_to_email: '%mailer.default_sender_email%'\n",
        "    fallbacks:\n",
        "        site_title: '%env(resolve:SITE_TITLE)%'\n",
    ];

    $content = file($filePath);

    $handle = fopen($filePath, 'w');
    if ($handle === false) {
        echo "File $filePath cannot be opened to read/write\n";
        exit(1);
    }

    $finalContent = [];
    foreach ($content as $item) {
        $finalContent[] = $item;
        if (preg_match('/parameters:/', $item)) {
            foreach ($parameters as $parameter) {
                $finalContent[] = $parameter;
            }
        }
    }

    foreach ($finalContent as $item) {
        fwrite($handle, $item);
    }

    fclose($handle);
}

function addDefaulti18nPrefixes(): void
{
    echo "Adding i18n prefixes to annotations.yaml\n";
    $filePath =
        __DIR__ . DIRECTORY_SEPARATOR .
        '..' . DIRECTORY_SEPARATOR .
        'config' . DIRECTORY_SEPARATOR .
        'routes' . DIRECTORY_SEPARATOR .
        'annotations.yaml';
    $lines = [
        "controllers:\n",
        "   resource: ../../src/Controller/\n",
        "   type: annotation\n",
        "   prefix:\n",
        "       '%locale%': '%locale%'\n",
        "\n",
        "kernel:\n",
        "   resource: ../../src/Kernel.php\n",
        "   type: annotation\n",
        "\n"
    ];

    $handle = fopen($filePath, 'w');
    if ($handle === false) {
        echo "File $filePath cannot be opened to read/write\n";
        exit(2);
    }

    foreach ($lines as $item) {
        fwrite($handle, $item);
    }

    fclose($handle);
}

addParameters();
addDefaulti18nPrefixes();

echo "Removing post-update-cmd from composer.json\n";
exec(
    'sed -i \'\' \'/"php scripts\/post-update-cmd\.php",/d\' composer.json'
);
