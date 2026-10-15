<?php
declare(strict_types=1);
/**
 * Empaqueta el sitio en un ZIP listo para descomprimir en la raíz del dominio.
 * Uso: php tools/package.php   (requiere haber ejecutado build_content.php y el minificado)
 */
define('GX', 1);
define('GX_ROOT', dirname(__DIR__) . '/site');
require GX_ROOT . '/_core/lib/bootstrap.php';
require_once GX_ROOT . '/_core/lib/auth.php';
require_once GX_ROOT . '/_core/lib/images.php';

$root = dirname(__DIR__);
$site = $root . '/site';
$dist = $root . '/dist';
@mkdir($dist, 0755, true);

const DENY_EXT = 'php[0-9]*|phtml|pht|phps|phar|pgif|shtml|cgi|pl|py|sh|bash|asp|aspx|jsp|exe|dll|so|htaccess|htpasswd|ini';

function folder_htaccess(string $allowRegex = ''): string
{
    $h = "# Carpeta pública de solo lectura: sin scripts, sin listado de directorios.\nOptions -Indexes -ExecCGI\n"
        . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_mime.c>\n  RemoveHandler .php .phtml .pht .phar .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .sh\n  RemoveType .php .phtml .pht .phar .php3 .php4 .php5 .php7 .php8 .phps\n</IfModule>\n"
        . "<FilesMatch \"(?i)\\.(" . DENY_EXT . ")$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n  </IfModule>\n</FilesMatch>\n";
    if ($allowRegex !== '') {
        $h .= "# Solo se sirven los tipos de archivo esperados en esta carpeta\n<FilesMatch \"^(?=.*\\.)(?!.+\\.(" . $allowRegex . ")$)\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n  </IfModule>\n</FilesMatch>\n";
    }
    return $h;
}

$folders = [
    'uploads' => 'webp|html',
    'media' => 'mp4|html',
    'assets' => '',
    'assets/css' => 'css|html',
    'assets/js' => 'js|html',
    'assets/fonts' => 'woff2|txt|html',
    'sobre-nosotros' => 'html',
    'servicio-de-gruas-24-7' => 'html',
    'contactenos' => 'html',
    'admin/assets' => 'css|js|html',
];
foreach ($folders as $f => $allow) {
    @mkdir($site . '/' . $f, 0755, true);
    file_put_contents($site . '/' . $f . '/.htaccess', folder_htaccess($allow));
    file_put_contents($site . '/' . $f . '/index.html', '');
}
// uploads: además una CSP que neutraliza cualquier contenido activo
file_put_contents($site . '/uploads/.htaccess', file_get_contents($site . '/uploads/.htaccess')
    . "<IfModule mod_headers.c>\n  Header always set Content-Security-Policy \"default-src 'none'; sandbox\"\n  Header always set X-Content-Type-Options \"nosniff\"\n</IfModule>\n");
// carpetas de datos
@mkdir($site . '/_core/data', 0755, true);
foreach (glob($site . '/_core/data/*') ?: [] as $f) {
    is_file($f) && unlink($f);
}
@unlink($site . '/_core/data/.keep');
file_put_contents($site . '/_core/data/.htaccess', "# Acceso web bloqueado\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\nOptions -Indexes\n");
file_put_contents($site . '/_core/data/index.html', '');
foreach (['_core/lib', '_core/seed'] as $f) {
    file_put_contents($site . '/' . $f . '/index.html', '');
}

// redirecciones 301 de las imágenes antiguas de WordPress
$map = ['Logo' => 'logo', 'LM3' => 'logo-mobile', 'sn4' => 'hero-sobre', 'SDG' => 'hero-servicio', 'ctc2' => 'hero-contacto',
    '1' => 'hero-inicio', 'WhatsApp-Image-2022-10-04-at-19.24.40' => 'wa-2022'];
for ($i = 1; $i <= 16; $i++) {
    $map['SG-' . $i] = 'sg-' . $i;
}
for ($i = 2; $i <= 7; $i++) {
    $map['IMG-20240927-WA000' . $i] = 'izaje-' . $i;
}
$rules = "  # Imágenes antiguas de wp-content -> imágenes nuevas (301)\n";
foreach ($map as $old => $new) {
    $rules .= '  RewriteRule ^wp-content/uploads/\d{4}/\d{2}/' . preg_quote((string)$old, '/') . '(-\d+x\d+)?\.(jpe?g|png)$ /uploads/' . $new . ".webp [R=301,L,NC]\n";
}
$rules .= "  RewriteRule ^wp-content/uploads/2024/09/VID-20240927-WA0004\\.mp4$ /media/vid-1.mp4 [R=301,L,NC]\n"
    . "  RewriteRule ^wp-content/uploads/2024/09/VID-20240927-WA0001\\.mp4$ /media/vid-2.mp4 [R=301,L,NC]\n";
