<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Crypto;
use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Http;
use S5\Core\Log;
use S5\Core\Settings;
use S5\Provision\HostDriver;
use S5\Provision\ProvisionException;

/**
 * Construcción por pasos de la web del cliente: idempotente, reanudable, con progreso real y rollback completo.
 * Cada llamada a tick() avanza dentro de un presupuesto de tiempo; el navegador (o el cron) la repite.
 */
final class Pipeline
{
    private const WEIGHTS = [
        'validar' => 2, 'textos' => 8, 'subdominio' => 5, 'basedatos' => 5, 'copiar' => 20, 'configurar' => 5, 'instalar' => 12,
        'medios' => 8, 'paginas' => 12, 'tienda' => 8, 'finalizar' => 5, 'ssl' => 5, 'revision' => 5,
    ];
    private const TITLES = [
        'validar' => 'Revisando sus datos', 'textos' => 'Redactando los textos', 'subdominio' => 'Creando su dirección web',
        'basedatos' => 'Preparando la base de datos', 'copiar' => 'Copiando los archivos del sitio', 'configurar' => 'Configurando su sitio',
        'instalar' => 'Instalando WordPress', 'medios' => 'Subiendo sus fotos', 'paginas' => 'Armando las páginas',
        'tienda' => 'Montando su tienda', 'finalizar' => 'Dando los últimos toques', 'ssl' => 'Activando la conexión segura',
        'revision' => 'Revisando la calidad',
    ];
    private const MAX_ATTEMPTS = 3;

    public static function stepKeys(array $o): array
    {
        $keys = array_keys(self::TITLES);
        if ($o['plan'] !== 'tienda') {
            $keys = array_values(array_diff($keys, ['tienda']));
        }
        return $keys;
    }

    // ---------------------------------------------------------------- inicio
    public static function start(int $orderId, array $opts = []): void
    {
        $o = Orders::byId($orderId);
        if (!$o) {
            return;
        }
        Db::delete('build_steps', 'order_id=?', [$orderId]);
        foreach (self::stepKeys($o) as $k) {
            Db::insert('build_steps', ['order_id' => $orderId, 'step_key' => $k, 'status' => 'pending']);
        }
        $st = self::state($o);
        $st = ['job_id' => substr(bin2hex(random_bytes(8)), 0, 12), 'qa_attempt' => 0] + array_diff_key($st, ['copy_cursor' => 1, 'ssl_started' => 1, 'force_base' => 1, 'qa_attempt' => 1]);
        if (!empty($opts['demo'])) {
            $st['demo'] = true;
        }
        $f = ['status' => Orders::ST_CONSTRUYENDO, 'build_msg' => '', 'build_attempts' => 0, 'build_lock' => null, 'build_state' => json_encode($st), 'qa_result' => null];
        if (!$o['host_id']) {
            $f['host_id'] = Hosts::defaultId() ?: null;
        }
        Orders::set($orderId, $f);
        Log::audit('construccion_inicio', 'plan=' . $o['plan'], $orderId);
    }

    /** Reconstruye una vista previa existente (regenerar / editar datos). */
    public static function regenerate(int $orderId): void
    {
        $o = Orders::byId($orderId);
        if (!$o) {
            return;
        }
        $st = self::state($o);
        $jobId = substr(bin2hex(random_bytes(8)), 0, 12);
        $st = array_diff_key($st, ['copy_cursor' => 1, 'ssl_started' => 1, 'force_base' => 1]);
        $st['job_id'] = $jobId;
        $st['qa_attempt'] = 0;
        $st['regen'] = true;
        Db::delete('build_steps', 'order_id=?', [$orderId]);
        foreach (self::stepKeys($o) as $k) {
            $done = in_array($k, ['validar', 'subdominio', 'basedatos', 'instalar', 'ssl'], true);
            Db::insert('build_steps', ['order_id' => $orderId, 'step_key' => $k, 'status' => $done ? 'done' : 'pending']);
        }
        Orders::set($orderId, ['status' => Orders::ST_CONSTRUYENDO, 'build_msg' => '', 'build_lock' => null, 'build_attempts' => 0, 'build_state' => json_encode($st), 'regen_count' => (int) $o['regen_count'] + 1, 'qa_result' => null]);
        Log::audit('regenerar', 'n=' . ((int) $o['regen_count'] + 1), $orderId);
    }

