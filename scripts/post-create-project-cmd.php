<?php

function fixSecurityChecker(): void
{
    echo "Fixing security-checker\n";
    exec(
        'sed -i \'\' \'s/"security-checker security:check": "script"/"[ $COMPOSER_DEV_MODE -eq 0 ] || security-checker security:check": "script"/g\' composer.json'
    );
}

function runNpmInstall(): void
{
    echo "Running npm install\n";
    exec('npm i');
}

function installFrameworkStylePackage(): void
{
    echo "Install framework style package\n";
    exec('npm i git+ssh://git@github.com/sumocoders/FrameworkStylePackage.git --save-dev');
}

function addJsAndSass(): void
{
    echo "Add specific JS and SASS\n";
    exec(
        'cat scripts' . DIRECTORY_SEPARATOR .
        'assets' . DIRECTORY_SEPARATOR .
        'app.js >> assets' . DIRECTORY_SEPARATOR .
        'js' . DIRECTORY_SEPARATOR . 'app.js'
    );

    exec(
        'sed -i \'\' \'s/app\.css/app.scss/g\' assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js'
    );

    exec(
        'cat scripts' . DIRECTORY_SEPARATOR .
        'assets' . DIRECTORY_SEPARATOR .
        'app.scss > assets' . DIRECTORY_SEPARATOR .
        'css' . DIRECTORY_SEPARATOR . 'app.scss'
    );
}

function cleanup(): void
{
    echo "Cleaning up\n";
    exec('rm -rf ' . __DIR__);
}

function addConfigurationToWebpack(): void
{
    echo "Adding default framework parameters to services.yaml\n";
    $filePath = 'webpack.config.js';
    $parameters = [
        ".autoProvideVariables({",
        "  moment: 'moment'",
        "})",
    ];

    $content = file($filePath);

    $handle = fopen($filePath, 'w');
    if ($handle === false) {
        echo "File $filePath cannot be opened to read/write\n";
        exit(1);
    }

    $finalContent = [];
    foreach ($content as $item) {
        if (preg_match('/^;$/', $item)) {
            foreach ($parameters as $parameter) {
                $finalContent[] = $parameter;
            }
        }
        $finalContent[] = $item;
    }

    foreach ($finalContent as $item) {
        fwrite($handle, $item);
    }

    fclose($handle);
}

fixSecurityChecker();
runNpmInstall();
installFrameworkStylePackage();
addJsAndSass();
addConfigurationToWebpack();
cleanup();
