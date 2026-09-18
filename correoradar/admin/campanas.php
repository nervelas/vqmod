<?php
/** CorreoRadar - Campañas de correo. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');
    $id     = (int) cr_post('id', 0);

    switch ($accion) {
        case 'crear':
            $remitentes = array_map('intval', (array) cr_post('remitentes', []));
            $nuevo = [
                'nombre'       => mb_substr(trim((string) cr_post('nombre')) ?: 'Campaña', 0, 160),
                'lista_id'     => (int) cr_post('lista_id'),
                'plantilla_id' => (int) cr_post('plantilla_id'),
                'remitentes'   => implode(',', $remitentes),
                'limite_hora'  => max(1, min(5000, (int) cr_post('limite_hora', 40))),
                'pausa_min'    => max(0, min(600, (int) cr_post('pausa_min', 8))),
                'pausa_max'    => max(0, min(900, (int) cr_post('pausa_max', 30))),
                'estado'       => 'borrador',
                'creado'       => date('Y-m-d H:i:s'),
            ];
            if ($nuevo['pausa_max'] < $nuevo['pausa_min']) { $nuevo['pausa_max'] = $nuevo['pausa_min']; }

            if ($nuevo['lista_id'] <= 0 || $nuevo['plantilla_id'] <= 0 || !$remitentes) {
                cr_flash('error', 'Elige lista, plantilla y al menos un buzón.');
                cr_redirigir('admin/campanas.php');
            }
            $id = BD::insertar('cr_campanas', $nuevo);
            $r = Campana::preparar($id);
            cr_flash($r['ok'] ? 'exito' : 'error',
                $r['ok'] ? ('Campaña creada con ' . $r['total'] . ' destinatarios (' . $r['excluidos'] . ' excluidos).') : ($r['error'] ?? 'Error.'));
            cr_redirigir('admin/campanas.php?id=' . $id);
            // no se alcanza

        case 'preparar':
            $r = Campana::preparar($id);
            cr_flash($r['ok'] ? 'exito' : 'error',
                $r['ok'] ? ('Cola regenerada: ' . $r['total'] . ' destinatarios.') : ($r['error'] ?? 'Error.'));
            break;

        case 'pausar':
            Campana::pausar($id);
            cr_flash('info', 'Campaña pausada.');
            break;

        case 'reanudar':
            $r = Campana::iniciar($id);
            cr_flash($r['ok'] ? 'exito' : 'error', $r['ok'] ? 'Campaña reanudada.' : ($r['error'] ?? 'Error.'));
            break;

        case 'cancelar':
            Campana::cancelar($id);
            cr_flash('info', 'Campaña cancelada.');
            break;

        case 'borrar':
            BD::ejecutar('DELETE FROM `cr_envios` WHERE `campana_id` = ?', [$id]);
            BD::ejecutar('DELETE FROM `cr_campanas` WHERE `id` = ?', [$id]);
            cr_flash('exito', 'Campaña eliminada.');
            cr_redirigir('admin/campanas.php');
            // no se alcanza
    }
    cr_redirigir('admin/campanas.php?id=' . $id);
}

$id      = (int) cr_get('id', 0);
$campana = $id > 0 ? Campana::obtener($id) : null;

admin_cabecera(['titulo' => $campana ? 'Campaña: ' . $campana['nombre'] : 'Campañas', 'activo' => 'campanas.php']);

if (!$campana):
    $campanas   = Campana::todas();
    $listas     = Contactos::listas();
    $plantillas = BD::todos('SELECT `id`,`nombre` FROM `cr_plantillas` ORDER BY `nombre`');
    $buzones    = Remitente::activos();
?>

<?php if (!$buzones || !$listas || !$plantillas): ?>
  <div class="aviso aviso-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
      <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
    </svg>
    <span>
      Para lanzar una campaña necesitas tres cosas:
      <?= $buzones ? '✅' : '⬜' ?> <a href="remitentes.php">un buzón de salida</a> ·
      <?= $listas ? '✅' : '⬜' ?> <a href="listas.php">una lista de contactos</a> ·
      <?= $plantillas ? '✅' : '⬜' ?> <a href="plantillas.php">una plantilla</a>.
    </span>
  </div>
<?php endif; ?>

<div class="tarjeta" style="margin-bottom:20px">
  <h3>Campañas (<?= cr_numero(count($campanas)) ?>)</h3>
  <?php if (!$campanas): ?>
    <div class="vacio"><p>Todavía no has creado ninguna campaña.</p></div>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead><tr><th>Campaña</th><th>Lista</th><th>Progreso</th><th>Aperturas</th><th>Bajas</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($campanas as $c): ?>
          <?php $pct = (int) $c['total'] > 0 ? (int) round((int) $c['enviados'] / (int) $c['total'] * 100) : 0; ?>
          <tr>
            <td><a href="?id=<?= (int) $c['id'] ?>"><b><?= e((string) $c['nombre']) ?></b></a>
              <div class="pequeno suave"><?= e(cr_fecha((string) $c['creado'])) ?></div></td>
            <td class="pequeno"><?= e((string) ($c['lista'] ?? '—')) ?></td>
            <td style="min-width:150px">
              <div class="confianza">
                <span class="pista"><i style="width:<?= $pct ?>%"></i></span>
                <b><?= cr_numero((int) $c['enviados']) ?>/<?= cr_numero((int) $c['total']) ?></b>
              </div>
            </td>
            <td><?= cr_numero((int) $c['aperturas']) ?></td>
            <td><?= cr_numero((int) $c['bajas']) ?></td>
            <td>
              <?php $e = (string) $c['estado']; ?>
              <span class="chip <?= $e === 'enviando' ? 'chip-neon' : ($e === 'completada' ? '' : 'chip-gris') ?>"><?= e(ucfirst($e)) ?></span>
            </td>
            <td><a class="btn btn-fantasma btn-peq" href="?id=<?= (int) $c['id'] ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($buzones && $listas && $plantillas): ?>
<div class="tarjeta">
  <h3>Nueva campaña</h3>
  <form method="post">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="accion" value="crear">

    <div class="rejilla rejilla-2">
      <div>
        <div class="campo-grupo">
          <label class="etiqueta" for="nombre">Nombre de la campaña</label>
          <input type="text" id="nombre" name="nombre" class="campo" required placeholder="Colegios · septiembre">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="lista_id">Lista de contactos</label>
          <select id="lista_id" name="lista_id" class="campo" required>
            <?php foreach ($listas as $l): ?>
              <option value="<?= (int) $l['id'] ?>"><?= e((string) $l['nombre']) ?> (<?= (int) $l['contactos'] ?> contactos)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="plantilla_id">Plantilla</label>
          <select id="plantilla_id" name="plantilla_id" class="campo" required>
            <?php foreach ($plantillas as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= e((string) $p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <div class="campo-grupo">
          <label class="etiqueta">Buzones de salida</label>
          <?php foreach ($buzones as $b): ?>
            <label class="interruptor" style="display:flex;margin-bottom:8px">
              <input type="checkbox" name="remitentes[]" value="<?= (int) $b['id'] ?>" checked>
              <span class="pista" aria-hidden="true"></span>
              <span class="txt"><?= e((string) $b['de_correo']) ?>
                <span class="suave pequeno">(<?= (int) $b['limite_hora'] ?>/h)</span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="limite_hora">Máximo por hora en esta campaña</label>
          <input type="number" id="limite_hora" name="limite_hora" class="campo" style="max-width:140px" min="1" max="5000" value="40">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="campo-grupo">
            <label class="etiqueta" for="pausa_min">Pausa mínima (s)</label>
            <input type="number" id="pausa_min" name="pausa_min" class="campo" min="0" max="600" value="8">
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="pausa_max">Pausa máxima (s)</label>
            <input type="number" id="pausa_max" name="pausa_max" class="campo" min="0" max="900" value="30">
          </div>
        </div>
      </div>
    </div>

    <button class="btn" style="margin-top:10px">Crear y preparar campaña</button>
  </form>
</div>
<?php endif; ?>

<?php else:
    $stats     = Campana::estadisticas($id);
    $lista     = Contactos::lista((int) $campana['lista_id']);
    $plantilla = BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [(int) $campana['plantilla_id']]);
    $buzonesIds = Campana::remitentesDe($campana);
    $cronClave = Ajustes::obtener('cron_clave', '');
    $ultimos = BD::todos(
        'SELECT `correo`,`estado`,`error`,`enviado_en`,`aperturas`,`clics` FROM `cr_envios`
         WHERE `campana_id` = ? ORDER BY `enviado_en` DESC, `id` DESC LIMIT 25',
        [$id]
    );
?>

<div class="rejilla rejilla-4" style="margin-bottom:20px">
  <div class="metrica"><b id="m-enviados"><?= cr_numero($stats['enviados']) ?></b><span>Enviados de <?= cr_numero($stats['total']) ?></span></div>
  <div class="metrica"><b id="m-aperturas"><?= cr_numero($stats['aperturas']) ?></b><span>Aperturas · <?= $stats['tasa_apertura'] ?>%</span></div>
  <div class="metrica"><b id="m-clics"><?= cr_numero($stats['clics']) ?></b><span>Clics · <?= $stats['tasa_clic'] ?>%</span></div>
  <div class="metrica"><b id="m-bajas"><?= cr_numero($stats['bajas']) ?></b><span>Bajas · <?= $stats['tasa_baja'] ?>%</span></div>
</div>

<div class="tarjeta" style="margin-bottom:20px">
  <div class="progreso-cab">
    <div class="progreso-titulo">
      <span class="mini-radar" id="radar-envio" style="<?= $campana['estado'] === 'enviando' ? '' : 'opacity:.25' ?>" aria-hidden="true"></span>
      <span>
        <span id="m-estado"><?= e(ucfirst((string) $campana['estado'])) ?></span> ·
        <span class="suave pequeno" id="m-pendientes"><?= cr_numero($stats['pendientes']) ?> pendientes</span>
      </span>
    </div>
    <div class="acciones-fila">
      <?php if (in_array($campana['estado'], ['borrador', 'preparada', 'pausada'], true)): ?>
        <button type="button" class="btn btn-peq" id="btn-enviar">Empezar a enviar</button>
      <?php endif; ?>
      <?php if ($campana['estado'] === 'enviando'): ?>
        <button type="button" class="btn btn-peq" id="btn-enviar">Enviar ahora un lote</button>
        <form method="post"><?= Seguridad::campoCsrf() ?>
          <input type="hidden" name="accion" value="pausar"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-fantasma btn-peq">Pausar</button></form>
      <?php endif; ?>
      <?php if (!in_array($campana['estado'], ['completada', 'cancelada'], true)): ?>
        <form method="post"><?= Seguridad::campoCsrf() ?>
          <input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                  data-confirmar="¿Cancelar la campaña? Los envíos pendientes no se harán.">Cancelar</button></form>
      <?php endif; ?>
      <a class="btn btn-fantasma btn-peq" href="campanas.php">Volver</a>
    </div>
  </div>

  <div class="barra-progreso" id="barra-envio"><i style="width:<?= $stats['total'] > 0 ? (int) round($stats['enviados'] / max(1, $stats['total']) * 100) : 0 ?>%"></i></div>
  <p class="progreso-url" id="m-aviso">
    Lista <b><?= e((string) ($lista['nombre'] ?? '—')) ?></b> ·
    plantilla <b><?= e((string) ($plantilla['nombre'] ?? '—')) ?></b> ·
    <?= count($buzonesIds) ?> buzón(es) ·
    <?= (int) $campana['limite_hora'] ?> correos/hora ·
    pausa de <?= (int) $campana['pausa_min'] ?>–<?= (int) $campana['pausa_max'] ?> s
  </p>

  <div class="contadores">
    <div class="contador"><b id="m-pend2"><?= cr_numero($stats['pendientes']) ?></b><span>Pendientes</span></div>
    <div class="contador destacado"><b id="m-env2"><?= cr_numero($stats['enviados']) ?></b><span>Enviados</span></div>
    <div class="contador"><b id="m-reb"><?= cr_numero($stats['rebotes']) ?></b><span>Rebotes</span></div>
    <div class="contador"><b id="m-err"><?= cr_numero($stats['errores']) ?></b><span>Errores</span></div>
  </div>
</div>

<div class="aviso aviso-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
  </svg>
  <span>
    <b>No hace falta que dejes esta página abierta.</b> Programa una tarea cron cada 5 minutos y la campaña
    avanzará sola, respetando los límites por hora:
    <br><code class="mono">curl -s "<?= e(cr_url('cron.php?clave=' . $cronClave)) ?>"</code>
    <?php if ($cronClave === ''): ?>
      <br><b style="color:var(--error)">Falta la clave del cron: genérala en Ajustes → Campañas.</b>
    <?php endif; ?>
  </span>
</div>

<div class="rejilla rejilla-2" style="align-items:start">
  <div class="tarjeta">
    <h3>Últimos envíos</h3>
    <?php if (!$ultimos): ?>
      <p class="suave pequeno">Todavía no se ha enviado nada.</p>
    <?php else: ?>
      <div class="tabla-scroll" style="max-height:420px;overflow-y:auto">
        <table class="tabla panel-tabla" style="min-width:0">
          <thead><tr><th>Correo</th><th>Estado</th><th>Enviado</th><th>Ab.</th></tr></thead>
          <tbody>
          <?php foreach ($ultimos as $u): ?>
            <tr>
              <td class="celda-correo" style="font-size:.8rem"><?= e((string) $u['correo']) ?></td>
              <td>
                <?php $e = (string) $u['estado']; ?>
                <span class="chip <?= $e === 'enviado' ? 'chip-neon' : ($e === 'pendiente' ? 'chip-gris' : 'chip-rojo') ?>"><?= e($e) ?></span>
                <?php if (!empty($u['error'])): ?>
                  <div class="pequeno" style="color:var(--error);max-width:220px"><?= e(cr_recortar((string) $u['error'], 70)) ?></div>
                <?php endif; ?>
              </td>
              <td class="pequeno suave"><?= $u['enviado_en'] ? e(cr_fecha((string) $u['enviado_en'])) : '—' ?></td>
              <td class="pequeno"><?= (int) $u['aperturas'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Vista previa del mensaje</h3>
    <p class="pequeno suave">Asunto: <b><?= e((string) ($plantilla['asunto'] ?? '')) ?></b></p>
    <div style="max-height:380px;overflow:auto;border:1px solid var(--borde);border-radius:var(--r-sm);padding:14px;margin-top:10px;background:#fff;color:#16181c">
      <?= nl2br(e(cr_recortar((string) ($plantilla['cuerpo'] ?? ''), 1500))) ?>
    </div>
    <div class="acciones-fila" style="margin-top:14px">
      <a class="btn btn-fantasma btn-peq" href="plantillas.php?editar=<?= (int) $campana['plantilla_id'] ?>">Editar plantilla</a>
      <form method="post"><?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="preparar"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-fantasma btn-peq"
                data-confirmar="Se regenerará la cola de destinatarios. ¿Continuar?">Regenerar cola</button></form>
      <form method="post"><?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                data-confirmar="¿Borrar la campaña y su historial de envíos?">Borrar campaña</button></form>
    </div>
  </div>
</div>

<script>
/* Envío por lotes desde el navegador, con el mismo motor que usa el cron. */
(function () {
  var btn = document.getElementById('btn-enviar');
  if (!btn) { return; }

  var CSRF = <?= ejs(Seguridad::tokenCsrf()) ?>;
  var API  = <?= ejs(cr_url('api/campana.php')) ?>;
  var ID   = <?= (int) $id ?>;
  var estadoActual = <?= ejs((string) $campana['estado']) ?>;
  var corriendo = false;

  function pinta(p) {
    var n = function (id, v) { var e = document.getElementById(id); if (e) { e.textContent = v; } };
    n('m-enviados', p.enviados); n('m-env2', p.enviados);
    n('m-aperturas', p.aperturas); n('m-clics', p.clics); n('m-bajas', p.bajas);
    n('m-pendientes', p.pendientes + ' pendientes'); n('m-pend2', p.pendientes);
    n('m-reb', p.rebotes); n('m-err', p.errores);
    n('m-estado', p.estado.charAt(0).toUpperCase() + p.estado.slice(1));

    var barra = document.querySelector('#barra-envio i');
    if (barra) { barra.style.width = Math.max(2, p.porcentaje) + '%'; }
    if (p.aviso) { document.getElementById('m-aviso').textContent = p.aviso; }
    var radar = document.getElementById('radar-envio');
    if (radar) { radar.style.opacity = p.estado === 'enviando' ? '1' : '.25'; }
  }

  function llamar(accion) {
    return fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ accion: accion, campana_id: ID, csrf: CSRF }),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function ciclo() {
    if (!corriendo) { return; }
    llamar('paso').then(function (p) {
      if (!p.ok) { throw new Error(p.error || 'Error en el envío'); }
      pinta(p);
      if (p.terminado || p.pendientes === 0 || p.estado !== 'enviando') {
        corriendo = false;
        btn.textContent = p.terminado ? 'Campaña completada' : 'Enviar ahora un lote';
        btn.disabled = !!p.terminado;
        return;
      }
      setTimeout(ciclo, 1200);
    }).catch(function (e) {
      corriendo = false;
      btn.disabled = false;
      btn.textContent = 'Reintentar';
      var aviso = document.getElementById('m-aviso');
      if (aviso) { aviso.textContent = e.message; }
    });
  }

  btn.addEventListener('click', function () {
    if (corriendo) { return; }
    corriendo = true;
    btn.disabled = true;
    btn.textContent = 'Enviando…';
    var primera = (estadoActual !== 'enviando') ? 'iniciar' : 'paso';
    llamar(primera).then(function (p) {
      if (!p.ok) { throw new Error(p.error || 'No se pudo iniciar'); }
      estadoActual = 'enviando';
      pinta(p);
      btn.disabled = false;
      setTimeout(ciclo, 1200);
    }).catch(function (e) {
      corriendo = false;
      btn.disabled = false;
      btn.textContent = 'Reintentar';
      var aviso = document.getElementById('m-aviso');
      if (aviso) { aviso.textContent = e.message; }
    });
  });
})();
</script>

<?php endif; admin_pie(); ?>
