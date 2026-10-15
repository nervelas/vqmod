<?php
/** Licitador - controlador frontal. */
require __DIR__ . '/src/bootstrap.php';
if (request_path() === '/ping-rewrite') { header('Content-Type: text/plain'); echo 'ok'; exit; } // prueba de URLs limpias (también antes de instalar)
if (!is_installed()) { header('Location: ' . (base_path()) . '/instalar/'); exit; }
try {
    boot_http();
    db(); boot_settings();
    require __DIR__ . '/src/routes_public.php';
    require __DIR__ . '/src/routes_client.php';
    require __DIR__ . '/src/routes_admin.php';
    require __DIR__ . '/src/routes_admin2.php';
    dispatch();
} catch (Throwable $e) {
    error_log('[' . date('c') . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Un momento…</title><body style="background:#0B0A08;color:#F3EDE0;font-family:sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px"><div><h1 style="color:#C9A24B;font-family:Georgia,serif">Estamos ajustando algo</h1><p>Algo no salió como esperábamos. Intenta de nuevo en unos minutos; ya quedó registrado para revisarlo.</p><p><a href="' . htmlspecialchars(base_path() . '/', ENT_QUOTES) . '" style="color:#C9A24B">Volver al inicio</a></p></div></body></html>';
}
