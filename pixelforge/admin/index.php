<?php
/** Panel de administración: todo se configura aquí, sin tocar código. */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

Security::headers();

$settings = pf_settings();
$store = pf_store();
$base = Support::baseUrl();

if (!$settings->isInstalled()) {
    Support::redirect($base . '/index.php');
}
Security::requireAuth();

$mensaje = '';
$tipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf($_POST['csrf'] ?? null);
    $accion = Security::str('accion', '', 40);

    if ($accion === 'guardar') {
        $orden = [];
        foreach (array_keys(ProviderRegistry::catalog()) as $id) {
            $orden[$id] = Security::int('orden_' . $id, 99, 1, 99);
            $settings->set('provider_enabled_' . $id, Security::str('activo_' . $id, '0', 4) === '1' ? '1' : '0');

            $modelo = Security::str('modelo_' . $id, '', 120);
            if ($modelo !== '') {
                $settings->set('model_' . $id, $modelo);
            }

            $key = (string) ($_POST['key_' . $id] ?? '');
            $borrar = Security::str('borrar_key_' . $id, '0', 4) === '1';
            if ($borrar) {
                $settings->setApiKey($id, '');
            } elseif (trim($key) !== '') {
                $settings->setApiKey($id, trim($key));
            }
        }
        asort($orden);
        $settings->set('provider_order', implode(',', array_keys($orden)));

        $settings->set('realism_suffix', Security::str('realism_suffix', '', 600));
        $formato = Security::str('default_format', 'png', 8);
        $settings->set('default_format', in_array($formato, Imaging::FORMATS, true) ? $formato : 'png');
        $settings->set('default_width', (string) Security::int('default_width', 1024, 64, 4096));
        $settings->set('default_height', (string) Security::int('default_height', 1024, 64, 4096));
        $settings->set('http_timeout', (string) Security::int('http_timeout', 90, 20, 180));
        $settings->set('http_retries', (string) Security::int('http_retries', 3, 1, 5));
        $settings->set('rate_limit_hour', (string) Security::int('rate_limit_hour', 60, 0, 1000));
        $settings->set('keep_history', (string) Security::int('keep_history', 500, 20, 5000));
        $settings->set('pollinations_nologo', Security::str('pollinations_nologo', '0', 4) === '1' ? '1' : '0');
        $settings->set('pollinations_private', Security::str('pollinations_private', '0', 4) === '1' ? '1' : '0');

        $settings->reload();
        $mensaje = 'Ajustes guardados.';
        Logger::write('admin', 'Ajustes actualizados');
    } elseif ($accion === 'password') {
        $actual = (string) ($_POST['password_actual'] ?? '');
        $nueva = (string) ($_POST['password_nueva'] ?? '');
        $repetir = (string) ($_POST['password_repetir'] ?? '');
        if (!$settings->verifyPassword($actual)) {
            $mensaje = 'La contraseña actual no es correcta.';
            $tipo = 'error';
        } elseif (strlen($nueva) < 8) {
            $mensaje = 'La nueva contraseña debe tener al menos 8 caracteres.';
            $tipo = 'error';
        } elseif ($nueva !== $repetir) {
            $mensaje = 'La nueva contraseña y su repetición no coinciden.';
            $tipo = 'error';
        } else {
            $settings->setPassword($nueva);
            $mensaje = 'Contraseña actualizada.';
            Logger::write('auth', 'Contraseña cambiada desde el panel');
        }
    } elseif ($accion === 'limpiar_log') {
        Logger::clear();
        $mensaje = 'Registro vaciado.';
    }
}

$proveedores = [];
$posicion = 1;
foreach (ProviderRegistry::ordered($settings) as $provider) {
    $proveedores[] = [
        'id' => $provider->id(),
        'label' => $provider->label(),
        'requires_key' => $provider->requiresKey(),
        'enabled' => $settings->providerEnabled($provider->id()),
        'configured' => $provider->isConfigured(),
        'model' => $provider->model(),
        'mask' => $settings->apiKeyMask($provider->id()),
        'posicion' => $posicion++,
        'negative' => $provider->supportsNegativePrompt(),
    ];
}

Support::view('admin', [
    'base' => $base,
    'csrf' => Security::csrfToken(),
    'settings' => $settings,
    'proveedores' => $proveedores,
    'mensaje' => $mensaje,
    'tipo' => $tipo,
    'uso' => $store->usageByDay(7),
    'checks' => Support::environment(),
    'log' => Logger::tail(80),
    'driver' => $store->driver(),
    'engine' => Imaging::engine(),
    'totalImagenes' => $store->imageCount(),
]);
