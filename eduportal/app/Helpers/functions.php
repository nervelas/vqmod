<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;

if (!function_exists('e')) {
    /** Escapado de salida obligatorio en todas las vistas. */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path_url')) {
    /** Ruta base de la aplicacion (funciona en dominio raiz o subcarpeta). */
    function base_path_url(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(str_replace('/index.php', '', $script), '/');
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }
        return $base = ($dir === '' ? '/' : $dir . '/');
    }
}

if (!function_exists('url')) {
    function url(string $ruta = '/'): string
    {
        return rtrim(base_path_url(), '/') . '/' . ltrim($ruta, '/');
    }
}

if (!function_exists('url_absoluta')) {
    function url_absoluta(string $ruta = '/'): string
    {
        $esquema = \App\Core\Session::isHttps() ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $esquema . '://' . $host . url($ruta);
    }
}

if (!function_exists('asset')) {
    function asset(string $ruta): string
    {
        $rel = 'assets/' . ltrim($ruta, '/');
        $abs = BASE_PATH . '/' . $rel;
        $v = is_file($abs) ? (string)filemtime($abs) : (string)time();
        return url($rel) . '?v=' . $v;
    }
}

if (!function_exists('archivo_url')) {
    /** URL servida por el controlador de archivos (storage no es publico). */
    function archivo_url(?string $relativo): string
    {
        if (!$relativo) {
            return url('assets/img/placeholder.svg');
        }
        return url('archivo/' . ltrim($relativo, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('moneda')) {
    /** Formato de moneda: Q1,250.00 */
    function moneda(mixed $monto, bool $simbolo = true): string
    {
        $s = (string)Settings::get('moneda', 'Q');
        return ($simbolo ? $s : '') . number_format((float)$monto, 2, '.', ',');
    }
}

if (!function_exists('fecha')) {
    /** Formato dd/mm/aaaa */
    function fecha(?string $valor, string $formato = 'd/m/Y'): string
    {
        if (!$valor || str_starts_with($valor, '0000')) {
            return '';
        }
        try {
            return (new DateTime($valor))->format($formato);
        } catch (Throwable) {
            return '';
        }
    }
}

if (!function_exists('fecha_hora')) {
    function fecha_hora(?string $valor): string
    {
        return fecha($valor, 'd/m/Y H:i');
    }
}

if (!function_exists('mes_nombre')) {
    function mes_nombre(int $mes): string
    {
        $m = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $m[$mes] ?? '';
    }
}

if (!function_exists('dia_nombre')) {
    function dia_nombre(string $fecha): string
    {
        $d = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
        return $d[(int)date('w', strtotime($fecha))] ?? '';
    }
}

if (!function_exists('hoy')) {
    function hoy(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('rol_nombre')) {
    function rol_nombre(?string $rol): string
    {
        return [
            'superadmin' => 'Administracion',
            'secretaria' => 'Secretaria y Contabilidad',
            'docente'    => 'Docente',
            'padre'      => 'Padre o Encargado',
        ][$rol ?? ''] ?? '';
    }
}

if (!function_exists('config_colegio')) {
    function config_colegio(string $clave, mixed $default = ''): mixed
    {
        return Settings::get($clave, $default);
    }
}

if (!function_exists('activo_si')) {
    function activo_si(bool $cond, string $clase = 'activo'): string
    {
        return $cond ? $clase : '';
    }
}

if (!function_exists('recorta')) {
    function recorta(?string $texto, int $largo = 120): string
    {
        $t = trim(strip_tags((string)$texto));
        return mb_strlen($t) > $largo ? mb_substr($t, 0, $largo - 1) . '…' : $t;
    }
}

if (!function_exists('wa_link')) {
    /** Enlace de WhatsApp con plantilla editable. */
    function wa_link(?string $telefono, string $mensaje): string
    {
        $tel = preg_replace('/\D+/', '', (string)$telefono);
        if ($tel === '' || $tel === null) {
            return '';
        }
        if (strlen($tel) === 8) {
            $tel = '502' . $tel; // Guatemala
        }
        return 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensaje);
    }
}

if (!function_exists('plantilla')) {
    /** Reemplaza {marcadores} en las plantillas configurables. */
    function plantilla(string $texto, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $texto = str_replace('{' . $k . '}', (string)$v, $texto);
        }
        return $texto;
    }
}

if (!function_exists('estado_badge')) {
    function estado_badge(string $estado): string
    {
        $map = [
            'pendiente'  => 'warn', 'parcial' => 'info', 'pagado' => 'ok', 'anulado' => 'mute',
            'activo'     => 'ok', 'retirado' => 'mute', 'graduado' => 'info',
            'revision'   => 'warn', 'aprobado' => 'ok', 'rechazado' => 'bad',
            'presente'   => 'ok', 'ausente' => 'bad', 'tarde' => 'warn', 'justificado' => 'info',
            'nueva'      => 'warn', 'contactada' => 'info', 'inscrita' => 'ok', 'descartada' => 'mute',
            'entregado'  => 'ok', 'revisado' => 'info',
        ];
        return $map[$estado] ?? 'mute';
    }
}

if (!function_exists('nota_clase')) {
    function nota_clase(?float $nota): string
    {
        if ($nota === null) {
            return '';
        }
        $min = Settings::float('nota_minima', 60);
        if ($nota >= $min + 20) {
            return 'nota-alta';
        }
        return $nota >= $min ? 'nota-ok' : 'nota-baja';
    }
}

if (!function_exists('icono')) {
    /** Set de iconos SVG en linea, estilo consistente (trazo 1.75). */
    function icono(string $nombre, int $tam = 20): string
    {
        $p = [
            'panel'      => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z"/>',
            'alumnos'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'dinero'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4"/>',
            'notas'      => '<path d="M4 3h11l5 5v13H4z"/><path d="M15 3v5h5"/><path d="M8 13h8M8 17h5"/>',
            'asistencia' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/><path d="M9 15l2 2 4-4"/>',
            'aviso'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'calendario' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>',
            'tarea'      => '<path d="M9 4h6a2 2 0 0 1 2 2v1h2v14H5V7h2V6a2 2 0 0 1 2-2z"/><path d="M9 12h6M9 16h4"/>',
            'mensaje'    => '<path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'reporte'    => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
            'config'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 2.6 7a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'usuarios'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'salir'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
            'buscar'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
            'mas'        => '<path d="M12 5v14M5 12h14"/>',
            'editar'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'borrar'     => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
            'ver'        => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>',
            'descargar'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
            'subir'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
            'check'      => '<path d="M20 6L9 17l-5-5"/>',
            'x'          => '<path d="M18 6L6 18M6 6l12 12"/>',
            'menu'       => '<path d="M3 12h18M3 6h18M3 18h18"/>',
            'luna'       => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            'sol'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'campana'    => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'escuela'    => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>',
            'libro'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'recibo'     => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
            'grupo'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'flecha'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'atras'      => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
            'telefono'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
            'correo'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/>',
            'pin'        => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'estrella'   => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.3-6.2 3.3L7 14.2l-5-4.9 6.9-1z"/>',
            'escudo'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'respaldo'   => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
            'whatsapp'   => '<path d="M20.5 3.5A11 11 0 0 0 3.2 17.3L2 22l4.8-1.2A11 11 0 1 0 20.5 3.5z"/><path d="M8.5 8.5c.3 2 1.4 3.6 3 4.8 1.1.8 2.4 1.2 3 1.2.5 0 1-.6 1.2-1l-2-1-1 .9c-1-.5-2.1-1.6-2.6-2.6l.9-1-1-2c-.4.2-1 .7-1 1.2z"/>',
            'reloj'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'filtro'     => '<path d="M3 4h18l-7 8v6l-4 2v-8z"/>',
        ][$nombre] ?? '<circle cx="12" cy="12" r="9"/>';
        return '<svg class="ico" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" '
             . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
             . 'aria-hidden="true" focusable="false">' . $p . '</svg>';
    }
}
