<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Str;
use App\Core\Validator;

/**
 * Formularios de enrutamiento.
 *
 * routing_forms.questions: JSON [{"id":"servicio","label":"¿Qué necesitas?","type":"select|radio|checkbox|text|textarea|number|email|phone","options":["A","B"],"required":true}]
 * routing_rules.conditions: JSON [{"question":"servicio","op":"eq|neq|contains|gt|lt|in","value":"A"}]
 *   (también se aceptan los alias igual, distinto, contiene, mayor, menor, en_lista; "in" recibe lista o texto separado por comas)
 * Acciones: event (slug o id), host (slug o id), team (slug o id), message, url (solo http/https).
 */
final class RoutingService
{
    private const OPS = ['igual' => 'eq', 'distinto' => 'neq', 'contiene' => 'contains', 'mayor' => 'gt', 'menor' => 'lt', 'en_lista' => 'in',
        'eq' => 'eq', 'neq' => 'neq', 'contains' => 'contains', 'gt' => 'gt', 'lt' => 'lt', 'in' => 'in'];

    /**
     * @return array ['action','target','reason','rule_id','redirect'=>?string,'message'=>?string,'events'=>array,'log_id'=>int]
     * @throws \InvalidArgumentException si faltan respuestas obligatorias o son inválidas
     */
    public static function run(array $form, array $answers, string $ip): array
    {
        $questions = self::questions($form);
        $clean = self::validate($questions, $answers);
        $rules = Db::all('SELECT * FROM routing_rules WHERE form_id = ? AND active = 1 ORDER BY priority ASC, id ASC', [(int) $form['id']]);

        $res = null;
        foreach ($rules as $r) {
            $conds = json_decode((string) $r['conditions'], true);
            if (!is_array($conds) || $conds === []) {
                continue;
            }
            $hit = self::match($conds, (string) $r['match_mode'], $clean, $questions);
            if ($hit === null) {
                continue;
            }
            $dest = self::destination((string) $r['action'], (string) $r['action_value'], (string) ($r['message'] ?? ''));
            if ($dest === null) {
                continue; // el destino ya no existe: se prueba la siguiente regla
            }
            $res = $dest + ['rule_id' => (int) $r['id']];
            $res['reason'] = 'Respondió ' . $hit . ', se aplicó la regla ' . (int) $r['id'] . ' → ' . $dest['label'];
            break;
        }
        if ($res === null) {
            $dest = self::destination((string) $form['default_action'], (string) ($form['default_value'] ?? ''), (string) ($form['default_message'] ?? ''));
            if ($dest === null) {
                $dest = ['action' => 'message', 'target' => '', 'redirect' => null, 'events' => [], 'message' => 'Por ahora no podemos orientarte automáticamente. Escríbenos y con gusto te ayudamos.', 'label' => 'mensaje informativo'];
            }
            $res = $dest + ['rule_id' => null];
            $res['reason'] = 'Ninguna regla coincidió con las respuestas, se aplicó la acción por defecto → ' . $dest['label'];
        }
        $res['log_id'] = Db::insert('routing_logs', [
            'form_id' => (int) $form['id'],
            'rule_id' => $res['rule_id'],
            'answers' => (string) json_encode($clean, JSON_UNESCAPED_UNICODE),
            'action' => $res['action'],
            'target' => $res['target'] !== '' ? mb_substr((string) $res['target'], 0, 500) : null,
            'reason' => mb_substr($res['reason'], 0, 500),
            'ip_trunc' => Str::ipTrunc($ip) ?: null,
            'created_at' => Clock::utc(),
        ]);
        unset($res['label']);
        return $res;
    }

