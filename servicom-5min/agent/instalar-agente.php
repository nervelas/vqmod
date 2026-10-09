<?php
declare(strict_types=1);
/** Asistente del agente: guarda la configuración, genera el secreto compartido y se auto-elimina. */
define('S5_ROOT', __DIR__);
if (PHP_VERSION_ID < 80000) { exit('Se requiere PHP 8.0 o superior.'); }
header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff');
session_start();
if (empty($_SESSION['t'])) { $_SESSION['t'] = bin2hex(random_bytes(16)); }
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$secDir = dirname(S5_ROOT) . '/servicom-agent-secrets';
$existing = is_file($secDir . '/config.php') || is_file(S5_ROOT . '/storage/config.php');
if ($existing) { @unlink(__FILE__); http_response_code(404); exit('El agente ya está configurado; este instalador se eliminó.'); }
$err = []; $done = false; $secret = '';
$in = $_POST + ['host' => 'localhost', 'port' => '2083', 'user' => '', 'token' => '', 'home' => '', 'webs' => '', 'domain_root' => 'servicom.gt', 'portal' => 'https://crear.servicom.gt', 'ips' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'], (string) ($_POST['t'] ?? ''))) { $err[] = 'Sesión expirada.'; }
    $webs = rtrim((string) $in['webs'], '/');
    if ($webs === '' || $webs[0] !== '/' || str_contains($webs, '..')) { $err[] = 'La carpeta de webs debe ser una ruta absoluta (distinta de public_html).'; }
    if (!preg_match('#^https://[a-z0-9.-]+(?::\d+)?$#i', rtrim((string) $in['portal'], '/'))) { $err[] = 'URL del portal no válida (https://crear.tudominio).'; }
    if (!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/', strtolower((string) $in['domain_root']))) { $err[] = 'Dominio base no válido.'; }
    if (trim((string) $in['user']) === '' || trim((string) $in['token']) === '') { $err[] = 'Indique el usuario de cPanel y su token de API.'; }
    $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $in['ips']) ?: []), fn($x) => filter_var($x, FILTER_VALIDATE_IP)));
    if (!$err) {
        @mkdir($webs, 0755, true); @mkdir($webs . '/_base', 0755, true);
        $secret = bin2hex(random_bytes(32));
        $cfg = ['secret' => $secret, 'allowed_ips' => $ips, 'webs_path' => $webs, 'domain_root' => strtolower((string) $in['domain_root']), 'portal_url' => rtrim((string) $in['portal'], '/'),
            'cpanel' => ['host' => (string) $in['host'], 'port' => (int) $in['port'], 'user' => (string) $in['user'], 'token' => (string) $in['token'], 'home' => rtrim((string) $in['home'], '/'), 'verify_ssl' => true]];
        $target = (is_dir($secDir) || @mkdir($secDir, 0750)) && is_writable($secDir) ? $secDir . '/config.php' : S5_ROOT . '/storage/config.php';
        @mkdir(dirname($target), 0750, true);
        if (@file_put_contents($target, "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) { $err[] = 'No se pudo escribir la configuración.'; }
        else { @chmod($target, 0640); $done = true; @unlink(__FILE__); }
    }
}
$self = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/') . dirname($_SERVER['SCRIPT_NAME'] ?? '/');
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Agente de Servicom</title>
<style>body{font-family:system-ui,sans-serif;background:#0d0d10;color:#f1ebdc;margin:0;padding:16px}main{max-width:620px;margin:0 auto}label{display:block;margin:.8rem 0 .2rem;font-size:.9rem;color:#a79f8b}input{width:100%;min-height:44px;padding:.5rem .7rem;border-radius:10px;border:1px solid #2b2b33;background:#101015;color:#f1ebdc;font:inherit;box-sizing:border-box}button{min-height:48px;padding:.6rem 1.2rem;border-radius:10px;border:0;background:#c9a45c;color:#16120a;font-weight:700;margin-top:1rem}.err{background:#2a1111;border:1px solid #7a2a2a;padding:.7rem 1rem;border-radius:10px}code{word-break:break-all;color:#e8cf94}</style></head><body><main><h1 style="color:#c9a45c">Agente de Servicom</h1>
<?php if ($done): ?><p><b>Listo.</b> En el portal (Hostings → Agregar → tipo «Agente») use:</p><p>URL: <code><?= $h(rtrim($self, '/') . '/agent.php') ?></code></p><p>Secreto compartido (cópielo ahora; no se vuelve a mostrar):<br><code><?= $h($secret) ?></code></p>
<p>Luego construya el paquete base aquí: <code>php tools/build_base_agent.php</code>. Este instalador ya se eliminó.</p>
<?php else: foreach ($err as $e): ?><div class="err"><?= $h($e) ?></div><?php endforeach; ?>
<form method="post" autocomplete="off"><input type="hidden" name="t" value="<?= $h($_SESSION['t']) ?>">
<label>Servidor cPanel (normalmente localhost)</label><input name="host" value="<?= $h($in['host']) ?>"><label>Puerto</label><input name="port" value="<?= $h($in['port']) ?>">
<label>Usuario de cPanel</label><input name="user" value="<?= $h($in['user']) ?>"><label>Token de API de cPanel</label><input type="password" name="token" autocomplete="new-password">
<label>Carpeta personal (ej. /home/USUARIO)</label><input name="home" value="<?= $h($in['home']) ?>">
<label>Carpeta de las webs de clientes (ej. /home/USUARIO/webs-clientes)</label><input name="webs" value="<?= $h($in['webs']) ?>">
<label>Dominio base de las webs</label><input name="domain_root" value="<?= $h($in['domain_root']) ?>">
<label>URL del portal</label><input name="portal" value="<?= $h($in['portal']) ?>">
<label>IP del portal permitidas (opcional, separadas por coma)</label><input name="ips" value="<?= $h($in['ips']) ?>">
<button>Guardar y generar secreto</button></form><?php endif; ?></main></body></html>
