<?php
declare(strict_types=1);

// Instalador de Agenda Premium en 4 pasos. Se bloquea al terminar.
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tz;
use App\Services\InstallService;

$req = new Request();
$GLOBALS['__request'] = $req;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if ($req->isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}

function inst_page(string $title, string $body, int $step = 0): void
{
    global $nonce;
    $steps = ['Requisitos', 'Base de datos', 'Tu negocio', 'Profesión'];
    echo '<!doctype html><html lang="es" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . ' · Instalación de Agenda Premium</title>';
    echo '<link rel="stylesheet" href="' . e(asset('css/fonts.css')) . '"><link rel="stylesheet" href="' . e(asset('css/core.css')) . '"><link rel="stylesheet" href="' . e(url('/instalar/instalar.css')) . '">';
    echo '</head><body class="installer"><main class="inst-wrap" id="main"><header class="inst-head"><p class="inst-brand serif">Agenda Premium</p><p class="muted">Instalación guiada · 4 pasos</p></header>';
    if ($step > 0) {
        echo '<ol class="inst-steps" aria-label="Progreso">';
        foreach ($steps as $i => $s) {
            $n = $i + 1;
            echo '<li class="' . ($n === $step ? 'is-active' : ($n < $step ? 'is-done' : '')) . '"' . ($n === $step ? ' aria-current="step"' : '') . '><span class="mono">' . $n . '</span> ' . e($s) . '</li>';
        }
        echo '</ol>';
    }
    echo '<section class="card card-gold inst-card"><div class="card-body stack">' . $body . '</div></section>';
    echo '<p class="muted inst-foot">Tus datos se guardan solo en tu propio servidor.</p></main></body></html>';
}

if (InstallService::isLocked()) {
    inst_page('Ya instalado', '<h1 class="serif">El sistema ya está instalado</h1><p>Por seguridad, el instalador quedó bloqueado. <strong>Borra la carpeta <span class="mono">/instalar</span> de tu hosting.</strong></p><p><a class="btn btn-gold" href="' . e(url('/admin')) . '">Ir al panel</a></p>');
    exit;
}

Session::start($req);
$step = max(1, min(4, (int) ($req->query['paso'] ?? 1)));
$data = Session::get('inst', []);
$errors = [];

if ($req->isPost()) {
    if (!Csrf::check($req)) {
        http_response_code(419);
        inst_page('Página caducada', '<h1 class="serif">La página caducó</h1><p>Recarga e inténtalo de nuevo.</p><p><a class="btn btn-gold" href="?paso=1">Volver</a></p>');
        exit;
    }
    $post = $req->post;
    $s = static fn (string $k, int $max = 190): string => \App\Core\Str::clean((string) ($post[$k] ?? ''), $max);
    if ($step === 1) {
        $ok = true;
        foreach (InstallService::requirements() as $r) {
            if ($r['required'] && !$r['ok']) {
                $ok = false;
            }
        }
        if ($ok) {
            header('Location: ?paso=2');
            exit;
        }
        $errors[] = 'Falta resolver los requisitos marcados en rojo.';
    } elseif ($step === 2) {
        $db = ['host' => $s('host') ?: 'localhost', 'port' => (int) ($post['port'] ?? 3306) ?: 3306, 'name' => $s('name'), 'user' => $s('user'), 'pass' => (string) ($post['pass'] ?? '')];
        if ($db['name'] === '' || $db['user'] === '') {
            $errors[] = 'Escribe el nombre de la base de datos y el usuario.';
        } else {
            $err = InstallService::testDb($db);
            if ($err !== null) {
                $errors[] = $err;
            } else {
                $data['db'] = $db;
                Session::set('inst', $data);
                header('Location: ?paso=3');
                exit;
            }
        }
        $data['db_form'] = $db;
    } elseif ($step === 3) {
        $biz = ['name' => $s('biz_name', 120), 'email' => $s('biz_email'), 'phone' => $s('biz_phone', 30), 'whatsapp' => $s('biz_whatsapp', 30), 'timezone' => $s('timezone', 64)];
        $adm = ['name' => $s('adm_name', 120), 'email' => $s('adm_email'), 'password' => (string) ($post['adm_pass'] ?? '')];
        if ($biz['name'] === '') {
            $errors[] = 'Escribe el nombre de tu negocio.';
        }
        if (!\App\Core\Validator::email($adm['email'])) {
            $errors[] = 'Escribe un correo válido para el administrador.';
        }
        if ($adm['name'] === '') {
            $errors[] = 'Escribe el nombre del administrador.';
        }
        if ($biz['phone'] !== '' && \App\Core\Str::phone($biz['phone']) === null) {
            $errors[] = 'El teléfono debe tener 8 dígitos (+502) o ser un número internacional completo.';
        }
        if ($biz['whatsapp'] !== '' && \App\Core\Str::phone($biz['whatsapp']) === null) {
            $errors[] = 'El WhatsApp debe tener 8 dígitos (+502) o ser un número internacional completo.';
        }
        if (!Tz::valid($biz['timezone'])) {
            $biz['timezone'] = 'America/Guatemala';
        }
        $pw = \App\Core\Crypto::passwordStrongEnough($adm['password']);
        if ($pw !== null) {
            $errors[] = $pw;
        } elseif ($adm['password'] !== (string) ($post['adm_pass2'] ?? '')) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        $data['biz'] = $biz;
        $data['adm'] = ['name' => $adm['name'], 'email' => $adm['email']];
        if (!$errors) {
            $data['adm']['password'] = $adm['password'];
            Session::set('inst', $data);
            header('Location: ?paso=4');
            exit;
        }
        Session::set('inst', $data);
    } elseif ($step === 4) {
        if (empty($data['db']) || empty($data['biz']) || empty($data['adm']['password'])) {
            header('Location: ?paso=1');
            exit;
        }
        try {
            $res = InstallService::install([
                'db' => $data['db'], 'business' => $data['biz'], 'admin' => $data['adm'],
                'profession' => $s('profession', 40) ?: 'otro', 'demo' => !empty($post['demo']), 'base_url' => '',
            ]);
            Session::set('inst', []);
            $cronUrl = $req->baseUrl() . '/cron.php?token=' . $res['cron_token'];
            $body = '<h1 class="serif">¡Listo! Tu agenda está instalada</h1>'
                . '<p class="alert alert-warn"><strong>Importante:</strong> borra ahora la carpeta <span class="mono">/instalar</span> de tu hosting. El instalador ya quedó bloqueado.</p>'
                . '<p><a class="btn btn-gold btn-lg" href="' . e(url('/admin')) . '">Entrar al panel</a></p>'
                . '<h2 class="serif">Recordatorios automáticos (cron)</h2><p>Para enviar recordatorios y correos a tiempo, programa esta tarea en tu hosting cada 5 minutos:</p>'
                . '<p class="mono code">' . e($cronUrl) . '</p><p class="muted">También puedes usar el comando <span class="mono">php ' . e(APP_ROOT) . '/cron.php</span>. Si no puedes programar el cron, el sistema trabaja con respaldo cada vez que alguien visita la página.</p>';
            inst_page('Instalación completa', $body);
            exit;
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falló la instalación', $e);
            $errors[] = $e instanceof \RuntimeException ? $e->getMessage() : 'No pudimos terminar la instalación. Revisa los datos e inténtalo de nuevo; el detalle quedó en storage/logs.';
        }
    }
}

