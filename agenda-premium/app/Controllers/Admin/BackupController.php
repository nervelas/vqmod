<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Clock;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Respaldo de la base de datos y exportación/importación de la configuración completa. */
final class BackupController extends A4Controller
{
    private const MAX_IMPORT_BYTES = 5 * 1024 * 1024;
    private const IMPORT_TTL = 1800;
    private const SECTION_LABELS = [
        'settings' => 'Ajustes', 'schedules' => 'Horarios', 'schedule_rules' => 'Reglas de horario', 'holidays' => 'Feriados', 'events' => 'Tipos de evento',
        'event_types' => 'Tipos de evento', 'custom_fields' => 'Preguntas personalizadas', 'workflows' => 'Flujos y recordatorios', 'routing_forms' => 'Formularios de enrutamiento',
        'packages' => 'Paquetes', 'coupons' => 'Cupones', 'resources' => 'Recursos y salas', 'teams' => 'Equipos', 'hosts' => 'Anfitriones', 'webhooks' => 'Webhooks',
    ];

    public function index(Request $req, array $p, array $extra = []): Response
    {
        $keep = max(1, Settings::int('backup_keep', 7));
        $res = $this->page('admin/backup/index', array_merge([
            'title' => 'Respaldo e importación',
            'backups' => $this->listBackups(),
            'keep' => $keep,
            'last' => (string) Settings::get('last_backup_at', ''),
            'ready' => class_exists('App\\Services\\BackupService'),
            'configReady' => class_exists('App\\Services\\ConfigPortabilityService'),
            'preview' => null,
            'importError' => '',
            'free' => @disk_free_space(APP_ROOT . '/storage') ?: null,
        ], $extra), ['js/admin-settings.js'], '/admin/respaldo');
        return $res;
    }

    public function create(Request $req, array $p): Response
    {
        if (!class_exists('App\\Services\\BackupService')) {
            return $this->fail($req, 'El servicio de respaldos todavía no está disponible.', '/admin/respaldo');
        }
        if (!RateLimiter::hit('a4backup:' . (int) (Auth::user()['id'] ?? 0), 6, 600)) {
            return $this->fail($req, 'Ya creaste varios respaldos seguidos. Espera unos minutos.', '/admin/respaldo');
        }
        try {
            @set_time_limit(300);
            $path = \App\Services\BackupService::create();
            $name = basename($path);
            Settings::set('last_backup_at', Clock::utc());
            $keep = max(1, Settings::int('backup_keep', 7));
            \App\Services\BackupService::prune($keep);
            $this->audit('backup.create', $name, 'backup', $name);
            $this->flash('success', 'Listo, creamos el respaldo ' . $name . '. Descárgalo y guárdalo también fuera de tu servidor.');
        } catch (\Throwable $e) {
            Logger::error('No se pudo crear el respaldo', $e);
            $this->flash('error', 'No pudimos crear el respaldo. Revisa que la carpeta storage/backups tenga permisos de escritura y que haya espacio en disco.');
        }
        return $this->redirect('/admin/respaldo');
    }