$ht = file_get_contents($site . '/.htaccess');
$ht = preg_replace('/#GX-IMG-REDIRECTS\n(?:  # Imágenes antiguas.*?\n(?:  RewriteRule \^wp-content\/uploads.*\n)+)?/s', '#GX-IMG-REDIRECTS' . "\n" . $rules, $ht, 1);
file_put_contents($site . '/.htaccess', $ht);

// credenciales iniciales (solo se guardan los hashes en el paquete)
$adminPass = gx_random_password(20);
$htPass = gx_random_password(20);
$adminUser = 'adm-gruas';
$htUser = 'acceso-gruas';
$keep = getenv('KEEP_CREDS') === '1' && is_file($dist . '/CREDENCIALES-INICIALES.txt');
if ($keep) {   // reempaquetar sin cambiar las claves (p. ej. para añadir el reporte al ZIP ya probado)
    preg_match('/Panel: (\S+) \/ (\S+)/', (string)file_get_contents($dist . '/CREDENCIALES-INICIALES.txt'), $m1);
    preg_match('/HTTP: (\S+) \/ (\S+)/', (string)file_get_contents($dist . '/CREDENCIALES-INICIALES.txt'), $m2);
    [$adminUser, $adminPass, $htUser, $htPass] = [$m1[1], $m1[2], $m2[1], $m2[2]];
}
gx_json_write($site . '/_core/seed/install.json', [
    'admin_user' => $adminUser, 'admin_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
    'ht_user' => $htUser, 'ht_hash' => gx_apr1($htPass),
]);

// favicons iniciales desde el logo
$seed = gx_json_read($site . '/_core/seed/content.json');
foreach ($seed['media'] as $m) {
    if ($m['key'] === 'logo') {
        gx_make_favicons(['file' => $m['file']]);
    }
}

// páginas estáticas iniciales: se instala una copia temporal y se copian solo las salidas generadas
$tmp = sys_get_temp_dir() . '/gx-pregen-' . bin2hex(random_bytes(4));
passthru('cp -a ' . escapeshellarg($site) . ' ' . escapeshellarg($tmp), $rc);
passthru('php ' . escapeshellarg(__DIR__ . '/pregen.php') . ' ' . escapeshellarg($tmp), $rc);
if ($rc !== 0) {
    fwrite(STDERR, "pregen falló\n");
    exit(1);
}
foreach (['index.html', '404.html', '403.html', '500.html', 'sitemap.xml', 'robots.txt', 'sobre-nosotros/index.html', 'servicio-de-gruas-24-7/index.html', 'contactenos/index.html', 'assets/css/theme.css'] as $f) {
    if (!is_file($tmp . '/' . $f)) {
        fwrite(STDERR, "falta $f\n");
        exit(1);
    }
    copy($tmp . '/' . $f, $site . '/' . $f);
}
passthru('rm -rf ' . escapeshellarg($tmp));
if (!is_file($site . '/favicon.ico')) {
    fwrite(STDERR, "falta favicon\n");
    exit(1);
}

// README
$readme = <<<TXT
GRÚAS EXPRESS TOWING — SITIO WEB (plataforma propia, sin WordPress)
====================================================================

INSTALACIÓN (1 minuto)
1. Suba el ZIP a la carpeta raíz del dominio (public_html) y descomprímalo ahí.
   Debe quedar: index.html, .htaccess, admin/, _core/, uploads/, assets/ ...
2. Abra  https://SU-DOMINIO/admin/   La primera visita instala todo automáticamente
   (base de datos, contenido y páginas). El sitio público ya funciona desde el primer momento.
3. BORRE este archivo README.txt del servidor después de anotar las claves.

