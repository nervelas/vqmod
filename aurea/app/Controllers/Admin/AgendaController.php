<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Util;
use Aurea\Services\AvailabilityService;
use Aurea\Services\HolidayService;

final class AgendaController extends AdminController
{
    private const SCALE = 1.2; // px por minuto

    public function index(): Response
    {
        $this->need('agenda');
        $view = in_array($this->req->str('vista'), ['dia', 'semana', 'mes'], true) ? $this->req->str('vista') : 'dia';
        $date = Util::isDate($this->req->str('fecha')) ? $this->req->str('fecha') : date('Y-m-d');
        $scope = $this->scopePro();
        $profs = Db::all('SELECT id,name,color FROM professionals WHERE active=1' . ($scope ? ' AND id=' . (int)$scope : '') . ' ORDER BY sort,name');
        $profSel = $scope ?: ($this->req->str('prof') === '' || $this->req->str('prof') === 'all' ? 0 : $this->req->int('prof'));
        if ($profSel && !in_array($profSel, array_map('intval', array_column($profs, 'id')), true)) { $profSel = 0; }
        $showCancelled = $this->req->str('canceladas') === '1';

        if ($view === 'mes') { $data = $this->month($date, $profs, $profSel, $showCancelled); }
        else { $data = $this->grid($view, $date, $profs, $profSel, $showCancelled); }

        $q = static fn(array $o) => '/admin/agenda?' . http_build_query(array_filter($o, static fn($v) => $v !== '' && $v !== null && $v !== 0));
        $base = ['vista' => $view, 'prof' => $profSel ?: '', 'canceladas' => $showCancelled ? '1' : ''];
        $step = $view === 'mes' ? '+1 month' : ($view === 'semana' ? '+1 week' : '+1 day');
        $stepB = $view === 'mes' ? '-1 month' : ($view === 'semana' ? '-1 week' : '-1 day');
        $nav = [
            'prev' => $q($base + ['fecha' => date('Y-m-d', strtotime($date . ' ' . $stepB))]),
            'next' => $q($base + ['fecha' => date('Y-m-d', strtotime($date . ' ' . $step))]),
            'today' => $q($base + ['fecha' => date('Y-m-d')]),
            'day' => $q(['vista' => 'dia', 'fecha' => $date] + $base), 'week' => $q(['vista' => 'semana', 'fecha' => $date] + $base), 'month' => $q(['vista' => 'mes', 'fecha' => $date] + $base),
        ];
        $shortcuts = ['n' => url('/admin/citas/nueva?fecha=' . $date), 't' => url($nav['today']), 'd' => url($nav['day']), 'w' => url($nav['week']), 'm' => url($nav['month']), 'prev' => url($nav['prev']), 'next' => url($nav['next'])];
        $label = $view === 'mes' ? ucfirst(Util::MONTHS[(int)date('n', strtotime($date)) - 1]) . ' ' . date('Y', strtotime($date))
            : ($view === 'semana' ? 'Semana del ' . fdate($data['from']) . ' al ' . fdate($data['to']) : Util::dateLong($date));
        return $this->render('agenda', $data + compact('view', 'date', 'profs', 'profSel', 'nav', 'shortcuts', 'label', 'showCancelled', 'scope'), 'agenda', 'Agenda');
    }

    private function fetchEvents(string $from, string $to, int $profSel, bool $cancelled): array
    {
        $st = $cancelled ? "('pending','confirmed','completed','no_show','cancelled')" : "('pending','confirmed','completed','no_show')";
        $sql = "SELECT a.id,a.start_at,a.end_at,a.status,a.professional_id,a.client_confirmed_at,c.name client_name,s.name service_name,p.color,p.name prof_name
            FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id
            WHERE a.start_at>=? AND a.start_at<? AND a.status IN $st";
        $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        $sc = $this->scopePro();
        if ($sc) { $sql .= ' AND a.professional_id=?'; $params[] = $sc; }
        elseif ($profSel) { $sql .= ' AND a.professional_id=?'; $params[] = $profSel; }
        return Db::all($sql . ' ORDER BY a.start_at', $params);
    }