    /** Conteo y porcentaje por regla (incluye reglas sin uso y la acción por defecto). */
    public static function stats(int $formId): array
    {
        $counts = [];
        foreach (Db::all('SELECT rule_id, COUNT(*) AS n FROM routing_logs WHERE form_id = ? GROUP BY rule_id', [$formId]) as $r) {
            $counts[$r['rule_id'] === null ? 0 : (int) $r['rule_id']] = (int) $r['n'];
        }
        $total = array_sum($counts);
        $rows = [];
        foreach (Db::all('SELECT id, priority, action, action_value, active FROM routing_rules WHERE form_id = ? ORDER BY priority, id', [$formId]) as $r) {
            $n = $counts[(int) $r['id']] ?? 0;
            $rows[] = ['rule_id' => (int) $r['id'], 'priority' => (int) $r['priority'], 'action' => $r['action'], 'target' => $r['action_value'], 'active' => (int) $r['active'], 'count' => $n, 'pct' => $total > 0 ? round($n * 100 / $total, 1) : 0.0];
        }
        $d = $counts[0] ?? 0;
        $rows[] = ['rule_id' => null, 'priority' => null, 'action' => 'default', 'target' => null, 'active' => 1, 'count' => $d, 'pct' => $total > 0 ? round($d * 100 / $total, 1) : 0.0];
        return ['total' => $total, 'rules' => $rows];
    }

