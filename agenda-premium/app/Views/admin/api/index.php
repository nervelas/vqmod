<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
$base = abs_url('/api/v1');
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">API y claves</h1><p class="page-sub">Conecta tu agenda con tu sitio web, tu facturación u otras herramientas usando una clave personal.</p></div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('/api-docs')) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Documentación de la API</a></div>
  </div>

  <?php if ($newKey) : ?>
    <section class="card card-gold mb-4" role="alert" aria-labelledby="h-nueva">
      <div class="card-head"><h2 id="h-nueva" class="serif">Tu clave nueva: <?= e($newKey['name']) ?></h2></div>
      <div class="card-body stack">
        <p>Cópiala ahora y guárdala en un lugar seguro. <strong>Por seguridad no volverá a mostrarse</strong>; si la pierdes, tendrás que crear otra.</p>
        <div class="input-group"><input class="input mono" id="new-key" readonly value="<?= e($newKey['key']) ?>" aria-label="Clave de API nueva"><button class="btn btn-gold" type="button" data-copy="#new-key"><?= icon('copy') ?>Copiar clave</button></div>
        <p class="muted">Alcance: <?= $newKey['scope'] === 'write' ? 'lectura y escritura' : 'solo lectura' ?>.</p>
      </div>
    </section>
  <?php endif; ?>

  <div class="grid cols-2 mb-4">
    <section class="card" aria-labelledby="h-crear">
      <div class="card-head"><h2 id="h-crear" class="serif">Crear una clave</h2></div>
      <form method="post" action="<?= e(url('/admin/api/claves')) ?>" class="card-body stack">
        <?= csrf_field() ?>
        <div class="field"><label for="k-name">Nombre de la integración</label><input class="input" id="k-name" name="name" maxlength="120" required placeholder="Sitio web, facturación…"></div>
        <div class="field"><label for="k-scope">Alcance</label>
          <select class="select" id="k-scope" name="scope"><option value="read">Solo lectura (consultar citas, clientes y eventos)</option><option value="write">Lectura y escritura (también crear y cambiar citas)</option></select></div>
        <div class="form-actions"><button class="btn btn-gold" type="submit"><?= icon('key') ?>Crear clave</button></div>
      </form>
    </section>
    <section class="card" aria-labelledby="h-ej">
      <div class="card-head"><h2 id="h-ej" class="serif">Ejemplo de uso</h2></div>
      <div class="card-body stack">
        <p class="muted">Envía la clave en la cabecera <span class="mono">Authorization</span>.</p>
        <pre class="p3-code" tabindex="0"><code>curl -H "Authorization: Bearer ap_TU_CLAVE" \
     "<?= e($base) ?>/bookings?limit=10"</code></pre>
        <p class="muted">Las claves de solo lectura reciben un error 403 si intentan modificar datos. Una clave revocada responde 401.</p>
      </div>
    </section>
  </div>

  <section class="card" aria-labelledby="h-claves">
    <div class="card-head"><h2 id="h-claves" class="serif">Claves existentes</h2></div>
    <?php if (!$keys) : ?>
      <div class="card-body"><div class="empty"><?= icon('key') ?><p class="empty-title">Aún no has creado claves</p><p class="empty-text">Crea una para empezar a consultar tu agenda desde otras aplicaciones.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Claves de API</caption>
        <thead><tr><th scope="col">Nombre</th><th scope="col">Clave</th><th scope="col">Alcance</th><th scope="col" class="hide-sm">Último uso</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($keys as $k) : $rev = $k['revoked_at'] !== null; ?>
          <tr<?= $rev ? ' class="muted"' : '' ?>>
            <th scope="row"><?= e($k['name']) ?><div class="muted">Creada <?= e(Fmt::dateShort((string) $k['created_at'], $tz)) ?><?= $k['user_name'] ? ' por ' . e($k['user_name']) : '' ?></div></th>
            <td class="mono"><?= e($k['key_prefix']) ?>…</td>
            <td><span class="badge <?= $k['scope'] === 'write' ? 'badge-gold' : 'badge-muted' ?>"><?= $k['scope'] === 'write' ? 'Escritura' : 'Lectura' ?></span></td>
            <td class="hide-sm nowrap"><?= $k['last_used_at'] ? e(Fmt::dateShort((string) $k['last_used_at'], $tz) . ' ' . Fmt::time((string) $k['last_used_at'], $tz)) : 'Nunca' ?></td>
            <td><span class="badge <?= $rev ? 'badge-err' : 'badge-ok' ?>"><?= $rev ? 'Revocada' : 'Activa' ?></span></td>
            <td class="right"><?php if (!$rev) : ?><form class="inline" method="post" action="<?= e(url('/admin/api/claves/' . (int) $k['id'] . '/revocar')) ?>" data-confirm="¿Revocar la clave &quot;<?= e($k['name']) ?>&quot;? Las integraciones que la usen dejarán de funcionar."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?>Revocar</button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