    private function grid(string $view, string $date, array $profs, int $profSel, bool $cancelled): array
    {
        if ($view === 'semana') {
            $mon = date('Y-m-d', strtotime($date . ' monday this week'));
            if (date('N', strtotime($date)) === '7') { $mon = date('Y-m-d', strtotime($date . ' -6 days')); }
            $from = $mon; $to = date('Y-m-d', strtotime($mon . ' +6 days'));
        } else { $from = $to = $date; }
        $events = $this->fetchEvents($from, $to, $profSel, $cancelled);
        $hol = HolidayService::range($from, $to);
        $selProfs = $profSel ? array_values(array_filter($profs, static fn($p) => (int)$p['id'] === $profSel)) : $profs;
        $ids = array_map(static fn($p) => (int)$p['id'], $selProfs);
        $sched = [];
        if ($ids) {
            foreach (Db::all('SELECT professional_id,weekday,start_time,end_time FROM schedules WHERE professional_id IN (' . Db::in($ids) . ')', $ids) as $r) {
                $sched[(int)$r['professional_id']][(int)$r['weekday']][] = [Util::minutes($r['start_time']), Util::minutes($r['end_time'])];
            }
        }
        $offs = $ids ? Db::all('SELECT professional_id,start_at,end_at,reason FROM time_off WHERE end_at>? AND start_at<? AND (professional_id IS NULL OR professional_id IN (' . Db::in($ids) . '))',
            array_merge([$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'], $ids)) : [];

        // Rango horario visible
        $min = 8 * 60; $max = 18 * 60;
        foreach ($sched as $byWd) { foreach ($byWd as $blocks) { foreach ($blocks as [$s, $e]) { $min = min($min, $s); $max = max($max, $e); } } }
        foreach ($events as $e) { $min = min($min, Util::minutes(date('H:i', strtotime($e['start_at'])))); $max = max($max, Util::minutes(date('H:i', strtotime($e['end_at']))) ?: 1440); }
        $min = max(0, intdiv($min, 60) * 60); $max = min(1440, (int)ceil($max / 60) * 60);
        $sc = self::SCALE;
        $H = ($max - $min) * $sc;

        $cols = [];
        $days = $view === 'semana' ? array_map(static fn($i) => date('Y-m-d', strtotime($from . " +$i days")), range(0, 6)) : [$date];
        $makeCol = function (string $d, ?array $prof) use ($events, $sched, $offs, $hol, $min, $max, $sc, $selProfs, $profSel) {
            $wd = (int)date('w', strtotime($d . ' 12:00:00'));
            $pids = $prof ? [(int)$prof['id']] : array_map(static fn($p) => (int)$p['id'], $selProfs);
            // Zonas fuera de horario (sin ningún profesional trabajando)
            $work = [];
            foreach ($pids as $pid) { foreach ($sched[$pid][$wd] ?? [] as $b) { $work[] = $b; } }
            usort($work, static fn($a, $b) => $a[0] <=> $b[0]);
            $merged = [];
            foreach ($work as $b) { if ($merged && $b[0] <= $merged[count($merged) - 1][1]) { $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $b[1]); } else { $merged[] = $b; } }
            $offz = []; $cur = $min;
            $h = $hol[$d] ?? null;
            if ($h && $h['kind'] === 'full') { $merged = []; }
            elseif ($h && $h['kind'] === 'half') { $c = Util::minutes($h['close']); $merged = array_values(array_filter(array_map(static fn($b) => [$b[0], min($b[1], $c)], $merged), static fn($b) => $b[0] < $b[1])); }
            foreach ($merged as [$s, $e]) { if ($s > $cur) { $offz[] = [$cur, $s]; } $cur = max($cur, $e); }
            if ($cur < $max) { $offz[] = [$cur, $max]; }
            $offzones = array_map(static fn($z) => ['top' => (max($z[0], $min) - $min) * $sc, 'h' => (min($z[1], $max) - max($z[0], $min)) * $sc, 'label' => ''], array_filter($offz, static fn($z) => $z[1] > $z[0]));
            // Ausencias
            $blocks = [];
            foreach ($offs as $o) {
                if ($o['professional_id'] !== null && !in_array((int)$o['professional_id'], $pids, true)) { continue; }
                $s = strtotime($o['start_at']); $e = strtotime($o['end_at']);
                $ds = strtotime($d . ' 00:00:00'); $de = $ds + 86400;
                if ($e <= $ds || $s >= $de) { continue; }
                $sm = (int)max(0, ($s - $ds) / 60); $em = (int)min(1440, ($e - $ds) / 60);
                $blocks[] = ['top' => (max($sm, $min) - $min) * $sc, 'h' => max(18, (min($em, $max) - max($sm, $min)) * $sc), 'label' => $o['reason'] ?: 'Ausencia'];
            }
            // Eventos del día (con empaquetado de carriles por solapamiento)
            $ev = array_values(array_filter($events, static fn($e) => substr($e['start_at'], 0, 10) === $d && (!$prof || (int)$e['professional_id'] === (int)$prof['id'])));
            $lanes = [];
            foreach ($ev as $k => $e) {
                $s = strtotime($e['start_at']); $placed = false;
                foreach ($lanes as $li => $end) { if ($s >= $end) { $lanes[$li] = strtotime($e['end_at']); $ev[$k]['lane'] = $li; $placed = true; break; } }
                if (!$placed) { $ev[$k]['lane'] = count($lanes); $lanes[] = strtotime($e['end_at']); }
            }
            $n = max(1, count($lanes));
            $out = [];
            foreach ($ev as $e) {
                $sm = Util::minutes(date('H:i', strtotime($e['start_at']))); $dur = (int)((strtotime($e['end_at']) - strtotime($e['start_at'])) / 60);
                $out[] = $e + ['top' => ($sm - $min) * $sc, 'h' => max(22, $dur * $sc), 'left' => $e['lane'] * 100 / $n, 'w' => 100 / $n];
            }
            $slots = [];
            for ($m = $min; $m < $max; $m += 30) { $slots[] = [($m - $min) * $sc, sprintf('%02d:%02d', intdiv($m, 60), $m % 60)]; }
            return ['date' => $d, 'prof' => $prof, 'off' => $offzones, 'blocks' => $blocks, 'events' => $out, 'slots' => $slots, 'holiday' => $h['name'] ?? null,
                'today' => $d === date('Y-m-d'), 'label' => $prof ? $prof['name'] : Util::dateShort($d)];
        };
        if ($view === 'dia') {
            foreach ($selProfs as $p) { $cols[] = $makeCol($date, $p); }
            if (!$cols) { $cols[] = $makeCol($date, null); }
        } else {
            foreach ($days as $d) { $cols[] = $makeCol($d, $profSel ? ($selProfs[0] ?? null) : null); }
        }
        $now = null;
        if (in_array(date('Y-m-d'), $days, true)) { $nm = (int)date('G') * 60 + (int)date('i'); if ($nm >= $min && $nm <= $max) { $now = ($nm - $min) * $sc; } }
        $times = [];
        for ($m = $min; $m < $max; $m += 60) { $times[] = [($m - $min) * $sc, sprintf('%02d:00', intdiv($m, 60))]; }
        return ['cols' => $cols, 'H' => $H, 'times' => $times, 'now' => $now, 'from' => $from, 'to' => $to, 'mode' => 'grid'];
    }

    private function month(string $date, array $profs, int $profSel, bool $cancelled): array
    {
        $first = date('Y-m-01', strtotime($date));
        $gridStart = date('Y-m-d', strtotime($first . ' -' . ((int)date('N', strtotime($first)) - 1) . ' days'));
        $gridEnd = date('Y-m-d', strtotime($gridStart . ' +41 days'));
        $events = $this->fetchEvents($gridStart, $gridEnd, $profSel, $cancelled);
        $by = [];
        foreach ($events as $e) { $by[substr($e['start_at'], 0, 10)][] = $e; }
        $hol = HolidayService::range($gridStart, $gridEnd);
        $cells = [];
        for ($d = $gridStart; $d <= $gridEnd; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $cells[] = ['date' => $d, 'out' => substr($d, 0, 7) !== substr($first, 0, 7), 'today' => $d === date('Y-m-d'), 'events' => $by[$d] ?? [], 'hol' => $hol[$d]['name'] ?? null];
        }
        return ['cells' => $cells, 'mode' => 'month', 'from' => $gridStart, 'to' => $gridEnd];
    }

    /** Horarios libres para el panel (ignora aviso mínimo / anticipación máxima). */
    public function slots(): Response
    {
        $this->need('agenda');
        $svc = Db::one('SELECT * FROM services WHERE id=? AND active=1', [$this->req->int('service')]);
        $date = $this->req->str('date');
        if (!$svc || !Util::isDate($date)) { return $this->json(['ok' => false, 'slots' => []]); }
        $pro = $this->req->str('professional', 'any');
        $sc = $this->scopePro();
        $only = $sc ?: (($pro === 'any' || $pro === '') ? null : (int)$pro);
        $profs = AvailabilityService::professionalsFor((int)$svc['id'], null, $only);
        $opts = ['ignore_notice' => true];
        $tok = $this->req->str('exclude');
        if (preg_match('/^\d+$/', $tok) && $tok !== '') { $opts['exclude'] = (int)$tok; }
        return $this->json(['ok' => true, 'slots' => AvailabilityService::daySlots($svc, $profs, $date, null, $opts)]);
    }
}
