<?php
declare(strict_types=1);

/** Escapa cualquier salida HTML (proteccion XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escapa para uso dentro de atributos JS/JSON. */
function ejson(mixed $value): string
{
    return htmlspecialchars(
        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}',
        ENT_QUOTES,
        'UTF-8'
    );
}

/** Serializa un valor para incrustarlo dentro de una etiqueta <script>. */
function jsvalue(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return $json === false ? 'null' : $json;
}

/** URL absoluta del sitio a partir de una ruta. */
function url(string $path = ''): string
{
    $base = rtrim(SITE_URL, '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/** Ruta relativa a la raiz de la instalacion (para href internos). */
function base(string $path = ''): string
{
    $prefix = rtrim(BASE_PATH, '/');
    $path   = ltrim($path, '/');
    return $prefix . '/' . $path;
}

/** Resuelve una ruta de imagen guardada en base de datos a una URL usable. */
function asset_url(?string $path, ?string $fallback = null): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return $fallback !== null ? asset_url($fallback) : '';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }
    return base(ltrim($path, '/'));
}

/** Convierte texto en slug amigable para URLs. */
function slugify(string $text): string
{
    $text = strtr($text, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
    ]);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text) ?? '';
    return $text === '' ? 'item' : $text;
}

/** Recorta un texto sin cortar palabras. */
function excerpt(?string $text, int $length = 160): string
{
    $text = trim(strip_tags((string) $text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length);
    $pos = mb_strrpos($cut, ' ');
    return rtrim($pos !== false ? mb_substr($cut, 0, $pos) : $cut, ' .,;:') . '…';
}

/** Convierte texto plano con saltos de linea a parrafos HTML seguros. */
function paragraphs(?string $text): string
{
    $text  = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/\r\n\r\n|\n\n|\r\r/', $text) ?: [$text];
    $out   = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out .= '<p>' . nl2br(e($p)) . '</p>';
        }
    }
    return $out;
}

/** Lista de items separados por salto de linea. */
function lines(?string $text): array
{
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $rows = preg_split('/\r\n|\n|\r/', $text) ?: [];
    return array_values(array_filter(array_map('trim', $rows), static fn($r) => $r !== ''));
}

/** Numero de telefono en formato apto para tel: y wa.me */
function digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function whatsapp_link(?string $phone, string $message = ''): string
{
    $n = digits($phone);
    if ($n === '') {
        return '#';
    }
    if (strlen($n) === 8) {
        $n = '502' . $n;
    }
    $url = 'https://wa.me/' . $n;
    return $message !== '' ? $url . '?text=' . rawurlencode($message) : $url;
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : base($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : null;
}

/** Decodifica un campo JSON almacenado en base de datos. */
function json_field(mixed $raw, array $default = []): array
{
    if (is_array($raw)) {
        return $raw;
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function fecha_larga(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}
