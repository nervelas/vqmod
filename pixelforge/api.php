<?php
/** API interna de PixelForge (JSON). Requiere sesión iniciada y token CSRF. */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

Security::headers();
header('Cache-Control: no-store');

$settings = pf_settings();
$store = pf_store();

if (!$settings->isInstalled()) {
    Support::jsonError('La aplicación aún no está instalada.', 409);
}
Security::requireAuth();

$action = Security::str('accion', Security::str('accion', '', 40, 'get'), 40);
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isWrite) {
    Security::requireCsrf($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
}

switch ($action) {
    case 'generar':
        if (!$isWrite) {
            Support::jsonError('Método no permitido.', 405);
        }
        generarImagen($store, $settings);
        break;

    case 'historial':
        historial($store);
        break;

    case 'eliminar':
        if (!$isWrite) {
            Support::jsonError('Método no permitido.', 405);
        }
        eliminar($store);
        break;

    case 'preset_guardar':
        if (!$isWrite) {
            Support::jsonError('Método no permitido.', 405);
        }
        guardarPreset($store);
        break;

    case 'preset_eliminar':
        if (!$isWrite) {
            Support::jsonError('Método no permitido.', 405);
        }
        $id = Security::id('id');
        Support::json(['ok' => $id !== '' && $store->presetDelete($id)]);
        break;

    case 'presets':
        Support::json(['ok' => true, 'presets' => $store->presetList()]);
        break;

    case 'estado':
        estado($store, $settings);
        break;

    default:
        Support::jsonError('Acción no reconocida.', 404);
}

// --------------------------------------------------------------------------

function generarImagen(Store $store, Settings $settings): void
{
    $porHora = $settings->int('rate_limit_hour');
    if (!Security::allowGeneration($store, $porHora)) {
        Support::jsonError(
            'Alcanzaste el límite de ' . $porHora . ' generaciones por hora configurado en el panel.',
            429
        );
    }

    $prompt = Security::str('prompt', '', 2000);
    if ($prompt === '') {
        Support::jsonError('Escribe un prompt para generar la imagen.', 422);
    }
    $width = Security::int('ancho', 1024, 64, 4096);
    $height = Security::int('alto', 1024, 64, 4096);
    $seedRaw = Security::str('seed', '', 20);
    $seed = $seedRaw === '' ? random_int(1, 2147483646) : max(0, min(2147483646, (int) $seedRaw));
    $batch = Security::str('lote', '', 64);
    if ($batch === '' || preg_match('/^[a-f0-9\-]{6,64}$/i', $batch) !== 1) {
        $batch = Support::uuid();
    }

    $input = [
        'prompt' => $prompt,
        'negative' => Security::str('negativo', '', 1000),
        'width' => $width,
        'height' => $height,
        'seed' => $seed,
        'format' => Security::str('formato', $settings->get('default_format'), 8),
        'realism' => Security::str('realismo', '0', 4) === '1',
        'batch_id' => $batch,
        'provider' => Security::str('proveedor', '', 32),
    ];

    try {
        $generator = new Forja($store, $settings);
        $result = $generator->generateOne($input);
    } catch (ProviderError $e) {
        Logger::write('generar', $e->getMessage());
        Support::jsonError($e->getMessage(), $e->reason === 'configuracion' ? 409 : 502, ['motivo' => $e->reason]);
        return;
    } catch (Throwable $e) {
        Logger::exception($e);
        Support::jsonError('Error inesperado al generar la imagen. El detalle quedó en el log.', 500);
        return;
    }

    Support::json([
        'ok' => true,
        'imagen' => presentar($result['record']),
        'avisos' => $result['notes'],
        'fallos' => $result['failures'],
        'lote' => $input['batch_id'],
    ]);
}

function historial(Store $store): void
{
    $limit = Security::int('limite', 24, 1, 96, 'get');
    $offset = Security::int('desde', 0, 0, 100000, 'get');
    $search = Security::str('buscar', '', 120, 'get');
    $rows = array_map('presentar', $store->imageList($limit, $offset, $search));
    Support::json([
        'ok' => true,
        'total' => $store->imageCount($search),
        'imagenes' => $rows,
    ]);
}

function eliminar(Store $store): void
{
    $id = Security::id('id');
    if ($id === '') {
        Support::jsonError('Identificador no válido.', 422);
    }
    $row = $store->imageGet($id);
    if ($row === null) {
        Support::jsonError('Esa imagen ya no existe.', 404);
    }
    Forja::deleteFiles($row);
    Support::json(['ok' => $store->imageDelete($id)]);
}

function guardarPreset(Store $store): void
{
    $type = Security::str('tipo', 'prompt', 16);
    if (!in_array($type, ['prompt', 'tamano'], true)) {
        Support::jsonError('Tipo de preset no válido.', 422);
    }
    $name = Security::str('nombre', '', 80);
    if ($name === '') {
        Support::jsonError('Ponle un nombre al preset.', 422);
    }
    $data = $type === 'prompt'
        ? [
            'prompt' => Security::str('prompt', '', 2000),
            'negativo' => Security::str('negativo', '', 1000),
            'realismo' => Security::str('realismo', '0', 4) === '1',
        ]
        : [
            'ancho' => Security::int('ancho', 1024, 64, 4096),
            'alto' => Security::int('alto', 1024, 64, 4096),
        ];
    $id = $store->presetInsert(['type' => $type, 'name' => $name, 'data' => $data]);
    Support::json(['ok' => true, 'id' => $id, 'presets' => $store->presetList()]);
}

function estado(Store $store, Settings $settings): void
{
    $providers = [];
    foreach (ProviderRegistry::ordered($settings) as $provider) {
        $providers[] = [
            'id' => $provider->id(),
            'label' => $provider->label(),
            'enabled' => $provider->isEnabled(),
            'configured' => $provider->isConfigured(),
            'negative' => $provider->supportsNegativePrompt(),
        ];
    }
    Support::json([
        'ok' => true,
        'proveedores' => $providers,
        'motor_imagen' => Imaging::engine(),
        'almacen' => $store->driver(),
        'uso' => $store->usageByDay(7),
    ]);
}

/** Da forma a un registro para el navegador (sin rutas internas ni keys). */
function presentar(array $row): array
{
    $base = Support::baseUrl();
    $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
    return [
        'id' => (string) $row['id'],
        'fecha' => (string) $row['created_at'],
        'lote' => (string) $row['batch_id'],
        'prompt' => (string) $row['prompt'],
        'negativo' => (string) $row['negative'],
        'proveedor' => (string) $row['provider'],
        'proveedor_label' => (string) $row['provider_label'],
        'modelo' => (string) $row['model'],
        'ancho' => (int) $row['width'],
        'alto' => (int) $row['height'],
        'ancho_origen' => (int) $row['source_width'],
        'alto_origen' => (int) $row['source_height'],
        'seed' => (int) $row['seed'],
        'formato' => (string) $row['format'],
        'realismo' => (int) $row['realism'] === 1,
        'peso' => Support::bytesHuman((int) $row['bytes']),
        'url' => $base . '/download.php?id=' . rawurlencode((string) $row['id']),
        'miniatura' => $base . '/download.php?id=' . rawurlencode((string) $row['id']) . '&mini=1',
        'descarga' => $base . '/download.php?id=' . rawurlencode((string) $row['id']) . '&guardar=1',
        'notas' => $meta['notas'] ?? [],
    ];
}
