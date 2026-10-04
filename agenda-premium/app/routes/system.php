<?php
declare(strict_types=1);

use App\Controllers\SystemController;
use App\Core\Router;

return static function (Router $r): void {
    $r->get('/f/{token:[a-f0-9]{32}}', [SystemController::class, 'file']);
    $r->get('/manifest.webmanifest', [SystemController::class, 'manifest']);
    $r->get('/ics/{token:[a-f0-9]{32}}.ics', [SystemController::class, 'hostFeed']);
};
