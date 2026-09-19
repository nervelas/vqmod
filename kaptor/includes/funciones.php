<?php
/**
 * Kaptor - Funciones de uso general.
 * Utilidades cortas para plantillas, URLs, fechas y respuestas JSON.
 */
declare(strict_types=1);

/** Escapa texto para imprimirlo dentro de HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Codifica un valor como literal JavaScript seguro dentro de <script>.
 * Escapa <, >, & y las comillas en hexadecimal, de modo que ningun dato puede
 * cerrar la etiqueta ni inyectar código.
 */
function ejs($valor): string
{
    return (string) json_encode(
        $valor,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

/** Detecta si la petición actual llega por HTTPS (incluye proxys inversos). */
function cr_es_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') { return true; }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) { return true; }
    $fw = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $fw === 'https';
}

/**
 * Marca por defecto de Kaptor, en SVG incrustado.
 *
 * Va dentro del documento (no como <img>) para que pueda leer las variables
 * de color del tema: así el emblema cambia con la paleta elegida en el panel.
 */
function cr_logo_svg(int $tamano = 36): string
{
    $t = (string) $tamano;
    return '<svg class="marca-logo" width="' . $t . '" height="' . $t . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<defs>'
        . '<linearGradient id="kap-oro" x1="0" y1="0" x2="1" y2="1">'
        .   '<stop offset="0" stop-color="var(--oro-claro)"/><stop offset="1" stop-color="var(--oro)"/>'
        . '</linearGradient>'
        . '<linearGradient id="kap-barrido" x1="0" y1="1" x2="1" y2="0">'
        .   '<stop offset="0" stop-color="var(--neon)" stop-opacity="0"/>'
        .   '<stop offset="1" stop-color="var(--neon)" stop-opacity=".55"/>'
        . '</linearGradient>'
        . '</defs>'
        . '<circle cx="32" cy="32" r="29" fill="none" stroke="url(#kap-oro)" stroke-width="1.7" opacity=".62"/>'
        . '<circle cx="32" cy="32" r="19.5" fill="none" stroke="url(#kap-oro)" stroke-width="1.2" opacity=".4"/>'
        . '<circle cx="32" cy="32" r="10" fill="none" stroke="url(#kap-oro)" stroke-width="1" opacity=".28"/>'
        . '<path d="M32 32 32 3A29 29 0 0 1 57.1 17.5Z" fill="url(#kap-barrido)"/>'
        . '<path d="M32 32 57.1 17.5" stroke="var(--neon)" stroke-width="1.8" stroke-linecap="round"/>'
        . '<path d="M20 26h24a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H20a2 2 0 0 1-2-2V28a2 2 0 0 1 2-2Z" fill="var(--fondo)" stroke="url(#kap-oro)" stroke-width="1.7"/>'
        . '<path d="m18.6 27.4 12.2 8.1a2.2 2.2 0 0 0 2.4 0l12.2-8.1" fill="none" stroke="url(#kap-oro)" stroke-width="1.7" stroke-linecap="round"/>'
        . '<circle cx="46.5" cy="21.5" r="3.4" fill="var(--neon)"/>'
        . '</svg>';
}

/**
 * Devuelve el nombre del sitio listo para pintarlo como logotipo, con la
 * primera letra en un <span> para que se vea en el color de acento.
 * El texto ya va escapado: se imprime directamente.
 */
function cr_logotipo(string $nombre): string
{
    $nombre = trim($nombre);
    if ($nombre === '') { return ''; }

    $inicial = mb_substr($nombre, 0, 1);
    $resto   = mb_substr($nombre, 1);

    return '<span>' . e($inicial) . '</span>' . e($resto);
}

/** URL base pública del sitio, sin barra final. */
function cr_url_base(): string
{
    static $base = null;
    if ($base !== null) { return $base; }

    if (defined('CR_URL_BASE') && CR_URL_BASE !== '') {
        return $base = rtrim(CR_URL_BASE, '/');
    }
    $esquema = cr_es_https() ? 'https' : 'http';
    $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // El script puede vivir en /, /api/ o /admin/: subimos hasta la raiz del proyecto.
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = preg_replace('~/(api|admin)$~', '', $dir) ?? $dir;
    $dir = rtrim($dir, '/');
    return $base = $esquema . '://' . $host . $dir;
}

/** Construye una URL absoluta del sitio a partir de una ruta relativa. */
function cr_url(string $ruta = ''): string
{
    return cr_url_base() . '/' . ltrim($ruta, '/');
}

/** Ruta relativa correcta según donde este el script (raiz, /admin o /api). */
function cr_activo(string $ruta = ''): string
{
    return cr_url($ruta);
}

/** IP real del visitante (respeta cabeceras de proxy habituales del hosting). */
function cr_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $clave) {
        $valor = $_SERVER[$clave] ?? '';
        if ($valor === '') { continue; }
        $ip = trim(explode(',', (string) $valor)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
    }
    return '0.0.0.0';
}

