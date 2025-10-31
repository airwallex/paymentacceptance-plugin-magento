<?php

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Airwallex_Payments',
    __DIR__
);

spl_autoload_register(function ($class) {
    $prefix = 'Airwallex\\PayappsPlugin\\CommonLibrary\\';
    $baseDir = __DIR__ . '/payapps-plugin-php-common-lib/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
