<?php

echo "Fixing security-checker\n";
exec(
    'sed -i \'\' \'s/"security-checker security:check": "script"/"[ $COMPOSER_DEV_MODE -eq 0 ] || security-checker security:check": "script"/g\' composer.json'
);
echo "Cleaning up\n";
exec('rm -rf ' . __DIR__);