    public function download(Request $req, array $p): Response
    {
        $path = $this->resolve((string) ($p['name'] ?? ''));
        if ($path === null) {
            $this->abort(404);
        }
        $name = basename($path);
        $this->audit('backup.download', $name, 'backup', $name);
        $mime = substr($name, -3) === '.gz' ? 'application/gzip' : 'application/sql';
        $r = Response::file($path, $mime, $name, false);
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    public function delete(Request $req, array $p): Response
    {
        $path = $this->resolve((string) ($p['name'] ?? ''));
        if ($path === null) {
            return $this->fail($req, 'No encontramos ese respaldo.', '/admin/respaldo', 404);
        }
        $name = basename($path);
        @unlink($path);
        $this->audit('backup.delete', $name, 'backup', $name);
        $this->flash('success', 'Eliminamos el respaldo ' . $name . '.');
        return $this->redirect('/admin/respaldo');
    }

    public function keep(Request $req, array $p): Response
    {
        $n = Validator::intRange($req->str('keep', 4), 1, 60);
        if ($n === null) {
            return $this->fail($req, 'Indica cuántos respaldos conservar (entre 1 y 60).', '/admin/respaldo');
        }
        Settings::set('backup_keep', (string) $n);
        $before = count($this->listBackups());
        if (class_exists('App\\Services\\BackupService')) {
            try {
                \App\Services\BackupService::prune($n);
            } catch (\Throwable $e) {
                Logger::error('No se pudo depurar respaldos', $e);
            }
        }
        $removed = max(0, $before - count($this->listBackups()));
        $this->audit('backup.prune', 'Conservar ' . $n . '; eliminados ' . $removed, 'backup');
        $this->flash('success', 'Conservaremos los últimos ' . $n . ' respaldos' . ($removed > 0 ? ' (eliminamos ' . $removed . ' antiguo' . ($removed === 1 ? '' : 's') . ')' : '') . '.');
        return $this->redirect('/admin/respaldo');
    }

    public function exportConfig(Request $req, array $p): Response
    {
        if (!class_exists('App\\Services\\ConfigPortabilityService')) {
            return $this->fail($req, 'El servicio de exportación todavía no está disponible.', '/admin/respaldo');
        }
        try {
            $data = (array) \App\Services\ConfigPortabilityService::export();
        } catch (\Throwable $e) {
            Logger::error('No se pudo exportar la configuración', $e);
            return $this->fail($req, 'No pudimos exportar la configuración.', '/admin/respaldo');
        }
        $data = $this->stripSecrets($data);
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->audit('config.export', strlen($json) . ' bytes', 'config');
        $slug = Str::slug((string) Settings::get('business_name', 'agenda'), 30);
        return Response::download($json, 'application/json', 'configuracion-' . $slug . '-' . $this->stamp() . '.json');
    }

    /** Paso 1 de la importación: sube el JSON y muestra qué cambiaría. */
    public function importPreview(Request $req, array $p): Response
    {
        if (!class_exists('App\\Services\\ConfigPortabilityService')) {
            return $this->fail($req, 'El servicio de importación todavía no está disponible.', '/admin/respaldo');
        }
        $f = $req->files['file'] ?? null;
        $err = '';
        $data = null;
        if (!is_array($f) || !isset($f['error']) || is_array($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $err = 'Elige el archivo de configuración (.json) que exportaste.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $err = 'No pudimos recibir el archivo. Inténtalo de nuevo.';
        } elseif ((int) filesize((string) $f['tmp_name']) > self::MAX_IMPORT_BYTES) {
            $err = 'El archivo es demasiado grande (máximo 5 MB).';
        } elseif (strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION)) !== 'json') {
            $err = 'El archivo debe ser un .json exportado desde Agenda Premium.';
        } else {
            $raw = (string) file_get_contents((string) $f['tmp_name']);
            $raw = ltrim($raw, "\xEF\xBB\xBF");
            $data = json_decode($raw, true);
            if (!is_array($data) || !$data || array_keys($data) === range(0, count($data) - 1)) {
                $err = 'El archivo no tiene el formato esperado. Usa uno exportado desde “Exportar configuración”.';
                $data = null;
            } elseif (!isset($data['settings']) || !is_array($data['settings'])) {
                $err = 'Este archivo no parece una configuración de Agenda Premium (falta la sección de ajustes).';
                $data = null;
            }
        }
        if ($data === null) {
            $res = $this->index($req, [], ['importError' => $err]);
            $res->status = 422;
            return $res;
        }
        $data = $this->stripSecrets($data);
        $token = Str::token(16);
        $dir = APP_ROOT . '/storage/imports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->purgeImports();
        if (@file_put_contents($dir . '/import-' . $token . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            return $this->fail($req, 'No pudimos guardar el archivo temporalmente (permisos de storage).', '/admin/respaldo');
        }
        Session::set('a4_import', ['token' => $token, 'at' => Clock::now(), 'name' => Str::clean((string) ($f['name'] ?? 'configuracion.json'), 120)]);
        return $this->index($req, [], ['preview' => $this->diff($data), 'importToken' => $token, 'importName' => Str::clean((string) ($f['name'] ?? ''), 120)]);
    }

    /** Paso 2: aplica la importación ya previsualizada. */
    public function importApply(Request $req, array $p): Response
    {
        $st = Session::get('a4_import');
        $token = $req->str('token', 32);
        if (!is_array($st) || !hash_equals((string) ($st['token'] ?? ''), $token) || !preg_match('/^[a-f0-9]{32}$/', $token) || Clock::now() - (int) $st['at'] > self::IMPORT_TTL) {
            return $this->fail($req, 'La vista previa caducó. Sube el archivo de nuevo.', '/admin/respaldo');
        }
        $file = APP_ROOT . '/storage/imports/import-' . $token . '.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data)) {
            return $this->fail($req, 'No encontramos el archivo de la vista previa. Súbelo de nuevo.', '/admin/respaldo');
        }
        $replace = $req->str('mode', 12) === 'replace';
        if ($replace && mb_strtoupper($req->str('confirm', 20)) !== 'REEMPLAZAR') {
            $res = $this->index($req, [], ['preview' => $this->diff($data), 'importToken' => $token, 'importName' => (string) ($st['name'] ?? ''), 'importError' => 'Para reemplazar escribe la palabra REEMPLAZAR en el cuadro de confirmación.']);
            $res->status = 422;
            return $res;
        }
        try {
            @set_time_limit(300);
            $result = (array) \App\Services\ConfigPortabilityService::import($data, $replace);
        } catch (\Throwable $e) {
            Logger::error('Importación de configuración falló', $e);
            $msg = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'La importación falló y no se aplicó ningún cambio.';
            @unlink($file);
            Session::forget('a4_import');
            $this->flash('error', $msg);
            return $this->redirect('/admin/respaldo');
        }
        @unlink($file);
        Session::forget('a4_import');
        Settings::flush();
        Cache::clearAll();
        Cache::bumpAvailability();
        $this->audit('config.import', ($replace ? 'Reemplazar' : 'Combinar') . ': ' . $this->resultLine($result), 'config');
        $this->flash('success', 'Importamos la configuración (' . ($replace ? 'reemplazando lo existente' : 'combinando con lo existente') . '). ' . $this->resultLine($result));
        return $this->redirect('/admin/respaldo');
    }

