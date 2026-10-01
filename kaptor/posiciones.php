<?php
/**
 * Kaptor - Análisis de palabras clave.
 *
 * Se escribe una palabra o una frase, se escribe el dominio, y Kaptor dice en
 * qué puesto sale. No «más o menos en la primera página»: el puesto 5 de la
 * página 2, con la lista entera delante para contarlo.
 *
 * Tres vistas, la misma página:
 *   · sin parámetros  → el formulario y las últimas mediciones
 *   · POST            → se mide y se enseña el resultado
 *   · ?ver=N          → la prueba de una medición ya hecha
 *   · ?prueba=N&p=M   → el HTML original que devolvió el buscador
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();
@set_time_limit(180);

// Para abrir una medición concreta, un administrador puede ver las de todos;
// para la lista de recientes, cada uno ve las suyas y nada más.
$usuarioId = Auth::esAdmin() ? null : Auth::id();
$mias      = Auth::id();

// ---------------------------------------------------------------------------
//  La prueba en crudo: el HTML tal como lo devolvió el buscador.
// ---------------------------------------------------------------------------
$pruebaId = (int) cr_get('prueba', 0);
if ($pruebaId > 0) {
    $fila = Posiciones::porId($pruebaId, $usuarioId);
    if (!$fila) { http_response_code(404); exit('No encontrado.'); }

    $pag  = max(1, min((int) cr_get('p', 1), Posiciones::MAX_PAGINAS));
    $html = Posiciones::prueba($pruebaId, $pag);
    if ($html === '') { http_response_code(404); exit('Esa prueba ya no está guardada.'); }

    // Se sirve como texto: el HTML de un buscador trae scripts y no tiene por
    // qué ejecutarse dentro de Kaptor para que se pueda leer.
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="serp-' . $pruebaId . '-p' . $pag . '.html"');
    header('X-Content-Type-Options: nosniff');
    echo $html;
    exit;
}

// ---------------------------------------------------------------------------
//  Borrar una medición.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && cr_post('accion', '') === 'borrar') {
    Seguridad::exigirCsrf();
    if (Posiciones::borrar((int) cr_post('id', 0), $usuarioId)) {
        cr_flash('ok', 'Medición borrada.');
    }
    cr_redirigir('posiciones.php');
}

// ---------------------------------------------------------------------------
//  Medir.
// ---------------------------------------------------------------------------
$form = [
    'consulta'    => trim((string) cr_post('consulta', (string) cr_get('consulta', ''))),
    'dominio'     => trim((string) cr_post('dominio',  (string) cr_get('dominio', ''))),
    'motor'       => (string) cr_post('motor', 'google'),
    'proveedor'   => (string) cr_post('proveedor', Ajustes::obtener('pos_proveedor', 'directo')),
    'pais'        => (string) cr_post('pais',   Ajustes::obtener('buscador_pais', 'gt')),
    'idioma'      => (string) cr_post('idioma', Ajustes::obtener('buscador_idioma', 'es')),
    'dispositivo' => (string) cr_post('dispositivo', 'escritorio'),
    'paginas'     => (int) cr_post('paginas', Posiciones::PAGINAS),
    'exacta'      => cr_post('exacta', '') !== '',
];

$medicion = null;
$fallo    = null;   // el intento que no se pudo completar, con su diagnóstico
$error    = '';
$verId    = (int) cr_get('ver', 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && cr_post('accion', '') === 'medir') {
    Seguridad::exigirCsrf();

    if (!Auth::puedeExtraer()) {
        $error = 'Tu cuenta no tiene permiso para usar esta herramienta.';
    } elseif (Seguridad::limiteAlcanzado('posicion')) {
        // Cada medición son varias consultas seguidas a un buscador. Sin freno,
        // una tarde de pruebas deja la IP del hosting marcada como robot y
        // entonces no funciona nada, ni esto ni el extractor.
        $error = 'Has hecho muchas mediciones en la última hora. Espera un rato: '
               . 'los buscadores bloquean a quien les pregunta sin parar.';
    } else {
        Seguridad::registrarPeticion('posicion');
        $medicion = Posiciones::medir($form);

        // Si no se pudo leer ni una lista de resultados, esto NO es una
        // medición: es un intento fallido. Se dice lo que pasó y no se guarda
        // un «no aparece» que sería falso.
        if (empty($medicion['medible'])) {
            $error = (string) ($medicion['error'] ?: 'No se pudo leer ningún resultado del buscador.');
            $fallo = $medicion;
            $medicion = null;
        } else {
            $id = Posiciones::guardar($medicion, null, Auth::id());
            if ($id > 0) { cr_redirigir('posiciones.php?ver=' . $id); }
        }
    }
}

// ---------------------------------------------------------------------------
//  Ver una medición guardada.
// ---------------------------------------------------------------------------
$fila = null;
if ($verId > 0) {
    $fila = Posiciones::porId($verId, $usuarioId);
    if (!$fila) { $error = 'Esa medición no existe o no es tuya.'; }
}

$historial = $fila
    ? Posiciones::historial((string) $fila['consulta'], (string) $fila['dominio'],
                            (string) $fila['motor'], $usuarioId)
    : [];

$ultimas = Posiciones::ultimas($mias, 40);
$motores = Buscador::motores();

cr_cabecera([
    'titulo'      => 'Análisis de palabras clave',
    'activo'      => 'posiciones',
    'descripcion' => 'Di una palabra clave y tu dominio, y Kaptor dice en qué puesto exacto sales, con la lista completa para comprobarlo.',
    'css'         => ['auditor.css'],
]);
?>

<section class="contenedor seccion-posiciones">

  <header class="pos-cab">
    <span class="insignia"><span class="punto"></span> Posición exacta y comprobable</span>
    <h1><?= cr_titulo_brillo('Análisis de palabras clave', 2) ?></h1>
    <p class="pos-sub">Escribe la palabra y tu dominio. Kaptor dice el puesto, y deja la lista
      entera y la página original del buscador para que lo compruebes tú mismo.</p>
  </header>

  <?php foreach (cr_flash_pendientes() as $f): ?>
    <div class="aviso aviso-<?= e($f['tipo'] === 'ok' ? 'bien' : 'mal') ?>"><span><?= e($f['mensaje']) ?></span></div>
  <?php endforeach; ?>

  <?php if ($error !== ''): ?>
    <div class="aviso aviso-mal"><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if ($fallo && !empty($fallo['peticiones'])): ?>
    <section class="tarjeta pos-fallo">
      <h2>Qué contestó el buscador</h2>
      <p class="pequeno suave">Esto no es «no apareces»: es que no se pudo mirar. Aquí está lo que
        pasó en cada intento, para que se vea el motivo y no haya que adivinarlo.</p>
      <ol class="pos-lista-peticiones">
        <?php foreach ($fallo['peticiones'] as $pf): ?>
          <li>
            <span class="pp-pagina">Consulta <?= e((string) ($pf['consulta'] ?? '?')) ?></span>
            <span class="pp-url mono"><?= e((string) ($pf['url'] ?? '')) ?></span>
            <span class="pp-datos">
              HTTP <?= e((string) ($pf['codigo'] ?? 0)) ?> ·
              <?= e(number_format(((int) ($pf['bytes'] ?? 0)) / 1024, 1, ',', '.')) ?> KB —
              <b><?= e((string) ($pf['diagnostico'] ?? '')) ?></b>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="aviso aviso-info" style="margin-top:16px">
        <span><b>Cómo se arregla:</b> elige arriba <b>Serper.dev</b> (2.500 búsquedas gratis),
          <b>SerpApi</b> o <b>Google Custom Search</b>, saca la clave en su web y pégala en
          <a href="<?= e(cr_url('admin/ajustes.php#h-auditor')) ?>">Ajustes → Auditor</a>.
          Esos servicios devuelven el Google de verdad y no los bloquea nadie.</span>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===================== Formulario ===================== -->
  <form method="post" class="tarjeta pos-form" id="pos-form">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="accion" value="medir">

    <div class="pos-par">
      <div class="campo-grupo">
        <label class="etiqueta" for="consulta">Palabra o frase clave</label>
        <input type="text" id="consulta" name="consulta" class="campo" required maxlength="190"
               autocomplete="off" value="<?= e($fila['consulta'] ?? $form['consulta']) ?>">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="dominio">Tu dominio</label>
        <input type="text" id="dominio" name="dominio" class="campo" required maxlength="190"
               inputmode="url" autocapitalize="off" autocomplete="off"
               value="<?= e($fila['dominio'] ?? $form['dominio']) ?>">
      </div>
    </div>

    <div class="campo-grupo">
      <label class="etiqueta" for="proveedor">De dónde se sacan los resultados</label>
      <select id="proveedor" name="proveedor" class="campo">
        <?php foreach (Posiciones::PROVEEDORES as $clave => $nombre): ?>
          <option value="<?= e($clave) ?>" <?= $form['proveedor'] === $clave ? 'selected' : '' ?>>
            <?= e($nombre) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="pequeno suave" style="margin:8px 0 0">
        <b>Preguntar directamente</b> es gratis, pero Google cierra el paso a las búsquedas que
        salen de un servidor: cuando lo hace, Kaptor lo dice y no mide. Los otros tres son
        servicios que devuelven el Google de verdad y <b>funcionan siempre</b>; la clave se pega
        una vez en <a href="<?= e(cr_url('admin/ajustes.php#h-auditor')) ?>">Ajustes → Auditor</a>.
      </p>
    </div>

    <details class="pos-avanzado">
      <summary>Dónde y cómo se busca</summary>
      <div class="pos-opciones">
        <div class="campo-grupo">
          <label class="etiqueta" for="motor">Buscador <span class="suave pequeno">(solo si preguntas directamente)</span></label>
          <select id="motor" name="motor" class="campo">
            <?php foreach ($motores as $clave => $nombre): ?>
              <option value="<?= e($clave) ?>" <?= ($fila['motor'] ?? $form['motor']) === $clave ? 'selected' : '' ?>>
                <?= e($nombre) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="pais">País</label>
          <input type="text" id="pais" name="pais" class="campo" maxlength="2" size="2"
                 value="<?= e($fila['pais'] ?? $form['pais']) ?>">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="idioma">Idioma</label>
          <input type="text" id="idioma" name="idioma" class="campo" maxlength="2" size="2"
                 value="<?= e($fila['idioma'] ?? $form['idioma']) ?>">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="dispositivo">Se busca desde</label>
          <select id="dispositivo" name="dispositivo" class="campo">
            <option value="escritorio" <?= ($fila['dispositivo'] ?? $form['dispositivo']) === 'escritorio' ? 'selected' : '' ?>>Un ordenador</option>
            <option value="movil"      <?= ($fila['dispositivo'] ?? $form['dispositivo']) === 'movil' ? 'selected' : '' ?>>Un teléfono</option>
          </select>
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="paginas">Hasta cuántas páginas mirar</label>
          <select id="paginas" name="paginas" class="campo">
            <?php foreach ([1, 2, 3, 5, 10] as $n): ?>
              <option value="<?= $n ?>" <?= (int) $form['paginas'] === $n ? 'selected' : '' ?>>
                <?= $n ?> (<?= $n * Posiciones::POR_PAGINA ?> puestos)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="pos-casilla">
          <input type="checkbox" name="exacta" value="1" <?= $form['exacta'] ? 'checked' : '' ?>>
          <span>Medir esta dirección exacta, no todo el dominio</span>
        </label>
      </div>
    </details>

    <button class="btn btn-bloque" id="pos-btn">Ver mi posición</button>
    <p class="pos-nota">Se cuentan solo los resultados normales: los anuncios no ocupan puesto.
      Si Google pide verificación —lo hace a menudo con las consultas que salen de un servidor—,
      prueba con Bing o Mojeek: dan el puesto igual y casi nunca bloquean.</p>
  </form>

  <?php if ($fila):
    $resultados = json_decode((string) $fila['resultados'], true) ?: [];
    $peticiones = json_decode((string) $fila['peticiones'], true) ?: [];
    $pos  = $fila['posicion'] !== null ? (int) $fila['posicion'] : null;
    $pag  = $fila['pagina']   !== null ? (int) $fila['pagina']   : null;
    $enP  = $fila['en_pagina']!== null ? (int) $fila['en_pagina']: null;
  ?>

  <!-- ===================== El veredicto ===================== -->
  <section class="tarjeta pos-veredicto <?= $pos === null ? 'sin' : ($pos <= 10 ? 'top' : 'media') ?>">
    <?php if ($pos !== null): ?>
      <p class="pos-eti">«<?= e($fila['consulta']) ?>» · <?= e($fila['dominio']) ?></p>
      <p class="pos-numero"><?= e((string) $pos) ?><small>.º</small></p>
      <p class="pos-frase">
        <?php if ($pag !== null && $pag > 1): ?>
          Puesto <b><?= e((string) $enP) ?></b> de la <b>página <?= e((string) $pag) ?></b>
          — el <?= e((string) $pos) ?>.º contando desde el principio.
        <?php else: ?>
          Puesto <b><?= e((string) $pos) ?></b> de la <b>primera página</b>.
        <?php endif; ?>
      </p>
      <p class="pos-url"><a href="<?= e($fila['url_hallada']) ?>" target="_blank" rel="noopener nofollow"><?= e($fila['url_hallada']) ?></a></p>
    <?php else: ?>
      <p class="pos-eti">«<?= e($fila['consulta']) ?>» · <?= e($fila['dominio']) ?></p>
      <p class="pos-numero pos-nada">—</p>
      <p class="pos-frase">
        No aparece en los primeros <b><?= e(cr_numero((int) $fila['revisados'])) ?></b> resultados
        que se revisaron<?= $fila['error'] !== '' ? '' : '.' ?>
      </p>
      <?php if ($fila['error'] !== ''): ?>
        <p class="pos-frase pos-aviso"><?= e((string) $fila['error']) ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <dl class="pos-dl">
      <div><dt>Buscador</dt><dd><?= e($motores[$fila['motor']] ?? $fila['motor']) ?></dd></div>
      <div><dt>Fuente</dt><dd><?= e(Posiciones::PROVEEDORES[$fila['proveedor'] ?? 'directo'] ?? 'Directo') ?></dd></div>
      <div><dt>País</dt><dd><?= e(strtoupper((string) $fila['pais'])) ?></dd></div>
      <div><dt>Idioma</dt><dd><?= e($fila['idioma']) ?></dd></div>
      <div><dt>Desde</dt><dd><?= $fila['dispositivo'] === 'movil' ? 'Un teléfono' : 'Un ordenador' ?></dd></div>
      <div><dt>Revisados</dt><dd><?= e(cr_numero((int) $fila['revisados'])) ?> puestos</dd></div>
      <div><dt>Medido el</dt><dd><?= e(cr_fecha((string) $fila['creado'])) ?></dd></div>
    </dl>
  </section>

  <!-- ===================== La comprobación ===================== -->
  <section class="pos-prueba">
    <h2>Compruébalo</h2>
    <p class="inf-sub">Esto no hay que creérselo: se comprueba. Abajo está la búsqueda que se hizo,
      la lista completa en su orden y la página original que devolvió el buscador.</p>

    <div class="tarjeta pos-peticiones">
      <h3>Las búsquedas que se hicieron</h3>
      <p class="pequeno suave">Abre cualquiera en tu navegador y cuenta los resultados. Ojo: un
        buscador cambia lo que enseña con el tiempo y con quién pregunta, así que puede no salir
        idéntico a lo de ahora; por eso además se guardó la página original.</p>
      <ol class="pos-lista-peticiones">
        <?php foreach ($peticiones as $i => $p): ?>
          <li>
            <span class="pp-pagina">
              Consulta <?= e((string) ($p['consulta'] ?? $i + 1)) ?>
              <?php if (!empty($p['hasta'])): ?>
                · puestos <?= e((string) $p['desde']) ?>–<?= e((string) $p['hasta']) ?>
              <?php endif; ?>
            </span>
            <a class="pp-url mono" href="<?= e((string) ($p['url'] ?? '')) ?>" target="_blank" rel="noopener nofollow"><?= e((string) ($p['url'] ?? '')) ?></a>
            <span class="pp-datos">
              HTTP <?= e((string) ($p['codigo'] ?? 0)) ?> ·
              <?= e(number_format(((int) ($p['bytes'] ?? 0)) / 1024, 1, ',', '.')) ?> KB ·
              <?= e(cr_numero((int) ($p['ms'] ?? 0))) ?> ms
              <?php if (!empty($p['diagnostico'])): ?>
                · <?= e((string) $p['diagnostico']) ?>
              <?php endif; ?>
              <?php $nPag = (int) ($p['consulta'] ?? $p['pagina'] ?? 0);
                    if ($nPag > 0 && Posiciones::prueba((int) $fila['id'], $nPag) !== ''): ?>
                · <a href="<?= e(cr_url('posiciones.php?prueba=' . (int) $fila['id'] . '&p=' . $nPag)) ?>" target="_blank" rel="noopener">ver la página guardada</a>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="tarjeta pos-tabla-caja">
      <h3>Todos los resultados, en su orden</h3>
      <div class="tabla-scroll">
        <table class="tabla pos-tabla">
          <thead>
            <tr><th>Puesto</th><th>Pág.</th><th>Resultado</th></tr>
          </thead>
          <tbody>
            <?php foreach ($resultados as $r): ?>
              <tr class="<?= !empty($r['nuestro']) ? 'pos-nuestro' : '' ?>">
                <td class="pos-td-num"><?= e((string) ($r['puesto'] ?? '')) ?></td>
                <td class="pos-td-pag"><?= e((string) ($r['pagina'] ?? '')) ?>·<?= e((string) ($r['en_pagina'] ?? '')) ?></td>
                <td>
                  <span class="pos-titulo"><?= e((string) ($r['titulo'] ?? '')) ?></span>
                  <a class="pos-enlace" href="<?= e((string) ($r['url'] ?? '')) ?>" target="_blank" rel="noopener nofollow"><?= e(cr_recortar((string) ($r['url'] ?? ''), 90)) ?></a>
                  <?php if (!empty($r['nuestro'])): ?><span class="pos-marca">tu web</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$resultados): ?>
              <tr><td colspan="3" class="suave">El buscador no devolvió ningún resultado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (count($historial) > 1): ?>
      <div class="tarjeta pos-historial">
        <h3>Cómo se ha movido</h3>
        <ol class="pos-barras">
          <?php
          $peor = 1;
          foreach ($historial as $h) { $peor = max($peor, (int) ($h['posicion'] ?? 0)); }
          foreach ($historial as $h):
            $v = $h['posicion'] !== null ? (int) $h['posicion'] : null;
            $alto = $v === null ? 2 : max(4, (int) round(100 - ($v - 1) * 100 / max(1, $peor)));
          ?>
            <li>
              <span class="ph-barra"><i style="height:<?= e((string) $alto) ?>%"></i></span>
              <span class="ph-num"><?= $v === null ? '—' : e((string) $v) ?></span>
              <span class="ph-fecha"><?= e(cr_fecha((string) $h['creado'], false)) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ===================== Historial ===================== -->
  <?php if ($ultimas): ?>
    <section class="tarjeta pos-recientes">
      <h2>Mediciones recientes</h2>
      <div class="tabla-scroll">
        <table class="tabla">
          <thead>
            <tr><th>Palabra clave</th><th>Dominio</th><th>Puesto</th><th class="col-conf">Buscador</th><th class="col-dominio">Fecha</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($ultimas as $u): ?>
              <tr>
                <td><a href="<?= e(cr_url('posiciones.php?ver=' . (int) $u['id'])) ?>"><?= e((string) $u['consulta']) ?></a></td>
                <td class="mono"><?= e((string) $u['dominio']) ?></td>
                <td>
                  <?php if ($u['posicion'] !== null): ?>
                    <span class="pastilla p-<?= (int) $u['posicion'] <= 10 ? 'verde' : ((int) $u['posicion'] <= 30 ? 'ambar' : 'rojo') ?>"><?= e((string) $u['posicion']) ?></span>
                  <?php else: ?>
                    <span class="pastilla p-rojo">—</span>
                  <?php endif; ?>
                </td>
                <td class="col-conf"><?= e($motores[$u['motor']] ?? (string) $u['motor']) ?></td>
                <td class="col-dominio suave"><?= e(cr_fecha((string) $u['creado'])) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('¿Borrar esta medición y su prueba?')">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-fantasma btn-peq" style="color:var(--error)">Borrar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

</section>

<script>
// Medir tarda: varias páginas de resultados con su pausa entre una y otra.
// Sin un aviso, la gente pulsa dos y tres veces.
(function () {
  var f = document.getElementById('pos-form'), b = document.getElementById('pos-btn');
  if (!f || !b) { return; }
  f.addEventListener('submit', function () {
    b.disabled = true;
    b.textContent = 'Buscando en el buscador…';
  });
})();
</script>

<?php cr_pie(false); ?>
