<?php
declare(strict_types=1);

$counts = [];
foreach ([
    'services' => ['Servicios activos', 'servicios', 'servicios'],
    'projects' => ['Proyectos publicados', 'portafolio', 'proyectos'],
    'posts'    => ['Publicaciones activas', 'blog', 'blog'],
    'slides'   => ['Diapositivas activas', 'imagen', 'slider'],
] as $table => [$label, $ic, $link]) {
    try {
        $counts[] = ['n' => (int) Database::value("SELECT COUNT(*) FROM `$table` WHERE status = 1", [], 0), 'label' => $label, 'icon' => $ic, 'link' => $link];
    } catch (Throwable) {
    }
}
$unread   = admin_unread();
$totalMsg = (int) Database::value('SELECT COUNT(*) FROM submissions', [], 0);
$last     = Database::all('SELECT * FROM submissions ORDER BY id DESC LIMIT 5');
$theme    = Theme::active();
$checks   = [
    ['ok' => Settings::get('phone') !== '', 'text' => 'Teléfono de contacto configurado', 'url' => admin_url('general')],
    ['ok' => Settings::get('email') !== '', 'text' => 'Correo electrónico configurado', 'url' => admin_url('general')],
    ['ok' => Settings::get('whatsapp') !== '', 'text' => 'WhatsApp configurado', 'url' => admin_url('general')],
    ['ok' => Settings::get('seo_default_description') !== '', 'text' => 'Meta descripción principal escrita', 'url' => admin_url('seo')],
    ['ok' => str_starts_with(SITE_URL, 'https://'), 'text' => 'Sitio configurado con HTTPS en config/config.php', 'url' => ''],
    ['ok' => Settings::get('google_analytics') !== '', 'text' => 'Google Analytics conectado (opcional)', 'url' => admin_url('seo')],
    ['ok' => !is_dir(APP_ROOT . '/install'), 'text' => 'Carpeta /install eliminada del servidor', 'url' => ''],
    ['ok' => (int) Database::value('SELECT COUNT(*) FROM testimonials WHERE status = 1', [], 0) > 0, 'text' => 'Testimonios reales publicados', 'url' => admin_url('testimonios')],
];

admin_header('Escritorio', 'dashboard', [['label' => 'Ver el sitio', 'url' => base(''), 'icon' => 'web']]);
?>
<div class="cards">
  <?php foreach ($counts as $c): ?>
    <a class="kpi" href="<?= e(admin_url($c['link'])) ?>">
      <span class="kpi__icon"><?= icon($c['icon'], 21) ?></span>
      <span><b><?= $c['n'] ?></b><span><?= e($c['label']) ?></span></span>
    </a>
  <?php endforeach; ?>
  <a class="kpi" href="<?= e(admin_url('mensajes')) ?>">
    <span class="kpi__icon" style="<?= $unread > 0 ? 'background:rgba(220,38,38,.12);color:var(--a-danger)' : '' ?>"><?= icon('contacto', 21) ?></span>
    <span><b><?= $unread ?></b><span>Mensajes sin leer (<?= $totalMsg ?> en total)</span></span>
  </a>
</div>

<div style="display:grid;gap:1.15rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr))">
  <div class="panel">
    <div class="panel__head"><h2><?= icon('contacto', 19) ?>Últimos mensajes</h2>
      <a class="btn btn--light btn--sm" href="<?= e(admin_url('mensajes')) ?>">Ver todos</a></div>
    <div class="table-wrap">
      <?php if ($last === []): ?>
        <div class="empty"><?= icon('contacto', 38) ?><p>Aún no ha recibido mensajes desde el formulario.</p></div>
      <?php else: ?>
        <table>
          <thead><tr><th>Nombre</th><th>Servicio</th><th>Fecha</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($last as $m): ?>
              <tr>
                <td><?= e($m['name']) ?><?= (int) $m['is_read'] === 0 ? ' <span class="pill pill--new">Nuevo</span>' : '' ?></td>
                <td><?= e(excerpt((string) $m['service'], 26) ?: '—') ?></td>
                <td><?= e(date('d/m/Y', strtotime((string) $m['created_at']) ?: time())) ?></td>
                <td class="actions"><a class="btn btn--light btn--sm" href="<?= e(admin_url('mensajes', ['id' => $m['id']])) ?>">Ver</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head"><h2><?= icon('seo', 19) ?>Lista de comprobación</h2></div>
    <div class="panel__body">
      <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
        <?php foreach ($checks as $c): ?>
          <li style="display:flex;gap:.6rem;align-items:flex-start;font-size:.9rem">
            <span style="color:<?= $c['ok'] ? 'var(--a-ok)' : 'var(--a-warn)' ?>;margin-top:.15rem"><?= icon($c['ok'] ? 'check' : 'cerrar', 16) ?></span>
            <span><?= e($c['text']) ?>
              <?php if (!$c['ok'] && $c['url'] !== ''): ?> — <a href="<?= e($c['url']) ?>">configurar</a><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel__head"><h2><?= icon('diseno', 19) ?>Apariencia actual</h2>
    <a class="btn btn--light btn--sm" href="<?= e(admin_url('temas')) ?>">Cambiar tema</a></div>
  <div class="panel__body" style="display:flex;gap:1.1rem;align-items:center;flex-wrap:wrap">
    <span style="width:62px;height:62px;border-radius:12px;border:1px solid var(--a-line);background:<?= e($theme['palette']['bg'] ?? '#000') ?>;position:relative;flex:none">
      <i style="position:absolute;right:7px;bottom:7px;width:20px;height:20px;border-radius:50%;background:<?= e($theme['palette']['accent'] ?? '#fff') ?>;display:block"></i>
    </span>
    <div>
      <strong style="font-size:1.05rem"><?= e($theme['name'] ?? '') ?></strong>
      <p class="panel__hint"><?= e($theme['description'] ?? '') ?></p>
      <p class="panel__hint">Modo <?= e(($theme['mode'] ?? 'dark') === 'dark' ? 'oscuro' : 'claro') ?> · Tipografías: <?= e(str_replace("'", '', (string) ($theme['fonts']['display'] ?? ''))) ?> + <?= e(str_replace("'", '', (string) ($theme['fonts']['body'] ?? ''))) ?></p>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