    // ---- ayudas ----

    /** @return array<int,array{name:string,size:int,at:string}> */
    private function listBackups(): array
    {
        if (!class_exists('App\\Services\\BackupService')) {
            return [];
        }
        $out = [];
        try {
            foreach ((array) \App\Services\BackupService::list() as $item) {
                $name = is_array($item) ? (string) ($item['name'] ?? (isset($item['path']) ? basename((string) $item['path']) : '')) : basename((string) $item);
                if (!preg_match('/^[A-Za-z0-9._\-]{1,120}$/', $name)) {
                    continue;
                }
                $path = \App\Services\BackupService::path($name);
                $size = is_array($item) && isset($item['size']) ? (int) $item['size'] : ($path && is_file($path) ? (int) filesize($path) : 0);
                $ts = 0;
                if (is_array($item)) {
                    foreach (['created_at', 'modified', 'mtime', 'time', 'date', 'at'] as $k) {
                        if (isset($item[$k])) {
                            $ts = is_numeric($item[$k]) ? (int) $item[$k] : (int) strtotime((string) $item[$k] . (strpos((string) $item[$k], 'UTC') === false && !preg_match('/[zZ+]/', (string) $item[$k]) ? ' UTC' : ''));
                            break;
                        }
                    }
                }
                if ($ts <= 0 && $path && is_file($path)) {
                    $ts = (int) filemtime($path);
                }
                $out[] = ['name' => $name, 'size' => $size, 'ts' => $ts, 'at' => $ts > 0 ? Tz::fromTs($ts) : ''];
            }
        } catch (\Throwable $e) {
            Logger::error('No se pudo listar respaldos', $e);
        }
        usort($out, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
        return $out;
    }

    private function resolve(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._\-]{1,120}$/', $name) || strpos($name, '..') !== false || !class_exists('App\\Services\\BackupService')) {
            return null;
        }
        $path = \App\Services\BackupService::path($name);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $real = realpath($path);
        $dir = realpath(APP_ROOT . '/storage/backups');
        if ($real === false || $dir === false || strncmp($real, $dir . DIRECTORY_SEPARATOR, strlen($dir) + 1) !== 0) {
            return null;
        }
        return $real;
    }

    /** Quita cualquier valor sensible del arreglo (defensa extra: el servicio ya no debe incluirlos). */
    private function stripSecrets(array $data): array
    {
        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach (array_keys($data['settings']) as $k) {
                if (in_array($k, Settings::SECRET_KEYS, true) || preg_match('/(pass|secret|token)$/i', (string) $k)) {
                    unset($data['settings'][$k]);
                }
            }
        }
        return $data;
    }

    private function diff(array $data): array
    {
        $current = [];
        try {
            $current = (array) \App\Services\ConfigPortabilityService::export();
        } catch (\Throwable $e) {
            $current = [];
        }
        $curSettings = isset($current['settings']) && is_array($current['settings']) ? $current['settings'] : Settings::all();
        $changed = [];
        $added = [];
        $same = 0;
        foreach ((array) $data['settings'] as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $new = (string) $v;
            $k = (string) $k;
            if (!array_key_exists($k, $curSettings)) {
                $added[] = ['key' => $k, 'new' => $this->short($new)];
            } elseif ((string) $curSettings[$k] !== $new) {
                $changed[] = ['key' => $k, 'old' => $this->short((string) $curSettings[$k]), 'new' => $this->short($new)];
            } else {
                $same++;
            }
        }
        $sections = [];
        foreach ($data as $k => $v) {
            if ($k === 'settings' || !is_array($v)) {
                continue;
            }
            $sections[] = [
                'label' => self::SECTION_LABELS[$k] ?? ucfirst(str_replace('_', ' ', (string) $k)),
                'incoming' => count($v),
                'current' => isset($current[$k]) && is_array($current[$k]) ? count($current[$k]) : 0,
            ];
        }
        $meta = [];
        foreach (['version', 'app_version', 'exported_at', 'business', 'format'] as $m) {
            if (isset($data[$m]) && is_scalar($data[$m])) {
                $meta[$m] = Str::clean((string) $data[$m], 80);
            }
        }
        return ['changed' => $changed, 'added' => $added, 'same' => $same, 'sections' => $sections, 'meta' => $meta];
    }

    private function short(string $v): string
    {
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;
        return $v === '' ? '(vacío)' : (mb_strlen($v) > 70 ? mb_substr($v, 0, 69) . '…' : $v);
    }

    private function resultLine(array $r): string
    {
        $labels = ['created' => 'creados', 'updated' => 'actualizados', 'skipped' => 'omitidos'];
        $parts = [];
        foreach ($labels as $k => $label) {
            if (isset($r[$k]) && is_array($r[$k]) && $r[$k]) {
                $bits = [];
                foreach ($r[$k] as $name => $n) {
                    if (is_int($n)) {
                        $bits[] = str_replace('_', ' ', (string) $name) . ' ' . $n;
                    }
                }
                if ($bits) {
                    $parts[] = $label . ': ' . implode(', ', array_slice($bits, 0, 8));
                }
            }
        }
        return implode('; ', $parts);
    }

    private function purgeImports(): void
    {
        foreach (glob(APP_ROOT . '/storage/imports/import-*.json') ?: [] as $f) {
            if (filemtime($f) < Clock::now() - self::IMPORT_TTL) {
                @unlink($f);
            }
        }
    }
}