ACCESOS INICIALES (se muestran UNA sola vez; cámbielas)
  Puerta 1 — Contraseña HTTP del servidor (aparece como ventana del navegador):
      Usuario:    $htUser
      Contraseña: $htPass
  Puerta 2 — Panel de administración:
      Usuario:    $adminUser
      Contraseña: $adminPass
  El panel obliga a cambiar la contraseña en el primer ingreso. Luego active la
  verificación en dos pasos (2FA) en «Mi cuenta». La contraseña HTTP también se cambia ahí.

CÓMO FUNCIONA
  - El sitio que ven los visitantes son SOLO archivos estáticos (como un sitio de Xara).
  - Edite todo en el panel y pulse «Publicar»: se regeneran las páginas, sitemap.xml y robots.txt.
  - PHP solo se ejecuta en 2 archivos: admin/index.php y enviar.php. Cualquier otro .php da error 403.

FORMULARIO DE CONTACTO
  - Al pulsar «Enviar mensaje» los datos se abren en el WhatsApp de la empresa (3556-7904)
    y además se guarda una copia en el panel (Mensajes) y se envía un aviso por correo.
  - Cambiar el correo que recibe la copia: Panel > Ajustes > Contacto > «Correo que recibe copia del formulario».
  - Cambiar el WhatsApp de destino: Panel > Ajustes > Contacto > «WhatsApp (número con código de país)».
  - Correo por SMTP (opcional): Panel > Ajustes > Envío de correo.

NOTAS DEL CONTENIDO ORIGINAL (se conservaron tal cual; puede corregirlas en el panel)
  - El enlace «Envíe un email» apunta a info@agroinco.com pero el texto visible es info@gruasexpresstowing.gt.
  - La página Servicio de grúas 24/7 dice «guas» y «República Mexicana»; se mantuvo el texto original.

PERMISOS (hosting compartido)
  - Carpetas 755 y archivos 644.
  - Con escritura para PHP SOLO: _core/data/ (base de datos, logs, respaldos), uploads/ y los
    archivos generados (index.html y de cada carpeta de página, sitemap.xml, robots.txt, assets/css/theme.css).
  - Recomendado: archivos PHP del núcleo en modo solo lectura (chmod 444 _core/lib/*.php admin/index.php enviar.php).
  - El panel NUNCA edita archivos PHP ni plantillas: solo contenido.

SEGURIDAD (recomendaciones para el hosting)
  - Contraseñas fuertes y únicas en cPanel, FTP/SFTP y correo; active la verificación en dos pasos del hosting.
  - Mantenga PHP actualizado (8.0 o superior; recomendado 8.2/8.3) y active el antivirus/antimalware del hosting (Imunify/ClamAV).
  - Pida al hosting (o MultiPHP INI Editor) desactivar: exec, passthru, shell_exec, system, proc_open, popen,
    pcntl_exec, show_source, allow_url_fopen/include. El sitio no usa ninguna.
  - Si cambia el nombre de la carpeta admin/, hágalo ANTES de la primera visita.
  - Si el monitor detecta un archivo modificado o PHP no autorizado, se activa el MODO BLOQUEO: el panel y el
    formulario se desactivan y llega un correo de alerta; el sitio público sigue funcionando.
    Para reactivar: restaure los archivos limpios desde este ZIP, y borre por FTP
    _core/data/lockdown.flag y _core/data/baseline.json.
  - Cambie TODAS las contraseñas después de limpiar una infección anterior (la de WordPress/hosting).
TXT;
file_put_contents($site . '/README.txt', $readme . "\n");

// ZIP
$zipPath = $dist . '/gruas-express-towing.zip';
@unlink($zipPath);
$z = new ZipArchive();
$z->open($zipPath, ZipArchive::CREATE);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $file) {
    $abs = $file->getPathname();
    $rel = ltrim(substr($abs, strlen($site)), '/');
    if (preg_match('~^_core/data/(?!\.htaccess$|index\.html$)~', $rel)) {
        continue;
    }
    $z->addFile($abs, $rel);
    $n++;
}
// documentación
foreach (['ANALISIS_SEGURIDAD.md', 'INVENTARIO.md', 'REPORTE_VERIFICACION.md'] as $d) {
    if (is_file($root . '/' . $d)) {
        $z->addFile($root . '/' . $d, $d);
    }
}
$z->close();
echo "ZIP: $zipPath ($n archivos, " . round(filesize($zipPath) / 1048576, 1) . " MB)\n";
file_put_contents($dist . '/CREDENCIALES-INICIALES.txt', "Panel: $adminUser / $adminPass\nHTTP: $htUser / $htPass\n");
