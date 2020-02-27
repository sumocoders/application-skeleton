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

function installNpmPackages(): void
{
    echo "Install framework style package\n";
    exec('npm i frameworkstylepackage@^1 --save-dev');
    exec('npm i webpack-shell-plugin-alt --save-dev');
    exec('npm install sass-loader@^7.0.1 node-sass --save-dev');
}

function addJsAndSass(): void
{
    echo "Add specific JS and SASS\n";
    exec('cat scripts/assets/app.js >> assets/js/app.js');
    exec('sed -i \'\' \'s/app\.css/app.scss/g\' assets/js/app.js');

    exec('mv assets/css/app.css assets/css/app.scss');
    exec('cat scripts/assets/app.scss > assets/css/app.scss');
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
        "    .autoProvideVariables({\n",
        "      moment: 'moment'\n",
        "    })\n",
        "    \n",
        "    .addPlugin(new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/))\n",
        "    .addPlugin(\n",
        "      new WebpackShellPlugin(\n",
        "        {\n",
        "          onBuildStart: [\n",
        "            'bin/console bazinga:js-translation:dump public/build --format=json --merge-domains',\n",
        "            'bin/console fos:js-routing:dump --format=json --locale=nl --target=public/build/routes/fos_js_routes.json',\n",
        "          ],\n",
        "        }\n",
        "      )\n",
        "    )\n",
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

        $item = preg_replace('/\/\/\.enableSassLoader\(\)/', '.enableSassLoader()', $item);
        $item = preg_replace('/\/\/\.autoProvidejQuery\(\)/', '.autoProvidejQuery()', $item);
        $finalContent[] = $item;

        if (preg_match('/var\sEncore\s=/', $item)) {
            $finalContent[] = 'var webpack = require(\'webpack\');';
            $finalContent[] = 'var WebpackShellPlugin = require(\'webpack-shell-plugin-alt\');';
        }
    }

    foreach ($finalContent as $item) {
        fwrite($handle, $item);
    }

    fclose($handle);
}

fixSecurityChecker();
runNpmInstall();
installNpmPackages();
addJsAndSass();
addConfigurationToWebpack();
cleanup();
