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

function addJMSi18nParameters()
{
    echo "Adding default framework parameters to services.yaml\n";
    $filePath =
        __DIR__ . DIRECTORY_SEPARATOR .
        '..' . DIRECTORY_SEPARATOR .
        'config' . DIRECTORY_SEPARATOR .
        'packages' . DIRECTORY_SEPARATOR .
        'translation.yaml';
    $lines = [
        "\n",
        "jms_i18n_routing:\n",
        "    default_locale: '%locale%'\n",
        "    locales: '%locales%'\n",
        "    strategy: prefix_except_default\n",
        "\n",
    ];

    $handle = fopen($filePath, 'a');
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
addJMSi18nParameters();


echo "Removing post-install-cmd from composer.json\n";
exec(
    'sed -i \'\' \'/"scripts\/post-install.php",/d\' composer.json'
);