/** Redirección segura (solo rutas internas). */
function cr_redirigir(string $ruta): void
{
    $destino = preg_match('~^https?://~i', $ruta) ? $ruta : cr_url($ruta);
    header('Location: ' . $destino);
    exit;
}

/** Devuelve una respuesta JSON y termina la ejecución. */
function cr_json(array $datos, int $codigo = 200): void
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Lee un valor de $_POST con valor por defecto. */
function cr_post(string $clave, $defecto = '')
{
    return $_POST[$clave] ?? $defecto;
}

/** Lee un valor de $_GET con valor por defecto. */
function cr_get(string $clave, $defecto = '')
{
    return $_GET[$clave] ?? $defecto;
}

/** Lee el cuerpo JSON de una petición AJAX (o cae a $_POST). */
function cr_cuerpo_json(): array
{
    $bruto = file_get_contents('php://input') ?: '';
    if ($bruto !== '') {
        $datos = json_decode($bruto, true);
        if (is_array($datos)) { return $datos; }
    }
    return $_POST;
}

/** Fecha legible en español: 18/09/2026 14:35 */
function cr_fecha(?string $fecha, bool $conHora = true): string
{
    if (!$fecha) { return '—'; }
    $ts = strtotime($fecha);
    if ($ts === false) { return '—'; }
    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

/** Cadena aleatoria segura en hexadecimal. */
function cr_aleatorio(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/** Recorta un texto largo anadiendo puntos suspensivos. */
function cr_recortar(string $texto, int $max = 60): string
{
    if (function_exists('mb_strlen') && mb_strlen($texto) > $max) {
        return mb_substr($texto, 0, $max - 1) . '…';
    }
    if (strlen($texto) > $max) { return substr($texto, 0, $max - 1) . '…'; }
    return $texto;
}

/** Host limpio de una URL (sin www.) para nombrar archivos y agrupar. */
function cr_host_de_url(string $url): string
{
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
    $host = strtolower($host);
    return preg_replace('~^www\.~', '', $host) ?? $host;
}

/** Convierte un texto en un "slug" apto para nombres de archivo. */
function cr_slug(string $texto): string
{
    $texto = strtolower(trim($texto));
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        if ($conv !== false) { $texto = $conv; }
    }
    $texto = preg_replace('~[^a-z0-9]+~', '-', $texto) ?? $texto;
    return trim($texto, '-') ?: 'sitio';
}

/** Guarda un mensaje temporal para mostrarlo tras una redirección. */
function cr_flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y limpia los mensajes temporales pendientes. */
function cr_flash_pendientes(): array
{
    $lista = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($lista) ? $lista : [];
}

/** Número formateado a la española (1.234). */
function cr_numero($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

/**
 * ¿El visitante actual puede ver (o descargar) este escaneo?
 * Puede verlo su autor, la sesión anonima que lo creo o un administrador.
 */
function cr_puede_ver_escaneo(array $escaneo): bool
{
    if (Auth::esAdmin()) { return true; }

    $propietario = (int) ($escaneo['usuario_id'] ?? 0);
    if ($propietario > 0 && $propietario === Auth::id()) { return true; }

    $mios = $_SESSION['mis_escaneos'] ?? [];
    return is_array($mios) && in_array((int) $escaneo['id'], $mios, true);
}

/** Anota un escaneo como propio de esta sesión (para visitantes anonimos). */
function cr_marcar_escaneo(int $id): void
{
    $mios = $_SESSION['mis_escaneos'] ?? [];
    if (!is_array($mios)) { $mios = []; }
    $mios[] = $id;
    // Solo guardamos los últimos 50 para no engordar la sesión.
    $_SESSION['mis_escaneos'] = array_slice(array_values(array_unique($mios)), -50);
}

/**
 * Envuelve las últimas palabras de un título en <span class="brillo">.
 *
 * Es puramente de presentación: da al final del titular el degradado dorado
 * con el destello que pasa. Si el título es muy corto se deja como está,
 * porque resaltarlo entero no resalta nada.
 */
function cr_titulo_brillo(string $titulo, int $palabras = 2): string
{
    $titulo = trim($titulo);
    $piezas = preg_split('~\s+~u', $titulo) ?: [];
    if (count($piezas) <= $palabras + 1) { return e($titulo); }

    $finales = array_splice($piezas, -$palabras);
    return e(implode(' ', $piezas)) . ' <span class="brillo">' . e(implode(' ', $finales)) . '</span>';
}
