<?php
declare(strict_types=1);

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

/** Escape de salida HTML. Usar SIEMPRE al imprimir datos. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Atajo de URL interna. */
function url(string $ruta = '/', array $query = []): string
{
    return Url::a($ruta, $query);
}

function csrf(): string
{
    return Csrf::campo();
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Q1,250.00 */
function q(mixed $monto, bool $simbolo = true): string
{
    $n = (float) $monto;
    $s = number_format(abs($n), 2, '.', ',');
    $signo = $n < 0 ? '-' : '';
    return $signo . ($simbolo ? Ajustes::get('moneda_simbolo', 'Q') : '') . $s;
}

/** dd/mm/aaaa */
function fecha(?string $valor, string $porDefecto = '—'): string
{
    if (!$valor || str_starts_with($valor, '0000')) {
        return $porDefecto;
    }
    $t = strtotime($valor);
    return $t ? date('d/m/Y', $t) : $porDefecto;
}

/** dd/mm/aaaa hh:mm */
function fechahora(?string $valor, string $porDefecto = '—'): string
{
    if (!$valor || str_starts_with($valor, '0000')) {
        return $porDefecto;
    }
    $t = strtotime($valor);
    return $t ? date('d/m/Y H:i', $t) : $porDefecto;
}

function hora(?string $valor): string
{
    if (!$valor) {
        return '—';
    }
    $t = strtotime($valor);
    return $t ? date('H:i', $t) : '—';
}

/** "hace 5 minutos" */
function hace(?string $valor): string
{
    if (!$valor) {
        return '';
    }
    $t = strtotime($valor);
    if (!$t) {
        return '';
    }
    $d = time() - $t;
    if ($d < 60)     { return 'hace un momento'; }
    if ($d < 3600)   { return 'hace ' . (int) ($d / 60) . ' min'; }
    if ($d < 86400)  { return 'hace ' . (int) ($d / 3600) . ' h'; }
    if ($d < 2592000){ return 'hace ' . (int) ($d / 86400) . ' d'; }
    return fecha($valor);
}

/** Nombre del mes en español. */
function mesNombre(int $mes): string
{
    $m = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
          'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $m[$mes] ?? '';
}

/** '2026-03' => 'marzo 2026' */
function periodoNombre(?string $periodo): string
{
    if (!$periodo || !preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
        return (string) $periodo;
    }
    return ucfirst(mesNombre((int) $m[2])) . ' ' . $m[1];
}

function diaNombre(int $dia): string
{
    $d = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    return $d[$dia] ?? '';
}

/** Fecha larga: "27 de agosto de 2026" */
function fechaLarga(?string $valor): string
{
    if (!$valor) {
        return '';
    }
    $t = strtotime($valor);
    if (!$t) {
        return '';
    }
    return (int) date('j', $t) . ' de ' . mesNombre((int) date('n', $t)) . ' de ' . date('Y', $t);
}

/** Recorta texto sin cortar palabras. */
function recortar(?string $texto, int $largo = 120): string
{
    $texto = trim(strip_tags((string) $texto));
    if (mb_strlen($texto) <= $largo) {
        return $texto;
    }
    return mb_substr($texto, 0, $largo - 1) . '…';
}

/** Iniciales para avatares. */
function iniciales(string $nombre): string
{
    $p = preg_split('/\s+/', trim($nombre)) ?: [];
    $i = mb_substr($p[0] ?? '', 0, 1);
    if (count($p) > 1) {
        $i .= mb_substr($p[count($p) - 1], 0, 1);
    }
    return mb_strtoupper($i);
}

/** URL pública de un archivo subido. */
function subida(?string $archivo, string $carpeta = ''): string
{
    if (!$archivo) {
        return '';
    }
    if (str_starts_with($archivo, 'http')) {
        return $archivo;
    }
    $ruta = $carpeta !== '' ? trim($carpeta, '/') . '/' . ltrim($archivo, '/') : ltrim($archivo, '/');
    return url('/uploads/' . $ruta);
}

/** Clase de color según antigüedad de la mora. */
function semaforoMora(int $dias): string
{
    if ($dias <= 0)   { return 'ok'; }
    if ($dias <= 30)  { return 'aviso'; }
    if ($dias <= 60)  { return 'alerta'; }
    if ($dias <= 90)  { return 'grave'; }
    return 'critico';
}

function estadoBadge(string $estado): string
{
    $mapa = [
        'pendiente'  => 'aviso', 'parcial' => 'aviso', 'pagado' => 'ok', 'anulado' => 'neutro',
        'revision'   => 'aviso', 'aprobado' => 'ok', 'rechazado' => 'grave',
        'activo'     => 'ok', 'usado' => 'neutro', 'vencido' => 'neutro', 'cancelado' => 'neutro',
        'aprobada'   => 'ok', 'rechazada' => 'grave', 'cancelada' => 'neutro', 'completada' => 'info',
        'recibida'   => 'aviso', 'proceso' => 'info', 'resuelta' => 'ok', 'cerrada' => 'neutro',
        'habitada'   => 'ok', 'desocupada' => 'neutro', 'venta' => 'info', 'alquiler' => 'info',
        'abierta'    => 'ok', 'borrador' => 'neutro',
    ];
    return $mapa[$estado] ?? 'neutro';
}

/** Enlace de WhatsApp con texto pre-cargado. */
function whatsapp(?string $telefono, string $texto = ''): string
{
    $n = preg_replace('/\D+/', '', (string) $telefono) ?? '';
    if ($n === '') {
        return '';
    }
    if (strlen($n) === 8) {
        $n = Ajustes::get('pais_codigo', '502') . $n;
    }
    return 'https://wa.me/' . $n . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}

/** Reemplaza {variables} en plantillas de mensajes. */
function plantilla(string $texto, array $vars): string
{
    foreach ($vars as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

function usuarioActual(): ?array
{
    return Auth::usuario();
}

function esRol(string ...$roles): bool
{
    return Auth::es(...$roles);
}

/** Marca activa un enlace del menú. */
function activo(string $prefijo, string $clase = 'is-activo'): string
{
    return Url::activa($prefijo) ? $clase : '';
}

function attr(bool $condicion, string $atributo): string
{
    return $condicion ? $atributo : '';
}

/** Slug seguro. */
function slug(string $texto): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $t = strtolower((string) $t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim($t, '-');
}

/** Redondeo monetario consistente. */
function money(float $n): float
{
    return round($n, 2);
}

/** Icono SVG en línea. */
function ico(string $nombre, int $tam = 20, string $clase = ''): string
{
    return \App\Core\Icono::svg($nombre, $tam, $clase);
}

/** Nombre visible del rol. */
function rolNombre(string $rol): string
{
    return [
        'admin'         => 'Administración',
        'junta'         => 'Junta directiva',
        'garita'        => 'Garita / Seguridad',
        'residente'     => 'Residente',
        'contabilidad'  => 'Contabilidad',
    ][$rol] ?? $rol;
}

/** Atributo nonce para los <script> propios (política de seguridad de contenido). */
function nonce(): string
{
    return ' nonce="' . e(\App\Core\Respuesta::nonce()) . '"';
}
