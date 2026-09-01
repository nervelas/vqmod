<?php
/**
 * PixelForge — punto de entrada.
 * Primera visita: crea la base de datos y pide una contraseña. Después: login y estudio.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

Security::headers();

$settings = pf_settings();
$store = pf_store();
$base = Support::baseUrl();
$problems = Support::criticalProblems();
$action = Security::str('action', '', 40);
$message = '';
$messageType = 'error';

if ($problems && $action === '') {
    Support::view('diagnostico', ['checks' => Support::environment(), 'base' => $base]);
    exit;
}

// --- Primera visita: crear contraseña ------------------------------------
if (!$settings->isInstalled()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'instalar') {
        Security::requireCsrf($_POST['csrf'] ?? null);
        $pass = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($pass) < 8) {
            $message = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($pass !== $confirm) {
            $message = 'Las dos contraseñas no coinciden.';
        } else {
            $settings->setPassword($pass);
            Security::login();
            Logger::write('auth', 'Instalación completada y sesión iniciada');
            Support::redirect($base . '/index.php');
        }
    }
    Support::view('instalar', [
        'base' => $base,
        'csrf' => Security::csrfToken(),
        'message' => $message,
        'checks' => Support::environment(),
        'driver' => $store->driver(),
    ]);
    exit;
}

// --- Sesión ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    Security::requireCsrf($_POST['csrf'] ?? null);
    if (Security::loginBlocked($store)) {
        $message = 'Demasiados intentos fallidos. Espera 15 minutos antes de volver a probar.';
        Logger::write('auth', 'Login bloqueado por intentos desde ' . Support::clientIp());
    } elseif ($settings->verifyPassword((string) ($_POST['password'] ?? ''))) {
        Security::loginSucceeded($store);
        Security::login();
        Logger::write('auth', 'Sesión iniciada');
        Support::redirect($base . '/index.php');
    } else {
        Security::loginFailed($store);
        $message = 'Contraseña incorrecta.';
        Logger::write('auth', 'Contraseña incorrecta desde ' . Support::clientIp());
    }
}

if ($action === 'logout') {
    Security::requireCsrf($_GET['csrf'] ?? ($_POST['csrf'] ?? null));
    Security::logout();
    Security::startSession();
    Support::redirect($base . '/index.php');
}

if (!Security::isLoggedIn()) {
    if (($_GET['error'] ?? '') === 'csrf') {
        $message = 'Tu sesión caducó. Vuelve a iniciar sesión.';
    }
    Support::view('login', [
        'base' => $base,
        'csrf' => Security::csrfToken(),
        'message' => $message,
    ]);
    exit;
}

// --- Estudio --------------------------------------------------------------
$providers = [];
foreach (ProviderRegistry::ordered($settings) as $provider) {
    $providers[] = [
        'id' => $provider->id(),
        'label' => $provider->label(),
        'enabled' => $provider->isEnabled(),
        'configured' => $provider->isConfigured(),
        'requires_key' => $provider->requiresKey(),
        'negative' => $provider->supportsNegativePrompt(),
        'model' => $provider->model(),
    ];
}

Support::view('estudio', [
    'base' => $base,
    'csrf' => Security::csrfToken(),
    'settings' => $settings,
    'providers' => $providers,
    'presets' => $store->presetList(),
    'engine' => Imaging::engine(),
    'driver' => $store->driver(),
    'webp' => Imaging::formatSupported('webp'),
    'sessionStart' => (int) ($_SESSION['session_start'] ?? ($_SESSION['session_start'] = time())),
]);
