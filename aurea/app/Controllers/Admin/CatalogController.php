<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Util;
use Aurea\Services\HolidayService;
use Aurea\Services\TemplateService;

/** CRUD genérico dirigido por app/Data/catalog.php (servicios, sedes, feriados, cupones, etc.). */
final class CatalogController extends AdminController
{
    private string $mod = '';
    private array $cfg = [];

    private function load(): void
    {
        $seg = explode('/', trim($this->req->path, '/'));
        $this->mod = (string)($seg[1] ?? '');
        $all = require AUREA_ROOT . '/app/Data/catalog.php';
        if (!isset($all[$this->mod])) { $this->abort(404); }
        $this->cfg = $all[$this->mod];
        $this->need($this->cfg['ability']);
    }

    /** Alcance por profesional para el módulo de ausencias. */
    private function rowAllowed(?array $row): bool
    {
        if ($this->mod !== 'ausencias') { return true; }
        $sc = $this->scopePro();
        return $sc === null || ($row && (int)$row['professional_id'] === $sc);
    }

    private function options(array $f): array
    {
        $o = $f[3]['options'] ?? [];
        if (!empty($f[3]['query'])) { foreach (Db::all($f[3]['query']) as $r) { $o[$r['id']] = $r['name']; } }
        return $o;
    }

    public function index(): Response
    {
        $this->load();
        $params = []; $extra = [];
        $sql = $this->cfg['sql'];
        if ($this->mod === 'feriados') {
            $year = $this->req->int('anio', (int)date('Y'));
            $params = [$year . '-01-01', $year . '-12-31'];
            $extra['year'] = $year;
        }
        if ($this->mod === 'ausencias' && $this->scopePro()) { $sql = str_replace('ORDER BY', 'WHERE t.professional_id=' . (int)$this->scopePro() . ' ORDER BY', $sql); }
        $rows = Db::all($sql, $params);
        return $this->render('catalog_list', ['cfg' => $this->cfg, 'mod' => $this->mod, 'rows' => $rows] + $extra, $this->mod, $this->cfg['title']);
    }

    public function form(): Response
    {
        $this->load();
        $id = $this->id();
        $row = $id ? Db::one('SELECT * FROM `' . $this->cfg['table'] . '` WHERE id=?', [$id]) : null;
        if ($id && (!$row || !$this->rowAllowed($row))) { $this->abort(404); }
        if (!$id && !empty($this->cfg['no_create'])) { $this->abort(404); }
        if (!$row) {
            $row = [];
            foreach ($this->cfg['fields'] as $f) { $row[$f[0]] = $f[3]['default'] ?? ''; }
        }
        if ($this->mod === 'ausencias') { foreach (['start_at', 'end_at'] as $k) { if (!empty($row[$k])) { $row[$k] = str_replace(' ', 'T', substr((string)$row[$k], 0, 16)); } } }
        $opts = [];
        foreach ($this->cfg['fields'] as $f) { if ($f[2] === 'select') { $opts[$f[0]] = $this->options($f); } }
        if ($this->mod === 'ausencias' && $this->scopePro()) { $opts['professional_id'] = array_intersect_key($opts['professional_id'], [$this->scopePro() => 1]); }
        $extra = [];
        if ($this->mod === 'servicios') { $extra['profs'] = Db::all('SELECT p.id, p.name, ps.service_id linked FROM professionals p LEFT JOIN professional_services ps ON ps.professional_id=p.id AND ps.service_id=? ORDER BY p.name', [$id]); }
        return $this->render('catalog_form', ['cfg' => $this->cfg, 'mod' => $this->mod, 'row' => $row, 'id' => $id, 'opts' => $opts] + $extra, $this->mod, ($id ? __('Editar') : __('Nuevo')) . ' · ' . $this->cfg['singular']);
    }

