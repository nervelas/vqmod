<?php
/** Descarga de fotografía real para los platillos. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Fotografía de los platillos');
$conFoto = $total - max(0, $faltan - 1);
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Descargar fotografía real</h2>
          <p>Busca en bancos de imágenes con licencia libre (Wikimedia Commons y Openverse) y asigna una fotografía a cada platillo que no tenga.</p>
        </div>
      </div>

      <div class="row-between" style="align-items:flex-end">
        <div>
          <p class="label">Sin fotografía</p>
          <p class="display" style="font-size:var(--step-3);color:var(--gold)" id="faltan"><?= (int)$faltan ?></p>
        </div>
        <div style="flex:1;min-width:200px">
          <div style="height:6px;background:var(--line-soft);border-radius:3px;overflow:hidden">
            <div id="barra" style="height:100%;width:<?= $total > 0 ? max(0, min(100, round(($total - $faltan) / max(1,$total) * 100))) : 0 ?>%;background:var(--gold);transition:width .4s var(--ease)"></div>
          </div>
          <p class="field-hint" id="estado">Listo para empezar. Tarda entre uno y tres minutos.</p>
        </div>
      </div>

      <div class="row mt-2">
        <button class="btn" type="button" id="empezar"<?= (int)$faltan === 0 ? ' disabled' : '' ?>>Descargar fotografías</button>
        <button class="btn btn-ghost" type="button" id="parar" hidden>Detener</button>
        <a class="btn btn-ghost" href="<?= e(mg_url('/panel/menu')) ?>">Volver al menú</a>
      </div>

      <div class="alert mt-2" id="aviso" hidden><span></span></div>

      <ul class="stack mt-2" id="registro" style="gap:.4rem;font-size:var(--step--1);max-height:340px;overflow:auto"></ul>
    </div>

    <div class="card">
      <div class="card-head"><h3>Si prefieres tus propias fotos</h3></div>
      <p class="muted" style="font-size:var(--step--1)">
        Súbelas desde <a class="link-line gold" href="<?= e(mg_url('/panel/menu')) ?>">cada platillo</a>, o importa una
        carpeta completa por línea de comandos. Los nombres de archivo se emparejan solos con los platillos:
      </p>
      <div class="copy-box mt-1">
        <pre>php tools/importar-fotos.php --carpeta=/home/usuario/fotos --restaurante=<?= (int)$restaurant['id'] ?></pre>
        <button class="btn btn-ghost btn-sm" type="button" data-copy="php tools/importar-fotos.php --carpeta=/home/usuario/fotos --restaurante=<?= (int)$restaurant['id'] ?>">Copiar</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Créditos</h3><p>Autoría y licencia de cada fotografía descargada.</p></div>
    <?php if ($creditos): ?>
      <ul class="stack" style="gap:.7rem;font-size:12px" id="creditos">
        <?php foreach ($creditos as $c): ?>
          <li style="border-bottom:1px solid var(--line-soft);padding-bottom:.6rem">
            <b><?= e($c['title'] !== '' ? \MenuGold\Core\Str::limit($c['title'], 44) : 'Imagen') ?></b>
            <span class="muted" style="display:block"><?= e($c['author']) ?> · <?= e($c['license']) ?></span>
            <?php if ($c['source_url'] !== ''): ?>
              <a class="link-line faint" href="<?= e($c['source_url']) ?>" target="_blank" rel="noopener nofollow">Ver origen</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted" style="font-size:var(--step--1)">Aún no hay fotografías descargadas. Aparecerán aquí con su autor y su licencia.</p>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
(function () {
  var empezar = document.getElementById('empezar');
  var parar   = document.getElementById('parar');
  var estado  = document.getElementById('estado');
  var barra   = document.getElementById('barra');
  var faltanEl= document.getElementById('faltan');
  var registro= document.getElementById('registro');
  var aviso   = document.getElementById('aviso');
  var total   = <?= (int)$total + 1 ?>;
  var corriendo = false, hechas = 0, fallidas = 0;

  function linea(texto, ok) {
    var li = document.createElement('li');
    li.style.color = ok ? 'var(--text-dim)' : 'var(--text-faint)';
    li.textContent = (ok ? '✓ ' : '· ') + texto;
    registro.insertBefore(li, registro.firstChild);
  }

  function lote() {
    if (!corriendo) { return; }
    window.MGPanel.post(<?= json_encode(mg_url('/panel/menu/fotos/lote')) ?>, { cuantas: 3 })
      .then(function (r) {
        if (!r || !r.ok) { detener('No se pudo continuar. Inténtalo de nuevo.'); return; }
        hechas += r.hechas; fallidas += r.fallidas;
        (r.detalle || []).forEach(function (d) {
          linea(d.ok ? (d.que + ' — ' + (d.autor || 'autor desconocido') + ' · ' + (d.licencia || '')) : (d.que + ' — sin resultado'), d.ok);
        });
        faltanEl.textContent = r.faltan;
        barra.style.width = Math.max(0, Math.min(100, Math.round((total - r.faltan) / total * 100))) + '%';
        estado.textContent = hechas + ' descargadas' + (fallidas ? ', ' + fallidas + ' sin resultado' : '') + ' · faltan ' + r.faltan;
        if (r.aviso) { aviso.hidden = false; aviso.querySelector('span').textContent = r.aviso; }
        if (r.faltan > 0 && (r.hechas > 0 || fallidas < 8)) { setTimeout(lote, 300); }
        else { detener(r.faltan === 0 ? '¡Listo! Todos los platillos tienen fotografía.' : 'Se detuvo: revisa el aviso de arriba.'); }
      })
      .catch(function () { detener('Se perdió la conexión con el panel.'); });
  }

  function detener(msg) {
    corriendo = false;
    empezar.hidden = false; parar.hidden = true; empezar.disabled = false;
    empezar.textContent = 'Continuar descarga';
    if (msg) { estado.textContent = msg; }
    if (hechas > 0) { setTimeout(function () { window.location.reload(); }, 2500); }
  }

  empezar.addEventListener('click', function () {
    corriendo = true; hechas = 0; fallidas = 0;
    empezar.hidden = true; parar.hidden = false;
    estado.textContent = 'Buscando fotografías…';
    lote();
  });
  parar.addEventListener('click', function () { detener('Detenido. Puedes continuar cuando quieras.'); });
})();
</script>
<?php $view->stop() ?>
