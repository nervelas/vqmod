<?php
$sym = (string) $q['currency_symbol'];
$steps = \App\Models\Quote::CLIENT_STEPS;
$cur = $steps[$q['status']]['n'] ?? 1;
$labels = [1 => 'Recibida', 2 => 'En elaboración', 3 => 'Enviada', 4 => $q['status'] === 'aprobada' ? 'Aprobada' : ($q['status'] === 'perdida' ? 'Cerrada' : 'Aprobación')];
$when = [1 => $q['created_at'], 2 => $q['updated_at'], 3 => $q['sent_at'], 4 => $q['approved_at'] ?: $q['lost_at']];
$canAct = in_array($q['status'], ['enviada', 'negociacion'], true);
$showPrices = !in_array($q['status'], ['nueva', 'elaboracion'], true);
?>
<div class="section section--tight blueprint" style="padding-top:34px">
  <div class="wrap">
    <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>

    <div class="section__head" style="margin-bottom:24px">
      <div>
        <span class="kicker">Seguimiento de cotización</span>
        <h1 class="h1" style="margin-top:14px"><?= e($q['number']) ?></h1>
        <p class="lead" style="margin-top:12px">
          <?= e($q['contact_company'] ?: $q['contact_name']) ?> · emitida el <?= e(fechaLarga((string) $q['created_at'])) ?>
        </p>
      </div>
      <div style="text-align:right">
        <div class="label">Total</div>
        <?php if ($showPrices): ?>
          <div style="font-family:var(--f-display);font-size:clamp(1.7rem,3vw,2.4rem);letter-spacing:-.035em"><?= e(money((float) $q['total'], $sym)) ?></div>
          <?php if ($q['valid_until']): ?><p class="small muted" style="margin:4px 0 0">Válida hasta el <?= e(fechaCorta((string) $q['valid_until'])) ?></p><?php endif; ?>
        <?php else: ?>
          <div style="font-family:var(--f-display);font-size:1.3rem">En elaboración</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- estados -->
    <div class="steps" role="list" aria-label="Avance de la cotización">
      <?php for ($n = 1; $n <= 4; $n++): ?>
        <div class="step<?= $n < $cur ? ' is-done' : ($n === $cur ? ' is-cur' : '') ?>" role="listitem">
          <span class="step__n"><?= e(str_pad((string) $n, 2, '0', STR_PAD_LEFT)) ?>/</span>
          <div class="step__label"><?= e($labels[$n]) ?></div>
          <div class="step__when"><?= $when[$n] ? e(fechaCorta((string) $when[$n])) : '—' ?></div>
        </div>
      <?php endfor; ?>
    </div>

    <div class="cart" style="margin-top:30px">
      <div>
        <div class="card">
          <div class="card__head"><h2 class="h3">Detalle de la cotización</h2><span class="badge ml-auto"><?= e(count($items)) ?> líneas</span></div>
          <div class="card__body card__body--flush tablescroll">
            <table class="datatable" style="border:0;border-radius:0">
              <caption class="sr-only">Productos cotizados</caption>
              <thead><tr>
                <th scope="col">Código</th><th scope="col">Descripción</th>
                <th scope="col" class="num">Cant.</th>
                <?php if ($showPrices): ?><th scope="col" class="num">P. unitario</th><th scope="col" class="num">Total</th><?php endif; ?>
              </tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td class="nowrap"><span class="code-chip"><?= e($it['code'] ?: '—') ?></span></td>
                    <td><?= e($it['name']) ?>
                      <?php if ($it['specs']): ?><br><span class="small muted"><?= e($it['specs']) ?></span><?php endif; ?>
                      <?php if ($it['notes']): ?><br><span class="small" style="color:var(--accent)"><?= e($it['notes']) ?></span><?php endif; ?>
                    </td>
                    <td class="num nowrap"><?= e(qty((float) $it['qty'])) ?> <?= e($it['unit']) ?></td>
                    <?php if ($showPrices): ?>
                      <td class="num nowrap"><?= e(money((float) $it['unit_price'], $sym)) ?><?= (float) $it['discount_pct'] > 0 ? ' <span class="small muted">&minus;' . e(qty((float) $it['discount_pct'])) . '%</span>' : '' ?></td>
                      <td class="num nowrap"><strong><?= e(money((float) $it['line_total'], $sym)) ?></strong></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($showPrices): ?>
            <div class="card__body" style="border-top:1px solid var(--line)">
              <div class="totals" style="max-width:320px;margin-left:auto">
                <div><span>Subtotal</span><b><?= e(money((float) $q['subtotal'], $sym)) ?></b></div>
                <?php if ((float) $q['discount_amount'] > 0): ?>
                  <div><span>Descuento</span><b>&minus; <?= e(money((float) $q['discount_amount'], $sym)) ?></b></div>
                <?php endif; ?>
                <?php if ((float) $q['tax_rate'] > 0): ?>
                  <div><span><?= e($company['tax_label']) ?> <?= e(qty((float) $q['tax_rate'])) ?>%</span><b><?= e(money((float) $q['tax_amount'], $sym)) ?></b></div>
                <?php endif; ?>
                <div class="grand"><span>Total</span><b><?= e(money((float) $q['total'], $sym)) ?></b></div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($q['delivery_time'] || $q['payment_terms'] || $q['notes']): ?>
          <div class="card" style="margin-top:16px">
            <div class="card__head"><h2 class="h3">Condiciones</h2></div>
            <div class="card__body">
              <table class="spectable">
                <tbody>
                  <?php if ($q['delivery_time']): ?><tr><th scope="row">Tiempo de entrega</th><td><?= e($q['delivery_time']) ?></td></tr><?php endif; ?>
                  <?php if ($q['payment_terms']): ?><tr><th scope="row">Condiciones de pago</th><td><?= e($q['payment_terms']) ?></td></tr><?php endif; ?>
                  <?php if ($q['valid_until']): ?><tr><th scope="row">Vigencia</th><td>Hasta el <?= e(fechaLarga((string) $q['valid_until'])) ?></td></tr><?php endif; ?>
                  <?php if ($q['notes']): ?><tr><th scope="row">Observaciones</th><td><?= nl2br(e($q['notes'])) ?></td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- acciones y bitácora -->
      <div class="stack">
        <div class="panelbox">
          <div class="cota" style="margin-bottom:16px">Acciones</div>
          <?php if ($hasPdf): ?>
            <a class="btn btn--ghost btn--block" href="<?= e(url('/c/' . $q['track_token'] . '/pdf')) ?>" target="_blank" rel="noopener" style="margin-bottom:10px">Descargar el PDF</a>
          <?php endif; ?>

          <?php if ($q['status'] === 'aprobada'): ?>
            <div class="alert alert--ok" style="margin:0"><span aria-hidden="true">✓</span><span>Usted aprobó esta cotización el <?= e(fechaCorta((string) $q['approved_at'])) ?>. Su asesor ya fue notificado.</span></div>
          <?php elseif ($q['status'] === 'perdida'): ?>
            <div class="alert" style="margin:0"><span aria-hidden="true">△</span><span>Esta cotización está cerrada. Si aún necesita el material, escríbanos y preparamos una nueva.</span></div>
          <?php elseif (!$canAct): ?>
            <div class="alert" style="margin:0"><span aria-hidden="true">◷</span><span>Su cotización está en elaboración. Le avisaremos por correo en cuanto esté lista.</span></div>
          <?php else: ?>
            <form method="post" action="<?= e(url('/c/' . $q['track_token'] . '/aprobar')) ?>" data-confirm="¿Confirma que aprueba esta cotización?" style="margin-bottom:14px">
              <?= csrf_field() ?>
              <label class="sr-only" for="ap_name">Su nombre</label>
              <input class="input" id="ap_name" name="name" placeholder="Su nombre" maxlength="120" value="<?= e($q['contact_name']) ?>" style="margin-bottom:10px">
              <button class="btn btn--accent btn--block" type="submit">Aprobar cotización <span class="arw" aria-hidden="true">&rarr;</span></button>
            </form>
            <details>
              <summary class="btn btn--ghost btn--block" style="cursor:pointer;list-style:none">Solicitar cambios</summary>
              <form method="post" action="<?= e(url('/c/' . $q['track_token'] . '/cambios')) ?>" style="margin-top:12px">
                <?= csrf_field() ?>
                <div class="field"><label for="ch_name">Su nombre</label>
                  <input class="input" id="ch_name" name="name" maxlength="120" value="<?= e($q['contact_name']) ?>"></div>
                <div class="field"><label for="ch_comment">¿Qué necesita ajustar?</label>
                  <textarea class="textarea" id="ch_comment" name="comment" rows="4" required minlength="5" maxlength="1500" placeholder="Cantidad, plazo de entrega, precio, cambio de producto…"></textarea></div>
                <button class="btn btn--ghost btn--block" type="submit">Enviar comentario</button>
              </form>
            </details>
          <?php endif; ?>
        </div>

        <div class="panelbox">
          <div class="cota" style="margin-bottom:16px">Su asesor</div>
          <p style="font-family:var(--f-display);font-size:1.05rem;letter-spacing:-.015em;margin:0"><?= e($q['seller_name'] ?: $company['name']) ?></p>
          <p class="small muted" style="margin:6px 0 14px">
            <?= e($q['seller_email'] ?: $company['email']) ?><br><?= e($q['seller_phone'] ?: $company['phone']) ?>
          </p>
          <?php $wa = preg_replace('/\D/', '', (string) ($q['seller_whatsapp'] ?: $company['whatsapp'])) ?: ''; ?>
          <?php if ($wa !== ''): ?>
            <a class="btn btn--ghost btn--sm btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hola, le escribo por la cotización ' . $q['number'] . '.')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
          <?php endif; ?>
        </div>

        <?php if ($events): ?>
        <div class="panelbox">
          <div class="cota" style="margin-bottom:18px">Historial</div>
          <ul class="timeline">
            <?php foreach (array_slice($events, 0, 10) as $ev): ?>
              <li class="<?= in_array($ev['type'], ['estado', 'cliente'], true) ? 'is-key' : '' ?>">
                <time><?= e(fechaHora((string) $ev['created_at'])) ?></time>
                <b><?= e($ev['title']) ?></b>
                <?php if ($ev['body']): ?><p><?= e(str_limit((string) $ev['body'], 180)) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
