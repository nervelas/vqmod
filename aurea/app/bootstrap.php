<?php
declare(strict_types=1);

define('AUREA_ROOT', dirname(__DIR__));
define('AUREA_VERSION', '1.0.0');

require __DIR__ . '/Core/Autoloader.php';
\Aurea\Core\Autoloader::register(__DIR__);
require __DIR__ . '/Core/helpers.php';
