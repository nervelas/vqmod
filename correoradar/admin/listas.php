<?php
/** CorreoRadar - Listas de contactos. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion   = (string) cr_post('accion');
    $listaId  = (int) cr_post('lista_id', 0);

    switch ($accion) {
        case 'crear':
            $id = Contactos::crearLista((string) cr_post('nombre'), (string) cr_post('descripcion'));
            cr_flash('exito', 'Lista creada.');
            cr_redirigir('admin/listas.php?id=' . $id);
            // no se alcanza

        case 'importar_escaneo':
            $r = Contactos::importarDeEscaneo($listaId, (int) cr_post('escaneo_id'), [
                'solo_genericos' => !empty($_POST['solo_genericos']),
                'confianza_min'  => (int) cr_post('confianza_min', 0),
                'solo_mx'        => !empty($_POST['solo_mx']),
            ]);
            cr_flash('exito', $r['anadidos'] . ' contactos añadidos (' . $r['omitidos'] . ' omitidos por duplicados, filtros o supresión).');
            break;

        case 'importar_texto':
            $texto = (string) cr_post('contactos');
            if (!empty($_FILES['archivo']['tmp_name']) && is_uploaded_file($_FILES['archivo']['tmp_name'])) {
                if ((int) $_FILES['archivo']['size'] > 5 * 1024 * 1024) {
                    cr_flash('error', 'El archivo no puede pesar más de 5 MB.');
                    break;
                }
                $texto .= "\n" . (string) file_get_contents($_FILES['archivo']['tmp_name']);
            }
            $r = Contactos::importarTexto($listaId, $texto);
            cr_flash('exito', $r['anadidos'] . ' contactos añadidos (' . $r['omitidos'] . ' omitidos).');
            break;

        case 'limpiar':
            $n = Contactos::limpiarSuprimidos($listaId);
            cr_flash('exito', $n . ' contactos marcados como suprimidos.');
            break;

        case 'borrar_contacto':
            BD::ejecutar('DELETE FROM `cr_contactos` WHERE `id` = ? AND `lista_id` = ?', [(int) cr_post('id'), $listaId]);
            Contactos::actualizarRecuento($listaId);
            cr_flash('exito', 'Contacto eliminado.');
            break;

        case 'borrar_lista':
            Contactos::borrarLista($listaId);
            cr_flash('exito', 'Lista eliminada.');
            cr_redirigir('admin/listas.php');
            // no se alcanza
    }
    cr_redirigir('admin/listas.php?id=' . $listaId);
}

$listaId = (int) cr_get('id', 0);
$lista   = $listaId > 0 ? Contactos::lista($listaId) : null;

admin_cabecera(['titulo' => $lista ? 'Lista: ' . $lista['nombre'] : 'Listas de contactos', 'activo' => 'listas.php']);

if (!$lista):
    $listas = Contactos::listas();
?>

<div class="rejilla rejilla-2" style="align-items:start">
  <div class="tarjeta" style="grid-column:1 / -1">
    <h3>Tus listas (<?= cr_numero(count($listas)) ?>)</h3>
    <?php if (!$listas): ?>
      <div class="vacio"><p>Todavía no hay listas. Crea la primera aquí abajo.</p></div>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tabla panel-tabla">
          <thead><tr><th>Nombre</th><th>Contactos activos</th><th>Creada</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($listas as $l): ?>
            <tr>
              <td><a href="?id=<?= (int) $l['id'] ?>"><b><?= e((string) $l['nombre']) ?></b></a>
                <?php if (!empty($l['descripcion'])): ?>
                  <div class="pequeno suave"><?= e(cr_recortar((string) $l['descripcion'], 70)) ?></div>
                <?php endif; ?>
              </td>
              <td><b class="oro"><?= cr_numero((int) $l['contactos']) ?></b></td>
              <td class="pequeno suave"><?= e(cr_fecha((string) $l['creado'])) ?></td>
              <td><a class="btn btn-fantasma btn-peq" href="?id=<?= (int) $l['id'] ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Crear una lista</h3>
    <form method="post">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="campo-grupo">
        <label class="etiqueta" for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="campo" required placeholder="Centros educativos · Guatemala">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="descripcion">Descripción (opcional)</label>
        <textarea id="descripcion" name="descripcion" class="campo" rows="2"></textarea>
      </div>
      <button class="btn btn-bloque">Crear lista</button>
    </form>
  </div>

  <div class="tarjeta">
    <h3>Cómo se llenan las listas</h3>
    <div class="estado-linea"><span><b>Desde una extracción</b><em>Abre la lista y elige uno de tus escaneos: se importan los correos con sus filtros de confianza.</em></span></div>
    <div class="estado-linea"><span><b>Pegando texto o CSV</b><em>Una dirección por línea, o un CSV con las columnas correo, nombre, centro y teléfono.</em></span></div>
    <div class="estado-linea"><span><b>Siempre se respeta la supresión</b><em>Quien se dio de baja nunca vuelve a entrar en ninguna lista.</em></span></div>
  </div>
</div>

<?php else:
    $buscar  = trim((string) cr_get('q', ''));
    $pagina  = max(1, (int) cr_get('p', 1));
    $porPag  = 50;
    $contactos = Contactos::deLista($listaId, $porPag, ($pagina - 1) * $porPag, $buscar);
    $totalCont = (int) BD::valor('SELECT COUNT(*) FROM `cr_contactos` WHERE `lista_id` = ?', [$listaId], 0);
    $escaneos  = BD::todos('SELECT `id`,`host`,`correos`,`inicio` FROM `cr_escaneos` WHERE `correos` > 0 ORDER BY `id` DESC LIMIT 40');
    $paginas   = max(1, (int) ceil($totalCont / $porPag));
?>

<div class="rejilla rejilla-4" style="margin-bottom:20px">
  <div class="metrica"><b><?= cr_numero((int) $lista['contactos']) ?></b><span>Contactos activos</span></div>
  <div class="metrica"><b><?= cr_numero($totalCont) ?></b><span>Total en la lista</span></div>
  <div class="metrica"><b><?= cr_numero((int) BD::valor('SELECT COUNT(*) FROM `cr_contactos` WHERE `lista_id` = ? AND `estado` <> \'activo\'', [$listaId], 0)) ?></b><span>Bajas y rebotes</span></div>
  <div class="metrica"><b><?= cr_numero(Supresion::total()) ?></b><span>En supresión global</span></div>
</div>

<div class="rejilla rejilla-2" style="align-items:start;margin-bottom:20px">
  <div class="tarjeta">
    <h3>Importar desde una extracción</h3>
    <?php if (!$escaneos): ?>
      <p class="suave pequeno">Todavía no tienes extracciones con correos. Haz una desde la portada.</p>
    <?php else: ?>
      <form method="post">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="importar_escaneo">
        <input type="hidden" name="lista_id" value="<?= $listaId ?>">
        <div class="campo-grupo">
          <label class="etiqueta" for="escaneo_id">Extracción</label>
          <select id="escaneo_id" name="escaneo_id" class="campo" required>
            <?php foreach ($escaneos as $es): ?>
              <option value="<?= (int) $es['id'] ?>">
                <?= e((string) $es['host']) ?> · <?= (int) $es['correos'] ?> correos · <?= e(cr_fecha((string) $es['inicio'], false)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="confianza_min">Confianza mínima</label>
          <input type="number" id="confianza_min" name="confianza_min" class="campo" style="max-width:120px" min="0" max="99" value="60">
        </div>
        <label class="interruptor" style="margin-bottom:10px">
          <input type="checkbox" name="solo_genericos" value="1">
          <span class="pista" aria-hidden="true"></span>
          <span class="txt">Solo buzones genéricos (info@, contacto@…)</span>
        </label>
        <label class="interruptor" style="margin-bottom:16px">
          <input type="checkbox" name="solo_mx" value="1" checked>
          <span class="pista" aria-hidden="true"></span>
          <span class="txt">Descartar los que no tengan MX</span>
        </label>
        <button class="btn btn-bloque">Importar contactos</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Pegar o subir contactos</h3>
    <form method="post" enctype="multipart/form-data">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="importar_texto">
      <input type="hidden" name="lista_id" value="<?= $listaId ?>">
      <div class="campo-grupo">
        <label class="etiqueta" for="contactos">Una dirección por línea, o CSV con cabecera</label>
        <textarea id="contactos" name="contactos" class="campo mono" rows="6"
                  placeholder="direccion@colegio.edu.gt&#10;Colegio San José &lt;info@sanjose.edu.gt&gt;&#10;&#10;o bien:&#10;correo;nombre;centro&#10;info@colegio.gt;Ana Pérez;Colegio Central"></textarea>
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="archivo">…o sube un archivo CSV/TXT</label>
        <input type="file" id="archivo" name="archivo" class="campo" accept=".csv,.txt">
      </div>
      <button class="btn btn-bloque">Importar</button>
    </form>
  </div>
</div>

<div class="tarjeta">
  <div class="resultados-cab">
    <div>
      <h3 style="margin:0">Contactos</h3>
      <p class="sub"><?= cr_numero($totalCont) ?> en total</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <form method="get" class="buscador" style="min-width:220px">
        <input type="hidden" name="id" value="<?= $listaId ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" name="q" class="campo" placeholder="Buscar…" value="<?= e($buscar) ?>">
      </form>
      <form method="post">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="limpiar">
        <input type="hidden" name="lista_id" value="<?= $listaId ?>">
        <button class="btn btn-fantasma btn-peq">Depurar suprimidos</button>
      </form>
      <form method="post">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="borrar_lista">
        <input type="hidden" name="lista_id" value="<?= $listaId ?>">
        <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                data-confirmar="¿Borrar la lista «<?= e((string) $lista['nombre']) ?>» y todos sus contactos?">Borrar lista</button>
      </form>
      <a class="btn btn-fantasma btn-peq" href="listas.php">Volver</a>
    </div>
  </div>

  <?php if (!$contactos): ?>
    <div class="vacio"><p>No hay contactos que mostrar.</p></div>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead><tr><th>Correo</th><th>Nombre</th><th>Centro</th><th>Teléfono</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($contactos as $c): ?>
          <tr>
            <td><span class="celda-correo"><?= e((string) $c['correo']) ?></span></td>
            <td class="pequeno"><?= e((string) $c['nombre']) ?: '—' ?></td>
            <td class="pequeno"><?= e(cr_recortar((string) $c['centro'], 28)) ?: '—' ?></td>
            <td class="pequeno mono"><?= e((string) $c['telefono']) ?: '—' ?></td>
            <td>
              <?php $est = (string) $c['estado']; ?>
              <span class="chip <?= $est === 'activo' ? 'chip-neon' : ($est === 'rebotado' ? 'chip-rojo' : 'chip-gris') ?>">
                <?= e(ucfirst($est)) ?>
              </span>
            </td>
            <td>
              <form method="post">
                <?= Seguridad::campoCsrf() ?>
                <input type="hidden" name="accion" value="borrar_contacto">
                <input type="hidden" name="lista_id" value="<?= $listaId ?>">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-fantasma btn-peq" style="color:var(--error)">Quitar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($paginas > 1): ?>
      <nav class="paginacion">
        <?php
        $base = '?id=' . $listaId . '&q=' . rawurlencode($buscar) . '&p=';
        $desde = max(1, $pagina - 3); $hasta = min($paginas, $pagina + 3);
        for ($i = $desde; $i <= $hasta; $i++) {
            echo $i === $pagina ? '<span class="actual">' . $i . '</span>'
                                : '<a href="' . e($base . $i) . '">' . $i . '</a>';
        }
        ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php endif; admin_pie(); ?>
