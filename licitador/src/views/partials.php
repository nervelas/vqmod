<?php
/** Piezas reutilizables de las vistas. */
function icon(string $n, string $cls = ''): string {
    return '<svg class="i ' . e($cls) . '" aria-hidden="true" focusable="false"><use href="' . e(asset('img/icons.svg')) . '#i-' . e($n) . '"/></svg>';
}
function flash_html(): string {
    $o = '';
    foreach (flash() ?: [] as [$t, $m]) $o .= '<div class="flash ' . e($t) . '" role="status">' . icon($t === 'ok' ? 'check-circle' : 'alert') . '<span>' . e($m) . '</span></div>';
    return $o;
}
function brand_mark(): string {
    static $n = 0; $n++;
    return '<svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><defs><linearGradient id="gm' . $n . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F1D58A"/><stop offset="1" stop-color="#B58A2E"/></linearGradient><linearGradient id="gs' . $n . '" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#F1D58A" stop-opacity="0"/><stop offset="1" stop-color="#F1D58A" stop-opacity=".85"/></linearGradient></defs><circle cx="16" cy="16" r="14.2" fill="#0B0A08" stroke="url(#gm' . $n . ')" stroke-width="1.5"/><circle cx="16" cy="16" r="8.2" fill="none" stroke="#C9A24B" stroke-opacity=".5"/><g class="sw"><path d="M16 16V2.4A13.6 13.6 0 0 1 28.6 9z" fill="url(#gs' . $n . ')"/></g><circle cx="16" cy="16" r="1.6" fill="#F1D58A"/><circle cx="22.4" cy="11" r="2" fill="#2FBF8F"/></svg>';
}
function brand_html(string $sub = ''): string {
    $logo = S('logo');
    $mark = $logo ? '<img class="brand-logo" src="' . e(url('archivo/logo')) . '" alt="" width="30" height="30">' : brand_mark();
    return '<span class="brand">' . $mark . '<span>' . e(mb_strtoupper(S('site_name', 'Licitador'))) . '</span>' . ($sub ? '<small>' . e($sub) . '</small>' : '') . '</span>';
}
function theme_vars(): string {
    $c = fn($k, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', S($k, $d)) ? S($k, $d) : $d;
    return '<style>:root{--gold:' . $c('gold', '#C9A24B') . ';--new:' . $c('accent_new', '#2FBF8F') . ';--urgent:' . $c('accent_urgent', '#C2334D') . '}</style>';
}
function head_html(string $title, string $desc = '', bool $index = false): string {
    $site = S('site_name', 'Licitador'); $full = $title === $site || str_contains($title, $site) ? $title : $title . ' · ' . $site;
    $o = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' . e($full) . '</title>';
    $o .= '<meta name="description" content="' . e($desc ?: S('seo_desc')) . '">';
    $o .= '<meta name="robots" content="' . ($index ? 'index,follow' : 'noindex,nofollow') . '">';
    $o .= '<meta name="theme-color" content="#0B0A08"><meta name="color-scheme" content="dark light"><meta property="og:title" content="' . e($full) . '"><meta property="og:description" content="' . e($desc ?: S('seo_desc')) . '"><meta property="og:type" content="website"><meta property="og:locale" content="es_GT">';
    $o .= '<link rel="icon" href="' . e(asset('img/favicon.svg')) . '" type="image/svg+xml">';
    $o .= '<link rel="preload" href="' . e(asset('fonts/bodoni-moda-latin-aFTH7P.woff2')) . '" as="font" type="font/woff2" crossorigin>';
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
function score_ring(int $p, string $size = ''): string {
    return '<div class="ring" style="--p:' . $p . ($size ? ';--size:' . $size : '') . '" title="Coincidencia con tu perfil"><b>' . $p . '<small>%</small></b></div>';
}
function tender_card(array $t, array $opts = []): string {
    $u = urgency_class($t['deadline']); $mod = modalidades()[$t['modality']] ?? $t['modality'];
    $o = '<article class="tcard glass reveal ' . $u . '"><div class="tc-main"><div class="tc-tags">';
    if (($t['mstatus'] ?? '') === 'nuevo') $o .= '<span class="badge b-new">' . icon('spark') . 'Nuevo</span>';
    $lab = ['guardado' => 'Guardado', 'participando' => 'Participando', 'ganado' => 'Ganado', 'perdido' => 'Perdido', 'descartado' => 'Descartado'];
    if (isset($lab[$t['mstatus'] ?? ''])) $o .= '<span class="badge">' . $lab[$t['mstatus']] . '</span>';
    if (!empty($t['is_demo'])) $o .= '<span class="badge b-demo" title="Concurso de ejemplo">DEMO</span>';
    $o .= '<span class="badge b-mod">' . e($mod) . '</span></div>';
    $o .= '<h3 class="tcard-title"><a href="' . e(url('app/oportunidad/' . $t['id'])) . '">' . e($t['title']) . '</a></h3>';
    if (!empty($t['score'])) $o .= score_ring((int) $t['score']);
    $o .= '<ul class="tc-meta"><li class="w">' . icon('building') . '<span>' . e($t['entity']) . '</span></li><li>' . icon('pin') . '<span>' . e($t['department']) . '</span></li><li class="amt">' . icon('coins') . '<span>' . e(money($t['amount'])) . '</span></li><li>' . icon('calendar') . '<span>' . e(fdate($t['deadline'])) . '</span></li><li class="due ' . $u . '">' . icon('clock') . '<span>' . e(days_label($t['deadline'])) . '</span></li></ul></div>';
    if (!empty($t['reasons'])) $o .= '<p class="why">' . icon('radar') . '<span>' . e(ucfirst($t['reasons'])) . '</span></p>';
    if (empty($opts['noactions'])) {
        $o .= '<form method="post" action="' . e(url('app/accion/' . $t['id'])) . '" class="tcard-actions" data-ajax-actions>' . csrf_field();
        $o .= '<button name="accion" value="participar" class="btn btn-gold btn-sm">' . icon('award') . 'Voy a participar</button><button name="accion" value="guardar" class="btn btn-ghost btn-sm">' . icon('bookmark') . 'Guardar</button>';
        $o .= ($t['mstatus'] ?? '') === 'descartado' ? '<button name="accion" value="restaurar" class="btn btn-ghost btn-sm">' . icon('refresh') . 'Restaurar</button>' : '<button name="accion" value="descartar" class="btn btn-quiet btn-sm">' . icon('x') . 'Descartar</button>';
        $o .= '<a class="btn btn-quiet btn-sm" href="' . e(url('app/oportunidad/' . $t['id'])) . '">Ver detalle' . icon('arrow') . '</a></form>';
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
/** Menús de la aplicación: [grupo => [[ruta, etiqueta, ícono, módulo]]]. */
function app_menu(string $kind): array {
    if ($kind === 'client') return [
        'Radar' => [['app', 'Oportunidades', 'radar'], ['app/calendario', 'Vencimientos', 'calendar'], ['app/alertas', 'Alertas', 'bell'], ['app/mensajes', 'WhatsApp', 'chat']],
        'Mi cuenta' => [['app/asistente', 'Mis preferencias', 'sliders'], ['app/perfil', 'Mi empresa', 'briefcase'], ['app/suscripcion', 'Suscripción', 'card'], ['app/ayuda', 'Ayuda', 'help']],
    ];
    return [
        'Operación' => [['admin', 'Tablero', 'chart', 'dashboard'], ['admin/clientes', 'Clientes', 'users', 'clientes'], ['admin/pagos', 'Pagos', 'card', 'pagos'], ['admin/concursos', 'Concursos', 'gavel', 'concursos'], ['admin/planes', 'Planes', 'tag', 'planes']],
        'Datos y avisos' => [['admin/fuente', 'Fuente de datos', 'database', 'fuente'], ['admin/notificaciones', 'Notificaciones', 'bell', 'notificaciones']],
        'Sitio' => [['admin/contenido', 'Contenido', 'file', 'contenido'], ['admin/config/sitio', 'Textos y contacto', 'edit', 'contenido'], ['admin/config/seo', 'SEO y colores', 'globe', 'contenido']],
        'Sistema' => [['admin/config/general', 'Generales', 'sliders', 'ajustes'], ['admin/config/correo', 'Correo (SMTP)', 'mail', 'ajustes'], ['admin/config/whatsapp', 'WhatsApp', 'chat', 'ajustes'], ['admin/config/ia', 'Inteligencia artificial', 'spark', 'ajustes'], ['admin/ajustes', 'Cron, equipo y respaldo', 'shield', 'ajustes']],
    ];
}
function menu_on(string $p, string $cur, string $kind): bool {
    if ($p === 'app') return $cur === '/app' || str_starts_with($cur, '/app/oportunidad') || str_starts_with($cur, '/app/borrador');
    if ($p === 'admin') return $cur === '/admin';
    return $cur === '/' . $p || str_starts_with($cur, '/' . $p . '/');
}
function svg_bars(array $series, array $labels, string $cls = ''): string {
    $max = max(1, ...array_map(fn($s) => max($s ?: [0]), $series));
    $n = count($labels); $o = '<div class="chart" role="img" aria-label="Gráfica de los últimos días">';
    for ($i = 0; $i < $n; $i++) {
        $o .= '<div class="col" title="' . e($labels[$i]) . '"><div class="pair">';
        foreach ($series as $k => $s) { $h = round(($s[$i] ?? 0) / $max * 100); $o .= '<div class="bar' . ($k ? ' g' : '') . '" style="--h:' . $h . '%;animation-delay:' . ($i * 40) . 'ms" title="' . (int) ($s[$i] ?? 0) . '"></div>'; }
        $o .= '</div><small>' . e($labels[$i]) . '</small></div>';
    }
    return $o . '</div>';
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
