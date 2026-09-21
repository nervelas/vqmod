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

/**
 * Cierra una página a quien no ha iniciado sesión.
 *
 * Kaptor es privado: se usa con cuenta, y las cuentas las crea el
 * administrador desde el panel. Quien llegue sin sesión va al acceso, y
 * después de entrar vuelve a donde quería ir.
 *
 * Quedan fuera a propósito: la baja de las campañas (el enlace de "darse de
 * baja" tiene que funcionar para cualquiera que reciba un correo), el pixel
 * y el registro de clics, y el instalador.
 */
function cr_exigir_sesion(): void
{
    if (Auth::autenticado()) { return; }

    $destino = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $volver  = $destino !== '' ? '?volver=' . rawurlencode($destino) : '';
    cr_redirigir('login.php' . $volver);
}

/**
 * Lo que este usuario lleva hecho con Kaptor: extracciones, correos y
 * números. Sirve para que la portada enseñe el estado del instrumento en vez
 * de un hueco vacío.
 *
 * Si algo falla —una tabla que todavía no existe en una instalación a medio
 * actualizar— devuelve ceros: es un adorno, nunca debe tumbar la página.
 *
 * @return array{escaneos:int,correos:int,whatsapps:int,ultimo:string}
 */
function cr_resumen_usuario(): array
{
    $vacio = ['escaneos' => 0, 'correos' => 0, 'whatsapps' => 0, 'ultimo' => ''];

    try {
        $id = Auth::id();
        $donde = $id > 0 ? '`usuario_id` = ?' : '1 = 1';
        $args  = $id > 0 ? [$id] : [];

        $fila = BD::fila(
            'SELECT COUNT(*) AS escaneos, COALESCE(SUM(`correos`),0) AS correos,
                    COALESCE(SUM(`whatsapps`),0) AS whatsapps, MAX(`inicio`) AS ultimo
               FROM `cr_escaneos` WHERE ' . $donde,
            $args
        );
        if (!$fila) { return $vacio; }

        $ultimo = (string) ($fila['ultimo'] ?? '');
        return [
            'escaneos'  => (int) $fila['escaneos'],
            'correos'   => (int) $fila['correos'],
            'whatsapps' => (int) $fila['whatsapps'],
            'ultimo'    => $ultimo !== '' ? date('d/m/Y H:i', strtotime($ultimo) ?: time()) : '',
        ];
    } catch (Throwable $e) {
        return $vacio;
    }
}

/**
 * El anillo con la nota del informe, dibujado en SVG.
 *
 * Va en SVG y no con CSS porque este dibujo tiene que sobrevivir a la
 * impresión a PDF, y los navegadores descartan los fondos y los degradados
 * de CSS al imprimir salvo que el usuario los active a mano.
 */
function cr_anillo_nota(int $nota, int $tam = 132): string
{
    $nota = max(0, min(100, $nota));
    $r    = 54;                                  // radio del trazo
    $vuelta = 2 * M_PI * $r;
    $pintado = $vuelta * ($nota / 100);

    $color = match (true) {
        $nota >= 80 => '#22A06B',
        $nota >= 55 => '#C98A1A',
        default     => '#D64545',
    };

    return '<svg class="anillo-nota" viewBox="0 0 128 128" width="' . $tam . '" height="' . $tam . '" role="img"'
        . ' aria-label="Nota: ' . $nota . ' sobre 100">'
        . '<circle cx="64" cy="64" r="' . $r . '" fill="none" stroke="currentColor" stroke-opacity=".16" stroke-width="11"/>'
        . '<circle cx="64" cy="64" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="11"'
        . ' stroke-linecap="round" stroke-dasharray="' . round($pintado, 2) . ' ' . round($vuelta, 2) . '"'
        . ' transform="rotate(-90 64 64)"/>'
        . '<text x="64" y="72" text-anchor="middle" font-size="40" font-weight="700" fill="' . $color . '">' . $nota . '</text>'
        . '</svg>';
}

/** Icono de cada área del informe. */
function cr_icono_area(string $area): string
{
    $trazos = [
        // Velocidad: un rayo.
        'velocidad' => '<path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12L13 2Z"/>',
        // Celular: un teléfono.
        'movil'     => '<rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10.6 18.6h2.8"/>',
        // Google: una lupa.
        'seo'       => '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m15.8 15.8 4.4 4.4"/>',
        // Seguridad: un escudo.
        'seguridad' => '<path d="M12 2.6 4.8 5.6v6c0 4.4 3 8.2 7.2 9.8 4.2-1.6 7.2-5.4 7.2-9.8v-6L12 2.6Z"/>',
        // Contacto: un globo de conversación.
        'negocio'   => '<path d="M20.5 12.2c0 4-3.8 7.2-8.5 7.2-1 0-2-.15-2.9-.4L4 21l1.4-3.8C4 15.9 3.5 14.1 3.5 12.2c0-4 3.8-7.2 8.5-7.2s8.5 3.2 8.5 7.2Z"/>',
        // IA: una chispa.
        'ia'        => '<path d="M12 2.8 13.9 9l6.2 1.9-6.2 1.9L12 19l-1.9-6.2L3.9 10.9 10.1 9 12 2.8Z"/><path d="M18.8 16.2 19.6 18.6l2.4.8-2.4.8-.8 2.4-.8-2.4-2.4-.8 2.4-.8.8-2.4Z"/>',
    ];

    $d = $trazos[$area] ?? '<circle cx="12" cy="12" r="8"/>';

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
