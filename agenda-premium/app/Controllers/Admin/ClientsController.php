<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Core\Validator;

/** Clientes: fichas, notas, etiquetas, archivos, importar/exportar, fusionar y privacidad. */
final class ClientsController extends Controller
{
    private const PER_PAGE = 25;
    private const IMPORT_MAX_ROWS = 2000;

    /** Acciones que un anfitrión (rol limitado) no puede hacer. */
    private function denyHost(): void
    {
        if (Auth::scopedHostId() !== null) {
            throw new HttpException(403);
        }
    }

    // ------------------------------------------------------------------ Lista

    private function filters(Request $req): array
    {
        [$sql, $params] = A1Support::clientScope('c');
        $sql .= ' AND c.anonymized_at IS NULL';
        $f = ['q' => $req->str('q', 80), 'etiqueta' => A1Support::cleanTag($req->str('etiqueta', 40)), 'estado' => $req->str('estado', 12), 'orden' => $req->str('orden', 10)];
        if ($f['q'] !== '') {
            $like = A1Support::like($f['q']);
            $sql .= " AND (c.name LIKE ? ESCAPE '|' OR c.email LIKE ? ESCAPE '|' OR c.phone LIKE ? ESCAPE '|' OR c.nit LIKE ? ESCAPE '|'";
            array_push($params, $like, $like, $like, $like);
            $digits = preg_replace('/\D+/', '', $f['q']) ?? '';
            if (strlen($digits) >= 4) {
                $sql .= " OR c.phone LIKE ? ESCAPE '|'";
                $params[] = A1Support::like($digits);
            }
            $sql .= ')';
        }
        if ($f['etiqueta'] !== '') {
            $sql .= " AND CONCAT(',', REPLACE(COALESCE(c.tags, ''), ', ', ','), ',') LIKE ? ESCAPE '|'";
            $params[] = A1Support::like(',' . $f['etiqueta'] . ',');
        }
        if ($f['estado'] === 'bloqueados') {
            $sql .= ' AND c.blocked = 1';
        } elseif ($f['estado'] === 'inasistencias') {
            $sql .= ' AND c.noshow_count > 0';
        } else {
            $f['estado'] = '';
        }
        if (!in_array($f['orden'], ['nombre', 'reciente', 'citas'], true)) {
            $f['orden'] = 'nombre';
        }
        return [$sql, $params, $f];
    }

    private function countsSql(): array
    {
        [$bs, $bp] = A1Support::bookingScope('bb');
        return [
            "(SELECT COUNT(*) FROM bookings bb WHERE bb.client_id = c.id AND {$bs}) AS n_bookings, (SELECT MAX(bb.starts_at) FROM bookings bb WHERE bb.client_id = c.id AND bb.status IN ('confirmed','completed') AND {$bs}) AS last_at",
            array_merge($bp, $bp),
        ];
    }

    public function index(Request $req, array $p): Response
    {
        [$where, $params, $f] = $this->filters($req);
        [$cols, $cp] = $this->countsSql();
        $total = (int) Db::val('SELECT COUNT(*) FROM clients c WHERE ' . $where, $params);
        [$offset, $pages, $page] = A1Support::pages($total, $req->int('pagina', 1), self::PER_PAGE);
        $order = ['nombre' => 'c.name ASC', 'reciente' => 'c.created_at DESC', 'citas' => 'n_bookings DESC, c.name ASC'][$f['orden']];
        $rows = Db::all("SELECT c.id, c.name, c.email, c.phone, c.tags, c.noshow_count, c.blocked, c.created_at, {$cols} FROM clients c WHERE {$where} ORDER BY {$order} LIMIT " . self::PER_PAGE . ' OFFSET ' . $offset, array_merge($cp, $params));
        $tags = [];
        foreach (Db::col("SELECT tags FROM clients WHERE tags IS NOT NULL AND tags <> '' AND anonymized_at IS NULL LIMIT 5000") as $t) {
            foreach (A1Support::tagList($t) as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
        arsort($tags);
        return $this->view('admin/clients/index', [
            'title' => 'Clientes',
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'f' => $f,
            'query' => array_filter($f, static fn ($v): bool => $v !== ''),
            'tags' => array_slice($tags, 0, 20, true),
            'tz' => Settings::tz(),
            'isHost' => Auth::scopedHostId() !== null,
        ], 'layouts/admin');
    }

    public function export(Request $req, array $p): Response
    {
        [$where, $params] = $this->filters($req);
        [$cols, $cp] = $this->countsSql();
        $rows = Db::all("SELECT c.*, {$cols} FROM clients c WHERE {$where} ORDER BY c.name LIMIT 20000", array_merge($cp, $params));
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['name'], $r['email'], $r['phone'] ? '+' . $r['phone'] : '', $r['nit'], $r['tags'], $r['source'], $r['noshow_count'], $r['blocked'] ? 'Sí' : 'No', $r['n_bookings'], Tz::format($r['created_at'], Settings::tz(), 'd/m/Y')];
        }
        Auth::audit('export_clients', 'client', null, count($out) . ' clientes exportados a CSV');
        return Response::download(A1Support::csv(['Nombre', 'Correo', 'Teléfono', 'NIT', 'Etiquetas', 'Origen', 'Inasistencias', 'Bloqueado', 'Citas', 'Creado'], $out), 'text/csv', 'clientes-' . A1Support::today() . '.csv');
    }