$err = $errors ? '<div class="alert alert-err" role="alert"><ul>' . implode('', array_map(static fn (string $m): string => '<li>' . e($m) . '</li>', $errors)) . '</ul></div>' : '';
$csrf = csrf_field();
$val = static fn (array $a, string $k, string $d = ''): string => e((string) ($a[$k] ?? $d));

if ($step === 1) {
    $rows = '';
    $allOk = true;
    foreach (InstallService::requirements() as $r) {
        if ($r['required'] && !$r['ok']) {
            $allOk = false;
        }
        $cls = $r['ok'] ? 'badge-ok' : ($r['required'] ? 'badge-err' : 'badge-warn');
        $rows .= '<tr><td>' . e($r['name']) . '</td><td><span class="badge ' . $cls . '">' . ($r['ok'] ? 'Correcto' : ($r['required'] ? 'Falta' : 'Opcional')) . '</span></td><td class="muted">' . e($r['detail']) . '</td></tr>';
    }
    $body = '<h1 class="serif">Bienvenido</h1><p>Vamos a revisar que tu hosting esté listo. Solo toma unos minutos.</p>' . $err
        . '<div class="table-wrap"><table class="table"><thead><tr><th>Requisito</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<form method="post" action="?paso=1">' . $csrf . '<div class="form-actions"><button class="btn btn-gold" type="submit"' . ($allOk ? '' : ' disabled') . '>Continuar</button> <a class="btn btn-ghost" href="?paso=1">Volver a revisar</a></div></form>';
    inst_page('Requisitos', $body, 1);
} elseif ($step === 2) {
    $f = $data['db_form'] ?? $data['db'] ?? [];
    $body = '<h1 class="serif">Base de datos</h1><p>Crea una base de datos MySQL/MariaDB en tu hosting y escribe sus datos. Haremos una prueba de conexión antes de continuar.</p>' . $err
        . '<form method="post" action="?paso=2" class="stack" autocomplete="off">' . $csrf
        . '<div class="form-grid"><div class="field"><label for="host">Servidor</label><input class="input" id="host" name="host" value="' . $val($f, 'host', 'localhost') . '" required></div>'
        . '<div class="field"><label for="port">Puerto</label><input class="input" id="port" name="port" inputmode="numeric" value="' . $val($f, 'port', '3306') . '"></div>'
        . '<div class="field"><label for="name">Nombre de la base de datos</label><input class="input" id="name" name="name" value="' . $val($f, 'name') . '" required></div>'
        . '<div class="field"><label for="user">Usuario</label><input class="input" id="user" name="user" value="' . $val($f, 'user') . '" required></div>'
        . '<div class="field"><label for="pass">Contraseña</label><input class="input" id="pass" name="pass" type="password" autocomplete="new-password"></div></div>'
        . '<div class="form-actions"><a class="btn btn-ghost" href="?paso=1">Atrás</a> <button class="btn btn-gold" type="submit">Probar conexión y continuar</button></div></form>';
    inst_page('Base de datos', $body, 2);
} elseif ($step === 3) {
    if (empty($data['db'])) {
        header('Location: ?paso=2');
        exit;
    }
    $b = $data['biz'] ?? [];
    $a = $data['adm'] ?? [];
    $tzOpts = '';
    $sel = (string) ($b['timezone'] ?? 'America/Guatemala');
    foreach (Tz::list() as $z) {
        $tzOpts .= '<option value="' . e($z) . '"' . ($z === $sel ? ' selected' : '') . '>' . e($z) . '</option>';
    }
    $body = '<h1 class="serif">Tu negocio y tu cuenta</h1><p>Estos datos se pueden cambiar después desde el panel.</p>' . $err
        . '<form method="post" action="?paso=3" class="stack">' . $csrf
        . '<div class="form-grid"><div class="field"><label for="biz_name">Nombre del negocio</label><input class="input" id="biz_name" name="biz_name" value="' . $val($b, 'name', 'Agenda Premium') . '" required></div>'
        . '<div class="field"><label for="biz_email">Correo del negocio</label><input class="input" id="biz_email" name="biz_email" type="email" value="' . $val($b, 'email') . '"></div>'
        . '<div class="field"><label for="biz_phone">Teléfono</label><input class="input" id="biz_phone" name="biz_phone" inputmode="tel" placeholder="5555 1234" value="' . $val($b, 'phone') . '"></div>'
        . '<div class="field"><label for="biz_whatsapp">WhatsApp</label><input class="input" id="biz_whatsapp" name="biz_whatsapp" inputmode="tel" placeholder="5555 1234" value="' . $val($b, 'whatsapp') . '"></div>'
        . '<div class="field"><label for="timezone">Zona horaria</label><select class="select" id="timezone" name="timezone">' . $tzOpts . '</select></div></div>'
        . '<h2 class="serif">Administrador</h2><div class="form-grid">'
        . '<div class="field"><label for="adm_name">Tu nombre</label><input class="input" id="adm_name" name="adm_name" value="' . $val($a, 'name') . '" required></div>'
        . '<div class="field"><label for="adm_email">Tu correo (para entrar)</label><input class="input" id="adm_email" name="adm_email" type="email" value="' . $val($a, 'email') . '" required></div>'
        . '<div class="field"><label for="adm_pass">Contraseña</label><input class="input" id="adm_pass" name="adm_pass" type="password" autocomplete="new-password" required><p class="hint">Mínimo 10 caracteres con mayúsculas, minúsculas y números.</p></div>'
        . '<div class="field"><label for="adm_pass2">Repite la contraseña</label><input class="input" id="adm_pass2" name="adm_pass2" type="password" autocomplete="new-password" required></div></div>'
        . '<div class="form-actions"><a class="btn btn-ghost" href="?paso=2">Atrás</a> <button class="btn btn-gold" type="submit">Continuar</button></div></form>';
    inst_page('Tu negocio', $body, 3);
} else {
    if (empty($data['db']) || empty($data['adm']['password'])) {
        header('Location: ?paso=1');
        exit;
    }
    $opts = '';
    if (class_exists('App\\Services\\ProfessionService')) {
        foreach (\App\Services\ProfessionService::all() as $key => $p) {
            $k = is_array($p) && isset($p['key']) ? (string) $p['key'] : (string) $key;
            $opts .= '<option value="' . e($k) . '">' . e((string) ($p['name'] ?? $k)) . '</option>';
        }
    }
    $body = '<h1 class="serif">¿A qué te dedicas?</h1><p>Cargaremos eventos, preguntas, recordatorios y textos sugeridos para tu profesión. Todo se puede editar después.</p>' . $err
        . '<form method="post" action="?paso=4" class="stack">' . $csrf
        . '<div class="field"><label for="profession">Profesión</label><select class="select" id="profession" name="profession">' . $opts . '</select></div>'
        . '<label class="check"><input type="checkbox" name="demo" value="1"> <span>Cargar datos de ejemplo (clientes y citas de demostración que podrás borrar)</span></label>'
        . '<div class="form-actions"><a class="btn btn-ghost" href="?paso=3">Atrás</a> <button class="btn btn-gold btn-lg" type="submit">Instalar ahora</button></div></form>';
    inst_page('Profesión', $body, 4);
}
