<?php

declare(strict_types=1);

namespace GlobalLandingPageTest;

require dirname(__DIR__) . '/vendor/autoload.php';
spl_autoload_register(function ($class) {
    if ($class === 'GlobalLandingPage\\Module') {
        require dirname(__DIR__) . '/Module.php';
        return;
    }
    $path = __DIR__ . '/Stubs/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
require __DIR__ . '/Stubs/Laminas/Form/Element/Checkbox.php';
require __DIR__ . '/Stubs/Laminas/Form/Element/Select.php';
require __DIR__ . '/Stubs/Laminas/Form/Element/Textarea.php';
require __DIR__ . '/Stubs/Laminas/Form/Element/Color.php';
require __DIR__ . '/Stubs/Laminas/Form/Form.php';
require __DIR__ . '/Stubs/Omeka/Form/Element/Asset.php';
require __DIR__ . '/Stubs/Omeka/Form/Element/SiteSelect.php';