    // ------------------------------------------------------------------ Alta y edición

    public function createForm(Request $req, array $p): Response
    {
        $this->denyHost();
        return $this->form(null, ['name' => '', 'email' => '', 'phone' => '', 'nit' => '', 'tags' => '', 'source' => '', 'timezone' => ''], '');
    }

    public function editForm(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        return $this->form($c, $c, '');
    }

    private function form(?array $client, array $v, string $error, int $status = 200): Response
    {
        $r = $this->view('admin/clients/form', ['title' => $client ? 'Editar cliente' : 'Nuevo cliente', 'client' => $client, 'v' => $v, 'error' => $error, 'zones' => Tz::list(), 'bizTz' => Settings::tz()], 'layouts/admin');
        $r->status = $status;
        return $r;
    }

    /** @return array{0:?array,1:string} datos limpios o mensaje de error */
    private function readForm(Request $req, ?int $ignoreId): array
    {
        $d = [
            'name' => $req->str('name', 160), 'email' => strtolower($req->str('email', 190)), 'phone' => $req->str('phone', 30), 'nit' => $req->str('nit', 30),
            'tags' => implode(', ', A1Support::tagList($req->str('tags', 255))), 'source' => $req->str('source', 190), 'timezone' => $req->str('timezone', 64),
        ];
        if ($d['name'] === '') {
            return [$d, 'Escribe el nombre de la persona.'];
        }
        if ($d['email'] !== '' && !Validator::email($d['email'])) {
            return [$d, 'El correo no parece válido.'];
        }
        $phone = null;
        if ($d['phone'] !== '') {
            $phone = Str::phone($d['phone'], (string) Settings::get('phone_cc', '502'));
            if ($phone === null) {
                return [$d, 'El teléfono no es válido. Usa 8 dígitos o escríbelo con código de país.'];
            }
        }
        if ($d['timezone'] !== '' && !Tz::valid($d['timezone'])) {
            return [$d, 'La zona horaria no es válida.'];
        }
        $ign = $ignoreId ?? 0;
        if ($d['email'] !== '') {
            $dup = Db::one('SELECT id, name FROM clients WHERE email = ? AND id <> ? AND anonymized_at IS NULL LIMIT 1', [$d['email'], $ign]);
            if ($dup) {
                return [$d, 'Ya existe un cliente con ese correo (' . $dup['name'] . '). Búscalo en la lista o usa «Fusionar» si es la misma persona.'];
            }
        }
        if ($phone !== null) {
            $dup = Db::one('SELECT id, name FROM clients WHERE phone = ? AND id <> ? AND anonymized_at IS NULL LIMIT 1', [$phone, $ign]);
            if ($dup) {
                return [$d, 'Ya existe un cliente con ese teléfono (' . $dup['name'] . '). Búscalo en la lista o usa «Fusionar» si es la misma persona.'];
            }
        }
        $d['phone'] = $phone ?? '';
        return [$d, ''];
    }

