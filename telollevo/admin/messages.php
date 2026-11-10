<?php
require __DIR__ . '/_boot.php';
auth_require();

/* --- Exportar CSV --- */
if (isset($_GET['export'])) {
    $msgs = (array)store_read('messages', []);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="solicitudes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = ['nombre', 'telefono', 'email', 'servicio', 'interes', 'recogida', 'entrega', 'descripcion', 'tamano', 'cuando', 'fecha', 'hora', 'comentarios'];
    fputcsv($out, array_merge(['Fecha'], $cols, ['Leído']));
    $safe = function ($v) { $v = (string)$v; return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v; };
    foreach ($msgs as $m) {
        $row = [$m['date']];
        foreach ($cols as $c) $row[] = $safe($m['data'][$c] ?? '');
        $row[] = empty($m['read']) ? 'No' : 'Sí';
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/* --- Acciones --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    $mid = preg_replace('/[^a-f0-9]/', '', (string)($_POST['id'] ?? ''));
    store_update('messages', function ($list) use ($act, $mid) {
        $out = [];
        foreach ((array)$list as $m) {
            if ($act === 'readall') { $m['read'] = true; $out[] = $m; continue; }
            if (($m['id'] ?? '') === $mid) {
                if ($act === 'delete') continue;
                if ($act === 'read') $m['read'] = true;
                if ($act === 'unread') $m['read'] = false;
            }
            $out[] = $m;
        }
        return $out;
    }, []);
    flash('ok', $act === 'delete' ? 'Mensaje eliminado.' : ($act === 'readall' ? 'Todos los mensajes marcados como leídos.' : 'Mensaje actualizado.'));
    header('Location: ' . url('admin/messages.php' . (isset($_POST['page']) ? '?page=' . (int)$_POST['page'] : '')), true, 303);
    exit;
}

$openId = preg_replace('/[^a-f0-9]/', '', (string)($_GET['id'] ?? ''));
if ($openId !== '') {
    store_update('messages', function ($list) use ($openId) {
        foreach ($list as &$m) { if (($m['id'] ?? '') === $openId) $m['read'] = true; }
        return $list;
    }, []);
}
$msgs = (array)store_read('messages', []);
$per = 15; $pages = max(1, (int)ceil(count($msgs) / $per));
$page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$slice = array_slice($msgs, ($page - 1) * $per, $per);

function wa_phone(string $p): string { $d = preg_replace('/\D/', '', $p); return strlen($d) === 8 ? '502' . $d : $d; }
$lab = ['nombre' => 'Nombre', 'telefono' => 'Teléfono', 'email' => 'Correo', 'servicio' => 'Servicio', 'interes' => 'Tarjeta de interés', 'recogida' => 'Recogida', 'entrega' => 'Entrega', 'descripcion' => 'Qué se envía', 'tamano' => 'Volumen de entregas', 'cuando' => 'Cuándo', 'fecha' => 'Fecha', 'hora' => 'Hora', 'comentarios' => 'Comentarios'];

admin_header('Mensajes', 'messages', 'Solicitudes recibidas desde el formulario de la página.');
?>
<div class="toolbar">
  <span class="muted"><?= count($msgs) ?> solicitud(es) · <?= unread_count() ?> sin leer</span>
  <div class="tb-actions">
    <?php if ($msgs): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="readall"><button class="btn btn-soft btn-sm" type="submit"><?= icon('check', '') ?><span>Marcar todo como leído</span></button></form>
      <a class="btn btn-soft btn-sm" href="<?= e(url('admin/messages.php?export=csv')) ?>"><?= icon('download', '') ?><span>Exportar CSV</span></a>
    <?php endif; ?>
  </div>
</div>
<?php if (!$msgs): ?>
  <div class="card empty-card"><?= icon('envelope-open', '') ?><h2>Todavía no hay mensajes</h2><p>Cuando un cliente complete el formulario de la página, su solicitud aparecerá aquí y también llegará a tu correo.</p></div>
<?php endif; ?>
<div class="msg-list">
<?php foreach ($slice as $m): $d = $m['data']; $unread = empty($m['read']); ?>
  <details class="msg <?= $unread ? 'unread' : '' ?>" <?= $m['id'] === $openId ? 'open' : '' ?>>
    <summary>
      <span class="dot"></span>
      <span class="m-name"><?= e($d['nombre']) ?></span>
      <span class="m-serv"><?= e($d['servicio']) ?></span>
      <time><?= e(date('d/m/Y H:i', strtotime($m['date']))) ?></time>
      <?= icon('caret-down', 'caret') ?>
    </summary>
    <div class="msg-body">
      <dl>
        <?php foreach ($lab as $k => $l): if (($d[$k] ?? '') === '') continue; ?>
          <div><dt><?= e($l) ?></dt><dd><?= nl2br(e($d[$k])) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <p class="mail-state <?= !empty($m['mail_ok']) ? 'ok' : 'warn' ?>"><?= icon(!empty($m['mail_ok']) ? 'check-circle' : 'warning-circle', '') ?>
        <?= !empty($m['mail_ok']) ? 'Correo enviado a tu bandeja.' : 'El correo no pudo enviarse' . (!empty($m['mail_err']) ? ' (' . e($m['mail_err']) . ')' : '') . '. El mensaje quedó guardado aquí. Revisa Correo y seguridad.' ?></p>
      <div class="msg-actions">
        <a class="btn btn-wa btn-sm" target="_blank" rel="noopener noreferrer" href="https://wa.me/<?= e(wa_phone($d['telefono'])) ?>?text=<?= rawurlencode('Hola ' . $d['nombre'] . ', te escribimos de ' . get_content()['site']['brand'] . ' por tu solicitud de ' . $d['servicio'] . '.') ?>"><?= icon('whatsapp-logo', '', 'fill') ?><span>Responder por WhatsApp</span></a>
        <a class="btn btn-soft btn-sm" href="tel:+<?= e(wa_phone($d['telefono'])) ?>"><?= icon('phone-call', '') ?><span>Llamar</span></a>
        <?php if ($d['email'] !== ''): ?><a class="btn btn-soft btn-sm" href="mailto:<?= e($d['email']) ?>?subject=<?= rawurlencode('Tu solicitud de envío') ?>"><?= icon('envelope-simple', '') ?><span>Correo</span></a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>"><input type="hidden" name="page" value="<?= $page ?>">
          <button class="btn btn-text btn-sm" name="action" value="<?= $unread ? 'read' : 'unread' ?>" type="submit"><?= $unread ? 'Marcar como leído' : 'Marcar como no leído' ?></button>
          <button class="btn btn-danger btn-sm" name="action" value="delete" type="submit" data-confirm="¿Eliminar este mensaje definitivamente?"><?= icon('trash-simple', '') ?><span>Eliminar</span></button>
        </form>
      </div>
    </div>
  </details>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Páginas"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'cur' : '' ?>" href="<?= e(url('admin/messages.php?page=' . $i)) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php admin_footer();