    public static function logs(int $formId, int $limit = 50): array
    {
        $rows = Db::all('SELECT * FROM routing_logs WHERE form_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)), [$formId]);
        foreach ($rows as &$r) {
            $r['answers'] = json_decode((string) $r['answers'], true) ?: [];
        }
        unset($r);
        return $rows;
    }

    private static function questions(array $form): array
    {
        $q = json_decode((string) ($form['questions'] ?? '[]'), true);
        $out = [];
        foreach (is_array($q) ? $q : [] as $item) {
            $id = (string) ($item['id'] ?? $item['name'] ?? '');
            if ($id !== '') {
                $out[$id] = $item + ['label' => $id, 'type' => 'text', 'options' => [], 'required' => false];
            }
        }
        return $out;
    }

    /** Valida y limpia las respuestas contra las preguntas; ignora claves desconocidas. */
    private static function validate(array $questions, array $answers): array
    {
        $clean = [];
        foreach ($questions as $id => $q) {
            $label = (string) $q['label'];
            $raw = $answers[$id] ?? null;
            $type = (string) $q['type'];
            $opts = array_map('strval', (array) ($q['options'] ?? []));
            $vals = is_array($raw) ? array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $raw), static fn ($v) => $v !== '')) : (($raw === null || !is_scalar($raw) || trim((string) $raw) === '') ? [] : [trim((string) $raw)]);
            if ($vals === []) {
                if (!empty($q['required'])) {
                    throw new \InvalidArgumentException('Falta responder: «' . $label . '».');
                }
                continue;
            }
            if (count($vals) > 1 && $type !== 'checkbox') {
                throw new \InvalidArgumentException('Elige una sola respuesta en «' . $label . '».');
            }
            foreach ($vals as $v) {
                if (mb_strlen($v) > 500) {
                    throw new \InvalidArgumentException('La respuesta de «' . $label . '» es demasiado larga.');
                }
                if (in_array($type, ['select', 'radio', 'checkbox'], true) && $opts !== [] && !in_array($v, $opts, true)) {
                    throw new \InvalidArgumentException('La opción elegida en «' . $label . '» no es válida.');
                }
                if ($type === 'number' && !is_numeric(str_replace(',', '.', $v))) {
                    throw new \InvalidArgumentException('«' . $label . '» debe ser un número.');
                }
                if ($type === 'email' && !Validator::email($v)) {
                    throw new \InvalidArgumentException('El correo de «' . $label . '» no parece válido.');
                }
            }
            $clean[$id] = $type === 'checkbox' ? $vals : $vals[0];
        }
        return $clean;
    }

    /** Devuelve null si no coincide, o la frase «X a «pregunta»» de la primera condición cumplida. */
    private static function match(array $conds, string $mode, array $answers, array $questions): ?string
    {
        $hits = [];
        foreach ($conds as $c) {
            $qid = (string) ($c['question'] ?? '');
            $op = self::OPS[(string) ($c['op'] ?? '')] ?? null;
            $ok = $op !== null && self::test($op, $answers[$qid] ?? null, $c['value'] ?? '');
            if ($ok) {
                $a = $answers[$qid] ?? '';
                $hits[] = '«' . (is_array($a) ? implode(', ', $a) : (string) $a) . '» a «' . ($questions[$qid]['label'] ?? $qid) . '»';
            } elseif ($mode === 'all') {
                return null;
            }
        }
        return $hits === [] ? null : implode(' y ', $hits);
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(trim($s));
    }

    private static function test(string $op, $answer, $expected): bool
    {
        $vals = $answer === null ? [] : array_map('strval', (array) $answer);
        if ($vals === []) {
            return $op === 'neq';
        }
        $nv = array_map([self::class, 'norm'], $vals);
        $exp = is_array($expected) ? null : self::norm((string) $expected);
        switch ($op) {
            case 'eq':
                return $exp !== null && in_array($exp, $nv, true);
            case 'neq':
                return $exp !== null && !in_array($exp, $nv, true);
            case 'contains':
                foreach ($nv as $v) {
                    if ($exp !== null && $exp !== '' && str_contains($v, $exp)) {
                        return true;
                    }
                }
                return false;
            case 'gt':
            case 'lt':
                $a = str_replace(',', '.', $vals[0]);
                $b = str_replace(',', '.', (string) (is_array($expected) ? '' : $expected));
                if (!is_numeric($a) || !is_numeric($b)) {
                    return false;
                }
                return $op === 'gt' ? (float) $a > (float) $b : (float) $a < (float) $b;
            default: // in
                $list = is_array($expected) ? $expected : explode(',', (string) $expected);
                $list = array_map(static fn ($v) => self::norm((string) $v), $list);
                return array_intersect($nv, $list) !== [];
        }
    }

    /** Resuelve la acción a un destino; null si el destino no existe o no es seguro. */
    private static function destination(string $action, string $value, string $message): ?array
    {
        $value = trim($value);
        $base = ['action' => $action, 'target' => '', 'redirect' => null, 'message' => null, 'events' => []];
        switch ($action) {
            case 'event':
                $e = ctype_digit($value) ? Db::one('SELECT slug, name FROM event_types WHERE id = ? AND active = 1', [(int) $value]) : Db::one('SELECT slug, name FROM event_types WHERE slug = ? AND active = 1', [$value]);
                if ($e === null) {
                    return null;
                }
                return ['target' => '/e/' . $e['slug'], 'redirect' => url('/e/' . $e['slug']), 'label' => 'cita «' . $e['name'] . '»'] + $base;
            case 'host':
                $h = ctype_digit($value) ? Db::one('SELECT slug, name FROM hosts WHERE id = ? AND active = 1', [(int) $value]) : Db::one('SELECT slug, name FROM hosts WHERE slug = ? AND active = 1', [$value]);
                if ($h === null) {
                    return null;
                }
                return ['target' => '/h/' . $h['slug'], 'redirect' => url('/h/' . $h['slug']), 'label' => 'agenda de «' . $h['name'] . '»'] + $base;
            case 'team':
                $t = ctype_digit($value) ? Db::one('SELECT id, slug, name FROM teams WHERE id = ? AND active = 1', [(int) $value]) : Db::one('SELECT id, slug, name FROM teams WHERE slug = ? AND active = 1', [$value]);
                if ($t === null) {
                    return null;
                }
                $events = Db::all('SELECT slug, name FROM event_types WHERE team_id = ? AND active = 1 AND visibility = ? ORDER BY sort_order, name', [(int) $t['id'], 'public']);
                if ($events === []) {
                    return null;
                }
                foreach ($events as &$ev) {
                    $ev['url'] = url('/e/' . $ev['slug']);
                }
                unset($ev);
                return ['target' => 'equipo:' . $t['slug'], 'redirect' => count($events) === 1 ? $events[0]['url'] : null, 'events' => $events, 'label' => 'equipo «' . $t['name'] . '»'] + $base;
            case 'url':
                if (!Validator::url($value)) {
                    return null;
                }
                return ['target' => $value, 'redirect' => $value, 'label' => 'enlace ' . $value] + $base;
            case 'message':
                return ['message' => $message !== '' ? $message : 'Gracias por tus respuestas. Te contactaremos pronto.', 'label' => 'mensaje informativo'] + $base;
        }
        return null;
    }
}
