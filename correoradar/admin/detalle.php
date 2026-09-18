<?php
/** CorreoRadar - Detalle de una extracción concreta. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

$id      = (int) cr_get('id', 0);
$escaneo = Rastreador::escaneo($id);
if (!$escaneo) {
    cr_flash('error', 'La extracción indicada no existe.');
    cr_redirigir('admin/historial.php');
}

$correos   = Rastreador::correos($id);
$telefonos = Rastreador::telefonos($id);
$enlacesWa = array_values(array_filter(explode(',', (string) $escaneo['enlaces_wa'])));
$redes     = array_values(array_filter(explode(',', (string) $escaneo['redes'])));
$paginas   = BD::todos(
    'SELECT `url`, `tipo`, `estado`, `http_codigo`, `correos` FROM `cr_cola` WHERE `escaneo_id` = ? ORDER BY `prioridad`, `id` LIMIT 200',
    [$id]
);

admin_cabecera(['titulo' => 'Extracción de ' . $escaneo['host'], 'activo' => 'historial.php']);
?>

<div class="rejilla rejilla-4" style="margin-bottom:20px">
  <div class="metrica"><b><?= cr_numero((int) $escaneo['correos']) ?></b><span>Correos</span></div>
  <div class="metrica"><b><?= cr_numero((int) $escaneo['paginas_ok']) ?></b><span>Páginas revisadas</span></div>
  <div class="metrica"><b><?= cr_numero((int) $escaneo['whatsapps']) ?></b><span>WhatsApp</span></div>
  <div class="metrica"><b><?= cr_numero(count($telefonos)) ?></b><span>Teléfonos en total</span></div>
</div>

<div class="tarjeta" style="margin-bottom:20px">
  <div class="resultados-cab">
    <div>
      <h3 style="margin:0">Origen</h3>
      <p class="sub mono"><?= e((string) $escaneo['url_origen']) ?></p>
      <p class="sub">
        <?= e(cr_fecha((string) $escaneo['inicio'])) ?> ·
        IP <span class="mono"><?= e((string) $escaneo['ip']) ?></span> ·
        estado <span class="chip <?= $escaneo['estado'] === 'completado' ? 'chip-neon' : 'chip-gris' ?>"><?= e((string) $escaneo['estado']) ?></span>
      </p>
    </div>
    <div class="acciones-fila">
      <?php foreach (['txt' => 'TXT', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $f => $et): ?>
        <form method="post" action="<?= e(cr_url('api/exportar.php')) ?>">
          <?= Seguridad::campoCsrf() ?>
          <input type="hidden" name="escaneo_id" value="<?= (int) $escaneo['id'] ?>">
          <input type="hidden" name="formato" value="<?= e($f) ?>">
                          <input type="hidden" name="datos" value="<?= $f === 'xlsx' ? 'todo' : 'correos' ?>">
          <button class="btn <?= $f === 'xlsx' ? 'btn-neon' : 'btn-fantasma' ?> btn-peq"><?= e($et) ?></button>
        </form>
      <?php endforeach; ?>
      <a class="btn btn-fantasma btn-peq" href="historial.php">Volver</a>
    </div>
  </div>

  <?php if (!$correos): ?>
    <div class="vacio"><p>Esta extracción no encontró ningún correo.</p></div>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead><tr><th>Correo</th><th>Dominio</th><th>Tipo</th><th>Confianza</th><th>MX</th><th>Método</th><th>Página exacta</th></tr></thead>
        <tbody>
        <?php foreach ($correos as $c): ?>
          <tr>
            <td><span class="celda-correo"><?= e((string) $c['correo']) ?></span></td>
            <td><?= e((string) $c['dominio']) ?></td>
            <td><span class="chip <?= $c['tipo'] === 'generico' ? '' : 'chip-neon' ?>"><?= $c['tipo'] === 'generico' ? 'Genérico' : 'Personal' ?></span></td>
            <td>
              <div class="confianza">
                <span class="pista"><i style="width:<?= (int) $c['confianza'] ?>%"></i></span>
                <b><?= (int) $c['confianza'] ?></b>
              </div>
            </td>
            <td>
              <?php if ($c['mx'] === null): ?><span class="chip chip-gris">—</span>
              <?php elseif ((int) $c['mx'] === 1): ?><span class="chip chip-neon">Sí</span>
              <?php else: ?><span class="chip chip-rojo">No</span><?php endif; ?>
            </td>
            <td><div class="celda-metodos">
              <?php foreach (array_filter(explode(',', (string) $c['metodo'])) as $m): ?>
                <span class="chip chip-gris"><?= e($m) ?></span>
              <?php endforeach; ?>
            </div></td>
            <td class="celda-url"><a href="<?= e((string) $c['url_origen']) ?>" target="_blank" rel="noopener nofollow"><?= e(cr_recortar((string) $c['url_origen'], 58)) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="rejilla rejilla-2">
  <div class="tarjeta">
    <h3>Páginas recorridas</h3>
    <div class="tabla-scroll" style="max-height:380px;overflow-y:auto">
      <table class="tabla panel-tabla" style="min-width:0">
        <thead><tr><th>Dirección</th><th>Tipo</th><th>HTTP</th><th>Correos</th></tr></thead>
        <tbody>
        <?php foreach ($paginas as $p): ?>
          <tr>
            <td class="celda-url"><?= e(cr_recortar((string) $p['url'], 52)) ?></td>
            <td><span class="chip chip-gris"><?= e(match ((string) $p['tipo']) {
              'recurso' => 'Recurso', 'sitemap' => 'Sitemap', default => 'Página',
            }) ?></span></td>
            <td><?= $p['http_codigo'] !== null ? (int) $p['http_codigo'] : '—' ?></td>
            <td><?= cr_numero((int) $p['correos']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$paginas): ?><tr><td colspan="4" class="suave">Sin detalle guardado.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tarjeta">
    <h3>Enlaces y perfiles</h3>
    <p class="etiqueta">Enlaces de WhatsApp sin número</p>
    <ul class="lista-extras">
      <?php foreach ($enlacesWa as $u): ?>
        <li><a href="<?= e($u) ?>" target="_blank" rel="noopener nofollow"><?= e(cr_recortar(preg_replace('~^https?://~', '', $u) ?? $u, 36)) ?></a></li>
      <?php endforeach; ?>
      <?php if (!$enlacesWa): ?><li><span class="suave">Ninguno</span></li><?php endif; ?>
    </ul>
    <p class="etiqueta" style="margin-top:16px">Perfiles sociales</p>
    <ul class="lista-extras">
      <?php foreach ($redes as $r): ?>
        <li><a href="<?= e($r) ?>" target="_blank" rel="noopener nofollow"><?= e(cr_recortar(preg_replace('~^https?://(www\.)?~', '', $r) ?? $r, 34)) ?></a></li>
      <?php endforeach; ?>
      <?php if (!$redes): ?><li><span class="suave">Ninguno</span></li><?php endif; ?>
    </ul>
  </div>
</div>

<!-- ===================== WHATSAPP Y TELÉFONOS ===================== -->
<div class="tarjeta" style="margin-top:20px">
  <div class="resultados-cab">
    <div>
      <h3 style="margin:0">WhatsApp y teléfonos</h3>
      <p class="sub"><?= cr_numero((int) $escaneo['whatsapps']) ?> con WhatsApp confirmado de <?= cr_numero(count($telefonos)) ?> números</p>
    </div>
    <?php if ($telefonos): ?>
      <div class="acciones-fila">
        <?php foreach (['txt' => 'TXT', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $f => $et): ?>
          <form method="post" action="<?= e(cr_url('api/exportar.php')) ?>">
            <?= Seguridad::campoCsrf() ?>
            <input type="hidden" name="escaneo_id" value="<?= (int) $escaneo['id'] ?>">
            <input type="hidden" name="formato" value="<?= e($f) ?>">
            <input type="hidden" name="datos" value="telefonos">
            <button class="btn btn-fantasma btn-peq"><?= e($et) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$telefonos): ?>
    <div class="vacio"><p>Esta extracción no encontró ningún número.</p></div>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead><tr><th>Número</th><th>País</th><th>Tipo</th><th>Confianza</th><th>Método</th><th>Página exacta</th><th>Chat</th></tr></thead>
        <tbody>
        <?php foreach ($telefonos as $t): ?>
          <tr>
            <td><span class="celda-correo"><?= e((string) $t['formato']) ?></span></td>
            <td><?= e((string) $t['pais']) ?></td>
            <td>
              <?php if ((int) $t['whatsapp'] === 1): ?>
                <span class="chip chip-wa">WhatsApp</span>
              <?php else: ?>
                <span class="chip chip-gris">Teléfono</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="confianza">
                <span class="pista"><i style="width:<?= (int) $t['confianza'] ?>%"></i></span>
                <b><?= (int) $t['confianza'] ?></b>
              </div>
            </td>
            <td><div class="celda-metodos">
              <?php foreach (array_filter(explode(',', (string) $t['metodo'])) as $m): ?>
                <span class="chip chip-gris"><?= e($m) ?></span>
              <?php endforeach; ?>
            </div></td>
            <td class="celda-url"><a href="<?= e((string) $t['url_origen']) ?>" target="_blank" rel="noopener nofollow"><?= e(cr_recortar((string) $t['url_origen'], 52)) ?></a></td>
            <td><a class="btn btn-neon btn-peq" href="<?= e(Telefono::enlaceWhatsapp((string) $t['numero'])) ?>" target="_blank" rel="noopener nofollow">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php admin_pie(); ?>