    public function create(Request $req, array $p): Response
    {
        $this->denyHost();
        [$d, $err] = $this->readForm($req, null);
        if ($err !== '') {
            return $this->form(null, $d, $err, 422);
        }
        $now = Clock::utc();
        $id = Db::insert('clients', [
            'name' => $d['name'], 'email' => $d['email'] ?: null, 'phone' => $d['phone'] ?: null, 'nit' => $d['nit'] ?: null, 'tags' => $d['tags'] ?: null,
            'source' => $d['source'] ?: 'Alta manual', 'timezone' => $d['timezone'] ?: null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Auth::audit('client_create', 'client', $id, 'Cliente creado: ' . $d['name']);
        $this->flash('success', 'Cliente creado.');
        return $this->redirect('/admin/clientes/' . $id);
    }

    public function update(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        [$d, $err] = $this->readForm($req, (int) $c['id']);
        if ($err !== '') {
            return $this->form($c, $d, $err, 422);
        }
        Db::update('clients', [
            'name' => $d['name'], 'email' => $d['email'] ?: null, 'phone' => $d['phone'] ?: null, 'nit' => $d['nit'] ?: null, 'tags' => $d['tags'] ?: null,
            'source' => $d['source'] ?: null, 'timezone' => $d['timezone'] ?: null, 'updated_at' => Clock::utc(),
        ], 'id = ?', [$c['id']]);
        Auth::audit('client_update', 'client', (int) $c['id'], 'Ficha actualizada');
        $this->flash('success', 'Ficha actualizada.');
        return $this->redirect('/admin/clientes/' . (int) $c['id']);
    }

    // ------------------------------------------------------------------ Ficha

    public function show(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        $id = (int) $c['id'];
        $isHost = Auth::scopedHostId() !== null;
        [$bs, $bp] = A1Support::bookingScope('b');
        $bookings = Db::all('SELECT b.id, b.status, b.starts_at, b.duration, b.total, b.utm_source, b.utm_medium, b.utm_campaign, b.referrer_host, b.created_via, b.created_at, e.name AS event_name, h.name AS host_name
                             FROM bookings b JOIN event_types e ON e.id = b.event_type_id JOIN hosts h ON h.id = b.host_id
                             WHERE b.client_id = ? AND ' . $bs . ' ORDER BY b.starts_at DESC LIMIT 100', array_merge([$id], $bp));
        $ids = array_map(static fn (array $b): int => (int) $b['id'], $bookings);
        $answers = $ids ? Db::all('SELECT ba.booking_id, ba.label, ba.value FROM booking_answers ba WHERE ba.booking_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND ba.value IS NOT NULL AND ba.value <> '' ORDER BY ba.booking_id DESC, ba.id LIMIT 80", $ids) : [];
        $first = $bookings ? $bookings[count($bookings) - 1] : null;
        $canPay = Auth::can('payments');
        $packages = $canPay ? Db::all('SELECT cp.id, cp.remaining, cp.expires_at, cp.paid, cp.purchased_at, p.name, p.sessions FROM client_packages cp JOIN packages p ON p.id = cp.package_id WHERE cp.client_id = ? ORDER BY cp.purchased_at DESC', [$id]) : [];
        $payments = $canPay ? Db::all('SELECT py.id, py.amount, py.method, py.status, py.reference, py.created_at, py.booking_id FROM payments py WHERE py.client_id = ? OR py.booking_id IN (SELECT id FROM bookings WHERE client_id = ?) ORDER BY py.created_at DESC LIMIT 30', [$id, $id]) : [];
        return $this->view('admin/clients/show', [
            'title' => $c['name'],
            'c' => $c,
            'tags' => A1Support::tagList($c['tags']),
            'notes' => Db::all('SELECT n.id, n.body, n.created_at, n.user_id, u.name AS author FROM client_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.client_id = ? ORDER BY n.created_at DESC, n.id DESC', [$id]),
            'bookings' => $bookings,
            'answers' => $answers,
            'first' => $first,
            'files' => $isHost ? [] : Db::all("SELECT id, token, original_name, mime, size, created_at FROM files WHERE owner_type = 'client' AND owner_id = ? ORDER BY created_at DESC", [$id]),
            'packages' => $packages,
            'payments' => $payments,
            'consents' => Db::all('SELECT document, version, ip_trunc, created_at FROM consents WHERE client_id = ? ' . ($c['email'] ? 'OR email = ? ' : '') . 'ORDER BY created_at DESC LIMIT 30', $c['email'] ? [$id, $c['email']] : [$id]),
            'tz' => Settings::tz(),
            'isHost' => $isHost,
            'isAdmin' => Auth::role() === 'admin',
            'canPay' => $canPay,
        ], 'layouts/admin');
    }

    public function addNote(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        $body = Str::clean((string) ($req->post['body'] ?? ''), 4000);
        if ($body === '') {
            return $this->fail($req, 'Escribe la nota antes de guardarla.', '/admin/clientes/' . $c['id']);
        }
        Db::insert('client_notes', ['client_id' => $c['id'], 'user_id' => Auth::user()['id'] ?? null, 'body' => $body, 'created_at' => Clock::utc()]);
        Auth::audit('client_note', 'client', (int) $c['id'], 'Nota interna agregada');
        $this->flash('success', 'Nota guardada.');
        return $this->redirect('/admin/clientes/' . (int) $c['id'] . '#notas');
    }

    public function deleteNote(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        $n = Db::one('SELECT id, user_id FROM client_notes WHERE id = ? AND client_id = ?', [(int) $p['nid'], $c['id']]);
        if (!$n) {
            throw new HttpException(404);
        }
        if (Auth::role() !== 'admin' && (int) $n['user_id'] !== (int) Auth::user()['id']) {
            throw new HttpException(403);
        }
        Db::delete('client_notes', 'id = ?', [$n['id']]);
        Auth::audit('client_note_delete', 'client', (int) $c['id'], 'Nota eliminada');
        $this->flash('success', 'Nota eliminada.');
        return $this->redirect('/admin/clientes/' . (int) $c['id'] . '#notas');
    }

    public function tags(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        $tags = A1Support::tagList($req->str('tags', 255));
        if (count($tags) > 15) {
            return $this->fail($req, 'Usa como máximo 15 etiquetas.', '/admin/clientes/' . $c['id']);
        }
        Db::update('clients', ['tags' => $tags ? implode(', ', $tags) : null, 'updated_at' => Clock::utc()], 'id = ?', [$c['id']]);
        Auth::audit('client_tags', 'client', (int) $c['id'], 'Etiquetas: ' . implode(', ', $tags));
        $this->flash('success', 'Etiquetas actualizadas.');
        return $this->redirect('/admin/clientes/' . (int) $c['id']);
    }

    public function upload(Request $req, array $p): Response
    {
        $this->denyHost();
        $c = A1Support::client((int) $p['id']);
        if (empty($req->files['archivo']['name'])) {
            return $this->fail($req, 'Elige un archivo para subir.', '/admin/clientes/' . $c['id']);
        }
        try {
            $f = Upload::store($req->files['archivo'], ['kind' => 'client_file', 'owner_type' => 'client', 'owner_id' => (int) $c['id'], 'uploaded_by' => Auth::user()['id'] ?? null, 'max_bytes' => 8 * 1024 * 1024, 'is_public' => false]);
        } catch (\RuntimeException $e) {
            return $this->fail($req, $e->getMessage(), '/admin/clientes/' . $c['id']);
        }
        Auth::audit('client_file', 'client', (int) $c['id'], 'Archivo subido: ' . $f['original_name']);
        $this->flash('success', 'Archivo guardado en la ficha.');
        return $this->redirect('/admin/clientes/' . (int) $c['id'] . '#archivos');
    }

    public function deleteFile(Request $req, array $p): Response
    {
        $this->denyHost();
        $c = A1Support::client((int) $p['id']);
        $f = Db::one("SELECT id, original_name FROM files WHERE id = ? AND owner_type = 'client' AND owner_id = ?", [(int) $p['fid'], $c['id']]);
        if (!$f) {
            throw new HttpException(404);
        }
        Upload::delete((int) $f['id']);
        Auth::audit('client_file_delete', 'client', (int) $c['id'], 'Archivo eliminado: ' . $f['original_name']);
        $this->flash('success', 'Archivo eliminado.');
        return $this->redirect('/admin/clientes/' . (int) $c['id'] . '#archivos');
    }

    public function block(Request $req, array $p): Response
    {
        $this->denyHost();
        $c = A1Support::client((int) $p['id']);
        $block = $req->bool('blocked') ? 1 : 0;
        Db::update('clients', ['blocked' => $block, 'updated_at' => Clock::utc()], 'id = ?', [$c['id']]);
        Auth::audit($block ? 'client_block' : 'client_unblock', 'client', (int) $c['id'], $block ? 'Cliente bloqueado' : 'Cliente desbloqueado');
        $this->flash('success', $block ? 'Cliente bloqueado: no podrá reservar en línea.' : 'Cliente desbloqueado.');
        return $this->redirect('/admin/clientes/' . (int) $c['id']);
    }

    // ------------------------------------------------------------------ Privacidad

    public function exportPerson(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        if (!class_exists('App\\Services\\PrivacyService')) {
            $this->flash('warn', 'El servicio de privacidad aún no está disponible.');
            return $this->redirect('/admin/clientes/' . (int) $c['id']);
        }
        $data = \App\Services\PrivacyService::exportPerson((int) $c['id']);
        Auth::audit('client_export_data', 'client', (int) $c['id'], 'Datos de la persona exportados (JSON)');
        return Response::download((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), 'application/json', 'datos-cliente-' . (int) $c['id'] . '.json');
    }

    public function erase(Request $req, array $p): Response
    {
        $c = A1Support::client((int) $p['id']);
        $back = '/admin/clientes/' . (int) $c['id'];
        if (trim((string) ($req->post['confirm'] ?? '')) !== 'ELIMINAR') {
            return $this->fail($req, 'Para eliminar escribe la palabra ELIMINAR en mayúsculas.', $back);
        }
        $u = Auth::user();
        if (!password_verify((string) ($req->post['password'] ?? ''), (string) $u['password_hash'])) {
            return $this->fail($req, 'Tu contraseña no es correcta.', $back);
        }
        if (!class_exists('App\\Services\\PrivacyService')) {
            return $this->fail($req, 'El servicio de privacidad aún no está disponible.', $back);
        }
        try {
            \App\Services\PrivacyService::erasePerson((int) $c['id']);
        } catch (\Throwable $e) {
            Logger::error('Falló la eliminación de datos del cliente ' . $c['id'], $e);
            return $this->fail($req, 'No pudimos eliminar los datos. Ya quedó registrado; inténtalo de nuevo.', $back, 500);
        }
        Auth::audit('client_erase', 'client', (int) $c['id'], 'Datos personales eliminados (derecho de supresión)');
        $this->flash('success', 'Los datos personales fueron eliminados.');
        return $this->redirect('/admin/clientes');
    }

    // ------------------------------------------------------------------ Importar CSV

    public function importForm(Request $req, array $p): Response
    {
        $this->denyHost();
        return $this->view('admin/clients/import', ['title' => 'Importar clientes', 'preview' => null, 'error' => ''], 'layouts/admin');
    }

    private function importError(string $msg): Response
    {
        $r = $this->view('admin/clients/import', ['title' => 'Importar clientes', 'preview' => null, 'error' => $msg], 'layouts/admin');
        $r->status = 422;
        return $r;
    }

    public function importPreview(Request $req, array $p): Response
    {
        $this->denyHost();
        $f = $req->files['archivo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['tmp_name'])) {
            return $this->importError('Elige un archivo CSV para continuar.');
        }
        if ((int) $f['size'] > 2 * 1024 * 1024) {
            return $this->importError('El archivo es muy grande (máximo 2 MB).');
        }
        if (!in_array(strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            return $this->importError('Sube un archivo con extensión .csv (puedes exportarlo desde Excel).');
        }
        $raw = (string) file_get_contents((string) $f['tmp_name']);
        $parsed = self::parseCsv($raw);
        if ($parsed['error'] !== '') {
            return $this->importError($parsed['error']);
        }
        $rows = $this->classify($parsed['rows']);
        $token = Str::token(8);
        Session::set('import_token', $token);
        Session::set('import_rows', array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'nuevo' || $r['status'] === 'actualizar')));
        $counts = ['nuevo' => 0, 'actualizar' => 0, 'error' => 0, 'duplicado' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']]++;
        }
        return $this->view('admin/clients/import', ['title' => 'Importar clientes', 'preview' => ['rows' => array_slice($rows, 0, 60), 'total' => count($rows), 'counts' => $counts, 'token' => $token, 'cols' => $parsed['cols']], 'error' => ''], 'layouts/admin');
    }

    /** Lee un CSV (coma, punto y coma o tabulación; UTF-8 o Latin-1) y mapea columnas por encabezado. */
    public static function parseCsv(string $raw): array
    {
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if ($raw === '') {
            return ['rows' => [], 'cols' => [], 'error' => 'El archivo está vacío.'];
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }
        $firstLine = strtok($raw, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($firstLine, $d) > substr_count($firstLine, $delim)) {
                $delim = $d;
            }
        }
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $header = fgetcsv($fh, 0, $delim, '"', '');
        if (!$header) {
            return ['rows' => [], 'cols' => [], 'error' => 'No pudimos leer el encabezado del archivo.'];
        }
        $aliases = [
            'name' => ['nombre', 'name', 'cliente', 'nombre completo'], 'email' => ['correo', 'email', 'e-mail', 'correo electronico', 'correo electrónico'],
            'phone' => ['telefono', 'teléfono', 'phone', 'celular', 'movil', 'móvil', 'whatsapp'], 'nit' => ['nit'],
            'tags' => ['etiquetas', 'tags', 'etiqueta'], 'source' => ['origen', 'source', 'fuente'],
        ];
        $map = [];
        foreach ($header as $i => $h) {
            $h = mb_strtolower(trim((string) $h));
            foreach ($aliases as $key => $list) {
                if (in_array($h, $list, true) && !isset($map[$key])) {
                    $map[$key] = $i;
                }
            }
        }
        if (!isset($map['name'])) {
            return ['rows' => [], 'cols' => [], 'error' => 'No encontramos la columna «Nombre». La primera fila debe tener encabezados como: Nombre, Correo, Teléfono, NIT, Etiquetas.'];
        }
        $rows = [];
        $line = 1;
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $line++;
            if ($r === [null] || !array_filter($r, static fn ($x): bool => trim((string) $x) !== '')) {
                continue;
            }
            if (count($rows) >= self::IMPORT_MAX_ROWS) {
                return ['rows' => [], 'cols' => [], 'error' => 'El archivo tiene más de ' . self::IMPORT_MAX_ROWS . ' filas. Divídelo en partes más pequeñas.'];
            }
            $row = ['line' => $line];
            foreach (array_keys($aliases) as $key) {
                $row[$key] = isset($map[$key]) ? Str::clean((string) ($r[$map[$key]] ?? ''), $key === 'tags' ? 255 : 190) : '';
            }
            $rows[] = $row;
        }
        fclose($fh);
        if (!$rows) {
            return ['rows' => [], 'cols' => [], 'error' => 'El archivo no tiene filas de datos debajo del encabezado.'];
        }
        return ['rows' => $rows, 'cols' => array_keys($map), 'error' => ''];
    }

    /** Valida cada fila y decide: nuevo, actualizar (ya existe), duplicado (repetido en el archivo) o error. */
    private function classify(array $rows): array
    {
        $seen = [];
        $cc = (string) Settings::get('phone_cc', '502');
        foreach ($rows as &$r) {
            $r['status'] = 'nuevo';
            $r['msg'] = '';
            $r['email'] = strtolower($r['email']);
            $r['tags'] = implode(', ', A1Support::tagList($r['tags']));
            if ($r['name'] === '') {
                $r['status'] = 'error';
                $r['msg'] = 'Falta el nombre';
                continue;
            }
            if ($r['email'] !== '' && !Validator::email($r['email'])) {
                $r['status'] = 'error';
                $r['msg'] = 'Correo no válido';
                continue;
            }
            $phone = null;
            if ($r['phone'] !== '') {
                $phone = Str::phone($r['phone'], $cc);
                if ($phone === null) {
                    $r['status'] = 'error';
                    $r['msg'] = 'Teléfono no válido';
                    continue;
                }
            }
            $r['phone'] = $phone ?? '';
            $key = $r['email'] !== '' ? 'e:' . $r['email'] : ($r['phone'] !== '' ? 'p:' . $r['phone'] : 'n:' . mb_strtolower($r['name']));
            if (isset($seen[$key])) {
                $r['status'] = 'duplicado';
                $r['msg'] = 'Repetido en el archivo (fila ' . $seen[$key] . ')';
                continue;
            }
            $seen[$key] = $r['line'];
            $ex = $this->findExisting($r['email'], $r['phone']);
            if ($ex) {
                $r['status'] = 'actualizar';
                $r['msg'] = 'Ya existe: ' . $ex['name'];
            }
        }
        unset($r);
        return $rows;
    }

    private function findExisting(string $email, string $phone): ?array
    {
        if ($email !== '') {
            $x = Db::one('SELECT * FROM clients WHERE email = ? AND anonymized_at IS NULL ORDER BY id LIMIT 1', [$email]);
            if ($x) {
                return $x;
            }
        }
        return $phone !== '' ? Db::one('SELECT * FROM clients WHERE phone = ? AND anonymized_at IS NULL ORDER BY id LIMIT 1', [$phone]) : null;
    }

    public function importConfirm(Request $req, array $p): Response
    {
        $this->denyHost();
        $token = (string) Session::get('import_token', '');
        $rows = Session::get('import_rows', []);
        if ($token === '' || !hash_equals($token, (string) ($req->post['token'] ?? '')) || !is_array($rows)) {
            $this->flash('warn', 'La vista previa caducó. Sube el archivo de nuevo.');
            return $this->redirect('/admin/clientes/importar');
        }
        Session::forget('import_token');
        Session::forget('import_rows');
        $new = 0;
        $upd = 0;
        $now = Clock::utc();
        Db::tx(function () use ($rows, &$new, &$upd, $now): void {
            foreach ($rows as $r) {
                $ex = $this->findExisting((string) $r['email'], (string) $r['phone']);
                if ($ex) {
                    $merge = ['updated_at' => $now];
                    foreach (['email', 'phone', 'nit', 'source'] as $k) {
                        if (empty($ex[$k]) && $r[$k] !== '') {
                            $merge[$k] = $r[$k];
                        }
                    }
                    $tags = A1Support::tagList(($ex['tags'] ?? '') . ',' . $r['tags']);
                    $merge['tags'] = $tags ? implode(', ', $tags) : null;
                    Db::update('clients', $merge, 'id = ?', [$ex['id']]);
                    $upd++;
                } else {
                    Db::insert('clients', ['name' => $r['name'], 'email' => $r['email'] ?: null, 'phone' => $r['phone'] ?: null, 'nit' => $r['nit'] ?: null, 'tags' => $r['tags'] ?: null, 'source' => $r['source'] ?: 'Importación CSV', 'created_at' => $now, 'updated_at' => $now]);
                    $new++;
                }
            }
        });
        Auth::audit('client_import', 'client', null, "Importación CSV: {$new} nuevos, {$upd} actualizados");
        $this->flash('success', "Importación lista: {$new} clientes nuevos y {$upd} actualizados.");
        return $this->redirect('/admin/clientes');
    }

    // ------------------------------------------------------------------ Duplicados y fusión

    public function duplicates(Request $req, array $p): Response
    {
        $this->denyHost();
        $groups = [];
        foreach ([['phone', 'mismo teléfono'], ['email', 'mismo correo'], ['nit', 'mismo NIT']] as [$col, $why]) {
            foreach (Db::all("SELECT {$col} AS k FROM clients WHERE {$col} IS NOT NULL AND {$col} <> '' AND anonymized_at IS NULL GROUP BY {$col} HAVING COUNT(*) > 1 LIMIT 50") as $g) {
                $members = Db::all("SELECT id, name, email, phone FROM clients WHERE {$col} = ? AND anonymized_at IS NULL ORDER BY id", [$g['k']]);
                $groups[] = ['why' => $why, 'members' => $members];
            }
        }
        foreach (Db::all("SELECT LOWER(TRIM(name)) AS k FROM clients WHERE anonymized_at IS NULL AND name <> '' GROUP BY LOWER(TRIM(name)) HAVING COUNT(*) > 1 LIMIT 50") as $g) {
            $groups[] = ['why' => 'mismo nombre', 'members' => Db::all('SELECT id, name, email, phone FROM clients WHERE LOWER(TRIM(name)) = ? AND anonymized_at IS NULL ORDER BY id', [$g['k']])];
        }
        return $this->view('admin/clients/duplicates', ['title' => 'Posibles duplicados', 'groups' => $groups], 'layouts/admin');
    }

    public function mergeForm(Request $req, array $p): Response
    {
        $this->denyHost();
        $a = $req->int('a');
        $b = $req->int('b');
        $ca = $a ? Db::one('SELECT * FROM clients WHERE id = ? AND anonymized_at IS NULL', [$a]) : null;
        $cb = $b && $b !== $a ? Db::one('SELECT * FROM clients WHERE id = ? AND anonymized_at IS NULL', [$b]) : null;
        $stats = static fn (?array $c): array => $c ? [
            'bookings' => (int) Db::val('SELECT COUNT(*) FROM bookings WHERE client_id = ?', [$c['id']]),
            'notes' => (int) Db::val('SELECT COUNT(*) FROM client_notes WHERE client_id = ?', [$c['id']]),
            'files' => (int) Db::val("SELECT COUNT(*) FROM files WHERE owner_type = 'client' AND owner_id = ?", [$c['id']]),
        ] : [];
        return $this->view('admin/clients/merge', ['title' => 'Fusionar clientes', 'a' => $ca, 'b' => $cb, 'sa' => $stats($ca), 'sb' => $stats($cb), 'error' => ''], 'layouts/admin');
    }

    public function merge(Request $req, array $p): Response
    {
        $this->denyHost();
        $a = $req->int('a');
        $b = $req->int('b');
        $main = $req->int('main');
        if ($a <= 0 || $b <= 0 || $a === $b || !in_array($main, [$a, $b], true)) {
            return $this->fail($req, 'Elige dos clientes distintos y cuál será el principal.', '/admin/clientes/fusionar');
        }
        $other = $main === $a ? $b : $a;
        $keep = Db::one('SELECT * FROM clients WHERE id = ? AND anonymized_at IS NULL', [$main]);
        $drop = Db::one('SELECT * FROM clients WHERE id = ? AND anonymized_at IS NULL', [$other]);
        if (!$keep || !$drop) {
            return $this->fail($req, 'No encontramos a uno de los clientes.', '/admin/clientes/duplicados');
        }
        Db::tx(function () use ($keep, $drop): void {
            $m = (int) $keep['id'];
            $o = (int) $drop['id'];
            Db::update('bookings', ['client_id' => $m], 'client_id = ?', [$o]);
            Db::update('client_notes', ['client_id' => $m], 'client_id = ?', [$o]);
            Db::update('files', ['owner_id' => $m], "owner_type = 'client' AND owner_id = ?", [$o]);
            Db::update('payments', ['client_id' => $m], 'client_id = ?', [$o]);
            Db::update('client_packages', ['client_id' => $m], 'client_id = ?', [$o]);
            Db::update('consents', ['client_id' => $m], 'client_id = ?', [$o]);
            Db::update('message_queue', ['client_id' => $m], 'client_id = ?', [$o]);
            $upd = ['noshow_count' => (int) $keep['noshow_count'] + (int) $drop['noshow_count'], 'blocked' => ((int) $keep['blocked'] || (int) $drop['blocked']) ? 1 : 0, 'updated_at' => Clock::utc()];
            foreach (['email', 'phone', 'nit', 'source', 'timezone'] as $k) {
                if (empty($keep[$k]) && !empty($drop[$k])) {
                    $upd[$k] = $drop[$k];
                }
            }
            $tags = A1Support::tagList(($keep['tags'] ?? '') . ',' . ($drop['tags'] ?? ''));
            $upd['tags'] = $tags ? implode(', ', $tags) : null;
            // El duplicado se elimina primero para liberar correo/teléfono antes de copiarlos al principal.
            Db::delete('clients', 'id = ?', [$o]);
            Db::update('clients', $upd, 'id = ?', [$m]);
        });
        Auth::audit('client_merge', 'client', $main, 'Fusionado el cliente #' . $other . ' (' . $drop['name'] . ') en #' . $main);
        $this->flash('success', 'Clientes fusionados: todo el historial quedó en la ficha de ' . $keep['name'] . '.');
        return $this->redirect('/admin/clientes/' . $main);
    }
}
