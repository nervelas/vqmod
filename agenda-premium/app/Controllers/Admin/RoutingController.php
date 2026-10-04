<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Services\RoutingService;

/** Formularios de enrutamiento: constructor de preguntas y reglas, bitácora y estadísticas. */
final class RoutingController extends A3Controller
{
    private const OPS = ['eq', 'neq', 'contains', 'gt', 'lt', 'in'];
    private const ACTIONS = ['event', 'host', 'team', 'message', 'url'];

    public function index(Request $req, array $p): Response
    {
        $forms = Db::all('SELECT f.*, (SELECT COUNT(*) FROM routing_rules r WHERE r.form_id = f.id) AS rule_count, (SELECT COUNT(*) FROM routing_logs l WHERE l.form_id = f.id) AS log_count FROM routing_forms f ORDER BY f.active DESC, f.id DESC');
        foreach ($forms as &$f) {
            $f['link'] = abs_url('/enrutar/' . $f['slug']);
            $f['q_count'] = count(json_decode((string) $f['questions'], true) ?: []);
        }
        unset($f);
        return $this->page('admin/routing/index', ['forms' => $forms], '/admin/enrutamiento', 'Formularios de enrutamiento');
    }

    public function form(Request $req, array $p): Response
    {
        $form = null;
        $questions = [];
        $rules = [];
        if (isset($p['id'])) {
            $form = Db::one('SELECT * FROM routing_forms WHERE id = ?', [(int) $p['id']]);
            if (!$form) {
                throw new HttpException(404);
            }
            $questions = $this->uiQuestions((string) $form['questions']);
            foreach (Db::all('SELECT * FROM routing_rules WHERE form_id = ? ORDER BY priority, id', [$form['id']]) as $r) {
                $rules[] = [
                    'id' => (int) $r['id'], 'match' => $r['match_mode'], 'conditions' => json_decode((string) $r['conditions'], true) ?: [],
                    'action' => $r['action'], 'target' => (string) $r['action_value'], 'message' => (string) $r['message'], 'active' => (int) $r['active'],
                ];
            }
        }
        $boot = [
            'questions' => $questions, 'rules' => $rules,
            'events' => Db::all('SELECT id, name FROM event_types WHERE active = 1 ORDER BY sort_order, name'),
            'hosts' => Db::all('SELECT id, name FROM hosts WHERE active = 1 ORDER BY sort_order, name'),
            'teams' => Db::all('SELECT id, name FROM teams WHERE active = 1 ORDER BY name'),
        ];
        return $this->page('admin/routing/form', ['form' => $form, 'boot' => $boot, 'link' => $form ? abs_url('/enrutar/' . $form['slug']) : ''], '/admin/enrutamiento', $form ? 'Editar formulario' : 'Nuevo formulario', ['js/admin-routing.js']);
    }

