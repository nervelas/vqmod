<?php
$nuevo = (int) ($_GET['nuevo'] ?? 0);
$activos = array_values(array_filter($preregistros, static fn($p) => $p['estado'] === 'activo'));
$otros   = array_values(array_filter($preregistros, static fn($p) => $p['estado'] !== 'activo'));
?>
<div class="fila-entre mb-3">
  <div class="aviso-caja info crecer" style="max-width:620px">
    <?= ico('info', 20) ?>
    <div>Autorice a su visita y compártale el <strong>código QR</strong> o los <strong>6 dígitos</strong>.
      En la garita lo escanean y entra en segundos, sin llamadas.</div>
  </div>
  <a class="btn btn-oro" href="<?= e(url('/portal/visitas/nueva')) ?>"><?= ico('mas', 17) ?> Autorizar visita</a>
</div>

<?php if ($activos === []): ?>
  <div class="tarjeta">
    <div class="vacio">
      <?= ico('qr', 44) ?>
      <h3>No tiene visitas autorizadas</h3>
      <p>Cree una autorización y su visita entrará sin esperas.</p>
      <a class="btn btn-oro" href="<?= e(url('/portal/visitas/nueva')) ?>"><?= ico('mas', 18) ?> Autorizar una visita</a>
    </div>
  </div>
<?php else: ?>
  <div class="rejilla rejilla-3 mb-3">
    <?php foreach ($activos as $p): ?>
      <article class="tarjeta <?= $nuevo === (int) $p['id'] ? 'tarjeta-flota' : '' ?>"
               <?= $nuevo === (int) $p['id'] ? 'style="border-color:var(--arcilla);box-shadow:var(--s-2)"' : '' ?>>
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0;font-size:1rem"><?= e(recortar((string) $p['visitante'], 30)) ?></h3>
            <div class="texto-3" style="font-size:.78rem">
              <?php if ((int) $p['recurrente'] === 1): ?>Acceso recurrente<?php else: ?>Un solo ingreso<?php endif; ?>
            </div>
          </div>
          <span class="chip ok">Vigente</span>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <div class="qr-caja">
            <img src="<?= e(url('/qr/pase/' . (int) $p['id'])) ?>" alt="Código QR de la autorización" width="220" height="220" loading="lazy">
            <div class="codigo-grande"><?= e(chunk_split((string) $p['codigo'], 3, ' ')) ?></div>
          </div>
          <div class="mt-2" style="font-size:.85rem">
            <?php if (!empty($p['placa'])): ?><div class="fila-entre"><span class="texto-3">Placa</span><b><?= e($p['placa']) ?></b></div><?php endif; ?>
            <div class="fila-entre"><span class="texto-3">Vigencia</span><b><?= e(fechahora((string) $p['valido_hasta'])) ?></b></div>
            <?php if ((int) $p['recurrente'] === 1 && !empty($p['hora_desde'])): ?>
              <div class="fila-entre"><span class="texto-3">Horario</span><b><?= e(hora((string) $p['hora_desde'])) ?> a <?= e(hora((string) $p['hora_hasta'])) ?></b></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin" style="gap:6px">
          <?php
          $texto = 'Su código de ingreso a ' . App\Core\Ajustes::get('nombre', 'el residencial') . ' es: ' . $p['codigo']
                 . '. Preséntelo en la garita. Vigente hasta ' . fechahora((string) $p['valido_hasta']) . '.';
          ?>
          <a class="btn btn-sm btn-claro" target="_blank" rel="noopener" href="<?= e(whatsapp('', '')) ?: '#' ?>"
             data-compartir="<?= e($texto) ?>"><?= ico('chat', 15) ?> Compartir</a>
          <a class="btn btn-sm btn-claro" href="<?= e(url('/doc/pase/' . (int) $p['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 15) ?> PDF</a>
          <form method="post" action="<?= e(url('/portal/visitas/' . (int) $p['id'] . '/cancelar')) ?>"
                data-confirmar="El código dejará de funcionar de inmediato."
                data-confirmar-titulo="¿Cancelar esta autorización?" data-confirmar-boton="Sí, cancelar">
            <?= csrf() ?>
            <button class="btn btn-sm btn-fantasma" type="submit" aria-label="Cancelar la autorización de <?= e($p['visitante']) ?>"><?= ico('equis', 15) ?></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="rejilla rejilla-2">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Historial de ingresos</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($historial === []): ?>
        <p class="texto-3 centrado" style="padding:22px 0;margin:0">Todavía no hay visitas registradas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($historial as $v): ?>
            <li class="item-lista">
              <span class="avatar sm"><?= e(iniciales((string) $v['visitante'])) ?></span>
              <div class="crecer">
                <b><?= e(recortar((string) $v['visitante'], 30)) ?></b>
                <div class="meta"><?= e(fechahora((string) $v['entrada'])) ?><?= !empty($v['placa']) ? ' · ' . e($v['placa']) : '' ?></div>
              </div>
              <span class="chip <?= $v['salida'] ? 'neutro' : 'info' ?>"><?= $v['salida'] ? 'Salió ' . e(hora((string) $v['salida'])) : 'Adentro' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <?php if ($otros !== []): ?>
    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Autorizaciones anteriores</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <ul class="lista-limpia">
          <?php foreach (array_slice($otros, 0, 10) as $p): ?>
            <li class="item-lista">
              <div class="crecer">
                <b><?= e(recortar((string) $p['visitante'], 30)) ?></b>
                <div class="meta">Código <?= e($p['codigo']) ?> · <?= e(fecha((string) $p['valido_hasta'])) ?></div>
              </div>
              <span class="chip <?= e(estadoBadge((string) $p['estado'])) ?>"><?= e(ucfirst((string) $p['estado'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </article>
  <?php endif; ?>
</section>

<script<?= nonce() ?>>
document.querySelectorAll('[data-compartir]').forEach(function (a) {
  a.addEventListener('click', async function (ev) {
    ev.preventDefault();
    var texto = a.dataset.compartir;
    if (navigator.share) {
      try { await navigator.share({ title: 'Código de ingreso', text: texto }); return; } catch (e) { /* cancelado */ }
    }
    RP.copiar(texto, 'Código copiado. Péguelo en WhatsApp o en un mensaje.');
  });
});
</script>
