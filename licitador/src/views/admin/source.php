<?php
$root = APP_ROOT; $token = S('cron_token', cfg('cron_token', '')); $php = PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') && !str_contains(PHP_BINARY, 'cgi') ? PHP_BINARY : '/usr/local/bin/php';
$line = '*/30 * * * * ' . $php . ' ' . $root . '/cron/sync.php >/dev/null 2>&1';
$lineUrl = '*/30 * * * * wget -q -O /dev/null "' . site_url('cron/sync.php') . '?token=' . $token . '"';
$st = S('source_type', 'demo');
?>
<div class="page-head"><div><p class="eyebrow">Datos</p><h1>Fuente de datos</h1></div>
<form method="post" action="<?= e(url('admin/fuente/sincronizar')) ?>"><?= csrf_field() ?><button class="btn btn-gold">Sincronizar ahora</button></form></div>
<section class="card"><h2>Configuración</h2>
<div class="flash warn">GUATECOMPRAS publica datos abiertos oficiales (OCDS, CC BY 4.0) en <strong>ocds.guatecompras.gt</strong>. Licitador solo descarga esos archivos públicos con un User-Agent identificable; <strong>no hace scraping ni evade protecciones</strong>. Pega abajo la URL de descarga (JSON OCDS o CSV) que publique la fuente oficial.</div>
<form method="post" class="form"><?= csrf_field() ?>
  <?= f_select('source_type', 'Origen de datos', ['demo' => 'Datos demo (agrega ejemplos nuevos cada día)', 'ocds' => 'OCDS / JSON desde URL', 'csv' => 'CSV desde URL (usa el mapeo guardado)', 'none' => 'Desactivada (solo carga manual por CSV)'], $st) ?>
  <?= f_input('source_url', 'URL de descarga', S('source_url'), 'url', 'placeholder="https://…"') ?>
  <?= f_input('source_ua', 'User-Agent (identifica a tu sitio ante la fuente)', S('source_ua', 'Licitador/' . APP_VERSION . ' (+' . site_url('') . ')')) ?>
  <?= f_input('official_url_tpl', 'Plantilla del enlace oficial ({nog} se reemplaza)', S('official_url_tpl', 'https://www.guatecompras.gt/concursos/consultaConcurso.aspx?nog={nog}')) ?>
  <button class="btn btn-gold">Guardar</button></form></section>
<section class="card"><h2>Cron (programar la sincronización)</h2>
  <p class="muted">En cPanel → Tareas Cron, cada 30 minutos, pega <strong>una</strong> de estas líneas:</p>
  <p><strong>Opción A (recomendada, línea de comandos):</strong></p><pre class="code" data-copy><?= e($line) ?></pre>
  <p><strong>Opción B (si tu hosting solo permite URL):</strong></p><pre class="code" data-copy><?= e($lineUrl) ?></pre>
  <p class="fine">La ruta de PHP puede variar según tu hosting (por ejemplo /usr/local/bin/php o /opt/cpanel/ea-php82/root/usr/bin/php). Último token regenerable en <a href="<?= e(url('admin/ajustes')) ?>">Cron, equipo y respaldo</a>.</p></section>
<section class="card"><h2>Registro de ejecuciones</h2><div class="tbl-wrap"><table class="tbl"><tr><th>Inicio</th><th>Origen</th><th>Estado</th><th>Nuevos</th><th>Alertas</th><th>Mensaje</th></tr>
<?php foreach ($runs as $r): ?><tr><td class="mono" data-l="Inicio"><?= e(fdate($r['started_at'], true)) ?></td><td data-l="Origen"><?= e($r['source']) ?></td><td data-l="Estado"><span class="pill <?= $r['status'] === 'ok' ? 'p-new' : ($r['status'] === 'parcial' ? 'p-soon' : 'p-urgent') ?>"><?= e($r['status']) ?></span></td><td class="mono" data-l="Nuevos"><?= (int) $r['imported'] ?></td><td class="mono" data-l="Alertas"><?= (int) $r['alerts'] ?></td><td data-l="Mensaje"><small><?= e($r['message']) ?></small></td></tr>
<?php endforeach; if (!$runs) echo '<tr><td colspan="6" class="muted">Sin ejecuciones todavía.</td></tr>'; ?></table></div></section>