    public function save(): Response
    {
        $this->load();
        $id = $this->req->int('id');
        $old = $id ? Db::one('SELECT * FROM `' . $this->cfg['table'] . '` WHERE id=?', [$id]) : null;
        if ($id && (!$old || !$this->rowAllowed($old))) { $this->abort(404); }
        if (!$id && !empty($this->cfg['no_create'])) { $this->abort(404); }
        $data = []; $errors = [];
        foreach ($this->cfg['fields'] as [$k, $label, $type, $o]) {
            $raw = $this->req->post[$k] ?? null;
            $raw = is_scalar($raw) ? trim((string)$raw) : '';
            $nullable = !empty($o['null']);
            if ($type === 'bool') { $data[$k] = $this->req->int($k) ? 1 : 0; continue; }
            if ($type === 'password') { continue; }
            if ($raw === '') {
                if (!empty($o['required'])) { $errors[] = "«$label» es obligatorio."; continue; }
                $data[$k] = $nullable ? null : (in_array($type, ['int', 'decimal'], true) ? ($o['default'] ?? 0) : '');
                continue;
            }
            switch ($type) {
                case 'int': case 'decimal':
                    if (!is_numeric(str_replace(',', '.', $raw))) { $errors[] = "«$label» debe ser un número."; break; }
                    $v = $type === 'int' ? (int)$raw : round((float)str_replace(',', '.', $raw), 2);
                    if (isset($o['min']) && $v < $o['min']) { $errors[] = "«$label» debe ser al menos {$o['min']}."; break; }
                    if (isset($o['max']) && $v > $o['max']) { $errors[] = "«$label» no puede superar {$o['max']}."; break; }
                    $data[$k] = $v; break;
                case 'select':
                    $allowed = $this->options([$k, $label, $type, $o]);
                    if (!array_key_exists($raw, $allowed) && !array_key_exists((int)$raw, $allowed)) { $errors[] = "«$label»: opción inválida."; break; }
                    $data[$k] = (isset($o['query']) || is_int(array_key_first($allowed))) && ctype_digit($raw) ? (int)$raw : $raw; break;
                case 'date': if (!Util::isDate($raw)) { $errors[] = "«$label»: fecha inválida."; break; } $data[$k] = $raw; break;
                case 'time': if (!Util::isTime($raw)) { $errors[] = "«$label»: hora inválida."; break; } $data[$k] = strlen($raw) === 5 ? $raw . ':00' : $raw; break;
                case 'datetime':
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $raw)) { $errors[] = "«$label»: fecha y hora inválidas."; break; }
                    $data[$k] = str_replace('T', ' ', $raw) . ':00'; break;
                case 'email': if (!Util::isEmail($raw)) { $errors[] = "«$label»: correo inválido."; break; } $data[$k] = mb_strtolower($raw); break;
                case 'url': $u = Util::safeUrl($raw); if ($u === '') { $errors[] = "«$label»: usa una dirección http(s) válida."; break; } $data[$k] = $u; break;
                case 'color': if (!Util::isColor($raw)) { $errors[] = "«$label»: color inválido."; break; } $data[$k] = $raw; break;
                default:
                    $max = (int)($o['max'] ?? 255);
                    if (mb_strlen($raw) > $max) { $errors[] = "«$label» excede $max caracteres."; break; }
                    $data[$k] = !empty($o['upper']) ? strtoupper($raw) : $raw;
            }
        }
        if (!$errors) { $e = $this->hook($data, $id, $old); if ($e) { $errors[] = $e; } }
        if ($errors) { $this->fail($errors[0]); return $this->redirect('/admin/' . $this->mod . ($id ? '/' . $id : '/nuevo')); }
        if ($id) { Db::update($this->cfg['table'], $id, $data); }
        else { $id = Db::insert($this->cfg['table'], $data + $this->defaultsFor()); }
        $this->afterSave($id);
        $this->audit($this->mod . '_saved', $this->cfg['table'], $id);
        $this->ok('Guardado.');
        return $this->redirect('/admin/' . $this->mod);
    }

    private function defaultsFor(): array
    {
        return in_array($this->cfg['table'], ['services', 'locations', 'coupons', 'time_off', 'users'], true) ? ['created_at' => date('Y-m-d H:i:s')] : [];
    }

    /** Validaciones y transformaciones específicas por módulo. */
    private function hook(array &$d, int $id, ?array $old): ?string
    {
        switch ($this->mod) {
            case 'servicios':
                if ($d['deposit_type'] === 'percent' && $d['deposit_value'] > 100) { return 'El porcentaje de anticipo no puede superar 100.'; }
                break;
            case 'cupones':
                if (!preg_match('/^[A-Z0-9_\-]{2,40}$/', (string)$d['code'])) { return 'El código solo puede tener letras, números, guion y guion bajo.'; }
                if (Db::val('SELECT id FROM coupons WHERE code=? AND id<>?', [$d['code'], $id])) { return 'Ya existe un cupón con ese código.'; }
                if ($d['kind'] === 'percent' && $d['value'] > 100) { return 'El porcentaje no puede superar 100.'; }
                if ($d['kind'] === 'gift' && !$id && (float)$d['balance'] <= 0) { $d['balance'] = $d['value']; }
                break;
            case 'feriados':
                if ($d['kind'] === 'half' && empty($d['close_time'])) { $d['close_time'] = '12:00:00'; }
                if ($d['kind'] === 'full') { $d['close_time'] = null; }
                if (Db::val('SELECT id FROM holidays WHERE hdate=? AND name=? AND id<>?', [$d['hdate'], $d['name'], $id])) { return 'Ya existe ese feriado en esa fecha.'; }
                break;
            case 'ausencias':
                if ($d['end_at'] <= $d['start_at']) { return 'El fin debe ser posterior al inicio.'; }
                if ($this->scopePro()) { $d['professional_id'] = $this->scopePro(); }
                break;
            case 'formularios':
                if (in_array($d['ftype'], ['select', 'checkbox'], true) && $d['ftype'] === 'select' && trim((string)$d['options']) === '') { return 'Escribe las opciones de la selección.'; }
                if ($id && !empty($d['cond_field_id']) && (int)$d['cond_field_id'] === $id) { return 'Un campo no puede depender de sí mismo.'; }
                break;
            case 'usuarios':
                $email = (string)$d['email'];
                if (Db::val('SELECT id FROM users WHERE email=? AND id<>?', [$email, $id])) { return 'Ya existe un usuario con ese correo.'; }
                $pw = (string)($this->req->post['password'] ?? '');
                if (!$id && $pw === '') { return 'La contraseña es obligatoria para un usuario nuevo.'; }
                if ($pw !== '') { if ($e = Auth::strongPassword($pw)) { return $e; } $d['password_hash'] = Auth::hash($pw); }
                if ($d['role'] === 'professional' && empty($d['professional_id'])) { return 'Un usuario con rol Profesional debe estar vinculado a un profesional.'; }
                if ($d['role'] !== 'professional') { $d['professional_id'] = null; }
                if ($id === (int)$this->user['id'] && (!$d['active'] || $d['role'] !== 'admin')) { return 'No puedes desactivarte ni quitarte el rol de administrador a ti mismo.'; }
                if ($old && $old['role'] === 'admin' && ($d['role'] !== 'admin' || !$d['active']) && (int)Db::val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND id<>?", [$id]) === 0) { return 'Debe quedar al menos un administrador activo.'; }
                break;
        }
        return null;
    }

    private function afterSave(int $id): void
    {
        if ($this->mod === 'servicios') {
            Db::exec('DELETE FROM professional_services WHERE service_id=? AND professional_id NOT IN (' . Db::in(array_map('intval', array_keys((array)($this->req->post['profs'] ?? []))) ?: [0]) . ')',
                array_merge([$id], array_map('intval', array_keys((array)($this->req->post['profs'] ?? []))) ?: [0]));
            foreach (array_keys((array)($this->req->post['profs'] ?? [])) as $pid) {
                if (Db::val('SELECT id FROM professionals WHERE id=?', [(int)$pid])) { Db::exec('INSERT IGNORE INTO professional_services (professional_id,service_id) VALUES (?,?)', [(int)$pid, $id]); }
            }
        }
    }

    public function delete(): Response
    {
        $this->load();
        if (!empty($this->cfg['no_delete'])) { $this->abort(404); }
        $id = $this->id();
        $row = Db::one('SELECT * FROM `' . $this->cfg['table'] . '` WHERE id=?', [$id]);
        if (!$row || !$this->rowAllowed($row)) { $this->abort(404); }
        if ($this->mod === 'usuarios') {
            if ($id === (int)$this->user['id']) { $this->fail('No puedes eliminar tu propio usuario.'); return $this->redirect('/admin/usuarios'); }
            if ($row['role'] === 'admin' && (int)Db::val("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND id<>?", [$id]) === 0) { $this->fail('Debe quedar al menos un administrador activo.'); return $this->redirect('/admin/usuarios'); }
        }
        try {
            Db::delete($this->cfg['table'], $id);
            $this->audit($this->mod . '_deleted', $this->cfg['table'], $id);
            $this->ok('Eliminado.');
        } catch (\PDOException $e) {
            $this->fail('No se puede eliminar porque tiene registros asociados. Desactívalo para ocultarlo.');
        }
        return $this->redirect('/admin/' . $this->mod);
    }

    public function generateHolidays(): Response
    {
        $this->need('admin');
        $y = $this->req->int('anio', (int)date('Y'));
        if ($y < 2020 || $y > 2100) { $this->fail('Año inválido.'); return $this->redirect('/admin/feriados'); }
        $n = HolidayService::seedYear($y);
        $this->audit('holidays_generated', 'holidays', null, (string)$y);
        $this->ok("Se agregaron $n feriados de $y (Semana Santa calculada automáticamente).");
        return $this->redirect('/admin/feriados?anio=' . $y);
    }

    public function restoreTemplates(): Response
    {
        $this->need('admin');
        TemplateService::seed(mb_strtolower(term('appt')), true);
        $this->audit('templates_restored', 'message_templates');
        $this->ok('Plantillas restauradas a los textos originales.');
        return $this->redirect('/admin/plantillas');
    }
}