    public static function state(array $o): array
    {
        $j = json_decode((string) ($o['build_state'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    private static function saveState(int $id, array $st): void
    {
        Db::update('orders', ['build_state' => json_encode($st)], 'id=?', [$id]);
    }

    // ---------------------------------------------------------------- progreso
    public static function progress(array $o): array
    {
        $rows = Db::all('SELECT * FROM ' . Db::t('build_steps') . ' WHERE order_id=?', [$o['id']]);
        $by = [];
        foreach ($rows as $r) {
            $by[$r['step_key']] = $r;
        }
        $keys = self::stepKeys($o);
        $total = 0;
        $done = 0.0;
        $pasos = [];
        $st = self::state($o);
        foreach ($keys as $k) {
            $w = self::WEIGHTS[$k];
            $total += $w;
            $r = $by[$k] ?? ['status' => 'pending', 'state' => null];
            $est = $r['status'];
            if ($est === 'done') {
                $done += $w;
            } elseif ($est === 'running' || ($k === 'copiar' && !empty($st['copy_cursor']))) {
                $s = json_decode((string) ($r['state'] ?? ''), true) ?: [];
                if ($k === 'copiar' && !empty($st['copy_total'])) {
                    $done += $w * min(0.99, ((int) ($st['copy_cursor'] ?? 0)) / max(1, (int) $st['copy_total']));
                } else {
                    $done += $w * 0.4;
                }
            }
            $pasos[] = ['clave' => $k, 'titulo' => self::TITLES[$k], 'estado' => $est === 'error' ? 'error' : ($est === 'done' ? 'listo' : ($est === 'running' ? 'en_curso' : 'pendiente'))];
        }
        $pct = $total > 0 ? (int) floor(100 * $done / $total) : 0;
        if ($o['status'] === Orders::ST_LISTA || $o['status'] === Orders::ST_PAGO || $o['status'] === Orders::ST_PUBLICADA) {
            $pct = 100;
        }
        $msg = (string) $o['build_msg'];
        $estado = 'construyendo';
        if ($o['status'] === Orders::ST_LISTA || $o['status'] === Orders::ST_PAGO || $o['status'] === Orders::ST_PUBLICADA) {
            $estado = 'lista';
        } elseif ($o['status'] === Orders::ST_PREPARANDO) {
            $estado = 'preparando';
            $msg = 'Estamos preparando su vista previa. Le avisaremos en cuanto esté lista.';
        } elseif ($o['status'] === Orders::ST_BORRADOR && $msg !== '') {
            $estado = 'error';
        }
        if (!empty($st['waiting']) && $o['status'] === Orders::ST_CONSTRUYENDO) {
            $msg = $st['waiting'];
        }
        return ['estado' => $estado, 'progreso' => $pct, 'pasos' => $pasos, 'mensaje' => $msg];
    }

    // ---------------------------------------------------------------- ejecución
    /** Avanza la construcción. Devuelve el progreso actual. */
    public static function tick(int $orderId, int $budget = 22): array
    {
        $t0 = microtime(true);
        $o = Orders::byId($orderId);
        if (!$o) {
            return ['estado' => 'error', 'progreso' => 0, 'pasos' => [], 'mensaje' => 'No encontrado'];
        }
        if ($o['status'] !== Orders::ST_CONSTRUYENDO) {
            return self::progress($o);
        }
        // candado por pedido
        $got = Db::q('UPDATE ' . Db::t('orders') . ' SET build_lock=? WHERE id=? AND (build_lock IS NULL OR build_lock<?)', [gmdate('Y-m-d H:i:s', time() + 90), $orderId, Db::now()])->rowCount();
        if ($got !== 1) {
            return self::progress($o);
        }
        @set_time_limit(max(60, $budget + 100));
        try {
            while (microtime(true) - $t0 < $budget) {
                $o = Orders::byId($orderId);
                if ($o['status'] !== Orders::ST_CONSTRUYENDO) {
                    break;
                }
                $next = null;
                foreach (self::stepKeys($o) as $k) {
                    $row = Db::one('SELECT * FROM ' . Db::t('build_steps') . ' WHERE order_id=? AND step_key=?', [$orderId, $k]);
                    if (!$row) {
                        Db::insert('build_steps', ['order_id' => $orderId, 'step_key' => $k, 'status' => 'pending']);
                        $row = Db::one('SELECT * FROM ' . Db::t('build_steps') . ' WHERE order_id=? AND step_key=?', [$orderId, $k]);
                    }
                    if ($row['status'] !== 'done') {
                        $next = $row;
                        break;
                    }
                }
                if ($next === null) {
                    self::finish($o);
                    break;
                }
                $r = self::runStep($o, $next, $budget - (microtime(true) - $t0));
                Db::update('orders', ['build_lock' => gmdate('Y-m-d H:i:s', time() + 90)], 'id=?', [$orderId]);
                if ($r === 'wait' || $r === 'stop') {
                    break;
                }
            }
        } finally {
            Db::update('orders', ['build_lock' => null], 'id=?', [$orderId]);
        }
        return self::progress(Orders::byId($orderId) ?: $o);
    }

    /** @return string done|more|wait|stop */
    private static function runStep(array $o, array $row, float $left): string
    {
        $id = (int) $o['id'];
        $k = $row['step_key'];
        Db::update('build_steps', ['status' => 'running', 'started_at' => $row['started_at'] ?: Db::now()], 'id=?', [$row['id']]);
        try {
            $res = self::{'step_' . $k}($o, max(4, (int) $left));
            if ($res === 'done') {
                Db::update('build_steps', ['status' => 'done', 'finished_at' => Db::now(), 'message' => null], 'id=?', [$row['id']]);
                $st = self::state(Orders::byId($id));
                unset($st['waiting']);
                self::saveState($id, $st);
            }
            return $res;
        } catch (ProvisionException $e) {
            return self::failure($o, $row, $e->getMessage(), $e->retry);
        } catch (\Throwable $e) {
            Log::error('Paso ' . $k . ' falló: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
            return self::failure($o, $row, 'Error interno: ' . $e->getMessage(), true);
        }
    }

    private static function failure(array $o, array $row, string $msg, bool $retry): string
    {
        $id = (int) $o['id'];
        $k = $row['step_key'];
        $att = (int) Db::val('SELECT attempts FROM ' . Db::t('build_steps') . ' WHERE id=?', [$row['id']]);
        Log::audit('paso_error', $k . ': ' . $msg, $id);
        if ($retry && $att < self::MAX_ATTEMPTS) {
            Db::update('build_steps', ['status' => 'pending', 'message' => mb_substr($msg, 0, 400), 'attempts' => $att + 1], 'id=?', [$row['id']]);
            usleep(300000);
            return 'wait';
        }
        Db::update('build_steps', ['status' => 'error', 'message' => mb_substr($msg, 0, 400)], 'id=?', [$row['id']]);
        $o = Orders::byId($id);
        $friendly = in_array($k, ['validar'], true) ? $msg : 'No pudimos crear su vista previa en este momento. Ya fue notificado nuestro equipo; puede intentarlo de nuevo en unos minutos. (Ref. P' . $id . '-' . $k . ')';
        if ($k === 'ssl') {
            // El sitio existe pero el certificado aún no está listo: no se muestra nada inseguro, se conserva.
            Orders::setStatus($id, Orders::ST_PREPARANDO, 'Estamos preparando su vista previa.');
            Orders::alert('warn', 'SSL no disponible a tiempo para ' . ($o['fqdn'] ?? '') . ': ' . $msg, $id);
            Notifier::owner('Vista previa esperando SSL', 'La web ' . ($o['fqdn'] ?? '') . " no tiene HTTPS válido todavía.\n" . $msg . "\nCuando AutoSSL termine, entre al panel y presione 'Reanudar'.", $id);
            return 'stop';
        }
        if ($k === 'revision') {
            Orders::setStatus($id, Orders::ST_PREPARANDO, 'Estamos preparando su vista previa.');
            Orders::alert('error', 'QA falló en ' . ($o['fqdn'] ?? '') . ': ' . $msg, $id);
            Notifier::owner('Vista previa con problemas de calidad', 'La web ' . ($o['fqdn'] ?? '') . " no pasó el control de calidad y no se mostró al cliente.\n" . $msg, $id);
            return 'stop';
        }
        // Fallo duro: rollback completo
        $errs = self::rollback($o);
        Orders::set($id, ['status' => $k === 'validar' ? Orders::ST_BORRADOR : Orders::ST_BORRADOR, 'build_msg' => $friendly, 'build_lock' => null]);
        Orders::alert('error', 'Falló la creación de ' . ($o['business_name'] ?: 'pedido #' . $id) . " en el paso '$k': " . $msg . ($errs ? ' | Limpieza incompleta: ' . implode('; ', $errs) : ''), $id);
        if ($k !== 'validar') {
            Notifier::owner('Falló la creación de una web', "Pedido #$id, paso '$k':\n$msg" . ($errs ? "\nLimpieza incompleta:\n" . implode("\n", $errs) : "\nSe hizo rollback completo."), $id);
        }
        return 'stop';
    }

    // ---------------------------------------------------------------- rollback
    /** Elimina TODO lo creado para el pedido. Idempotente. Devuelve lista de errores de limpieza. */
    public static function rollback(array $o, bool $clearSlug = false): array
    {
        $id = (int) $o['id'];
        $errs = [];
        $st = self::state($o);
        $slug = (string) ($o['slug'] ?? '');
        $hostId = (int) ($o['host_id'] ?? 0);
        if ($hostId && $slug !== '') {
            try {
                $d = Hosts::driver($hostId);
                $docroot = $d->docroot($slug);
                $try = function (callable $fn, string $what) use (&$errs) {
                    try { $fn(); } catch (\Throwable $e) { $errs[] = $what . ': ' . $e->getMessage(); }
                };
                if (!empty($o['domain_assigned'])) {
                    $try(fn() => $d->detachDomain((string) $o['domain_assigned'], $slug), 'dominio');
                }
                $try(fn() => $d->removeSite($docroot), 'carpeta');
                $db = (string) ($o['db_name'] ?: ($st['db_plan']['db'] ?? ''));
                $us = (string) ($o['db_user'] ?: ($st['db_plan']['user'] ?? ''));
                if (!empty($st['db_plan']['retry']) || (empty($o['db_name']) && empty($st['db_plan']['attempted']))) {
                    $db = $us = '';
                }
                if ($db !== '' || $us !== '') {
                    $try(fn() => $d->dbDelete($db, $us), 'base de datos');
                }
                if ((string) ($o['fqdn'] ?? '') !== '' || !empty($st['sub_created'])) {
                    $try(fn() => $d->subdomainDelete($slug), 'subdominio');
                }
            } catch (\Throwable $e) {
                $errs[] = 'hosting: ' . $e->getMessage();
            }
        }
        $jobs = Manifest::jobDir($id);
        if (is_dir($jobs) && Fs::inside($jobs, Files::root() . '/jobs')) {
            Fs::rmTreeSafe($jobs, [Files::root() . '/jobs']);
        }
        Db::delete('build_steps', 'order_id=?', [$id]);
        $f = ['fqdn' => null, 'site_path' => null, 'db_name' => null, 'db_user' => null, 'db_pass' => null, 'wp_user' => null, 'wp_pass' => null, 'wp_prefix' => null, 'build_state' => null];
        if ($clearSlug) {
            $f['slug'] = null;
        }
        Orders::set($id, $f);
        Log::audit('rollback', $errs ? implode('; ', $errs) : 'ok', $id);
        return $errs;
    }

    // ---------------------------------------------------------------- pasos
    private static function driver(array $o): HostDriver
    {
        $hid = (int) $o['host_id'];
        if (!$hid) {
            throw new ProvisionException('No hay un hosting configurado para crear webs.', false);
        }
        return Hosts::driver($hid);
    }

    private static function domainRoot(array $o): string
    {
        $h = Hosts::get((int) $o['host_id']);
        return strtolower((string) ($h['cfg']['domain_root'] ?? Settings::baseDomain()));
    }

    private static function step_validar(array $o, int $left): string
    {
        $b = Orders::data($o);
        $e = Brief::validateForBuild($b);
        if ($e) {
            throw new ProvisionException('Faltan datos: ' . implode(' ', array_values($e)), false);
        }
        if (!(int) $o['host_id'] || !Hosts::get((int) $o['host_id'])) {
            $hid = Hosts::defaultId();
            if (!$hid) {
                throw new ProvisionException('El sistema aún no tiene un hosting configurado. Avise a Servicom.', false);
            }
            Orders::set((int) $o['id'], ['host_id' => $hid]);
        }
        return 'done';
    }

    private static function step_textos(array $o, int $left): string
    {
        $st = self::state($o);
        $b = Orders::data($o);
        $force = !empty($st['force_base']);
        if ($o['texts'] && !$force && empty($st['regen'])) {
            return 'done';
        }
        if ($force) {
            $texts = BaseTexts::textos($b);
            $src = 'base';
            $model = '';
        } else {
            $r = AiClient::redactar($b, (int) $o['id']);
            $texts = $r['texts'];
            $src = $r['fuente'];
            $model = $r['modelo'] ?? '';
        }
        Orders::set((int) $o['id'], ['texts' => json_encode($texts, JSON_UNESCAPED_UNICODE), 'texts_source' => $src]);
        return 'done';
    }

    private static function step_subdominio(array $o, int $left): string
    {
        $id = (int) $o['id'];
        $b = Orders::data($o);
        $slug = (string) $o['slug'];
        $d = self::driver($o);
        $st = self::state($o);
        if ($slug === '') {
            $slug = Slugs::generate((string) $b['negocio']['nombre'], $id);
            Orders::set($id, ['slug' => $slug]);
        }
        if (Slugs::isReserved($slug)) {
            throw new ProvisionException('Nombre de subdominio reservado.', false);
        }
        // Nunca se adopta un subdominio que ya existe y no creamos nosotros (protege sitios ajenos y servicom.gt)
        for ($n = 0; empty($st['sub_created']) && $d->subdomainExists($slug); $n++) {
            if ($n >= 6) {
                throw new ProvisionException('No se encontró un nombre de subdominio libre.', false);
            }
            $slug = substr(Slugs::generate((string) $b['negocio']['nombre'], $id), 0, 28) . '-' . bin2hex(random_bytes(2));
            Orders::set($id, ['slug' => $slug]);
        }
        $d = self::driver($o);
        $root = self::domainRoot($o);
        $docroot = $d->docroot($slug);
        $st = self::state($o);
        $st['sub_created'] = true;
        self::saveState($id, $st);   // se registra ANTES de crear, para que el rollback lo limpie si se interrumpe
        $d->subdomainCreate($slug, $docroot);
        Orders::set($id, ['fqdn' => $slug . '.' . $root, 'site_path' => $docroot]);
        return 'done';
    }

    private static function step_basedatos(array $o, int $left): string
    {
        $id = (int) $o['id'];
        if ($o['db_name']) {
            return 'done';
        }
        $d = self::driver($o);
        $last = null;
        for ($try = 0; $try < 5; $try++) {
            $st = self::state(Orders::byId($id));
            if (empty($st['db_plan']) || !empty($st['db_plan']['retry'])) {
                $tag = bin2hex(random_bytes(3));
                $plan = $d->dbPlan('w' . $tag, 'u' . $tag);
                $st['db_plan'] = ['short' => 'w' . $tag, 'user_short' => 'u' . $tag, 'pass' => rtrim(strtr(base64_encode(random_bytes(24)), '+/', 'Aa'), '='), 'db' => $plan['db'], 'user' => $plan['user'], 'host' => $plan['host']];
                self::saveState($id, $st);   // los nombres se guardan ANTES de crear: si el proceso muere, el rollback sabe qué borrar
            }
            $st['db_plan']['attempted'] = true;
            self::saveState($id, $st);
            $p = $st['db_plan'];
            try {
                $r = $d->dbCreate($p['short'], $p['user_short'], $p['pass']);
            } catch (ProvisionException $e) {
                if ($e->retry && preg_match('/ya existe/u', $e->getMessage())) {
                    // colisión con algo que NO es nuestro: se descarta el plan (jamás se adopta ni se borra lo ajeno)
                    $st['db_plan'] = ['retry' => true];
                    self::saveState($id, $st);
                    $last = $e;
                    continue;
                }
                throw $e;
            }
            $st['db_plan']['db'] = $r['db'];
            $st['db_plan']['user'] = $r['user'];
            $st['db_plan']['host'] = $r['host'];
            self::saveState($id, $st);
            Orders::set($id, ['db_name' => $r['db'], 'db_user' => $r['user'], 'db_pass' => Crypto::encrypt($p['pass'])]);
            return 'done';
        }
        throw new ProvisionException('No se pudo reservar un nombre libre para la base de datos.', false);
    }

    private static function step_copiar(array $o, int $left): string
    {
        $id = (int) $o['id'];
        $st = self::state($o);
        $d = self::driver($o);
        $cursor = (int) ($st['copy_cursor'] ?? 0);
        $r = $d->copyBase((string) $o['site_path'], $cursor, min(14, max(5, $left)), !empty($st['regen']));
        $st['copy_cursor'] = (int) $r['cursor'];
        $st['copy_total'] = (int) $r['total'];
        self::saveState($id, $st);
        return $r['done'] ? 'done' : 'more';
    }

    private static function step_configurar(array $o, int $left): string
    {
        $id = (int) $o['id'];
        $d = self::driver($o);
        $st = self::state($o);
        $docroot = (string) $o['site_path'];
        $https = Settings::scheme() === 'https';
        $b = Orders::data($o);
        if (!$o['wp_user']) {
            $o['wp_user'] = 'sc' . bin2hex(random_bytes(4));
            $o['wp_prefix'] = 'wp' . substr(bin2hex(random_bytes(3)), 0, 5) . '_';
            $pass = rtrim(strtr(base64_encode(random_bytes(24)), '+/=', 'Aa9'), '=');
            $o['wp_pass'] = Crypto::encrypt($pass);
            Orders::set($id, ['wp_user' => $o['wp_user'], 'wp_prefix' => $o['wp_prefix'], 'wp_pass' => $o['wp_pass']]);
        }
        if (empty($st['config_written'])) {
            $p = $st['db_plan'];
            $d->writeFile($docroot, 'wp-config.php', WpFiles::config(['db' => $p['db'], 'user' => $p['user'], 'pass' => $p['pass'], 'host' => $p['host'] ?? 'localhost'], (string) $o['wp_prefix'], $https), 0640);
            $d->writeFile($docroot, '.htaccess', WpFiles::htaccess($https), 0644);
            $d->writeFile($docroot, 'wp-content/uploads/.htaccess', WpFiles::uploadsHtaccess(), 0644);
            $st['config_written'] = true;
            self::saveState($id, $st);
        }
        // manifiesto + assets
        $texts = json_decode((string) $o['texts'], true) ?: [];
        $jobId = $st['job_id'] ?? substr(bin2hex(random_bytes(8)), 0, 12);
        $mode = !empty($st['demo']) || !empty($o['is_demo']) ? 'demo' : 'preview';
        $built = Manifest::build(Orders::byId($id), $texts, $jobId, ['mode' => $mode]);
        $secret = bin2hex(random_bytes(24));
        $d->stageJob($docroot, $jobId, json_encode($built['manifest'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), $built['assets'], $secret);
        $st['job_id'] = $jobId;
        self::saveState($id, $st);
        return 'done';
    }

    /** Ejecuta un paso de sc-provision.php; vuelve a llamarlo mientras responda more:true. */
    private static function prov(array $o, string $step, int $left, array $args = []): string
    {
        $st = self::state($o);
        $d = self::driver($o);
        $t0 = microtime(true);
        do {
            $args['budget'] = (string) max(6, min(22, (int) ($left - (microtime(true) - $t0)) - 2));
            $r = $d->provision((string) $o['site_path'], (string) $st['job_id'], $step, $args);
            if (empty($r['ok'])) {
                throw new ProvisionException('Constructor (' . $step . '): ' . (string) ($r['error'] ?? 'error'), !empty($r['retry']));
            }
            $more = !empty($r['more']);
        } while ($more && (microtime(true) - $t0) < $left);
        return $more ? 'more' : 'done';
    }

    private static function step_instalar(array $o, int $left): string { return self::prov($o, 'install', $left); }
    private static function step_medios(array $o, int $left): string
    {
        // Fotos del cliente/presentación primero (ya van en el manifiesto); el stock solo rellena lo que falte.
        if (self::stockFill($o, $left) === 'more') {
            return 'more';
        }
        return self::prov(Orders::byId((int) $o['id']) ?: $o, 'media', $left);
    }

    /**
     * Completa con fotos de stock (StockImages) y, si hubo cambios, vuelve a preparar el manifiesto del trabajo.
     * Nunca lanza: un fallo aquí solo significa que el tema usará arte generado. @return string done|more
     */
    private static function stockFill(array $o, int $left): string
    {
        $id = (int) $o['id'];
        try {
            if (!Settings::bool('stock_online', true)) {
                return 'done';
            }
            $b = Orders::data($o);
            $stock = StockImages::prune($b['_stock'] ?? [], fn(int $fid): bool => $fid > 0 && Files::get($fid, $id) !== null);
            $b['_stock'] = $stock;
            $keep = array_map(fn($a) => (int) $a['file'], $stock['assets']);
            foreach (Files::forOrder($id, 'stock') as $f) {   // huérfanos de una ejecución anterior
                if (!in_array((int) $f['id'], $keep, true)) {
                    Files::delete($f);
                }
            }
            $res = StockImages::fetch($b, $id, max(3.0, min(16.0, $left - 6.0)));
            $b['_stock'] = $res['stock'];
            Orders::saveData($id, $b);
            if (!$res['done']) {
                return 'more';
            }
            $st = self::state(Orders::byId($id) ?: $o);
            $sig = md5((string) json_encode($res['stock']['slots']));
            if (($st['stock_sig'] ?? '') !== $sig) {
                if ($res['stock']['assets']) {
                    self::restageManifest(Orders::byId($id) ?: $o);
                }
                $st['stock_sig'] = $sig;
                self::saveState($id, $st);
            }
        } catch (\Throwable $e) {
            Log::error('Fotos de stock (pedido ' . $id . '): ' . $e->getMessage());
        }
        return 'done';
    }

    /** Reescribe manifest.json y los assets del trabajo (idempotente) para incluir las fotos de stock. */
    private static function restageManifest(array $o): void
    {
        $st = self::state($o);
        if (empty($st['job_id'])) {
            return;
        }
        $mode = !empty($st['demo']) || !empty($o['is_demo']) ? 'demo' : 'preview';
        $texts = json_decode((string) $o['texts'], true) ?: [];
        $built = Manifest::build($o, $texts, (string) $st['job_id'], ['mode' => $mode]);
        self::driver($o)->stageJob((string) $o['site_path'], (string) $st['job_id'], json_encode($built['manifest'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), $built['assets'], bin2hex(random_bytes(24)));
    }
    private static function step_paginas(array $o, int $left): string { return self::prov($o, 'pages', $left); }
    private static function step_tienda(array $o, int $left): string { return self::prov($o, 'store', $left); }
    private static function step_finalizar(array $o, int $left): string
    {
        $r = self::prov($o, 'finish', $left);
        if ($r === 'done') {
            self::restoreHtaccess($o);
        }
        return $r;
    }

    /** WordPress puede reescribir el bloque de rutas del .htaccess (vacío si corre fuera de Apache): se restablece el nuestro. */
    public static function restoreHtaccess(array $o): void
    {
        $d = self::driver($o);
        $susp = ($o['status'] ?? '') === Orders::ST_SUSPENDIDA;
        $d->writeFile((string) $o['site_path'], '.htaccess', WpFiles::htaccess(Settings::scheme() === 'https', $susp), 0644);
    }

    private static function step_ssl(array $o, int $left): string
    {
        $id = (int) $o['id'];
        if (Settings::scheme() !== 'https') {
            return 'done';   // entorno de pruebas / sin HTTPS: se documenta en el diagnóstico
        }
        $st = self::state($o);
        $r = Http::request('GET', 'https://' . $o['fqdn'] . Orders::portSuffix() . '/', ['timeout' => 15, 'verify' => true, 'resolve' => self::resolve($o)]);
        if ($r['errno'] === 0 && $r['status'] > 0 && $r['ssl_result'] === 0) {
            return 'done';
        }
        $max = Settings::has('ssl_wait_sec') ? max(5, Settings::int('ssl_wait_sec')) : max(2, Settings::int('ssl_wait_min', 15)) * 60;
        if (empty($st['ssl_started'])) {
            $st['ssl_started'] = time();
        }
        if (time() - (int) $st['ssl_started'] > $max) {
            throw new ProvisionException('El certificado SSL de ' . $o['fqdn'] . ' no se activó en ' . intdiv($max, 60) . ' minutos (' . ($r['error'] ?: 'HTTP ' . $r['status']) . ').', false);
        }
        $st['waiting'] = 'Estamos activando la conexión segura de su sitio. Esto puede tardar unos minutos…';
        self::saveState($id, $st);
        sleep(3);
        return 'wait';
    }

    /** Resolución local para pruebas (S5_QA_RESOLVE=127.0.0.1). */
    public static function resolve(array $o): array
    {
        $ip = getenv('S5_QA_RESOLVE');
        if (!$ip) {
            return [];
        }
        $port = getenv('S5_SITE_PORT') ?: (Settings::scheme() === 'https' ? '443' : '80');
        return [$o['fqdn'] . ':' . $port . ':' . $ip];
    }

    private static function step_revision(array $o, int $left): string
    {
        $id = (int) $o['id'];
        $st = self::state($o);
        $d = self::driver($o);
        $r = $d->provision((string) $o['site_path'], (string) $st['job_id'], 'qa', []);
        $problems = [];
        if (empty($r['ok'])) {
            $problems[] = ['url' => '', 'tipo' => 'qa', 'detalle' => (string) ($r['error'] ?? 'QA no respondió')];
        } else {
            foreach (($r['data']['problemas'] ?? []) as $p) {
                $problems[] = $p;
            }
            // verificación externa: la portada responde y el HTTPS es válido
            $home = Http::request('GET', Orders::previewUrl($o, true), ['timeout' => 30, 'follow' => true, 'verify' => Settings::scheme() === 'https', 'resolve' => self::resolve($o)]);
            if ($home['status'] !== 200) {
                $problems[] = ['url' => '/', 'tipo' => 'externo', 'detalle' => 'La portada respondió HTTP ' . $home['status'] . ($home['error'] ? ' (' . $home['error'] . ')' : '')];
            } elseif (preg_match('/(Warning|Notice|Fatal error|Deprecated|Parse error):/i', $home['body'])) {
                $problems[] = ['url' => '/', 'tipo' => 'externo', 'detalle' => 'La portada muestra mensajes de PHP.'];
            } else {
                // el menú de navegación debe estar en la portada, con sus enlaces y el botón del celular
                $nav = preg_match('#<nav class="sc-nav".*?</nav>#s', $home['body'], $mm) ? $mm[0] : '';
                $links = preg_match_all('#<a [^>]*href="[^"]+"#', $nav);
                if ($nav === '' || $links < 3 || !str_contains($home['body'], 'sc-burger')) {
                    $problems[] = ['url' => '/', 'tipo' => 'menu', 'detalle' => 'El menú de navegación no aparece completo (enlaces: ' . (int) $links . ').'];
                }
            }
        }
        Orders::set($id, ['qa_result' => json_encode(['ok' => !$problems, 'problemas' => $problems, 'intento' => (int) ($st['qa_attempt'] ?? 0) + 1], JSON_UNESCAPED_UNICODE)]);
        if (!$problems) {
            return 'done';
        }
        $att = (int) ($st['qa_attempt'] ?? 0);
        if ($att < 1) {
            // reintento automático UNA vez con textos y estructura base
            $st['qa_attempt'] = 1;
            $st['force_base'] = true;
            self::saveState($id, $st);
            $d->provision((string) $o['site_path'], (string) $st['job_id'], 'reset', []);
            Db::update('build_steps', ['status' => 'pending', 'attempts' => 0], 'order_id=? AND step_key IN (?,?,?,?,?,?)', [$id, 'textos', 'configurar', 'medios', 'paginas', 'tienda', 'finalizar']);
            Db::update('build_steps', ['status' => 'pending', 'attempts' => 0], 'order_id=? AND step_key=?', [$id, 'revision']);
            Orders::set($id, ['build_attempts' => 1]);
            Log::audit('qa_reintento', json_encode($problems, JSON_UNESCAPED_UNICODE), $id);
            return 'stop_retry';
        }
        throw new ProvisionException('Problemas detectados: ' . self::summarize($problems), false);
    }

    private static function summarize(array $p): string
    {
        $out = [];
        foreach (array_slice($p, 0, 6) as $x) {
            $out[] = trim(($x['tipo'] ?? '') . ' ' . ($x['url'] ?? '') . ' ' . ($x['detalle'] ?? ''));
        }
        return implode('; ', $out);
    }

    // ---------------------------------------------------------------- final
    private static function finish(array $o): void
    {
        $id = (int) $o['id'];
        $st = self::state($o);
        try {
            $d = self::driver($o);
            if (!empty($st['job_id'])) {
                $d->removeJob((string) $o['site_path'], (string) $st['job_id']);
            }
            $d->removeProvisionScript((string) $o['site_path']);
        } catch (\Throwable $e) {
            Log::error('Limpieza del trabajo: ' . $e->getMessage());
        }
        $jobs = Manifest::jobDir($id);
        if (is_dir($jobs) && Fs::inside($jobs, Files::root() . '/jobs')) {
            Fs::rmTreeSafe($jobs, [Files::root() . '/jobs']);
        }
        $demo = !empty($st['demo']) || !empty($o['is_demo']);
        $f = [
            'status' => $demo ? Orders::ST_PUBLICADA : Orders::ST_LISTA, 'build_msg' => '', 'build_lock' => null, 'is_demo' => $demo ? 1 : (int) $o['is_demo'],
            'expires_at' => $demo ? null : gmdate('Y-m-d H:i:s', time() + Settings::int('preview_days', 15) * 86400),
        ];
        if ($demo) {
            $f['published_at'] = Db::now();
        }
        // Si había un pago rechazado o similar no se toca; si ya había comprobante, vuelve a revisión de pago
        Orders::set($id, $f);
        $st = array_diff_key($st, ['waiting' => 1, 'force_base' => 1, 'regen' => 1]);
        self::saveState($id, $st);
        Log::audit('vista_lista', Orders::previewUrl($o, false), $id);
        if (!$demo) {
            Notifier::previewReady(Orders::byId($id));
        }
    }

    /** Reanuda un pedido en estado 'preparando' (por el dueño, tras AutoSSL o corrección). */
    public static function resume(int $orderId): void
    {
        $o = Orders::byId($orderId);
        if (!$o) {
            return;
        }
        $st = self::state($o);
        unset($st['ssl_started'], $st['waiting']);
        Db::update('build_steps', ['status' => 'pending', 'attempts' => 0], 'order_id=? AND status IN (?,?)', [$orderId, 'error', 'running']);
        Orders::set($orderId, ['status' => Orders::ST_CONSTRUYENDO, 'build_state' => json_encode($st), 'build_msg' => '']);
    }
}