    public function save(Request $req, array $p): Response
    {
        $id = $req->int('id');
        $back = $id ? '/admin/enrutamiento/' . $id . '/editar' : '/admin/enrutamiento/nuevo';
        $name = $req->str('name', 160);
        if ($name === '') {
            return $this->fail($req, 'Ponle un nombre al formulario.', $back);
        }
        if ($id > 0 && !Db::val('SELECT id FROM routing_forms WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        $qs = json_decode((string) $req->input('questions_json', '[]'), true);
        $rs = json_decode((string) $req->input('rules_json', '[]'), true);
        if (!is_array($qs) || !is_array($rs)) {
            return $this->fail($req, 'No pudimos leer las preguntas o reglas. Recarga la página e inténtalo de nuevo.', $back);
        }
        try {
            $questions = $this->cleanQuestions($qs);
            $rules = $this->cleanRules($rs, $questions);
            $default = $this->cleanDefault($req);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($req, $e->getMessage(), $back);
        }
        $slugIn = Str::slug($req->str('slug', 80) ?: $name);
        $data = [
            'name' => $name, 'slug' => Str::uniqueSlug('routing_forms', $slugIn, $id ?: null), 'description' => $req->str('description', 2000) ?: null,
            'questions' => (string) json_encode($questions, JSON_UNESCAPED_UNICODE), 'active' => $req->bool('active') ? 1 : 0,
            'default_action' => $default['action'], 'default_value' => $default['value'], 'default_message' => $default['message'],
        ];
        $id = (int) Db::tx(function () use ($id, $data, $rules): int {
            if ($id > 0) {
                Db::update('routing_forms', $data, 'id = ?', [$id]);
            } else {
                $data['created_at'] = Clock::utc();
                $id = Db::insert('routing_forms', $data);
            }
            $existing = array_map('intval', Db::col('SELECT id FROM routing_rules WHERE form_id = ?', [$id]));
            $keep = [];
            $prio = 10;
            foreach ($rules as $r) {
                $row = [
                    'priority' => $prio, 'match_mode' => $r['match'], 'conditions' => (string) json_encode($r['conditions'], JSON_UNESCAPED_UNICODE),
                    'action' => $r['action'], 'action_value' => $r['target'] !== '' ? $r['target'] : null, 'message' => $r['message'] !== '' ? $r['message'] : null, 'active' => $r['active'],
                ];
                $prio += 10;
                if ($r['id'] > 0 && in_array($r['id'], $existing, true)) {
                    Db::update('routing_rules', $row, 'id = ? AND form_id = ?', [$r['id'], $id]);
                    $keep[] = $r['id'];
                } else {
                    $keep[] = Db::insert('routing_rules', $row + ['form_id' => $id]);
                }
            }
            foreach (array_diff($existing, $keep) as $gone) {
                Db::delete('routing_rules', 'id = ? AND form_id = ?', [$gone, $id]);
            }
            return $id;
        });
        $this->audit('routing.save', 'routing_form', $id, $name);
        $this->flash('success', 'El formulario quedó guardado.');
        return $this->redirect('/admin/enrutamiento/' . $id . '/editar');
    }

    public function toggle(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, active FROM routing_forms WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::update('routing_forms', ['active' => (int) $row['active'] ? 0 : 1], 'id = ?', [$row['id']]);
        $this->flash('success', (int) $row['active'] ? 'El formulario se pausó: el enlace público ya no responde.' : 'El formulario está activo.');
        return $this->redirect('/admin/enrutamiento');
    }

    public function delete(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, name FROM routing_forms WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::delete('routing_forms', 'id = ?', [$row['id']]);
        $this->audit('routing.delete', 'routing_form', $row['id'], (string) $row['name']);
        $this->flash('success', 'El formulario se eliminó con su bitácora.');
        return $this->redirect('/admin/enrutamiento');
    }

    public function logs(Request $req, array $p): Response
    {
        $form = Db::one('SELECT * FROM routing_forms WHERE id = ?', [(int) $p['id']]);
        if (!$form) {
            throw new HttpException(404);
        }
        $labels = [];
        foreach (json_decode((string) $form['questions'], true) ?: [] as $q) {
            $labels[(string) ($q['id'] ?? '')] = (string) ($q['label'] ?? '');
        }
        $stats = RoutingService::stats((int) $form['id']);
        $logs = RoutingService::logs((int) $form['id'], 100);
        $prio = array_column($stats['rules'], 'priority', 'rule_id');
        return $this->page('admin/routing/logs', ['form' => $form, 'stats' => $stats, 'logs' => $logs, 'labels' => $labels, 'prio' => $prio], '/admin/enrutamiento', 'Bitácora de enrutamiento');
    }

    // ------------------------------------------------------------ conversión UI <-> BD

    private function uiQuestions(string $json): array
    {
        $out = [];
        foreach (json_decode($json, true) ?: [] as $q) {
            $type = (string) ($q['type'] ?? 'text');
            $opts = array_values(array_map('strval', (array) ($q['options'] ?? [])));
            $ui = match ($type) {
                'radio', 'select' => $opts === ['Sí', 'No'] ? 'yesno' : 'single',
                'checkbox' => 'multi',
                'number' => 'number',
                default => 'text',
            };
            $out[] = ['id' => (string) ($q['id'] ?? ''), 'label' => (string) ($q['label'] ?? ''), 'type' => $ui, 'options' => $ui === 'yesno' ? [] : $opts, 'required' => !empty($q['required'])];
        }
        return $out;
    }

    private function cleanQuestions(array $in): array
    {
        if (count($in) > 30) {
            throw new \InvalidArgumentException('Usa como máximo 30 preguntas por formulario.');
        }
        $out = [];
        $seen = [];
        foreach ($in as $i => $q) {
            if (!is_array($q)) {
                continue;
            }
            $n = $i + 1;
            $label = Str::clean((string) ($q['label'] ?? ''), 190);
            if ($label === '') {
                throw new \InvalidArgumentException('La pregunta ' . $n . ' necesita un texto.');
            }
            $id = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($q['id'] ?? ''))) ?? '';
            if ($id === '' || isset($seen[$id])) {
                $base = str_replace('-', '_', Str::slug($label, 20));
                $id = $base;
                $k = 2;
                while (isset($seen[$id])) {
                    $id = $base . '_' . $k++;
                }
            }
            $seen[$id] = true;
            $ui = (string) ($q['type'] ?? 'text');
            $opts = [];
            if (in_array($ui, ['single', 'multi'], true)) {
                foreach ((array) ($q['options'] ?? []) as $o) {
                    $o = Str::clean(is_scalar($o) ? (string) $o : '', 120);
                    if ($o !== '' && !in_array($o, $opts, true)) {
                        $opts[] = $o;
                    }
                }
                if (count($opts) < 2 || count($opts) > 30) {
                    throw new \InvalidArgumentException('La pregunta «' . $label . '» necesita entre 2 y 30 opciones.');
                }
            }
            $type = match ($ui) {
                'single' => 'radio', 'multi' => 'checkbox', 'number' => 'number', 'yesno' => 'radio', default => 'text',
            };
            if ($ui === 'yesno') {
                $opts = ['Sí', 'No'];
            }
            $row = ['id' => $id, 'label' => $label, 'type' => $type, 'required' => !empty($q['required'])];
            if ($opts) {
                $row['options'] = $opts;
            }
            $out[] = $row;
        }
        return $out;
    }

