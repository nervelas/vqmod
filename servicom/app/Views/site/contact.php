<?php $base = rtrim(url('/'), '/'); $wa = preg_replace('/\D/', '', (string) $company['whatsapp']) ?: ''; ?>
<section class="section blueprint" style="padding-top:60px">
  <div class="wrap">
    <div class="grid12" style="align-items:start">
      <div style="grid-column:span 6">
        <span class="secnum">01/</span>
        <h1 class="h1" style="margin:12px 0 18px">Hablemos de lo que necesita.</h1>
        <p class="lead">La forma más rápida de obtener precio es armar su lista en el catálogo y enviarla: la recibimos con códigos y cantidades exactas.</p>
        <a class="btn btn--accent" style="margin-top:24px" href="<?= e($base . '/catalogo') ?>">Ir al catálogo <span class="arw" aria-hidden="true">&rarr;</span></a>

        <div style="margin-top:38px">
          <div class="cota" style="margin-bottom:16px">Datos de contacto</div>
          <ul style="list-style:none;padding:0;display:grid;gap:0">
            <?php foreach (array_filter([
              ['Teléfono', (string) $company['phone'], $company['phone'] ? 'tel:' . preg_replace('/\D/', '', (string) $company['phone']) : ''],
              ['Correo', (string) $company['email'], $company['email'] ? 'mailto:' . $company['email'] : ''],
              ['WhatsApp', $wa !== '' ? '+' . $wa : '', $wa !== '' ? 'https://wa.me/' . $wa : ''],
              ['Dirección', trim((string) $company['address'] . ($company['city'] ? ', ' . $company['city'] : '')), (string) $company['maps_url']],
            ], static fn ($r) => $r[1] !== '') as $row): ?>
              <li style="display:flex;gap:18px;padding:14px 0;border-bottom:1px solid var(--line)">
                <span class="label" style="width:110px;flex:none;margin:0"><?= e($row[0]) ?></span>
                <?php if ($row[2] !== ''): ?>
                  <a href="<?= e($row[2]) ?>"<?= str_starts_with($row[2], 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($row[1]) ?></a>
                <?php else: ?><span><?= e($row[1]) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div style="grid-column:span 5/-1">
        <div class="panelbox panelbox--dark">
          <span class="secnum">02/</span>
          <h2 class="h3" style="color:#fff;margin:10px 0 10px">¿Busca un código específico?</h2>
          <p class="small" style="color:rgba(255,255,255,.68)">Escríbanos por WhatsApp con la referencia o una foto de la pieza y le confirmamos equivalencia y precio.</p>
          <?php if ($wa !== ''): ?>
            <a class="btn btn--accent btn--block" style="margin-top:20px" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hola, busco un repuesto. Le comparto la referencia:')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp <span class="arw" aria-hidden="true">&rarr;</span></a>
          <?php elseif ($company['email']): ?>
            <a class="btn btn--accent btn--block" style="margin-top:20px" href="mailto:<?= e($company['email']) ?>">Escribir un correo</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
