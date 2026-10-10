<?php
declare(strict_types=1);
namespace S5\Controllers;

use S5\Core\Csrf;
use S5\Core\Db;
use S5\Core\Http;
use S5\Core\Log;
use S5\Core\RateLimit;
use S5\Core\Sanitize;
use S5\Core\Settings;
use S5\Services\Analysis;
use S5\Services\Brief;
use S5\Services\Files;
use S5\Services\Lifecycle;
use S5\Services\Orders;
use S5\Services\Pipeline;
use S5\Services\PresentationParser;

/** API JSON del wizard (contrato §12). Capacidad = token largo del borrador + sesión + CSRF. */
final class ApiController
{
    private static function fail(string $msg, int $status = 400, array $extra = []): void
    {
        Http::json(['ok' => false, 'error' => $msg] + $extra, $status);
    }

    private static function guard(bool $needCsrf = true): void
    {
        if ($needCsrf && !Csrf::check()) {
            self::fail('Su sesión expiró. Recargue la página e inténtelo de nuevo.', 403);
        }
    }

    private static function input(): array
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, 6291456);
        if ($raw === '') {
            return [];
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    private static function order(array $p, bool $mustEdit = false): array
    {
        $o = Orders::byToken((string) ($p['token'] ?? ''));
        if (!$o) {
            self::fail('Este borrador ya no existe o venció.', 404);
        }
        if ($mustEdit && !Orders::editable($o)) {
            self::fail('Este pedido ya no se puede modificar.', 409);
        }
        return $o;
    }

    // ------------------------------------------------------------------
    public static function start(): void
    {
        self::guard();
        $in = self::input();
        if (!RateLimit::hit('draft-ip-' . Http::ip(), Settings::int('max_drafts_ip_day', 5) + 15, 86400)) {
            self::fail('Se alcanzó el límite de borradores por hoy. Inténtelo mañana o escríbanos por WhatsApp.', 429);
        }
        if (!empty($in['web_sitio'])) {   // honeypot
            Http::json(['ok' => true, 'token' => bin2hex(random_bytes(32))]);
        }
        $plan = ($in['plan'] ?? '') === 'tienda' ? 'tienda' : 'info';
        $o = Orders::create($plan, Http::ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        Log::audit('borrador_creado', $plan, (int) $o['id']);
        Http::json(['ok' => true, 'token' => $o['token']]);
    }

    public static function view(array $o): array
    {
        $o = Analysis::reapStale($o);
        $data = Orders::data($o);
        $files = [];
        foreach (Files::forOrder((int) $o['id']) as $f) {
            if ($f['kind'] === 'presentacion') {
                $files[(int) $f['id']] = ['id' => (int) $f['id'], 'nombre' => $f['orig_name'], 'tipo' => 'presentacion', 'mime' => $f['mime'], 'url_miniatura' => '', 'w' => null, 'h' => null];
            } else {
                $files[(int) $f['id']] = Files::publicInfo($f, $o['token']);
            }
        }
        $analysis = null;
        if ($o['analysis_state'] === 'lista' && $o['analysis']) {
            $analysis = json_decode((string) $o['analysis'], true);
            if (is_array($analysis)) {
                foreach ($analysis['imagenes'] ?? [] as $i => $im) {
                    $analysis['imagenes'][$i]['url'] = '/f/' . $o['token'] . '/' . (int) $im['id'] . '?t=1';
                }
            }
        }
        $left = max(0, Settings::int('max_regen', 3) - (int) $o['regen_count']);
        $out = [
            'ok' => true,
            'data' => $data,
            'estado' => self::publicState((string) $o['status']),
            'estado_interno' => $o['status'],
            'paso' => (string) ($o['step'] ?? ''),
            'creado_en' => strtotime((string) $o['created_at'] . ' UTC'),
            'plan' => $o['plan'],
            'archivos' => $files,
            'analisis' => ['estado' => $o['analysis_state'], 'mensaje' => $o['analysis_msg'], 'resultado' => $analysis, 'intentos_restantes' => max(0, 3 - (int) $o['analysis_count'])],
            'regeneraciones_restantes' => $left,
            'precio' => Orders::total($o),
            'banco' => PortalController::cfg()['banco'],
            'rechazo_pago' => $o['pay_reject_reason'],
        ];
        if (in_array($o['status'], [Orders::ST_LISTA, Orders::ST_PAGO], true)) {
            $out['url_vista_previa'] = Orders::previewUrl($o, true);
        }
        if (in_array($o['status'], [Orders::ST_CONSTRUYENDO, Orders::ST_PREPARANDO, Orders::ST_LISTA, Orders::ST_PAGO], true) || ($o['status'] === Orders::ST_BORRADOR && $o['build_msg'])) {
            $out['construccion'] = Pipeline::progress($o);
        }
        return $out;
    }

    public static function publicState(string $st): string
    {
        return [
            Orders::ST_BORRADOR => 'borrador', Orders::ST_ANALIZANDO => 'borrador', Orders::ST_CONSTRUYENDO => 'construyendo', Orders::ST_PREPARANDO => 'construyendo',
            Orders::ST_LISTA => 'listo', Orders::ST_PAGO => 'pago_revisar', Orders::ST_PUBLICADA => 'publicado', Orders::ST_VENCIDA => 'publicado', Orders::ST_SUSPENDIDA => 'publicado',
        ][$st] ?? 'borrador';
    }

    public static function get(array $p): void
    {
        $o = self::order($p);
        Http::json(self::view($o));
    }

    public static function save(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        if (!RateLimit::hit('save-' . $o['id'], 240, 600)) {
            self::fail('Demasiadas operaciones seguidas. Espere un momento.', 429);
        }
        $in = self::input();
        $cur = Orders::data($o);
        $new = Brief::sanitize(is_array($in['data'] ?? null) ? $in['data'] : [], $cur, (int) $o['id']);
        // el cliente no puede cambiar de plan una vez construido el sitio
        if ($o['fqdn'] && $new['plan'] !== $o['plan']) {
            $new['plan'] = $o['plan'];
        }
        Orders::saveData((int) $o['id'], $new);
        if (isset($in['paso']) && is_string($in['paso'])) {
            Orders::set((int) $o['id'], ['step' => preg_replace('/[^a-z_]/', '', mb_substr($in['paso'], 0, 20))]);
        }
        if ($o['plan'] !== $new['plan']) {
            Orders::set((int) $o['id'], ['plan' => $new['plan']]);
        }
        Http::json(['ok' => true, 'guardado_en' => gmdate('c')]);
    }

    public static function upload(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        if (!RateLimit::hit('upload-' . $o['id'], 90, 600) || !RateLimit::hit('upload-ip-' . Http::ip(), 400, 3600)) {
            self::fail('Está subiendo demasiados archivos seguidos. Espere un momento.', 429);
        }
        $tipo = (string) ($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['foto', 'logo', 'comprobante', 'presentacion'], true)) {
            self::fail('Tipo de archivo no válido.');
        }
        $u = $_FILES['archivo'] ?? null;
        if (!$u || !is_array($u) || ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $u['tmp_name'])) {
            $err = (int) ($u['error'] ?? 0);
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                self::fail('El archivo es demasiado grande.', 413);
            }
            self::fail('No se recibió el archivo. Inténtelo de nuevo.');
        }
        $tmp = (string) $u['tmp_name'];
        $name = (string) ($u['name'] ?? 'archivo');
        if ($tipo === 'presentacion') {
            $d = Orders::data($o);
            if (empty($d['presentacion']['acepto'])) {
                self::fail('Marque la casilla de aceptación para subir su presentación.');
            }
            $max = Settings::int('pres_max_mb', 10) * 1048576;
            $ins = PresentationParser::inspect($tmp, $name, $max);
            if (empty($ins['ok'])) {
                self::fail((string) $ins['mensaje'], 422, ['codigo' => $ins['codigo'] ?? '']);
            }
            // una sola presentación vigente por pedido
            Files::purgeKind((int) $o['id'], 'presentacion');
            Files::purgeKind((int) $o['id'], 'presentacion_img');
            $r = Files::storePresentation((int) $o['id'], $tmp, $name, (string) $ins['tipo']);
            if (!$r['ok']) {
                self::fail($r['error'], 422);
            }
            $d['presentacion']['file'] = (int) $r['file']['id'];
            $d['presentacion']['estado'] = 'pendiente';
            $d['presentacion']['confirmada'] = false;
            Orders::saveData((int) $o['id'], $d);
            Http::json(['ok' => true, 'id' => (int) $r['file']['id'], 'nombre' => $r['file']['orig_name'], 'tipo' => 'presentacion', 'url_miniatura' => '', 'mensaje' => 'Archivo recibido correctamente.']);
        }
        $r = $tipo === 'comprobante' ? Files::storeProof((int) $o['id'], $tmp, $name) : Files::storeImage((int) $o['id'], $tmp, $name, $tipo);
        if (!$r['ok']) {
            self::fail($r['error'], 422);
        }
        $info = Files::publicInfo($r['file'], $o['token']);
        Http::json(['ok' => true, 'id' => $info['id'], 'nombre' => $info['nombre'], 'tipo' => $tipo, 'url_miniatura' => $info['url_miniatura'], 'w' => $info['w'], 'h' => $info['h']]);
    }

    public static function deleteFile(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        $f = Files::get((int) ($p['id'] ?? 0), (int) $o['id']);
        if (!$f) {
            self::fail('Archivo no encontrado.', 404);
        }
        Files::delete($f);
        // limpiar referencias en el borrador
        $d = Orders::data($o);
        $id = (int) $f['id'];
        $strip = function (&$v) use ($id) { if ((int) $v === $id) { $v = null; } };
        $strip($d['negocio']['logo']);
        $d['contenido']['banner'] = array_values(array_filter($d['contenido']['banner'], fn($x) => (int) $x !== $id));
        $d['contenido']['galeria'] = array_values(array_filter($d['contenido']['galeria'], fn($x) => (int) $x !== $id));
        foreach ($d['contenido']['servicios'] as &$s) { $strip($s['foto']); }
        unset($s);
        foreach ($d['tienda']['productos'] as &$pp) { $strip($pp['foto']); }
        unset($pp);
        if ((int) ($d['pago']['comprobante'] ?? 0) === $id) { $d['pago']['comprobante'] = null; }
        if ((int) ($d['presentacion']['file'] ?? 0) === $id) {
            $d['presentacion'] = ['file' => null, 'acepto' => $d['presentacion']['acepto'], 'estado' => 'ninguna', 'confirmada' => false, 'usar' => [], 'fotos_usar' => []];
            Files::purgeKind((int) $o['id'], 'presentacion_img');
            Orders::set((int) $o['id'], ['analysis' => null, 'analysis_state' => 'ninguno', 'analysis_msg' => null]);
        }
        Orders::saveData((int) $o['id'], $d);
        Http::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ presentación
    public static function analyze(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        $d = Orders::data($o);
        if (empty($d['presentacion']['acepto']) || empty($d['presentacion']['file'])) {
            self::fail('Primero suba su presentación y marque la casilla de aceptación.');
        }
        if ((int) $o['analysis_count'] >= 3) {
            Http::json(['ok' => true, 'estado' => 'error', 'mensaje' => 'Ya usó los 3 análisis disponibles; puede llenar los datos manualmente.']);
        }
        if (in_array($o['analysis_state'], ['pendiente', 'procesando'], true)) {
            Http::json(['ok' => true, 'estado' => 'procesando']);
        }
        if (!RateLimit::hit('analyze-ip-' . Http::ip(), Settings::int('max_analysis_ip_day', 6), 86400)) {
            Orders::set((int) $o['id'], ['analysis_state' => 'error', 'analysis_msg' => 'No pudimos leer su presentación ahora; puede llenar los datos manualmente.']);
            Http::json(['ok' => true, 'estado' => 'error', 'mensaje' => 'No pudimos leer su presentación ahora; puede llenar los datos manualmente.']);
        }
        Analysis::queue((int) $o['id']);
        $d['presentacion']['estado'] = 'analizando';
        $d['presentacion']['confirmada'] = false;
        Orders::saveData((int) $o['id'], $d);
        $oid = (int) $o['id'];
        $spawned = Analysis::spawn($oid);
        if ($spawned) {
            Http::json(['ok' => true, 'estado' => 'procesando']);
        }
        // Sin procesos de fondo: se responde ya y el análisis continúa tras cerrar la conexión
        $out = json_encode(['ok' => true, 'estado' => 'procesando']);
        ignore_user_abort(true);
        @set_time_limit(170);
        if (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request')) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo $out;
            function_exists('fastcgi_finish_request') ? fastcgi_finish_request() : litespeed_finish_request();
            Analysis::run($oid);
            exit;
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Connection: close');
        header('Content-Length: ' . strlen($out));
        echo $out;
        @flush();
        Analysis::run($oid);
        exit;
    }

    public static function analysisStatus(array $p): void
    {
        $o = self::order($p);
        $o = Analysis::reapStale($o);
        // Sin procesos de fondo, el propio sondeo avanza el trabajo
        if ($o['analysis_state'] === 'pendiente') {
            Analysis::run((int) $o['id']);
            $o = Orders::byId((int) $o['id']);
        }
        $estado = ['ninguno' => 'pendiente', 'pendiente' => 'procesando', 'procesando' => 'procesando', 'lista' => 'lista', 'error' => 'error'][$o['analysis_state']] ?? 'pendiente';
        $res = null;
        if ($estado === 'lista' && $o['analysis']) {
            $res = json_decode((string) $o['analysis'], true);
        }
        Http::json(['ok' => true, 'estado' => $estado, 'resultado' => $res, 'mensaje' => $o['analysis_msg']]);
    }

    public static function skipPresentation(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        $d = Orders::data($o);
        $d['presentacion']['estado'] = 'omitida';
        Orders::saveData((int) $o['id'], $d);
        Http::json(['ok' => true]);
    }

    public static function confirmPresentation(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        if ($o['analysis_state'] !== 'lista' || !$o['analysis']) {
            self::fail('Todavía no hay resultados de la presentación para confirmar.', 409);
        }
        $in = self::input();
        $an = json_decode((string) $o['analysis'], true) ?: [];
        $usar = is_array($in['usar'] ?? null) ? $in['usar'] : [];
        $clean = [];
        foreach (['nombre', 'rubro', 'frase', 'quienes', 'contacto', 'horario', 'redes'] as $k) {
            $clean[$k] = !empty($usar[$k]);
        }
        foreach (['servicios', 'productos'] as $k) {
            $clean[$k] = array_values(array_filter(array_map('intval', is_array($usar[$k] ?? null) ? $usar[$k] : []), fn($i) => $i >= 0));
        }
        $fotos = array_map('intval', is_array($in['fotos'] ?? null) ? $in['fotos'] : []);
        $cur = Orders::data($o);
        // Ediciones del cliente sobre lo encontrado (tarjetas editables): se aplican a una copia del análisis
        if (is_array($in['editado'] ?? null)) {
            $an = self::applyEdits($an, $in['editado']);
        }
        $m = Brief::mergePresentation($cur, $an, $clean, $fotos, (int) $o['id']);
        Orders::saveData((int) $o['id'], $m['data']);
        Log::audit('presentacion_confirmada', '', (int) $o['id']);
        Http::json(['ok' => true, 'data' => $m['data'], 'conflictos' => $m['conflictos']]);
    }

    /** Modo «solo presentación»: aplica todo lo encontrado sin pedir revisión. Devuelve el borrador actualizado. */
    private static function autoApply(array $o): array
    {
        $an = json_decode((string) $o['analysis'], true) ?: [];
        $m = Brief::autoConfirm(Orders::data($o), $an, (int) $o['id']);
        Orders::saveData((int) $o['id'], $m['data']);
        Log::audit('presentacion_auto', '', (int) $o['id']);
        return $m;
    }

    public static function autoConfirm(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        if ($o['analysis_state'] !== 'lista' || !$o['analysis']) {
            self::fail('Todavía no hay resultados de la presentación.', 409);
        }
        $cur = Orders::data($o);
        if (!empty($cur['presentacion']['confirmada'])) {
            Http::json(['ok' => true, 'data' => $cur, 'conflictos' => [], 'ya' => true]);
        }
        $m = self::autoApply($o);
        Http::json(['ok' => true, 'data' => $m['data'], 'conflictos' => $m['conflictos'], 'logo' => $m['logo'] !== null, 'fotos' => count($m['fotos'])]);
    }

    /** Aplica ediciones del cliente a campos simples del análisis (siempre saneadas). */
    private static function applyEdits(array $an, array $ed): array
    {
        $simple = ['nombre' => 80, 'rubro_sugerido' => 40, 'frase_principal' => 120, 'quienes_somos' => 1500];
        foreach ($simple as $k => $max) {
            if (isset($ed[$k]) && is_string($ed[$k])) {
                $an[$k] = ['v' => Sanitize::multiline($ed[$k], $max), 'textual' => false];
            }
        }
        if (isset($ed['servicios']) && is_array($ed['servicios'])) {
            foreach ($ed['servicios'] as $i => $s) {
                if (isset($an['servicios'][(int) $i]) && is_array($s)) {
                    $an['servicios'][(int) $i]['nombre'] = Sanitize::text($s['nombre'] ?? $an['servicios'][(int) $i]['nombre'], 80);
                    $an['servicios'][(int) $i]['descripcion'] = Sanitize::text($s['descripcion'] ?? $an['servicios'][(int) $i]['descripcion'], 300);
                }
            }
        }
        if (isset($ed['productos']) && is_array($ed['productos'])) {
            foreach ($ed['productos'] as $i => $s) {
                if (isset($an['productos'][(int) $i]) && is_array($s)) {
                    foreach (['nombre' => 120, 'descripcion' => 600, 'categoria' => 60] as $k => $max) {
                        $an['productos'][(int) $i][$k] = Sanitize::text($s[$k] ?? $an['productos'][(int) $i][$k] ?? '', $max);
                    }
                    $an['productos'][(int) $i]['precio'] = Sanitize::price($s['precio'] ?? $an['productos'][(int) $i]['precio'] ?? 0);
                }
            }
        }
        if (isset($ed['contacto']) && is_array($ed['contacto'])) {
            foreach (['telefono', 'whatsapp', 'correo', 'direccion', 'horario'] as $k) {
                if (isset($ed['contacto'][$k]) && is_string($ed['contacto'][$k])) {
                    $an['contacto'][$k] = ['v' => Sanitize::text($ed['contacto'][$k], 200), 'textual' => false];
                }
            }
            if (isset($ed['contacto']['redes']) && is_array($ed['contacto']['redes'])) {
                foreach (Brief::REDES as $r) {
                    if (isset($ed['contacto']['redes'][$r])) {
                        $an['contacto']['redes'][$r] = Sanitize::text($ed['contacto']['redes'][$r], 300);
                    }
                }
            }
        }
        return $an;
    }

    // ------------------------------------------------------------------ construcción
    public static function create(array $p): void
    {
        self::guard();
        $o = self::order($p, true);
        $in = self::input();
        if (!empty($in['web_sitio'])) {
            Http::json(['ok' => true, 'estado' => 'construyendo']);   // honeypot: finge éxito
        }
        $t0 = (int) ($in['t0'] ?? 0);
        $fill = $t0 > 0 ? time() - (int) floor($t0 / ($t0 > 1e11 ? 1000 : 1)) : PHP_INT_MAX;
        $created = strtotime((string) $o['created_at'] . ' UTC');
        if ($fill < Settings::int('min_fill_seconds', 25) && (time() - $created) < Settings::int('min_fill_seconds', 25)) {
            self::fail('Por favor revise sus datos antes de continuar.', 429);
        }
        if ($o['status'] === Orders::ST_CONSTRUYENDO) {
            Http::json(['ok' => true, 'estado' => 'construyendo']);
        }
        $d = Orders::data($o);
        // Con presentación lista y sin confirmar, el sistema la aplica solo (no se pide nada más al cliente)
        if (!empty($d['presentacion']['file']) && empty($d['presentacion']['confirmada']) && !in_array($d['presentacion']['estado'], ['error', 'omitida', 'ninguna'], true) && $o['analysis_state'] === 'lista' && $o['analysis']) {
            $d = self::autoApply($o)['data'];
        }
        $d = Brief::fillDefaults($d);
        if (!empty($d['presentacion']['file'])) {
            Orders::saveData((int) $o['id'], $d);
        }
        $errs = Brief::validateForBuild($d);
        if ($errs) {
            self::fail(implode(' ', array_values($errs)), 422, ['campos' => $errs]);
        }
        $hasSite = !empty($o['fqdn']) && !empty($o['site_path']);
        if ($hasSite && in_array($o['status'], [Orders::ST_LISTA, Orders::ST_PREPARANDO, Orders::ST_PAGO], true)) {
            if ((int) $o['regen_count'] >= Settings::int('max_regen', 3)) {
                self::fail('Ya usó las ' . Settings::int('max_regen', 3) . ' regeneraciones disponibles de su vista previa.', 409);
            }
            Pipeline::regenerate((int) $o['id']);
        } else {
            if (!RateLimit::hit('build-ip-' . Http::ip(), Settings::int('max_drafts_ip_day', 5), 86400)) {
                self::fail('Se alcanzó el límite de vistas previas por hoy. Inténtelo mañana o escríbanos por WhatsApp.', 429);
            }
            Pipeline::start((int) $o['id']);
        }
        $prog = Pipeline::tick((int) $o['id'], 8);
        Http::json(['ok' => true, 'estado' => 'construyendo'] + ['construccion' => $prog]);
    }

    public static function progress(array $p): void
    {
        $o = self::order($p);
        $prog = $o['status'] === Orders::ST_CONSTRUYENDO ? Pipeline::tick((int) $o['id'], 20) : Pipeline::progress($o);
        $o = Orders::byId((int) $o['id']);
        $out = ['ok' => true] + $prog;
        if (in_array($o['status'], [Orders::ST_LISTA, Orders::ST_PAGO], true)) {
            $out['url'] = Orders::previewUrl($o, true);
        }
        Http::json($out);
    }

    public static function pay(array $p): void
    {
        self::guard();
        $o = self::order($p);
        if (!in_array($o['status'], [Orders::ST_LISTA, Orders::ST_PAGO], true)) {
            self::fail('Primero debe tener su vista previa lista.', 409);
        }
        $in = self::input();
        $nit = Sanitize::text($in['nombre_nit'] ?? '', 120);
        if ($nit === '') {
            self::fail('Escriba el nombre o NIT para el recibo.', 422);
        }
        $fid = (int) ($in['comprobante'] ?? 0);
        $f = $fid ? Files::get($fid, (int) $o['id']) : null;
        if (!$f || $f['kind'] !== 'comprobante') {
            self::fail('Suba su comprobante de transferencia o depósito.', 422);
        }
        if (!RateLimit::hit('pay-' . $o['id'], 10, 3600)) {
            self::fail('Demasiados intentos. Inténtelo más tarde.', 429);
        }
        $d = Orders::data($o);
        $d['pago'] = ['nombre_nit' => $nit, 'comprobante' => $fid];
        if (array_key_exists('tarjeta_extra', $in)) {
            $d['tarjeta_extra'] = (bool) $in['tarjeta_extra'];
        }
        Orders::saveData((int) $o['id'], $d);
        Lifecycle::submitPayment(Orders::byId((int) $o['id']), $nit, $fid);
        Http::json(['ok' => true, 'estado' => Orders::ST_PAGO]);
    }

    public static function regenerate(array $p): void
    {
        // La regeneración se pide con "crear" una vez editados los datos; este endpoint la inicia directamente.
        self::create($p);
    }
}
