<?php
$sym = (string) $q['currency_symbol'];
$statuses = \App\Models\Quote::STATUSES;
$light = \App\Models\Quote::trafficLight($q);
$canWrite = !$readonly;
page('barActions',
    '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/cotizaciones/' . $q['id'] . '/pdf')) . '" target="_blank" rel="noopener">Ver PDF</a>'
);
?>
<div class="tabs" role="navigation" aria-label="Navegación de la cotización">
  <a href="<?= e(url('/panel/tablero')) ?>">&larr; Tablero</a>
  <?php foreach ($versions as $v): ?>
    <a href="<?= e(url('/panel/cotizaciones/' . $v['id'])) ?>"<?= (int) $v['id'] === (int) $q['id'] ? ' aria-current="page"' : '' ?>>
      v<?= e($v['version']) ?> · <?= e(money((float) $v['total'], $sym)) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="qeditor" data-quote-editor data-item-url="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/item')) ?>">
  <!-- ================================================ columna principal -->
  <div class="stack">
    <div class="card">
      <div class="card__head">
        <span class="secnum">01/</span>
        <h2>Productos de la cotización</h2>
        <span class="badge<?= $q['status'] === 'aprobada' ? ' badge--ok' : ($q['status'] === 'perdida' ? ' badge--bad' : '') ?> ml-auto"><?= e($statuses[$q['status']]['label']) ?></span>
      </div>

      <?php if ($canWrite): ?>
      <div class="card__body" style="border-bottom:1px solid var(--paper-2)">
        <div class="flex" style="gap:10px;align-items:flex-end;flex-wrap:wrap">
          <div class="psearch" data-psearch style="flex:1;min-width:260px">
            <label class="label" for="psearch">Agregar producto (código o nombre)</label>
            <input class="input" id="psearch" autocomplete="off" placeholder="Escriba SM-21, empaque, 3/4&quot;…" aria-controls="pslist">
            <div class="psearch__list" id="pslist" role="listbox" aria-label="Resultados"></div>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-free-line>+ Línea libre</button>
        </div>
      </div>
      <?php endif; ?>

      <div class="card__body card__body--flush tablescroll">
        <table class="itemtable">
          <caption class="sr-only">Líneas de la cotización</caption>
          <thead><tr>
            <th scope="col">#</th><th scope="col">Código</th><th scope="col">Descripción</th>
            <th scope="col">Cant.</th><th scope="col">P. unit.</th><th scope="col">Desc. %</th><th scope="col">Total</th><th scope="col"><span class="sr-only">Quitar</span></th>
          </tr></thead>
          <tbody data-items>
            <?php if (!$items): ?>
              <tr><td colspan="8" style="padding:26px;text-align:center;color:var(--steel)">Aún no hay productos. Búsquelos por código arriba.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $i => $it): ?>
              <tr data-item="<?= e($it['id']) ?>">
                <td style="color:var(--steel);font-size:.75rem"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></td>
                <td><span class="code-chip"><?= e($it['code'] ?: '—') ?></span></td>
                <td>
                  <input class="txt" data-f="name" value="<?= e($it['name']) ?>" aria-label="Descripción"<?= $canWrite ? '' : ' readonly' ?>>
                  <?php if ($it['specs']): ?><div class="small muted" style="padding:0 9px"><?= e($it['specs']) ?></div><?php endif; ?>
                  <input class="txt small" data-f="notes" placeholder="Nota para esta línea…" value="<?= e($it['notes']) ?>" aria-label="Nota"<?= $canWrite ? '' : ' readonly' ?>>
                </td>
                <td style="width:88px"><input type="number" step="0.01" min="0.01" data-f="qty" value="<?= e((float) $it['qty']) ?>" aria-label="Cantidad"<?= $canWrite ? '' : ' readonly' ?>></td>
                <td style="width:112px"><input type="number" step="0.01" min="0" data-f="unit_price" value="<?= e((float) $it['unit_price']) ?>" aria-label="Precio unitario"<?= $canWrite ? '' : ' readonly' ?>></td>
                <td style="width:80px"><input type="number" step="0.01" min="0" max="100" data-f="discount_pct" value="<?= e((float) $it['discount_pct']) ?>" aria-label="Descuento"<?= $canWrite ? '' : ' readonly' ?>></td>
                <td class="num line" style="width:118px"><?= e(money((float) $it['line_total'], $sym)) ?></td>
                <td style="width:42px"><?php if ($canWrite): ?><button type="button" class="del" data-del="<?= e($it['id']) ?>" aria-label="Quitar línea">&times;</button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card__body" style="border-top:1px solid var(--line)">
        <div class="totals" style="max-width:340px;margin-left:auto">
          <div><span>Subtotal</span><b data-t-subtotal><?= e(money((float) $q['subtotal'], $sym)) ?></b></div>
          <div><span>Descuento global</span><b data-t-discount><?= e(money((float) $q['discount_amount'], $sym)) ?></b></div>
          <div><span><?= e($company['tax_label']) ?> <?= e(qty((float) $q['tax_rate'])) ?>%</span><b data-t-tax><?= e(money((float) $q['tax_amount'], $sym)) ?></b></div>
          <div class="grand"><span>Total</span><b data-t-total><?= e(money((float) $q['total'], $sym)) ?></b></div>
        </div>
      </div>
    </div>

    <!-- condiciones -->
    <form class="card" method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/guardar')) ?>">
      <?= csrf_field() ?>
      <div class="card__head"><span class="secnum">02/</span><h2>Cliente y condiciones</h2>
        <?php if ($canWrite): ?><button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar cambios</button><?php endif; ?>
      </div>
      <div class="card__body">
        <div class="row-2">
          <div class="field"><label for="customer_id">Cliente del CRM</label>
            <select class="select" id="customer_id" name="customer_id"<?= $canWrite ? '' : ' disabled' ?>>
              <option value="">— Sin vincular —</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= e($c['id']) ?>"<?= (int) $q['customer_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['nit'] ? ' · ' . e($c['nit']) : '' ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="contact_name">Contacto</label>
            <input class="input" id="contact_name" name="contact_name" maxlength="140" value="<?= e($q['contact_name']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
        </div>
        <div class="row-2">
          <div class="field"><label for="contact_company">Empresa</label>
            <input class="input" id="contact_company" name="contact_company" maxlength="180" value="<?= e($q['contact_company']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
          <div class="field"><label for="contact_nit">NIT</label>
            <input class="input" id="contact_nit" name="contact_nit" maxlength="30" value="<?= e($q['contact_nit']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
        </div>
        <div class="row-2">
          <div class="field"><label for="contact_phone">Teléfono</label>
            <input class="input" id="contact_phone" name="contact_phone" maxlength="40" value="<?= e($q['contact_phone']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
          <div class="field"><label for="contact_email">Correo</label>
            <input class="input" id="contact_email" name="contact_email" type="email" maxlength="150" value="<?= e($q['contact_email']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
        </div>

        <hr style="margin:8px 0 20px">

        <div class="row-3">
          <div class="field"><label for="discount_type">Descuento global</label>
            <select class="select" id="discount_type" name="discount_type"<?= $canWrite ? '' : ' disabled' ?>>
              <?php foreach (['ninguno' => 'Sin descuento', 'porcentaje' => 'Porcentaje %', 'monto' => 'Monto fijo'] as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= $q['discount_type'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="discount_value">Valor del descuento</label>
            <input class="input" id="discount_value" name="discount_value" type="number" step="0.01" min="0" value="<?= e((float) $q['discount_value']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
          <div class="field"><label for="tax_rate"><?= e($company['tax_label']) ?> %</label>
            <input class="input" id="tax_rate" name="tax_rate" type="number" step="0.001" min="0" max="100" value="<?= e((float) $q['tax_rate']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
        </div>
        <div class="row-3">
          <div class="field"><label for="validity_days">Validez (días)</label>
            <input class="input" id="validity_days" name="validity_days" type="number" min="1" max="365" value="<?= e((int) $q['validity_days']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
          <div class="field"><label for="delivery_time">Tiempo de entrega</label>
            <input class="input" id="delivery_time" name="delivery_time" maxlength="160" value="<?= e($q['delivery_time']) ?>" placeholder="8 días hábiles"<?= $canWrite ? '' : ' readonly' ?>></div>
          <div class="field"><label for="next_followup_at">Próximo seguimiento</label>
            <input class="input" id="next_followup_at" name="next_followup_at" type="date" value="<?= e($q['next_followup_at']) ?>"<?= $canWrite ? '' : ' readonly' ?>></div>
        </div>
        <div class="field"><label for="payment_terms">Condiciones de pago</label>
          <input class="input" id="payment_terms" name="payment_terms" maxlength="190" value="<?= e($q['payment_terms']) ?>" placeholder="50% anticipo, 50% contra entrega"<?= $canWrite ? '' : ' readonly' ?>></div>
        <div class="row-2">
          <div class="field"><label for="notes">Observaciones (salen en el PDF)</label>
            <textarea class="textarea" id="notes" name="notes" rows="3" maxlength="4000"<?= $canWrite ? '' : ' readonly' ?>><?= e($q['notes']) ?></textarea></div>
          <div class="field"><label for="internal_notes">Notas internas (no salen en el PDF)</label>
            <textarea class="textarea" id="internal_notes" name="internal_notes" rows="3" maxlength="4000"<?= $canWrite ? '' : ' readonly' ?>><?= e($q['internal_notes']) ?></textarea></div>
        </div>
      </div>
    </form>

    <!-- bitácora -->
    <div class="card">
      <div class="card__head"><span class="secnum">03/</span><h2>Bitácora de seguimiento</h2>
        <span class="badge ml-auto"><span class="qcard__dot dot-<?= e($light) ?>" style="display:inline-block;margin-right:6px"></span><?= e(humanDays((string) ($q['last_contact_at'] ?: $q['created_at']))) ?></span>
      </div>
      <?php if ($canWrite): ?>
      <div class="card__body" style="border-bottom:1px solid var(--paper-2)">
        <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/nota')) ?>">
          <?= csrf_field() ?>
          <div class="flex" style="gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="field" style="margin:0"><label for="ev_type">Tipo</label>
              <select class="select" id="ev_type" name="type">
                <option value="nota">Nota interna</option>
                <option value="llamada">Llamada</option>
                <option value="correo">Correo</option>
                <option value="whatsapp">WhatsApp</option>
              </select></div>
            <div class="field" style="margin:0;flex:1;min-width:240px"><label for="ev_body">Detalle</label>
              <input class="input" id="ev_body" name="body" maxlength="2000" required placeholder="Se llamó a compras, piden precio por volumen…"></div>
            <button class="btn btn--ghost btn--sm" type="submit">Registrar</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
      <div class="card__body">
        <ul class="timeline">
          <?php foreach ($events as $ev): ?>
            <li class="<?= in_array($ev['type'], ['estado', 'cliente'], true) ? 'is-key' : '' ?>">
              <time><?= e(fechaHora((string) $ev['created_at'])) ?> · <?= e($ev['actor'] ?: 'Sistema') ?></time>
              <b><?= e($ev['title']) ?></b>
              <?php if ($ev['body']): ?><p><?= nl2br(e($ev['body'])) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php if (!$events): ?><li><b class="muted">Sin movimientos registrados.</b></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- ========================================================== lateral -->
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2><?= e($q['number']) ?></h2></div>
      <div class="card__body">
        <table class="spectable" style="border-top:0">
          <tbody>
            <tr><th scope="row">Creada</th><td><?= e(fechaCorta((string) $q['created_at'])) ?></td></tr>
            <tr><th scope="row">Origen</th><td><?= e($q['source'] === 'web' ? 'Sitio web' : 'Panel') ?></td></tr>
            <tr><th scope="row">Enviada</th><td><?= $q['sent_at'] ? e(fechaCorta((string) $q['sent_at'])) : '—' ?></td></tr>
            <tr><th scope="row">Vista por el cliente</th><td><?= $q['viewed_at'] ? e(fechaCorta((string) $q['viewed_at'])) : 'aún no' ?></td></tr>
            <tr><th scope="row">Vence</th><td><?= $q['valid_until'] ? e(fechaCorta((string) $q['valid_until'])) : '—' ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($canWrite): ?>
    <div class="card">
      <div class="card__head"><h2>Enviar al cliente</h2></div>
      <div class="card__body">
        <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/enviar')) ?>" data-confirm="Se generará el PDF y se enviará por correo. ¿Continuar?">
          <?= csrf_field() ?>
          <div class="field"><label for="to">Correo destino</label>
            <input class="input" id="to" name="to" type="email" value="<?= e($q['contact_email']) ?>" required></div>
          <div class="field"><label for="message">Mensaje (opcional)</label>
            <textarea class="textarea" id="message" name="message" rows="3" maxlength="2000" placeholder="Estimado cliente, adjunto la cotización solicitada…"></textarea></div>
          <button class="btn btn--accent btn--block" type="submit">Generar PDF y enviar <span class="arw" aria-hidden="true">&rarr;</span></button>
        </form>
        <a class="btn btn--ghost btn--block" style="margin-top:10px" href="<?= e(\App\Models\Quote::whatsappLink($company, $q)) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>
        <div style="margin-top:16px">
          <div class="label">Enlace de seguimiento del cliente</div>
          <div class="copyfield">
            <label class="sr-only" for="tk">Enlace de seguimiento</label>
            <input id="tk" value="<?= e(\App\Models\Quote::trackUrl($q)) ?>" readonly>
            <button type="button" data-copy="tk">Copiar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Estado</h2></div>
      <div class="card__body">
        <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/estado')) ?>" id="statusForm">
          <?= csrf_field() ?>
          <div class="field"><label for="status">Mover a</label>
            <select class="select" id="status" name="status">
              <?php foreach ($statuses as $k => $m): ?>
                <option value="<?= e($k) ?>"<?= $q['status'] === $k ? ' selected' : '' ?>><?= e($m['label']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="lost_reason">Motivo (si se pierde)</label>
            <select class="select" id="lost_reason" name="lost_reason">
              <option value="">—</option>
              <?php foreach ($lostReasons as $lr): ?>
                <option value="<?= e($lr) ?>"<?= $q['lost_reason'] === $lr ? ' selected' : '' ?>><?= e(ucfirst($lr)) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="lost_detail">Detalle</label>
            <input class="input" id="lost_detail" name="lost_detail" maxlength="255" value="<?= e($q['lost_detail']) ?>"></div>
          <button class="btn btn--ghost btn--block" type="submit">Actualizar estado</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Acciones</h2></div>
      <div class="card__body stack-sm">
        <a class="btn btn--ghost btn--block" href="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Ver / descargar PDF</a>
        <?php if ($q['status'] === 'aprobada'): ?>
          <a class="btn btn--ghost btn--block" href="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/orden')) ?>" target="_blank" rel="noopener">Orden de trabajo</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/version')) ?>" data-confirm="Se creará la versión siguiente y la actual quedará en historial. ¿Continuar?">
          <?= csrf_field() ?><button class="btn btn--ghost btn--block" type="submit">Crear nueva versión</button>
        </form>
        <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/duplicar')) ?>">
          <?= csrf_field() ?><button class="btn btn--ghost btn--block" type="submit">Duplicar cotización</button>
        </form>
        <?php if (\App\Core\Auth::isAdmin()): ?>
          <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/asignar')) ?>">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom:8px"><label for="user_id">Asignar a</label>
              <select class="select" id="user_id" name="user_id">
                <?php foreach ($sellers as $s): ?>
                  <option value="<?= e($s['id']) ?>"<?= (int) $q['user_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <button class="btn btn--ghost btn--block" type="submit">Reasignar</button>
          </form>
          <form method="post" action="<?= e(url('/panel/cotizaciones/' . $q['id'] . '/eliminar')) ?>" data-confirm="Se eliminará la cotización y su historial. Esta acción no se puede deshacer.">
            <?= csrf_field() ?><button class="btn btn--danger btn--block" type="submit">Eliminar cotización</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
