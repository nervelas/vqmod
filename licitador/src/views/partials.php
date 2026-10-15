<?php
/** Piezas reutilizables de las vistas. */
function flash_html(): string {
    $o = '';
    foreach (flash() ?: [] as [$t, $m]) $o .= '<div class="flash ' . e($t) . '" role="status">' . e($m) . '</div>';
    return $o;
}
function brand_html(): string {
    $logo = S('logo');
    $mark = $logo ? '<img class="brand-logo" src="' . e(url('archivo/logo')) . '" alt="" width="28" height="28">' : '<span class="brand-dot" aria-hidden="true"></span>';
    return '<span class="brand">' . $mark . '<span>' . e(mb_strtoupper(S('site_name', 'Licitador'))) . '</span></span>';
}
function theme_vars(): string {
    $c = fn($k, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', S($k, $d)) ? S($k, $d) : $d;
    return '<style>:root{--gold:' . $c('gold', '#C9A24B') . ';--new:' . $c('accent_new', '#2FBF8F') . ';--urgent:' . $c('accent_urgent', '#C2334D') . '}</style>';
}
function head_html(string $title, string $desc = '', bool $index = false): string {
    $site = S('site_name', 'Licitador'); $full = $title === $site || str_contains($title, $site) ? $title : $title . ' · ' . $site;
    $o = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($full) . '</title>';
    $o .= '<meta name="description" content="' . e($desc ?: S('seo_desc')) . '">';
    $o .= '<meta name="robots" content="' . ($index ? 'index,follow' : 'noindex,nofollow') . '">';
    $o .= '<meta name="theme-color" content="#0B0A08"><meta property="og:title" content="' . e($full) . '"><meta property="og:description" content="' . e($desc ?: S('seo_desc')) . '"><meta property="og:type" content="website"><meta property="og:locale" content="es_GT">';
    $o .= '<link rel="icon" href="' . e(asset('img/favicon.svg')) . '" type="image/svg+xml">';
    $o .= '<script src="' . e(asset('js/theme.js')) . '"></script>';
    $o .= '<link rel="stylesheet" href="' . e(asset('css/app.min.css')) . '">' . theme_vars();
    return $o;
}
function urgency_class($deadline): string {
    $d = days_left($deadline); if ($d === null) return '';
    return $d <= 3 ? 'urgent' : ($d <= 7 ? 'soon' : '');
}
function days_label($deadline): string {
    $d = days_left($deadline); if ($d === null) return 'Sin fecha límite';
    if (strtotime($deadline) < time()) return 'Vencido';
    return $d === 0 ? 'Vence hoy' : ($d === 1 ? 'Queda 1 día' : "Quedan $d días");
}
function tender_card(array $t, array $opts = []): string {
    $u = urgency_class($t['deadline']); $mod = modalidades()[$t['modality']] ?? $t['modality'];
    $o = '<article class="card tcard ' . $u . ' reveal"><div class="tcard-top">';
    if (!empty($t['mstatus']) && $t['mstatus'] === 'nuevo') $o .= '<span class="badge b-new">Nuevo</span>';
    if ($t['mstatus'] ?? '' ) { $lab = ['guardado' => 'Guardado', 'participando' => 'Participando', 'ganado' => 'Ganado', 'perdido' => 'Perdido', 'descartado' => 'Descartado']; if (isset($lab[$t['mstatus']])) $o .= '<span class="badge b-muted">' . $lab[$t['mstatus']] . '</span>'; }
    if ($t['is_demo']) $o .= '<span class="badge b-demo" title="Concurso de ejemplo">DEMO</span>';
    $o .= '<span class="badge b-mod">' . e($mod) . '</span>';
    if (isset($t['score']) && $t['score'] > 0) $o .= '<span class="score" title="Qué tan bien coincide con tu perfil"><span class="mono">' . (int) $t['score'] . '</span>%</span>';
    $o .= '</div><h3 class="tcard-title"><a href="' . e(url('app/oportunidad/' . $t['id'])) . '">' . e($t['title']) . '</a></h3>';
    $o .= '<p class="tcard-entity">' . e($t['entity']) . ' · ' . e($t['department']) . '</p>';
    $o .= '<div class="tcard-meta"><div><small>Monto estimado</small><strong class="mono">' . e(money($t['amount'])) . '</strong></div><div><small>Fecha límite</small><strong class="mono">' . e(fdate($t['deadline'])) . '</strong></div><div class="due ' . $u . '"><small>Plazo</small><strong>' . e(days_label($t['deadline'])) . '</strong></div></div>';
    if (!empty($t['reasons'])) $o .= '<p class="why"><span class="why-ic" aria-hidden="true">◎</span> ' . e(ucfirst($t['reasons'])) . '</p>';
    if (empty($opts['noactions'])) {
        $o .= '<form method="post" action="' . e(url('app/accion/' . $t['id'])) . '" class="tcard-actions" data-ajax-actions>' . csrf_field();
        $o .= '<button name="accion" value="guardar" class="btn btn-ghost btn-sm">Guardar</button><button name="accion" value="participar" class="btn btn-gold btn-sm">Voy a participar</button>';
        $o .= ($t['mstatus'] ?? '') === 'descartado' ? '<button name="accion" value="restaurar" class="btn btn-ghost btn-sm">Restaurar</button>' : '<button name="accion" value="descartar" class="btn btn-quiet btn-sm">Descartar</button>';
        $o .= '</form>';
    }
    return $o . '</article>';
}
function pager(int $page, int $per, int $tot, string $base): string {
    $pages = (int) ceil($tot / $per); if ($pages <= 1) return '';
    $o = '<nav class="pager" aria-label="Paginación">';
    $q = $_GET; unset($q['p'], $q['r']);
    for ($i = 1; $i <= $pages; $i++) { $q['p'] = $i; $o .= '<a class="' . ($i === $page ? 'on' : '') . '" href="' . e(url($base . '?' . http_build_query($q))) . '">' . $i . '</a>'; }
    return $o . '</nav>';
}
function f_input(string $name, string $label, $val = '', string $type = 'text', string $attr = ''): string {
    return '<label>' . e($label) . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($val) . '" ' . $attr . '></label>';
}
function f_area(string $name, string $label, $val = '', int $rows = 4, string $attr = ''): string {
    return '<label>' . e($label) . '<textarea name="' . e($name) . '" rows="' . $rows . '" ' . $attr . '>' . e($val) . '</textarea></label>';
}
function f_select(string $name, string $label, array $opts, $sel = '', string $attr = ''): string {
    $o = '<label>' . e($label) . '<select name="' . e($name) . '" ' . $attr . '>';
    foreach ($opts as $k => $v) $o .= '<option value="' . e($k) . '"' . ((string) $k === (string) $sel ? ' selected' : '') . '>' . e($v) . '</option>';
    return $o . '</select></label>';
}
function f_check(string $name, string $label, bool $on, string $val = '1'): string {
    return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="' . e($val) . '"' . ($on ? ' checked' : '') . '> <span>' . e($label) . '</span></label>';
}
