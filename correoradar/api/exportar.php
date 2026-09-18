<?php
/**
 * CorreoRadar - Descarga de resultados en TXT, CSV o Excel (.xlsx).
 *
 * Se llama mediante un formulario POST (para poder enviar una selección larga
 * de correos) y devuelve el archivo directamente al navegador.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

Seguridad::exigirCsrf();

$id      = (int) cr_post('escaneo_id', 0);
$token   = trim((string) cr_post('token', ''));
$formato = strtolower((string) cr_post('formato', 'txt'));
$datos   = strtolower((string) cr_post('datos', 'correos'));

if (!in_array($formato, ['txt', 'csv', 'xlsx'], true)) {
    http_response_code(400);
    exit('Formato no válido.');
}
if (!in_array($datos, ['correos', 'telefonos', 'todo'], true)) {
    $datos = 'correos';
}

$escaneo = $id > 0 ? Rastreador::escaneo($id) : ($token !== '' ? Rastreador::porToken($token) : null);
if (!$escaneo) {
    http_response_code(404);
    exit('El escaneo no existe.');
}
if (!cr_puede_ver_escaneo($escaneo)) {
    http_response_code(403);
    exit('No tienes permiso para descargar este resultado.');
}

$correos   = $datos !== 'telefonos' ? Rastreador::correos((int) $escaneo['id']) : [];
$telefonos = $datos !== 'correos'   ? Rastreador::telefonos((int) $escaneo['id']) : [];

// Selección parcial: solo las filas marcadas en la tabla.
$seleccion = cr_post('seleccion', '');
if (is_string($seleccion) && trim($seleccion) !== '') {
    $marcados = array_filter(array_map('trim', explode(',', $seleccion)));
    if ($marcados) {
        $mapa = array_flip(array_map('mb_strtolower', $marcados));
        if ($datos === 'telefonos') {
            $telefonos = array_values(array_filter($telefonos, static fn($t) => isset($mapa[mb_strtolower($t['numero'])])));
        } elseif ($datos === 'correos') {
            $correos = array_values(array_filter($correos, static fn($c) => isset($mapa[mb_strtolower($c['correo'])])));
        }
    }
}

if (!$correos && !$telefonos) {
    http_response_code(404);
    exit('No hay datos que descargar.');
}

$nombre = Exportador::nombreArchivo($escaneo, $formato, $datos);

try {
    $contenido = match ($formato) {
        'csv'  => Exportador::csv($correos, $telefonos, $escaneo, $datos),
        'xlsx' => Exportador::xlsx($correos, $telefonos, $escaneo, $datos),
        default => Exportador::txt($correos, $telefonos, $datos),
    };
} catch (Throwable $e) {
    error_log('CorreoRadar / exportar: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo generar el archivo: ' . e($e->getMessage()));
}

Exportador::descargar($contenido, $nombre, Exportador::mime($formato));
