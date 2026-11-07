<?php
require __DIR__ . '/_boot.php';
auth_require();

$C = get_content();
$msgs = (array)store_read('messages', []);
$unread = unread_count();
$total = count($msgs);
$layout = $C['layout'];
$active = count(array_filter($layout['enabled']));
$labels = section_labels();

$checks = [
    ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>='), 'Versión compatible'],
    ['Carpeta de datos con permisos de escritura', is_writable(TLL_DATA), 'Necesaria para guardar cambios'],
    ['Carpeta de imágenes con permisos de escritura', is_writable(TLL_UPLOADS), 'Necesaria para subir imágenes'],
    ['Procesamiento de imágenes (GD)', extension_loaded('gd'), 'Optimiza y limpia las imágenes que subes'],
    ['Envío de correo', function_exists('mail') || !empty(get_settings()['smtp']['enabled']), 'Para recibir las solicitudes por correo'],
    ['Conexión segura (HTTPS)', is_https(), 'Recomendado: activa el certificado SSL en tu hosting'],
];
$shortcuts = [
    ['edit.php?s=hero', 'rocket-launch', 'Editar portada'],
    ['edit.php?s=services', 'moped', 'Servicios'],
    ['edit.php?s=rates', 'calculator', 'Tarifas'],
    ['edit.php?s=contact', 'address-book', 'Teléfono y redes'],
    ['media.php', 'images', 'Imágenes'],
    ['edit.php?s=theme', 'palette', 'Colores'],
];
admin_header('Inicio', 'dashboard', 'Bienvenido al panel de administración de ' . $C['site']['brand']);
?>
<?php if (using_default_password()): ?>
<div class="callout warn"><?= icon('shield-warning', '') ?><div><strong>Cambia tu contraseña inicial</strong><p>Por seguridad te recomendamos crear una contraseña propia. Hazlo en <a href="<?= e(url('admin/settings.php#cuenta')) ?>">Correo y seguridad</a>.</p></div></div>
<?php endif; ?>

<div class="stat-grid">
  <a class="stat-card" href="<?= e(url('admin/messages.php')) ?>"><span class="sc-ic"><?= icon('envelope-simple', '') ?></span><div><strong><?= $unread ?></strong><span>Mensajes nuevos</span></div></a>
  <a class="stat-card" href="<?= e(url('admin/messages.php')) ?>"><span class="sc-ic blue"><?= icon('chats', '') ?></span><div><strong><?= $total ?></strong><span>Solicitudes recibidas</span></div></a>
  <a class="stat-card" href="<?= e(url('admin/layout.php')) ?>"><span class="sc-ic green"><?= icon('layout', '') ?></span><div><strong><?= $active ?>/<?= count($labels) ?></strong><span>Secciones visibles</span></div></a>
  <a class="stat-card" href="<?= e(url()) ?>" target="_blank" rel="noopener"><span class="sc-ic navy"><?= icon('globe', '') ?></span><div><strong>En línea</strong><span>Ver el sitio web</span></div></a>
</div>

<div class="grid-2">
  <section class="card">
    <h2><?= icon('lightning', '') ?> Accesos rápidos</h2>
    <div class="shortcuts">
      <?php foreach ($shortcuts as $s): ?><a href="<?= e(url('admin/' . $s[0])) ?>"><?= icon($s[1], '') ?><span><?= e($s[2]) ?></span></a><?php endforeach; ?>
    </div>
    <p class="muted small">Todo lo que ves en la página se puede cambiar desde el menú de la izquierda: textos, imágenes, iconos, precios, colores, secciones y más.</p>
  </section>
  <section class="card">
    <h2><?= icon('envelope-open', '') ?> Últimas solicitudes</h2>
    <?php if (!$msgs): ?>
      <p class="empty">Aún no hay solicitudes. Cuando un cliente use el formulario, aparecerá aquí y llegará a tu correo.</p>
    <?php else: ?>
      <ul class="mini-list">
        <?php foreach (array_slice($msgs, 0, 5) as $m): $d = $m['data']; ?>
          <li class="<?= empty($m['read']) ? 'unread' : '' ?>"><a href="<?= e(url('admin/messages.php?id=' . $m['id'])) ?>">
            <strong><?= e($d['nombre']) ?></strong><span><?= e($d['servicio']) ?></span><time><?= e(date('d/m H:i', strtotime($m['date']))) ?></time></a></li>
        <?php endforeach; ?>
      </ul>
      <a class="link" href="<?= e(url('admin/messages.php')) ?>">Ver todas →</a>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2><?= icon('heartbeat', '') ?> Estado del sistema</h2>
  <ul class="checks">
    <?php foreach ($checks as $c): ?><li class="<?= $c[1] ? 'ok' : 'warn' ?>"><?= icon($c[1] ? 'check-circle' : 'warning-circle', '') ?><div><strong><?= e($c[0]) ?></strong><span><?= e($c[2]) ?></span></div></li><?php endforeach; ?>
  </ul>
</section>
<?php admin_footer();
