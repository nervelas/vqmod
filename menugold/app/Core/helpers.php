<?php
declare(strict_types=1);

use MenuGold\Core\App;
use MenuGold\Core\Csrf;
use MenuGold\Core\Lang;

if (!function_exists('e')) {
    /** Escapa cualquier valor para salida HTML segura. */
    function e($value): string
    {
        if ($value === null || $value === false) return '';
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('eattr')) {
    /** Escapa para atributo JS/JSON dentro de HTML. */
    function eattr($value): string
    {
        return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
    }
}

if (!function_exists('url')) {
    /** Construye una URL absoluta respetando subcarpeta / subdominio. */
    function url(string $path = '', array $query = []): string
    {
        return App::url($path, $query);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return App::asset($path);
    }
}

if (!function_exists('guion')) {
    /**
     * URL de un guion de JavaScript. Los archivos estan en disco como .jstxt
     * y los sirve el controlador Guion; el navegador ve /js/panel.js normal.
     */
    function guion(string $nombre): string
    {
        $file = MG_ROOT . '/assets/' . ($nombre === 'chart' ? 'vendor/chart.jstxt' : 'js/' . $nombre . '.jstxt');
        $v = is_file($file) ? substr((string)filemtime($file), -6) : '1';
        return App::url('js/' . $nombre . '.js') . '?v=' . $v;
    }
}

if (!function_exists('uploaded')) {
    /** URL publica de un archivo subido a /storage/uploads. */
    function uploaded(?string $file, ?string $fallback = null): string
    {
        if (!$file) return $fallback ? App::asset($fallback) : '';
        if (preg_match('~^https?://~i', $file)) return $file;
        return App::url('archivo/' . ltrim($file, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string { return Csrf::token(); }
}

if (!function_exists('old')) {
    function old(string $key, $default = ''): string
    {
        $data = $_SESSION['_old'] ?? [];
        return e($data[$key] ?? $default);
    }
}

if (!function_exists('money')) {
    /** Formatea un monto con el simbolo del restaurante actual (Q por defecto). */
    function money($amount, ?string $symbol = null): string
    {
        $symbol = $symbol ?? (App::restaurant()['simbolo'] ?? 'Q');
        return $symbol . number_format((float)$amount, 2, '.', ',');
    }
}

if (!function_exists('__')) {
    /** Traduccion del menu publico (es / en). */
    function __(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('t')) {
    /** Devuelve el campo traducido de una fila (nombre / nombre_en). */
    function t(array $row, string $field): string
    {
        if (Lang::current() !== 'es') {
            $alt = $field . '_' . Lang::current();
            if (!empty($row[$alt])) return (string)$row[$alt];
        }
        return (string)($row[$field] ?? '');
    }
}

if (!function_exists('dt')) {
    /** Fecha legible en espanol. */
    function dt(?string $value, string $format = 'd/m/Y H:i'): string
    {
        if (!$value || $value === '0000-00-00 00:00:00') return '';
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : '';
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $text, string $sep = '-'): string
    {
        $text = strtr($text, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
        $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', $sep, $text) ?? '');
        return trim($text, $sep) ?: 'x';
    }
}

if (!function_exists('array_get')) {
    function array_get($array, string $key, $default = null)
    {
        if (!is_array($array)) return $default;
        if (array_key_exists($key, $array)) return $array[$key];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) return $default;
            $array = $array[$segment];
        }
        return $array;
    }
}

if (!function_exists('jdec')) {
    /** json_decode tolerante que siempre devuelve array. */
    function jdec($value, array $default = []): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return $default;
        $out = json_decode($value, true);
        return is_array($out) ? $out : $default;
    }
}

if (!function_exists('icon')) {
    /** Iconos SVG en linea (estilo Lucide, sin dependencias externas). */
    function icon(string $name, string $class = 'ico'): string
    {
        static $p = null;
        if ($p === null) $p = require MG_ROOT . '/app/Core/icons.php';
        $d = $p[$name] ?? $p['circle'];
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $d . '</svg>';
    }
}

if (!function_exists('flash')) {
    /** Guarda o lee un mensaje flash. */
    function flash(?string $type = null, ?string $message = null)
    {
        if ($type === null) {
            $all = $_SESSION['_flash'] ?? [];
            unset($_SESSION['_flash']);
            return $all;
        }
        $_SESSION['_flash'][] = ['tipo' => $type, 'texto' => $message];
        return null;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, array $query = []): void
    {
        header('Location: ' . (preg_match('~^https?://~', $path) ? $path : App::url($path, $query)));
        exit;
    }
}

if (!function_exists('json_out')) {
    function json_out($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('cfg')) {
    function cfg(string $key, $default = null)
    {
        return App::config($key, $default);
    }
}
