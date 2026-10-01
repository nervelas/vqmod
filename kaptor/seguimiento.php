<?php
/**
 * Kaptor - Seguimiento de palabras clave.
 *
 * El cuadro de mando de quien lleva el SEO de varias empresas: todas las
 * palabras, todos los dominios, el puesto de cada uno y si subió o bajó desde
 * la vez anterior. Las mediciones las empuja el cron, así que esto se mira,
 * no se espera.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();
@set_time_limit(120);

$usuarioId = Auth::esAdmin() ? null : Auth::id();
$mias      = Auth::id();

$cliente = trim((string) cr_get('cliente', ''));
$aviso   = '';
$error   = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion', '');

    if ($accion === 'alta') {
        $palabras = (string) cr_post('palabras', '');
        $dominios = array_values(array_filter(array_map(
            'trim',
            preg_split('~[\s,;]+~', (string) cr_post('dominios', '')) ?: []
        )));

        if (trim($palabras) === '' || !$dominios) {
            $error = 'Hacen falta las palabras clave y al menos un dominio.';
        } else {
            $r = Seguimiento::altaEnBloque($palabras, $dominios, [
                'cliente'     => trim((string) cr_post('cliente', '')),
                'pais'        => (string) cr_post('pais', 'gt'),
                'idioma'      => (string) cr_post('idioma', 'es'),
                'dispositivo' => (string) cr_post('dispositivo', 'escritorio'),
                'motor'       => (string) cr_post('motor', 'google'),
                'profundidad' => (int) cr_post('profundidad', 100),
                'cada_dias'   => (int) cr_post('cada_dias', Seguimiento::CADA_DIAS),
            ], $mias);
            cr_flash('ok', $r['altas'] . ' palabras clave en seguimiento para '
                . count($dominios) . ' ' . (count($dominios) === 1 ? 'dominio' : 'dominios')
                . '. Se medirán solas; el cron las va empujando.');
            cr_redirigir('seguimiento.php');
        }
    }

    if ($accion === 'medir_ya') {
        $n = Seguimiento::rondaYa($usuarioId);
        cr_flash('ok', 'Ronda lanzada: ' . $n . ' búsquedas vuelven a la cola.');
        cr_redirigir('seguimiento.php');
    }

    if ($accion === 'pasada') {
        // Empujar a mano, para no tener que esperar al cron en la primera vez.
        $r = Seguimiento::pasada(Seguimiento::POR_PASADA);
        cr_flash('ok', $r['medidas'] > 0
            ? $r['medidas'] . ' búsquedas medidas · ' . $r['dominios'] . ' dominios · '
              . $r['creditos'] . ' consultas gastadas.'
            : 'No hay ninguna búsqueda esperando turno.');
        cr_redirigir('seguimiento.php' . ($cliente !== '' ? '?cliente=' . rawurlencode($cliente) : ''));
    }

    if ($accion === 'quitar') {
        Seguimiento::dejarDeVigilar((int) cr_post('clave_id', 0), (string) cr_post('dominio', ''));
        cr_flash('ok', 'Quitado del seguimiento.');
        cr_redirigir('seguimiento.php' . ($cliente !== '' ? '?cliente=' . rawurlencode($cliente) : ''));
    }
}

$cuadro   = Seguimiento::cuadro($usuarioId, $cliente);
$resumen  = Seguimiento::resumen($cuadro);
$clientes = Seguimiento::clientes($usuarioId);
$enCola   = count(Seguimiento::pendientes(100));
$motores  = Buscador::motores();

cr_cabecera([
    'titulo'      => 'Seguimiento de palabras clave',
    'activo'      => 'seguimiento',
    'descripcion' => 'Todas tus palabras clave, de todos tus clientes, con su posición y si subió o bajó.',
    'css'         => ['auditor.css'],
]);
?>

<section class="contenedor seccion-posiciones">

  <header class="pos-cab">
    <span class="insignia"><span class="punto"></span> Se mide solo</span>
    <h1><?= cr_titulo_brillo('Seguimiento de palabras clave', 2) ?></h1>
    <p class="pos-sub">Anota las palabras una vez y Kaptor las mide solo cada semana. Una búsqueda
      sirve para todos los dominios que vigiles con ella, así que llevar a varios clientes no
      cuesta más que llevar a uno.</p>
  </header>

  <?php foreach (cr_flash_pendientes() as $f): ?>
    <div class="aviso aviso-<?= e($f['tipo'] === 'ok' ? 'bien' : 'mal') ?>"><span><?= e($f['mensaje']) ?></span></div>
  <?php endforeach; ?>
  <?php if ($error !== ''): ?>
    <div class="aviso aviso-mal"><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if ($cuadro): ?>
    <!-- ===================== Resumen ===================== -->
    <section class="seg-resumen">
      <div class="seg-dato"><b><?= e(cr_numero($resumen['busquedas'])) ?></b><span>búsquedas</span></div>
      <div class="seg-dato"><b><?= e(cr_numero($resumen['filas'])) ?></b><span>posiciones vigiladas</span></div>
      <div class="seg-dato verde"><b><?= e(cr_numero($resumen['top3'])) ?></b><span>en el top 3</span></div>
      <div class="seg-dato verde"><b><?= e(cr_numero($resumen['top10'])) ?></b><span>en la primera página</span></div>
      <div class="seg-dato"><b><?= e(cr_numero($resumen['suben'])) ?> ▲</b><span>subieron</span></div>
      <div class="seg-dato"><b><?= e(cr_numero($resumen['bajan'])) ?> ▼</b><span>bajaron</span></div>
      <div class="seg-dato"><b><?= e(cr_numero($resumen['creditos'])) ?></b><span>consultas gastadas</span></div>
    </section>
  <?php endif; ?>

  <!-- ===================== Barra de acciones ===================== -->
  <div class="seg-barra">
    <div class="seg-filtros">
      <a class="btn btn-fantasma btn-peq <?= $cliente === '' ? 'activo' : '' ?>" href="<?= e(cr_url('seguimiento.php')) ?>">Todos</a>
      <?php foreach ($clientes as $c): ?>
        <a class="btn btn-fantasma btn-peq <?= $cliente === $c ? 'activo' : '' ?>"
           href="<?= e(cr_url('seguimiento.php?cliente=' . rawurlencode($c))) ?>"><?= e($c) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="seg-acciones">
      <?php if ($enCola > 0): ?>
        <span class="seg-cola"><?= e(cr_numero($enCola)) ?> esperando turno</span>
      <?php endif; ?>
      <form method="post" style="display:inline">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="pasada">
        <button class="btn btn-fantasma btn-peq">Medir <?= Seguimiento::POR_PASADA ?> ahora</button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('¿Volver a medirlo todo? Gastará una consulta por búsqueda.')">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="medir_ya">
        <button class="btn btn-fantasma btn-peq">Medirlo todo de nuevo</button>
      </form>
    </div>
  </div>

  <!-- ===================== El cuadro ===================== -->
  <?php if ($cuadro): ?>
    <section class="tarjeta seg-caja">
      <div class="tabla-scroll">
        <table class="tabla seg-tabla">
          <thead>
            <tr>
              <th>Palabra clave</th>
              <th class="col-conf">Cliente</th>
              <th>Dominio</th>
              <th>Puesto</th>
              <th>Cambio</th>
              <th class="col-dominio">Medido</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cuadro as $f):
              $p = $f['posicion']; $camb = $f['cambio']; ?>
              <tr>
                <td>
                  <span class="seg-consulta"><?= e((string) $f['consulta']) ?></span>
                  <span class="seg-donde"><?= e(strtoupper((string) $f['pais'])) ?> ·
                    <?= e((string) $f['idioma']) ?> ·
                    <?= $f['dispositivo'] === 'movil' ? 'móvil' : 'ordenador' ?></span>
                </td>
                <td class="col-conf"><?= e((string) $f['cliente']) ?></td>
                <td class="mono seg-dominio"><?= e((string) $f['dominio']) ?></td>
                <td>
                  <?php if ($f['medicion_id'] === null): ?>
                    <span class="pastilla">sin medir</span>
                  <?php elseif ($p === null): ?>
                    <span class="pastilla p-rojo" title="No sale en los primeros <?= e((string) $f['profundidad']) ?>">—</span>
                  <?php else: ?>
                    <a class="pastilla p-<?= $p <= 10 ? 'verde' : ($p <= 30 ? 'ambar' : 'rojo') ?>"
                       href="<?= e(cr_url('posiciones.php?ver=' . (int) $f['medicion_id'])) ?>"
                       title="Ver la prueba"><?= e((string) $p) ?></a>
                  <?php endif; ?>
                </td>
                <td class="seg-cambio">
                  <?php if ($camb === null): ?>
                    <span class="suave">—</span>
                  <?php elseif ($camb > 0): ?>
                    <span class="sube">▲ <?= e((string) $camb) ?></span>
                  <?php elseif ($camb < 0): ?>
                    <span class="baja">▼ <?= e((string) abs($camb)) ?></span>
                  <?php else: ?>
                    <span class="suave">=</span>
                  <?php endif; ?>
                </td>
                <td class="col-dominio suave">
                  <?= $f['medida_en'] ? e(cr_fecha((string) $f['medida_en'], false)) : 'nunca' ?>
                  <?php if ($f['ultimo_error'] !== ''): ?>
                    <span class="seg-error" title="<?= e((string) $f['ultimo_error']) ?>">no se pudo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('¿Dejar de vigilar este dominio para esta palabra?')">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="quitar">
                    <input type="hidden" name="clave_id" value="<?= (int) $f['clave_id'] ?>">
                    <input type="hidden" name="dominio" value="<?= e((string) $f['dominio']) ?>">
                    <button class="btn btn-fantasma btn-peq" style="color:var(--error)">Quitar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php else: ?>
    <div class="tarjeta seg-vacio">
      <p>Todavía no sigues ninguna palabra clave. Añádelas abajo: se pegan todas de una vez.</p>
    </div>
  <?php endif; ?>

  <!-- ===================== Alta en bloque ===================== -->
  <section class="tarjeta seg-alta">
    <h2>Añadir palabras clave</h2>
    <p class="pequeno suave">Una por línea. Si una palabra es de otro cliente, se puede escribir
      <span class="mono">palabra clave ; sudominio.com</span> y se le añade solo a ese.</p>

    <form method="post">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="alta">

      <div class="campo-grupo">
        <label class="etiqueta" for="palabras">Palabras clave</label>
        <textarea id="palabras" name="palabras" class="campo" rows="8" required
                  placeholder="colegio bilingue guatemala&#10;colegio en zona 15&#10;inscripciones colegio guatemala"></textarea>
      </div>

      <div class="pos-par">
        <div class="campo-grupo">
          <label class="etiqueta" for="dominios">Dominios a vigilar</label>
          <input type="text" id="dominios" name="dominios" class="campo" required
                 inputmode="url" autocapitalize="off" autocomplete="off"
                 placeholder="micliente.gt  competidor1.gt  competidor2.gt">
          <p class="pequeno suave" style="margin:6px 0 0">Separados por espacios. Añade también a la
            competencia: salen de la misma búsqueda y no cuestan nada más.</p>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="cliente">Cliente <span class="suave pequeno">(para agrupar)</span></label>
          <input type="text" id="cliente" name="cliente" class="campo" maxlength="120"
                 value="<?= e($cliente) ?>" placeholder="Colegio Santa Ana">
        </div>
      </div>

      <details class="pos-avanzado">
        <summary>Dónde y cada cuánto se mide</summary>
        <div class="pos-opciones">
          <div class="campo-grupo">
            <label class="etiqueta" for="motor">Buscador <span class="suave pequeno">(si se pregunta directamente)</span></label>
            <select id="motor" name="motor" class="campo">
              <?php foreach ($motores as $k => $n): ?>
                <option value="<?= e($k) ?>" <?= $k === 'google' ? 'selected' : '' ?>><?= e($n) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="pais">País</label>
            <input type="text" id="pais" name="pais" class="campo" maxlength="2" value="<?= e(Ajustes::obtener('buscador_pais', 'gt')) ?>">
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="idioma">Idioma</label>
            <input type="text" id="idioma" name="idioma" class="campo" maxlength="2" value="<?= e(Ajustes::obtener('buscador_idioma', 'es')) ?>">
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="dispositivo">Se busca desde</label>
            <select id="dispositivo" name="dispositivo" class="campo">
              <option value="escritorio">Un ordenador</option>
              <option value="movil">Un teléfono</option>
            </select>
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="profundidad">Hasta qué puesto mirar</label>
            <select id="profundidad" name="profundidad" class="campo">
              <?php foreach ([10, 30, 50, 100] as $n): ?>
                <option value="<?= $n ?>" <?= $n === 100 ? 'selected' : '' ?>><?= $n ?> puestos</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo-grupo">
            <label class="etiqueta" for="cada_dias">Medir cada</label>
            <select id="cada_dias" name="cada_dias" class="campo">
              <?php foreach ([1 => 'Un día', 3 => 'Tres días', 7 => 'Una semana', 15 => 'Quince días', 30 => 'Un mes'] as $d => $txt): ?>
                <option value="<?= $d ?>" <?= $d === Seguimiento::CADA_DIAS ? 'selected' : '' ?>><?= e($txt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </details>

      <button class="btn btn-bloque">Poner en seguimiento</button>
    </form>

    <div class="aviso aviso-info" style="margin-top:18px">
      <span><b>Lo que cuesta:</b> una consulta por búsqueda y ronda, da igual cuántos dominios
        vigiles con ella. Cien palabras medidas una vez por semana son unas cuatrocientas consultas
        al mes. Con «Preguntar directamente» no cuesta nada, pero Google bloquea; con un servicio
        de los de <a href="<?= e(cr_url('admin/ajustes.php#h-auditor')) ?>">Ajustes → Auditor</a>
        funciona siempre.</span>
    </div>
  </section>

  <div class="aviso aviso-info" style="margin-top:22px">
    <span><b>Para que se mida solo</b> hace falta el cron de Kaptor activo
      (Ajustes → Campañas → Clave del cron). Con él, las palabras se van midiendo a lo largo del
      día sin que nadie abra el navegador.</span>
  </div>

</section>

<?php cr_pie(false); ?>
