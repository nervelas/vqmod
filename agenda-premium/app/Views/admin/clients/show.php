<?php
/** @var array $c @var array $tags @var array $notes @var array $bookings @var array $answers @var ?array $first @var array $files @var array $packages @var array $payments @var array $consents @var string $tz @var bool $isHost @var bool $isAdmin @var bool $canPay */
use App\Controllers\Admin\A1Support;
use App\Core\Fmt;
use App\Core\Str;

$id = (int) $c['id'];
$me = (int) (\App\Core\Auth::user()['id'] ?? 0);
$methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_onsite' => 'Tarjeta', 'link' => 'Enlace de pago', 'package' => 'Paquete', 'gift_card' => 'Certificado', 'other' => 'Otro'];
$pst = ['pending' => 'Pendiente', 'verified' => 'Verificado', 'rejected' => 'Rechazado', 'refunded' => 'Reembolsado'];
$docs = ['privacy' => 'Política de privacidad', 'terms' => 'Términos', 'cookies' => 'Cookies'];
$answerBy = [];
foreach ($answers as $a) { $answerBy[] = $a; }
?>
<div class="page">
  <div class="page-head">
    <div>
      <p class="crumbs muted"><a href="<?= e(url('/admin/clientes')) ?>">Clientes</a> / Ficha</p>
      <h1 class="page-title serif"><?= e($c['name']) ?></h1>
      <p class="page-sub">
        <?php if ((int) $c['blocked']) : ?><span class="badge badge-err">Bloqueado</span><?php endif; ?>
        <?php if ((int) $c['noshow_count'] > 0) : ?><span class="badge badge-warn"><?= (int) $c['noshow_count'] ?> <?= (int) $c['noshow_count'] === 1 ? 'inasistencia' : 'inasistencias' ?></span><?php endif; ?>
        <span class="muted">Cliente desde <?= e(Fmt::dateShort($c['created_at'], $tz)) ?></span>
      </p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline" href="<?= e(url('/admin/clientes/' . $id . '/editar')) ?>"><?= icon('edit') ?>Editar</a>
      <a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva', ['cliente' => $id])) ?>"><?= icon('plus') ?>Nueva cita</a>
    </div>
  </div>

  <div class="split detail-split">
    <div class="stack">
      <section class="card">
        <div class="card-head"><h2 class="serif">Historial de citas</h2></div>
        <?php if (!$bookings) : ?>
          <div class="empty"><?= icon('calendar') ?><p class="empty-title serif">Sin citas todavía</p><p class="empty-text">Cuando agende, su historial aparecerá aquí.</p></div>
        <?php else : ?>
          <div class="table-wrap"><table class="table table-sm">
            <caption class="sr-only">Citas del cliente</caption>
            <thead><tr><th scope="col">Fecha</th><th scope="col">Tipo de cita</th><th scope="col" class="hide-sm">Atendió</th><th scope="col">Estado</th></tr></thead>
            <tbody><?php foreach ($bookings as $b) : ?>
              <tr><td class="nowrap"><a class="row-link mono" href="<?= e(url('/admin/citas/' . $b['id'])) ?>"><?= e(Fmt::dateShort($b['starts_at'], $tz)) ?> <?= e(Fmt::time($b['starts_at'], $tz)) ?></a></td>
                <td><?= e($b['event_name']) ?></td><td class="hide-sm"><?= e($b['host_name']) ?></td>
                <td><span class="badge <?= e(A1Support::statusBadge($b['status'])) ?>"><?= e(A1Support::statusLabel($b['status'])) ?></span></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </section>

      <section class="card" id="notas">
        <div class="card-head"><h2 class="serif">Notas internas</h2><p class="muted small">Privadas: solo las ve tu equipo, nunca la persona.</p></div>
        <div class="card-body stack">
          <form method="post" action="<?= e(url('/admin/clientes/' . $id . '/notas')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="field"><label class="sr-only" for="note-body">Nueva nota</label><textarea class="textarea" id="note-body" name="body" rows="3" maxlength="4000" placeholder="Escribe una nota sobre esta persona…" required></textarea></div>
            <div><button class="btn btn-outline" type="submit">Agregar nota</button></div>
          </form>
          <?php if ($notes) : ?>
          <ul class="notes">
            <?php foreach ($notes as $n) : ?>
              <li class="note">
                <p class="pre"><?= e($n['body']) ?></p>
                <p class="small muted"><?= e($n['author'] ?: 'Equipo') ?> · <?= e(Fmt::dateShort($n['created_at'], $tz)) ?> <?= e(Fmt::time($n['created_at'], $tz)) ?>
                <?php if ($isAdmin || (int) $n['user_id'] === $me) : ?>
                  <form method="post" action="<?= e(url('/admin/clientes/' . $id . '/notas/' . $n['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar esta nota?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Eliminar</button></form>
                <?php endif; ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($answerBy) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Preguntas respondidas</h2></div>
        <dl class="dl card-body"><?php foreach ($answerBy as $a) : ?><div><dt><?= e($a['label']) ?></dt><dd><?= e($a['value']) ?> <a class="muted small" href="<?= e(url('/admin/citas/' . $a['booking_id'])) ?>">cita #<?= (int) $a['booking_id'] ?></a></dd></div><?php endforeach; ?></dl>
      </section>
      <?php endif; ?>

      <?php if ($canPay && ($packages || $payments)) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Paquetes y pagos</h2></div>
        <div class="card-body stack">
          <?php if ($packages) : ?><ul class="list-plain"><?php foreach ($packages as $pk) : ?>
            <li><strong><?= e($pk['name']) ?></strong> · quedan <span class="mono"><?= (int) $pk['remaining'] ?></span> de <?= (int) $pk['sessions'] ?> sesiones<?= $pk['expires_at'] ? ' · vence ' . e(Fmt::dateShort($pk['expires_at'], $tz)) : '' ?> <?= (int) $pk['paid'] ? '<span class="badge badge-ok">Pagado</span>' : '<span class="badge badge-warn">Por cobrar</span>' ?></li>
          <?php endforeach; ?></ul><?php endif; ?>
          <?php if ($payments) : ?><div class="table-wrap"><table class="table table-sm"><caption class="sr-only">Pagos</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Método</th><th scope="col">Estado</th><th scope="col" class="right">Monto</th></tr></thead><tbody>
            <?php foreach ($payments as $py) : ?><tr><td class="mono"><?= e(Fmt::dateShort($py['created_at'], $tz)) ?></td><td><?= e($methods[$py['method']] ?? $py['method']) ?></td><td><?= e($pst[$py['status']] ?? $py['status']) ?></td><td class="right mono"><?= e(money($py['amount'])) ?></td></tr><?php endforeach; ?>
          </tbody></table></div><?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!$isHost) : ?>
      <section class="card" id="archivos">
        <div class="card-head"><h2 class="serif">Archivos</h2></div>
        <div class="card-body stack">
          <?php if ($files) : ?><ul class="list-plain"><?php foreach ($files as $f) : ?>
            <li class="row row-between"><a href="<?= e(url('/f/' . $f['token'])) ?>"><?= icon('paperclip') ?><?= e($f['original_name']) ?></a>
              <span class="muted small"><?= e(number_format($f['size'] / 1024, 0)) ?> KB · <?= e(Fmt::dateShort($f['created_at'], $tz)) ?>
              <form method="post" action="<?= e(url('/admin/clientes/' . $id . '/archivo/' . $f['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar este archivo?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Eliminar</button></form></span></li>
          <?php endforeach; ?></ul><?php else : ?><p class="muted">Todavía no hay archivos en esta ficha.</p><?php endif; ?>
          <form method="post" action="<?= e(url('/admin/clientes/' . $id . '/archivo')) ?>" enctype="multipart/form-data" class="row row-wrap gap-2">
            <?= csrf_field() ?>
            <label class="sr-only" for="archivo">Archivo</label><input class="input" id="archivo" type="file" name="archivo" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx,.xls,.xlsx" required>
            <button class="btn btn-outline" type="submit"><?= icon('upload') ?>Subir</button>
          </form>
          <p class="hint">Imágenes, PDF y documentos de oficina, hasta 8 MB.</p>
        </div>
      </section>
      <?php endif; ?>

      <section class="card">
        <div class="card-head"><h2 class="serif">Consentimientos</h2></div>
        <?php if (!$consents) : ?><div class="empty"><p class="empty-text">No hay consentimientos registrados para esta persona.</p></div>
        <?php else : ?><ul class="card-body list-plain"><?php foreach ($consents as $k) : ?><li><?= e($docs[$k['document']] ?? $k['document']) ?> · versión <?= (int) $k['version'] ?> <span class="muted small">· <?= e(Fmt::dateShort($k['created_at'], $tz)) ?> <?= e(Fmt::time($k['created_at'], $tz)) ?><?= $k['ip_trunc'] ? ' · IP ' . e($k['ip_trunc']) : '' ?></span></li><?php endforeach; ?></ul><?php endif; ?>
      </section>
    </div>

    <aside class="stack">
      <section class="card card-gold">
        <div class="card-head"><h2 class="serif">Contacto</h2></div>
        <dl class="dl card-body">
          <div><dt>Teléfono</dt><dd><?= $c['phone'] ? '<a href="tel:+' . e($c['phone']) . '" class="mono">' . e(Str::phoneDisplay($c['phone'])) . '</a>' : '<span class="muted">—</span>' ?></dd></div>
          <div><dt>Correo</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '<span class="muted">—</span>' ?></dd></div>
          <div><dt>NIT</dt><dd class="mono"><?= $c['nit'] ? e($c['nit']) : '<span class="muted">—</span>' ?></dd></div>
          <div><dt>Zona horaria</dt><dd><?= $c['timezone'] ? e($c['timezone']) : '<span class="muted">La del negocio</span>' ?></dd></div>
          <div><dt>Origen</dt><dd><?= $c['source'] ? e($c['source']) : '<span class="muted">—</span>' ?><?php if ($first && ($first['utm_source'] || $first['referrer_host'])) : ?><br><span class="muted small">Primera cita: <?= e(implode(' / ', array_filter([$first['utm_source'], $first['utm_medium'], $first['utm_campaign'], $first['referrer_host']]))) ?></span><?php endif; ?></dd></div>
        </dl>
        <?php if ($c['phone']) : ?><div class="card-foot"><a class="btn btn-outline btn-block" href="<?= e(A1Support::waLink((string) $c['phone'], 'Hola ' . explode(' ', (string) $c['name'])[0] . ', te escribimos de ' . (string) setting('business_name', '') . '.')) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?>Escribir por WhatsApp</a></div><?php endif; ?>
      </section>

      <section class="card">
        <div class="card-head"><h2 class="serif">Etiquetas</h2></div>
        <form class="card-body stack" method="post" action="<?= e(url('/admin/clientes/' . $id . '/etiquetas')) ?>">
          <?= csrf_field() ?>
          <?php if ($tags) : ?><div class="chips"><?php foreach ($tags as $t) : ?><a class="chip" href="<?= e(url('/admin/clientes', ['etiqueta' => $t])) ?>"><?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
          <div class="field"><label class="sr-only" for="tags">Etiquetas</label><input class="input" id="tags" name="tags" value="<?= e(implode(', ', $tags)) ?>" maxlength="255" placeholder="vip, recurrente"><p class="hint">Sepáralas con comas.</p></div>
          <button class="btn btn-outline" type="submit">Guardar etiquetas</button>
        </form>
      </section>

      <?php if (!$isHost) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Inasistencias y bloqueo</h2></div>
        <form class="card-body stack" method="post" action="<?= e(url('/admin/clientes/' . $id . '/bloqueo')) ?>">
          <?= csrf_field() ?>
          <p><span class="mono big"><?= (int) $c['noshow_count'] ?></span> <?= (int) $c['noshow_count'] === 1 ? 'vez' : 'veces' ?> sin asistir.</p>
          <?php if ((int) $c['blocked']) : ?>
            <p class="muted small">Está bloqueado: no puede reservar en línea.</p>
            <button class="btn btn-outline" type="submit">Desbloquear</button>
          <?php else : ?>
            <input type="hidden" name="blocked" value="1">
            <p class="muted small">Si lo bloqueas, no podrá reservar en línea. Tú puedes seguir agendándole citas.</p>
            <button class="btn btn-danger" type="submit" data-confirm="¿Bloquear a este cliente?">Bloquear cliente</button>
          <?php endif; ?>
        </form>
      </section>

      <section class="card">
        <div class="card-head"><h2 class="serif">Fusionar y privacidad</h2></div>
        <div class="card-body stack">
          <a class="btn btn-outline btn-block" href="<?= e(url('/admin/clientes/fusionar', ['a' => $id])) ?>"><?= icon('users') ?>Fusionar con otro cliente</a>
          <?php if ($isAdmin) : ?>
            <a class="btn btn-outline btn-block" href="<?= e(url('/admin/clientes/' . $id . '/datos')) ?>"><?= icon('download') ?>Descargar sus datos (JSON)</a>
            <button class="btn btn-danger btn-block" type="button" data-modal-open="#erase-modal"><?= icon('trash') ?>Eliminar sus datos</button>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php if ($isAdmin) : ?>
<dialog class="modal" id="erase-modal" aria-labelledby="er-title">
  <form method="post" action="<?= e(url('/admin/clientes/' . $id . '/eliminar')) ?>">
    <?= csrf_field() ?>
    <div class="modal-head row row-between"><h2 class="serif" id="er-title">Eliminar los datos de esta persona</h2><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Cerrar"><?= icon('x') ?></button></div>
    <div class="modal-body stack">
      <div class="alert alert-err" role="note">Esta acción no se puede deshacer. Se borrarán sus datos personales, notas y archivos; las citas quedarán de forma anónima.</div>
      <div class="field"><label for="er-confirm">Escribe <strong>ELIMINAR</strong> para confirmar</label><input class="input" id="er-confirm" name="confirm" autocomplete="off" required></div>
      <div class="field"><label for="er-pw">Tu contraseña</label><input class="input" id="er-pw" name="password" type="password" autocomplete="current-password" required></div>
    </div>
    <div class="modal-foot row"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-danger" type="submit">Eliminar definitivamente</button></div>
  </form>
</dialog>
<?php endif; ?>
