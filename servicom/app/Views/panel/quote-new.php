<div class="cols cols--sidebar">
  <form class="card" method="post" action="<?= e(url('/panel/cotizaciones/nueva')) ?>">
    <?= csrf_field() ?>
    <div class="card__head"><span class="secnum">01/</span><h2>Datos del cliente</h2></div>
    <div class="card__body">
      <div class="field">
        <label for="customer_id">Cliente existente</label>
        <select class="select" id="customer_id" name="customer_id">
          <option value="">— Cliente nuevo o eventual —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?><?= $c['nit'] ? ' · NIT ' . e($c['nit']) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Si elige un cliente, se toman sus datos y su lista de precios.</p>
      </div>
      <div class="row-2">
        <div class="field"><label for="contact_name">Nombre de contacto</label>
          <input class="input" id="contact_name" name="contact_name" maxlength="140" value="<?= e(old('contact_name')) ?>"></div>
        <div class="field"><label for="contact_company">Empresa</label>
          <input class="input" id="contact_company" name="contact_company" maxlength="180" value="<?= e(old('contact_company')) ?>"></div>
      </div>
      <div class="row-3">
        <div class="field"><label for="contact_nit">NIT</label><input class="input" id="contact_nit" name="contact_nit" maxlength="30"></div>
        <div class="field"><label for="contact_phone">Teléfono</label><input class="input" id="contact_phone" name="contact_phone" maxlength="40"></div>
        <div class="field"><label for="contact_email">Correo</label><input class="input" id="contact_email" name="contact_email" type="email" maxlength="150"></div>
      </div>
      <button class="btn btn--accent" type="submit">Crear y agregar productos <span class="arw" aria-hidden="true">&rarr;</span></button>
    </div>
  </form>
  <div class="card">
    <div class="card__head"><span class="secnum">02/</span><h2>Cómo funciona</h2></div>
    <div class="card__body">
      <ol style="padding-left:1.1em;color:var(--steel);font-size:.875rem;display:grid;gap:10px">
        <li>Cree la cotización con los datos del cliente.</li>
        <li>Agregue productos buscando por código o nombre.</li>
        <li>Ajuste precios, descuentos y condiciones.</li>
        <li>Genere el PDF y envíelo en un clic.</li>
      </ol>
      <p class="hint" style="margin-top:14px">El número correlativo se asigna automáticamente (<?= e($company['quote_prefix']) ?>-<?= date('Y') ?>-…).</p>
    </div>
  </div>
</div>
