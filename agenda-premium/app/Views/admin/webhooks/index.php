<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
$stLabel = ['pending' => ['Pendiente', 'badge-warn'], 'delivered' => ['Entregado', 'badge-ok'], 'failed' => ['Falló', 'badge-err']];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Webhooks</h1><p class="page-sub">Avisa a otros sistemas cuando pasa algo con una cita. También sirven para conectar con herramientas como Zapier o Make.</p></div>
    <div class="page-actions"><a class="btn btn-gold" href="<?= e(url('/admin/webhooks/nuevo')) ?>"><?= icon('plus') ?>Nuevo webhook</a></div>
  </div>

  <section class="card mb-4" aria-labelledby="h-hooks">
    <div class="card-head"><h2 id="h-hooks" class="serif">Destinos configurados</h2></div>
    <?php if (!$hooks) : ?>
      <div class="card-body"><div class="empty"><?= icon('code') ?><p class="empty-title">Todavía no hay webhooks</p><p class="empty-text">Crea uno con la dirección que recibirá los avisos. Cada envío va firmado para que puedas comprobar que viene de tu agenda.</p><a class="btn btn-gold" href="<?= e(url('/admin/webhooks/nuevo')) ?>">Crear el primero</a></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Webhooks configurados</caption>
        <thead><tr><th scope="col">Nombre</th><th scope="col" class="hide-sm">Dirección</th><th scope="col" class="hide-sm">Eventos</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($hooks as $h) : ?>
          <tr>
            <th scope="row"><?= e($h['name']) ?><div class="muted"><?= (int) $h['total'] ?> envíos<?= (int) $h['failed'] > 0 ? ' · ' . (int) $h['failed'] . ' con error' : '' ?></div></th>
            <td class="hide-sm p3-url mono"><?= e($h['url']) ?></td>
            <td class="hide-sm"><?= $h['events'] === '*' ? 'Todos' : e((string) count(explode(',', (string) $h['events']))) . ' eventos' ?></td>
            <td><span class="badge <?= (int) $h['active'] ? 'badge-ok' : 'badge-muted' ?>"><?= (int) $h['active'] ? 'Activo' : 'Pausado' ?></span></td>
            <td class="right nowrap">
              <form class="inline" method="post" action="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/prueba')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= icon('play') ?>Enviar prueba</button></form>
              <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/editar')) ?>" aria-label="Editar <?= e($h['name']) ?>"><?= icon('edit') ?></a>
              <form class="inline" method="post" action="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/estado')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $h['active'] ? 'Pausar' : 'Activar' ?></button></form>
              <form class="inline" method="post" action="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar este webhook y todo su historial de entregas?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Eliminar <?= e($h['name']) ?>"><?= icon('trash') ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="card mb-4" aria-labelledby="h-entregas">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-entregas" class="serif">Registro de entregas</h2>
      <form method="get" action="<?= e(url('/admin/webhooks')) ?>" class="row row-wrap gap-2">
        <label class="sr-only" for="fl-hook">Webhook</label>
        <select class="select" id="fl-hook" name="webhook"><option value="0">Todos los webhooks</option><?php foreach ($hooks as $h) : ?><option value="<?= (int) $h['id'] ?>"<?= sel($hookId, $h['id']) ?>><?= e($h['name']) ?></option><?php endforeach; ?></select>
        <label class="sr-only" for="fl-st">Estado</label>
        <select class="select" id="fl-st" name="estado"><option value="">Cualquier estado</option><?php foreach ($stLabel as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($status, $k) ?>><?= e($l[0]) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('filter') ?>Filtrar</button>
      </form>
    </div>
    <?php if (!$deliveries) : ?>
      <div class="card-body"><div class="empty"><?= icon('inbox') ?><p class="empty-title">Sin entregas todavía</p><p class="empty-text">Aquí verás cada aviso enviado, su resultado y podrás reenviarlo.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table table-sm">
        <caption class="sr-only">Entregas de webhooks</caption>
        <thead><tr><th scope="col">Fecha</th><th scope="col">Webhook</th><th scope="col">Evento</th><th scope="col">Estado</th><th scope="col" class="right">Intentos</th><th scope="col" class="hide-sm">Respuesta</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($deliveries as $d) : $s = $stLabel[$d['status']] ?? ['—', 'badge-muted']; ?>
          <tr>
            <td class="nowrap mono"><?= e(Fmt::dateShort((string) $d['created_at'], $tz)) ?> <?= e(Fmt::time((string) $d['created_at'], $tz)) ?></td>
            <td><?= e($d['hook_name']) ?></td>
            <td class="mono"><?= e($d['event']) ?></td>
            <td><span class="badge <?= e($s[1]) ?>"><?= e($s[0]) ?></span></td>
            <td class="right mono"><?= (int) $d['attempts'] ?></td>
            <td class="hide-sm p3-resp"><?php if ($d['response_code'] !== null) : ?><span class="mono"><?= (int) $d['response_code'] ?></span> <?php endif; ?><span class="muted"><?= e((string) $d['response_body']) ?></span></td>
            <td class="right"><form class="inline" method="post" action="<?= e(url('/admin/webhooks/entregas/' . (int) $d['id'] . '/reenviar')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?>Reenviar</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <?= partial('admin/webhooks/_docs') ?>
</div>