    private function cleanRules(array $in, array $questions): array
    {
        if (count($in) > 50) {
            throw new \InvalidArgumentException('Usa como máximo 50 reglas por formulario.');
        }
        $ids = array_column($questions, 'id');
        $out = [];
        foreach ($in as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $n = $i + 1;
            $conds = [];
            foreach ((array) ($r['conditions'] ?? []) as $c) {
                $q = (string) ($c['question'] ?? '');
                $op = (string) ($c['op'] ?? '');
                $val = Str::clean(is_scalar($c['value'] ?? null) ? (string) $c['value'] : '', 200);
                if (!in_array($q, $ids, true) || !in_array($op, self::OPS, true)) {
                    throw new \InvalidArgumentException('La regla ' . $n . ' tiene una condición incompleta: elige la pregunta y la comparación.');
                }
                if ($val === '') {
                    throw new \InvalidArgumentException('La regla ' . $n . ' tiene una condición sin valor.');
                }
                if (in_array($op, ['gt', 'lt'], true) && !is_numeric(str_replace(',', '.', $val))) {
                    throw new \InvalidArgumentException('En la regla ' . $n . ', "mayor que" y "menor que" necesitan un número.');
                }
                $conds[] = ['question' => $q, 'op' => $op, 'value' => $val];
            }
            if (!$conds) {
                throw new \InvalidArgumentException('La regla ' . $n . ' necesita al menos una condición.');
            }
            $action = (string) ($r['action'] ?? '');
            if (!in_array($action, self::ACTIONS, true)) {
                throw new \InvalidArgumentException('Elige qué debe pasar en la regla ' . $n . '.');
            }
            $target = Str::clean((string) ($r['target'] ?? ''), 500);
            $message = Str::clean((string) ($r['message'] ?? ''), 2000);
            $this->checkTarget($action, $target, $message, 'la regla ' . $n);
            $out[] = [
                'id' => (int) ($r['id'] ?? 0), 'match' => ($r['match'] ?? 'all') === 'any' ? 'any' : 'all', 'conditions' => $conds,
                'action' => $action, 'target' => $action === 'message' ? '' : $target, 'message' => $action === 'message' ? $message : '', 'active' => !empty($r['active']) ? 1 : 0,
            ];
        }
        return $out;
    }

    private function cleanDefault(Request $req): array
    {
        $action = $req->str('default_action', 10);
        if (!in_array($action, self::ACTIONS, true)) {
            $action = 'message';
        }
        $value = match ($action) {
            'event' => $req->str('dv_event', 20), 'host' => $req->str('dv_host', 20), 'team' => $req->str('dv_team', 20), 'url' => $req->str('dv_url', 500), default => '',
        };
        $message = $req->str('default_message', 2000);
        $this->checkTarget($action, $value, $message, 'la regla por defecto');
        return ['action' => $action, 'value' => $action === 'message' || $value === '' ? null : $value, 'message' => $message !== '' ? $message : null];
    }

    private function checkTarget(string $action, string $target, string $message, string $where): void
    {
        $ok = match ($action) {
            'event' => ctype_digit($target) && Db::val('SELECT id FROM event_types WHERE id = ?', [(int) $target]) !== null,
            'host' => ctype_digit($target) && Db::val('SELECT id FROM hosts WHERE id = ?', [(int) $target]) !== null,
            'team' => ctype_digit($target) && Db::val('SELECT id FROM teams WHERE id = ?', [(int) $target]) !== null,
            'url' => Validator::url($target),
            default => $message !== '',
        };
        if (!$ok) {
            $msg = match ($action) {
                'event' => 'Elige un tipo de cita válido en ' . $where . '.',
                'host' => 'Elige un anfitrión válido en ' . $where . '.',
                'team' => 'Elige un equipo válido en ' . $where . '.',
                'url' => 'Escribe una dirección web completa (con http:// o https://) en ' . $where . '.',
                default => 'Escribe el mensaje que verá la persona en ' . $where . '.',
            };
            throw new \InvalidArgumentException($msg);
        }
    }
}
