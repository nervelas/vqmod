<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Upload;
use Aurea\Core\Util;
use Aurea\Services\BookingService;

final class ClientController extends AdminController
{
    /** Condición SQL de visibilidad (IDOR): un Profesional solo ve clientes con cita con él. */
    private function visibleSql(string $alias = 'c'): array
    {
        $sp = $this->scopePro();
        return $sp ? [" AND EXISTS (SELECT 1 FROM appointments x WHERE x.client_id=$alias.id AND x.professional_id=?)", [$sp]] : ['', []];
    }

    private function client(int $id): array
    {
        $this->need('clients');
        [$vs, $vp] = $this->visibleSql();
        $c = Db::one('SELECT c.* FROM clients c WHERE c.id=?' . $vs, array_merge([$id], $vp));
        if (!$c) { $this->abort(404); }
        return $c;
    }

    public function index(): Response
    {
        $this->need('clients');
        $q = $this->req->str('q'); $filter = $this->req->str('f');
        [$vs, $vp] = $this->visibleSql();
        $where = '1=1' . $vs; $params = $vp;
        if ($q !== '') { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.tags LIKE ? OR c.nit LIKE ?)'; array_push($params, $like, $like, $like, $like, $like); }
        if ($filter === 'blocked') { $where .= ' AND c.blocked=1'; }
        if ($filter === 'noshow') { $where .= ' AND c.noshow_count>0'; }
        $total = (int)Db::val("SELECT COUNT(*) FROM clients c WHERE $where", $params);
        $pg = $this->paginate($total);
        $rows = Db::all("SELECT c.*, (SELECT COUNT(*) FROM appointments a WHERE a.client_id=c.id AND a.status IN ('confirmed','completed')) visits, (SELECT MAX(a.start_at) FROM appointments a WHERE a.client_id=c.id) last_at
            FROM clients c WHERE $where ORDER BY c.name LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        return $this->render('clients', compact('rows', 'q', 'filter', 'pg'), 'clientes', term('clients'));
    }

    public function show(): Response
    {
        $c = $this->client($this->id());
        $sp = $this->scopePro();
        $apps = Db::all('SELECT a.*, s.name service_name, p.name prof_name FROM appointments a JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id WHERE a.client_id=?' . ($sp ? ' AND a.professional_id=' . (int)$sp : '') . ' ORDER BY a.start_at DESC LIMIT 100', [$c['id']]);
        $files = Db::all("SELECT * FROM files WHERE owner_type='client' AND owner_id=? ORDER BY id DESC", [$c['id']]);
        $packages = Db::all('SELECT * FROM client_packages WHERE client_id=? ORDER BY id DESC', [$c['id']]);
        $catalog = Db::all('SELECT * FROM packages WHERE active=1 ORDER BY name');
        return $this->render('client', compact('c', 'apps', 'files', 'packages', 'catalog') + ['statuses' => BookingService::STATUSES], 'clientes', $c['name']);
    }

    public function edit(): Response
    {
        $this->need('clients');
        $id = $this->id();
        $c = $id ? $this->client($id) : ['id' => 0, 'name' => '', 'phone_cc' => '502', 'phone' => '', 'email' => '', 'nit' => '', 'tags' => '', 'notes' => '', 'blocked' => 0];
        return $this->render('client_form', compact('c'), 'clientes', $id ? __('Editar') . ' ' . $c['name'] : __('Nuevo %s', mb_strtolower(term('client'))));
    }

    public function save(): Response
    {
        $this->need('clients');
        $id = $this->req->int('id');
        if ($id) { $this->client($id); }
        $name = Util::limit($this->req->str('name'), 150);
        $ph = Util::phone($this->req->str('phone'), $this->req->str('cc', '502'));
        $email = $this->req->str('email');
        $err = null;
        if (mb_strlen($name) < 2) { $err = 'Escribe el nombre.'; }
        elseif (!$ph) { $err = 'Teléfono inválido (8 dígitos para Guatemala).'; }
        elseif ($email !== '' && !Util::isEmail($email)) { $err = 'Correo inválido.'; }
        if ($err) { $this->fail($err); return $this->redirect($id ? '/admin/clientes/' . $id . '/editar' : '/admin/clientes/nuevo'); }
        $d = ['name' => $name, 'phone_cc' => $ph[0], 'phone' => $ph[1], 'email' => $email, 'nit' => strtoupper(preg_replace('/[^0-9Kk\-]/', '', $this->req->str('nit')) ?? ''),
            'tags' => Util::limit($this->req->str('tags'), 255), 'notes' => Util::limit($this->req->str('notes'), 5000)];
        if (Auth::role() !== 'professional') { $d['blocked'] = $this->req->int('blocked') ? 1 : 0; }
        if ($id) { Db::update('clients', $id, $d); }
        else { $id = Db::insert('clients', $d + ['consent_at' => date('Y-m-d H:i:s'), 'consent_version' => 'panel', 'created_at' => date('Y-m-d H:i:s')]); }
        $this->audit('client_saved', 'client', $id);
        $this->ok('Guardado.');
        return $this->redirect('/admin/clientes/' . $id);
    }

    public function note(): Response
    {
        $c = $this->client($this->id());
        Db::update('clients', (int)$c['id'], ['notes' => Util::limit($this->req->str('notes'), 5000), 'tags' => Util::limit($this->req->str('tags'), 255)]);
        $this->ok('Notas guardadas.');
        return $this->redirect('/admin/clientes/' . $c['id']);
    }

    public function block(): Response
    {
        if (Auth::role() === 'professional') { $this->abort(403); }
        $c = $this->client($this->id());
        $to = (int)$c['blocked'] ? 0 : 1;
        Db::update('clients', (int)$c['id'], ['blocked' => $to]);
        $this->audit($to ? 'client_blocked' : 'client_unblocked', 'client', (int)$c['id']);
        $this->ok($to ? 'Cliente bloqueado: no podrá reservar en línea.' : 'Cliente desbloqueado.');
        return $this->redirect('/admin/clientes/' . $c['id']);
    }

    public function upload(): Response
    {
        $c = $this->client($this->id());
        try {
            if (empty($_FILES['file']['name'])) { throw new \RuntimeException('Selecciona un archivo.'); }
            Upload::privateDoc($_FILES['file'], 'client', (int)$c['id'], (int)$this->user['id']);
            $this->audit('client_file_added', 'client', (int)$c['id']);
            $this->ok('Archivo adjuntado.');
        } catch (\RuntimeException $e) { $this->fail($e->getMessage()); }
        return $this->redirect('/admin/clientes/' . $c['id']);
    }

    /** Verifica que el usuario pueda acceder al archivo según su dueño (cliente / cita / pago). */
    private function authorizedFile(int $id): array
    {
        $this->need('clients');
        $f = Db::one('SELECT * FROM files WHERE id=?', [$id]);
        if (!$f) { $this->abort(404); }
        $sp = $this->scopePro();
        if ($f['owner_type'] === 'client') { $this->client((int)$f['owner_id']); }
        elseif ($f['owner_type'] === 'appointment') { $this->appointment((int)$f['owner_id']); }
        elseif ($f['owner_type'] === 'payment') {
            $p = Db::one('SELECT appointment_id FROM payments WHERE id=?', [$f['owner_id']]);
            if (!$p) { $this->abort(404); }
            $this->appointment((int)$p['appointment_id']);
        } else { $this->abort(404); }
        return $f;
    }

    public function file(): Response
    {
        $f = $this->authorizedFile($this->id());
        $path = AUREA_ROOT . '/storage/private/' . $f['stored_name'];
        if (!preg_match('/^[a-f0-9]{40}$/', (string)$f['stored_name']) || !is_file($path)) { $this->abort(404); }
        $inline = in_array($f['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
        return Response::sendFile($path, (string)$f['mime'], (string)$f['original_name'], $inline);
    }

    public function deleteFile(): Response
    {
        $f = $this->authorizedFile($this->id());
        Upload::deleteFile((int)$f['id']);
        $this->audit('file_deleted', $f['owner_type'], (int)$f['owner_id']);
        $this->ok('Archivo eliminado.');
        return $this->redirect($f['owner_type'] === 'client' ? '/admin/clientes/' . $f['owner_id'] : '/admin');
    }

    public function assignPackage(): Response
    {
        if (!Auth::can('packages_sell')) { $this->abort(403); }
        $c = $this->client($this->id());
        $p = Db::one('SELECT * FROM packages WHERE id=? AND active=1', [$this->req->int('package_id')]);
        if (!$p) { $this->fail('Paquete inválido.'); return $this->redirect('/admin/clientes/' . $c['id']); }
        Db::insert('client_packages', ['client_id' => $c['id'], 'package_id' => $p['id'], 'name' => $p['name'], 'service_id' => $p['service_id'], 'sessions_total' => $p['sessions'], 'sessions_used' => 0,
            'price' => $p['price'], 'expires_at' => (int)$p['validity_days'] > 0 ? date('Y-m-d', strtotime('+' . (int)$p['validity_days'] . ' days')) : null, 'created_at' => date('Y-m-d H:i:s')]);
        $this->audit('package_sold', 'client', (int)$c['id'], $p['name']);
        $this->ok('Paquete asignado.');
        return $this->redirect('/admin/clientes/' . $c['id']);
    }

    /** Exportación de datos personales de un cliente (derecho de acceso). */
    public function exportData(): Response
    {
        $this->need('admin');
        $c = $this->client($this->id());
        $apps = Db::all('SELECT id,start_at,end_at,status,total,payment_status,client_note,internal_note,created_at FROM appointments WHERE client_id=?', [$c['id']]);
        foreach ($apps as &$a) { $a['respuestas'] = Db::all('SELECT label,value FROM appointment_answers WHERE appointment_id=?', [$a['id']]); $a['pagos'] = Db::all('SELECT amount,method,status,paid_at FROM payments WHERE appointment_id=?', [$a['id']]); }
        unset($a);
        $out = ['cliente' => $c, 'citas' => $apps, 'paquetes' => Db::all('SELECT name,sessions_total,sessions_used,expires_at FROM client_packages WHERE client_id=?', [$c['id']]),
            'archivos' => Db::all("SELECT original_name,mime,size,created_at FROM files WHERE owner_type='client' AND owner_id=?", [$c['id']]), 'exportado' => date('c')];
        $this->audit('client_exported', 'client', (int)$c['id']);
        return Response::download(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', 'datos-cliente-' . $c['id'] . '.json', 'application/json');
    }

    /** Eliminación definitiva de los datos de un cliente (derecho de supresión). */
    public function delete(): Response
    {
        $this->need('admin');
        $c = $this->client($this->id());
        foreach (Db::all("SELECT id FROM files WHERE (owner_type='client' AND owner_id=?) OR (owner_type='appointment' AND owner_id IN (SELECT id FROM appointments WHERE client_id=?))
            OR (owner_type='payment' AND owner_id IN (SELECT p.id FROM payments p JOIN appointments a ON a.id=p.appointment_id WHERE a.client_id=?))", [$c['id'], $c['id'], $c['id']]) as $f) { Upload::deleteFile((int)$f['id']); }
        Db::exec('DELETE FROM waitlist WHERE phone_cc=? AND phone=?', [$c['phone_cc'], $c['phone']]);
        Db::exec('DELETE FROM notifications_queue WHERE appointment_id IN (SELECT id FROM appointments WHERE client_id=?)', [$c['id']]);
        Db::delete('clients', (int)$c['id']);
        $this->audit('client_deleted', 'client', (int)$c['id'], 'Eliminación de datos personales');
        $this->ok('Se eliminaron definitivamente los datos del cliente y su historial.');
        return $this->redirect('/admin/clientes');
    }

    public function export(): Response
    {
        $this->need('clients');
        [$vs, $vp] = $this->visibleSql();
        $rows = Db::all('SELECT c.name, c.phone_cc, c.phone, c.email, c.nit, c.tags, c.noshow_count, c.blocked, c.created_at FROM clients c WHERE 1=1' . $vs . ' ORDER BY c.name', $vp);
        $out = array_map(static fn($r) => [$r['name'], '+' . $r['phone_cc'] . ' ' . $r['phone'], $r['email'], $r['nit'], $r['tags'], $r['noshow_count'], $r['blocked'] ? 'Sí' : 'No', fdate($r['created_at'])], $rows);
        return Response::download(Util::csv(['Nombre', 'Teléfono', 'Correo', 'NIT', 'Etiquetas', 'No asistió', 'Bloqueado', 'Creado'], $out), 'clientes-' . date('Ymd') . '.csv', 'text/csv; charset=utf-8');
    }

    public function import(): Response
    {
        $this->need('admin');
        $f = $_FILES['csv'] ?? null;
        if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int)$f['size'] > 2 * 1024 * 1024) { $this->fail('Sube un archivo CSV de hasta 2 MB.'); return $this->redirect('/admin/clientes'); }
        if (!preg_match('/\.csv$/i', (string)$f['name']) || !in_array(Upload::mime((string)$f['tmp_name']), ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream'], true)) { $this->fail('El archivo debe ser un CSV de texto.'); return $this->redirect('/admin/clientes'); }
        $h = fopen((string)$f['tmp_name'], 'rb');
        $first = (string)fgets($h);
        rewind($h);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $n = 0; $skip = 0; $row = 0;
        while (($r = fgetcsv($h, 0, $delim)) !== false) {
            $row++;
            if ($row === 1) { $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$r[0]); if (preg_match('/nombre|name/i', (string)$r[0])) { continue; } }
            if (count($r) < 2) { $skip++; continue; }
            $name = Util::limit((string)$r[0], 150); $ph = Util::phone((string)$r[1]); $email = trim((string)($r[2] ?? ''));
            if (mb_strlen($name) < 2 || !$ph || ($email !== '' && !Util::isEmail($email))) { $skip++; continue; }
            if (Db::val('SELECT id FROM clients WHERE phone_cc=? AND phone=?', [$ph[0], $ph[1]])) { $skip++; continue; }
            Db::insert('clients', ['name' => $name, 'phone_cc' => $ph[0], 'phone' => $ph[1], 'email' => $email, 'nit' => strtoupper(preg_replace('/[^0-9Kk\-]/', '', (string)($r[3] ?? '')) ?? ''),
                'tags' => Util::limit((string)($r[4] ?? ''), 255), 'consent_version' => 'importado', 'created_at' => date('Y-m-d H:i:s')]);
            $n++;
            if ($n >= 5000) { break; }
        }
        fclose($h);
        $this->audit('clients_imported', 'client', null, "$n importados, $skip omitidos");
        $this->ok("Importación lista: $n nuevos, $skip omitidos (duplicados o inválidos).");
        return $this->redirect('/admin/clientes');
    }
}
