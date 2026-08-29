<?php $base = url('/e/' . $company['slug']); $sym = (string) $company['currency_symbol']; ?>
<div class="section section--tight blueprint" style="padding-top:34px">
  <div class="wrap">
    <nav class="crumbs" aria-label="Ruta">
      <a href="<?= e($base) ?>">Inicio</a><span aria-hidden="true">/</span><span>Solicitud de cotización</span>
    </nav>
    <div class="section__head" style="margin-bottom:26px">
      <div>
        <span class="secnum">01/</span>
        <h1 class="h1" style="margin-top:12px">Su solicitud de cotización</h1>
        <p class="lead" style="margin-top:14px">Revise cantidades, agregue notas por producto y envíela. No necesita crear una cuenta.</p>
      </div>
    </div>

    <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>

    <?php if (!$lines): ?>
      <div class="empty">
        <h2 class="h3">Su lista está vacía</h2>
        <p>Explore el catálogo y agregue los productos que necesita cotizar.</p>
        <a class="btn btn--accent" style="margin-top:20px" href="<?= e($base . '/catalogo') ?>">Ir al catálogo <span class="arw" aria-hidden="true">&rarr;</span></a>
      </div>
    <?php else: ?>
      <div class="cart">
        <div>
          <div class="flex" style="margin-bottom:14px">
            <span class="cota" style="flex:1"><?= e(count($lines)) ?> producto<?= count($lines) === 1 ? '' : 's' ?></span>
            <form method="post" action="<?= e($base . '/carrito') ?>" data-confirm="¿Vaciar toda la lista?" style="margin:0">
              <?= csrf_field() ?><input type="hidden" name="action" value="clear">
              <button class="btn btn--ghost btn--xs" type="submit">Vaciar lista</button>
            </form>
          </div>

          <?php foreach ($lines as $l):
            $img = $l['image'] ?? null;
            $show = \App\Models\Product::priceVisible($company, $l); ?>
            <div class="cartrow">
              <img src="<?= e($img ? upload($img['path_thumb'] ?: $img['path']) : url('/assets/img/plates/sello-mecanico.svg')) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" width="88" height="70">
              <div>
                <div class="flex flex-wrap" style="gap:8px;align-items:baseline">
                  <span class="code-chip"><?= e($l['code']) ?></span>
                  <a style="font-family:var(--f-display);letter-spacing:-.015em" href="<?= e($base . '/producto/' . $l['slug']) ?>"><?= e($l['name']) ?></a>
                </div>
                <?php if ($show && (float) $l['price'] > 0): ?>
                  <p class="small muted" style="margin:6px 0 0"><?= e(money((float) $l['price'], $sym)) ?> / <?= e($l['unit']) ?></p>
                <?php endif; ?>
                <label class="sr-only" for="note<?= e($l['id']) ?>">Nota para <?= e($l['name']) ?></label>
                <input class="input" id="note<?= e($l['id']) ?>" data-cart-note="<?= e($l['id']) ?>" value="<?= e($l['cart_note']) ?>"
                       placeholder="Nota: medida exacta, equipo, urgencia…" maxlength="300" style="margin-top:10px;font-size:.8125rem;padding:9px 12px">
              </div>
              <div class="cartrow__ctrl">
                <div class="qtybox">
                  <button type="button" data-step="-" aria-label="Disminuir">&minus;</button>
                  <label class="sr-only" for="q<?= e($l['id']) ?>">Cantidad de <?= e($l['name']) ?></label>
                  <input id="q<?= e($l['id']) ?>" type="number" min="0.01" step="<?= e((float) $l['min_qty'] < 1 ? '0.01' : '1') ?>" value="<?= e(qty((float) $l['cart_qty'])) ?>" data-cart-qty="<?= e($l['id']) ?>">
                  <button type="button" data-step="+" aria-label="Aumentar">+</button>
                </div>
                <button class="btn btn--ghost btn--xs" type="button" data-cart-remove="<?= e($l['id']) ?>">Quitar</button>
              </div>
            </div>
          <?php endforeach; ?>

          <a class="linkarrow" style="margin-top:20px" href="<?= e($base . '/catalogo') ?>">Seguir agregando productos <span aria-hidden="true">&rarr;</span></a>
        </div>

        <!-- datos y envío -->
        <form class="panelbox panelbox--sticky" method="post" action="<?= e($base . '/enviar') ?>" novalidate>
          <?= csrf_field() ?>
          <span class="secnum">02/</span>
          <h2 class="h3" style="margin:10px 0 4px">Sus datos</h2>
          <p class="small muted" style="margin-bottom:20px">Con estos datos preparamos la cotización formal.</p>

          <div class="field"><label for="f_name">Nombre completo *</label>
            <input class="input" id="f_name" name="name" required maxlength="140" autocomplete="name" value="<?= e(old('name')) ?>"></div>
          <div class="field"><label for="f_company">Empresa</label>
            <input class="input" id="f_company" name="company" maxlength="180" autocomplete="organization" value="<?= e(old('company')) ?>"></div>
          <div class="row-2">
            <div class="field"><label for="f_nit">NIT</label>
              <input class="input" id="f_nit" name="nit" maxlength="30" value="<?= e(old('nit')) ?>" placeholder="C/F"></div>
            <div class="field"><label for="f_phone">Teléfono *</label>
              <input class="input" id="f_phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
          </div>
          <div class="field"><label for="f_email">Correo *</label>
            <input class="input" id="f_email" name="email" type="email" required maxlength="150" autocomplete="email" value="<?= e(old('email')) ?>"></div>
          <div class="field"><label for="f_msg">Comentarios generales</label>
            <textarea class="textarea" id="f_msg" name="message" rows="3" maxlength="2000" placeholder="Plazo requerido, equipo donde se instala, condiciones especiales…"><?= e(old('message')) ?></textarea></div>

          <div class="field">
            <label for="f_captcha">Verificación *</label>
            <div class="captchabox">
              <strong aria-hidden="true"><?= e($captcha['question']) ?> =</strong>
              <input class="input" id="f_captcha" name="captcha" inputmode="numeric" required aria-label="Resultado de <?= e($captcha['question']) ?>">
              <input type="hidden" name="captcha_stamp" value="<?= e($captcha['stamp']) ?>">
            </div>
          </div>
          <div class="sr-only" aria-hidden="true">
            <label for="website">No llenar</label>
            <input id="website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <button class="btn btn--accent btn--block" type="submit">Enviar solicitud de cotización <span class="arw" aria-hidden="true">&rarr;</span></button>
          <p class="small muted" style="margin:14px 0 0">Recibirá un enlace privado para seguir el avance de su cotización.</p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
