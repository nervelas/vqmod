<?php
$action = $c ? url('/super/empresas/' . $c['id']) : url('/super/empresas/nueva');
$v = static fn (string $k, mixed $d = ''): mixed => $c[$k] ?? old($k, $d);
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="cols cols--sidebar">
    <div class="stack">
      <div class="card">
        <div class="card__head"><span class="secnum">01/</span><h2>Datos de la empresa</h2>
          <button class="btn btn--accent btn--sm ml-auto" type="submit"><?= $c ? 'Guardar' : 'Crear empresa' ?></button></div>
        <div class="card__body">
          <div class="row-2">
            <div class="field"><label for="name">Nombre comercial *</label><input class="input" id="name" name="name" maxlength="140" required value="<?= e($v('name')) ?>"></div>
            <div class="field"><label for="legal_name">Razón social</label><input class="input" id="legal_name" name="legal_name" maxlength="180" value="<?= e($v('legal_name')) ?>"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="nit">NIT</label><input class="input" id="nit" name="nit" maxlength="30" value="<?= e($v('nit')) ?>"></div>
            <div class="field"><label for="slug">Identificador de URL</label>
              <input class="input" id="slug" name="slug" maxlength="60" value="<?= e($v('slug')) ?>" placeholder="industrial-perez">
              <p class="hint">Su sitio: <?= e(absUrl('/e/')) ?><strong><?= e($v('slug', 'identificador')) ?></strong></p></div>
            <div class="field"><label for="domain">Dominio o subdominio propio</label>
              <input class="input" id="domain" name="domain" maxlength="190" value="<?= e($v('domain')) ?>" placeholder="catalogo.suempresa.gt">
              <p class="hint">Apunte el dominio a este hosting y escríbalo aquí.</p></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="tagline">Frase del hero</label><input class="input" id="tagline" name="tagline" maxlength="190" value="<?= e($v('tagline')) ?>"></div>
            <div class="field"><label for="years_experience">Años en la industria</label><input class="input" id="years_experience" name="years_experience" type="number" min="0" max="200" value="<?= e((int) $v('years_experience', 0)) ?>"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" maxlength="150" value="<?= e($v('email')) ?>"></div>
            <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40" value="<?= e($v('phone')) ?>"></div>
            <div class="field"><label for="whatsapp">WhatsApp</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($v('whatsapp')) ?>"></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="address">Dirección</label><input class="input" id="address" name="address" maxlength="220" value="<?= e($v('address')) ?>"></div>
            <div class="field"><label for="city">Ciudad</label><input class="input" id="city" name="city" maxlength="90" value="<?= e($v('city')) ?>"></div>
          </div>
          <?php if ($c): ?>
            <div class="field"><label for="logo">Logo</label><input class="input" id="logo" name="logo" type="file" accept="image/*">
              <p class="hint">Al cambiarlo se regeneran los iconos PWA de la empresa.</p></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">02/</span><h2>Tema visual</h2></div>
        <div class="card__body">
          <div class="swatches" style="margin-bottom:16px">
            <?php foreach ($themes as $key => $t): ?>
              <label class="swatch">
                <input type="radio" name="theme" value="<?= e($key) ?>"<?= ($v('theme', 'acero')) === $key ? ' checked' : '' ?>
                       data-theme-pick data-accent="<?= e($t['accent']) ?>" data-ink="<?= e($t['ink']) ?>" data-paper="<?= e($t['paper']) ?>">
                <span style="background:linear-gradient(135deg,<?= e($t['paper']) ?> 0 42%,<?= e($t['accent']) ?> 42% 72%,<?= e($t['ink']) ?> 72%)"></span>
                <small><?= e($t['label']) ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="row-3">
            <div class="field"><label for="color_accent">Acento</label><input class="input" id="color_accent" name="color_accent" type="color" value="<?= e($v('color_accent', '#E8590C')) ?>"></div>
            <div class="field"><label for="color_ink">Tinta</label><input class="input" id="color_ink" name="color_ink" type="color" value="<?= e($v('color_ink', '#1C1F22')) ?>"></div>
            <div class="field"><label for="color_paper">Papel</label><input class="input" id="color_paper" name="color_paper" type="color" value="<?= e($v('color_paper', '#F5F6F4')) ?>"></div>
          </div>
        </div>
      </div>

      <?php if (!$c): ?>
        <div class="card">
          <div class="card__head"><span class="secnum">03/</span><h2>Administrador de la empresa</h2></div>
          <div class="card__body">
            <div class="row-2">
              <div class="field"><label for="admin_name">Nombre</label><input class="input" id="admin_name" name="admin_name" maxlength="120" value="<?= e(old('admin_name')) ?>"></div>
              <div class="field"><label for="admin_email">Correo *</label><input class="input" id="admin_email" name="admin_email" type="email" maxlength="150" required value="<?= e(old('admin_email')) ?>"></div>
            </div>
            <div class="field"><label for="admin_password">Contraseña *</label>
              <input class="input" id="admin_password" name="admin_password" type="password" minlength="8" required autocomplete="new-password">
              <p class="hint">Mínimo 8 caracteres con mayúsculas, minúsculas y números. Se crean además dos listas de precios y cuatro atributos base.</p></div>
          </div>
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card__head"><span class="secnum">03/</span><h2>Usuarios de la empresa</h2></div>
          <div class="card__body card__body--flush tablescroll">
            <table class="datatable" style="border:0;border-radius:0">
              <caption class="sr-only">Usuarios</caption>
              <thead><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Último acceso</th></tr></thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr><td><strong><?= e($u['name']) ?></strong></td><td class="small"><?= e($u['email']) ?></td>
                    <td><span class="badge"><?= e($u['role']) ?></span></td>
                    <td><span class="badge<?= $u['status'] === 'activo' ? ' badge--ok' : '' ?>"><?= e($u['status']) ?></span></td>
                    <td class="small muted"><?= $u['last_login_at'] ? e(fechaCorta((string) $u['last_login_at'])) : 'nunca' ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card__head"><h2>Plan y vigencia</h2></div>
        <div class="card__body">
          <div class="field"><label for="plan_id">Plan</label>
            <select class="select" id="plan_id" name="plan_id">
              <option value="">— Sin plan —</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= e($p['id']) ?>"<?= (int) ($c['plan_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>>
                  <?= e($p['name']) ?> — Q<?= e(number_format((float) $p['price_month'], 0)) ?>/mes
                </option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="status">Estado</label>
            <select class="select" id="status" name="status">
              <?php foreach (['prueba' => 'En prueba', 'activa' => 'Activa', 'suspendida' => 'Suspendida', 'cancelada' => 'Cancelada'] as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= ($v('status', 'prueba')) === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="expires_at">Vence el</label>
            <input class="input" id="expires_at" name="expires_at" type="date" value="<?= e($v('expires_at')) ?>">
            <p class="hint">Al vencer, el sitio público y el panel se bloquean automáticamente.</p></div>
          <button class="btn btn--accent btn--block" type="submit"><?= $c ? 'Guardar cambios' : 'Crear empresa' ?></button>
        </div>
      </div>

      <?php if ($c): ?>
        <div class="card">
          <div class="card__head"><h2>Uso actual</h2></div>
          <div class="card__body">
            <?php foreach ([['Productos', 'products'], ['Usuarios', 'users'], ['Cotizaciones del mes', 'quotes']] as $row):
              $u = (int) ($usage[$row[1]] ?? 0); $l = (int) ($limits[$row[1]] ?? 0);
              $pct = $l > 0 ? min(100, (int) round($u / $l * 100)) : 0; ?>
              <div style="margin-bottom:14px">
                <div class="flex small" style="justify-content:space-between;margin-bottom:6px"><span><?= e($row[0]) ?></span><b><?= e($u) ?><?= $l > 0 ? ' / ' . e($l) : ' / ∞' ?></b></div>
                <?php if ($l > 0): ?><span class="progressbar<?= $pct >= 100 ? ' is-full' : '' ?>"><i style="width:<?= e($pct) ?>%"></i></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <a class="btn btn--ghost btn--block" style="margin-top:10px" href="<?= e(url('/super/empresas/' . $c['id'] . '/entrar')) ?>">Entrar al panel de esta empresa</a>
          </div>
        </div>

        <div class="card">
          <div class="card__head"><h2>Zona sensible</h2></div>
          <div class="card__body">
            <p class="small muted">Escriba <strong><?= e($c['slug']) ?></strong> para confirmar la eliminación de la empresa y de todos sus datos.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($c): ?>
  <form method="post" action="<?= e(url('/super/empresas/' . $c['id'] . '/eliminar')) ?>" style="max-width:420px;margin-top:-12px"
        data-confirm="Esto elimina la empresa, sus productos, clientes y cotizaciones. ¿Continuar?">
    <?= csrf_field() ?>
    <div class="flex" style="gap:8px">
      <label class="sr-only" for="confirm">Identificador de la empresa</label>
      <input class="input" id="confirm" name="confirm" placeholder="<?= e($c['slug']) ?>" required>
      <button class="btn btn--danger nowrap" type="submit">Eliminar empresa</button>
    </div>
  </form>
<?php endif; ?>
