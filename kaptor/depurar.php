<?php
/**
 * Kaptor - Depurar una lista de correos.
 *
 * Se pega cualquier texto con correos dentro (una lista, un CSV, una columna
 * de Excel, un montón de firmas...) y devuelve la lista limpia:
 *   · sin repetidos
 *   · solo con las extensiones de dominio que se pidan (.com, .com.gt, .edu.gt…)
 *   · sin buzones basura si se marcan las casillas correspondientes
 *
 * Funciona sin JavaScript: el formulario se envía a sí mismo y las descargas
 * salen del mismo POST, así no depende de la conexión ni de la sesión.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

// Las listas muy grandes necesitan algo mas de aire; si el hosting no deja
// subirlo, se sigue trabajando con lo que haya (3 MB caben en 128 MB de PHP).
@ini_set('memory_limit', '512M');
@set_time_limit(120);

$puedeUsar = Auth::puedeExtraer();

$texto   = '';
$op      = [
    'extensiones'        => '',
    'excluir'            => '',
    'solo'               => '',
    'quitar_noreply'     => true,
    'quitar_desechables' => true,
    'quitar_suprimidos'  => false,
    'verificar_mx'       => false,
    'uno_por_dominio'    => false,
    'orden'              => 'correo',
];
$resultado = null;
$error     = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $puedeUsar) {
    Seguridad::exigirCsrf();

    $texto = (string) cr_post('lista', '');
    $op['extensiones']        = trim((string) cr_post('extensiones', ''));
    $op['excluir']            = trim((string) cr_post('excluir', ''));
    $op['solo']               = (string) cr_post('solo', '');
    $op['quitar_noreply']     = cr_post('quitar_noreply', '') !== '';
    $op['quitar_desechables'] = cr_post('quitar_desechables', '') !== '';
    $op['quitar_suprimidos']  = cr_post('quitar_suprimidos', '') !== '';
    $op['verificar_mx']       = cr_post('verificar_mx', '') !== '';
    $op['uno_por_dominio']    = cr_post('uno_por_dominio', '') !== '';
    $op['orden']              = (string) cr_post('orden', 'correo');

    if (trim($texto) === '') {
        $error = 'Pega primero la lista de correos que quieres depurar.';
    } elseif (strlen($texto) > Depurador::MAX_ENTRADA) {
        $error = 'La lista es demasiado grande (máximo 3 MB de texto, unos 100.000 correos). Divídela en dos partes.';
    } else {
        $resultado = Depurador::procesar($texto, $op);

        // Descarga directa desde el mismo envío.
        $formato = strtolower((string) cr_post('descargar', ''));
        if (in_array($formato, ['txt', 'csv', 'xlsx'], true)) {
            if (!$resultado['correos']) {
                $error = 'No quedó ningún correo que descargar con esos filtros.';
            } else {
                $etiqueta  = Depurador::extensiones($op['extensiones']);
                $contenido = Exportador::lista($resultado['correos'], $formato);
                Exportador::descargar(
                    $contenido,
                    Exportador::nombreLista($formato, $etiqueta ? implode('-', array_slice($etiqueta, 0, 2)) : ''),
                    Exportador::mime($formato)
                );
            }
        }
    }
}

/** Extensiones sugeridas: las del país mas las universales. */
$sugeridas = ['com', 'com.gt', 'edu.gt', 'gob.gt', 'org.gt', 'net.gt', 'gt', 'net', 'org', 'edu', 'info', 'es', 'mx'];
if ($resultado && $resultado['extensiones']) {
    $sugeridas = array_slice(array_keys($resultado['extensiones']), 0, 16);
}

cr_cabecera([
    'titulo'      => 'Depurar una lista de correos',
    'descripcion' => 'Pega una lista de correos y obtén una lista limpia, sin repetidos y filtrada por las extensiones de dominio que elijas.',
    'activo'      => 'depurar',
]);
?>

<section class="contenedor seccion-depurar">

  <header class="depurar-cab">
    <span class="insignia"><span class="punto"></span> Higiene de listas</span>
    <h1>Depurar una lista de correos</h1>
    <p class="portada-sub">
      Pega lo que sea: una lista, una columna de Excel, un CSV entero o mil firmas de correo.
      Kaptor saca los correos, los pone en minúsculas, <b>quita los repetidos</b> y te deja solo
      las extensiones que pidas.
    </p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="aviso aviso-error"><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if (!$puedeUsar): ?>
    <div class="aviso aviso-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
      </svg>
      <span>Para depurar listas necesitas una cuenta. <a href="<?= e(cr_url('login.php')) ?>">Inicia sesión</a>.</span>
    </div>
  <?php endif; ?>

  <form method="post" class="tarjeta caja-depurar" id="form-depurar">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="descargar" id="descargar" value="">

    <label class="caja-etiqueta" for="lista">Pega aquí tu lista</label>
    <textarea name="lista" id="lista" class="campo area-lista" rows="9" spellcheck="false" <?= $puedeUsar ? "" : "disabled" ?>
      placeholder="juan@colegio.edu.gt&#10;info@empresa.com.gt&#10;ventas@tienda.com, contacto@otra.org&#10;…también vale pegar un CSV o una columna entera de Excel"><?= e($texto) ?></textarea>

    <div class="depurar-filtros">

      <div class="campo-grupo">
        <label for="extensiones">Extensiones que quiero <span class="suave pequeno">(vacío = todas)</span></label>
        <input type="text" name="extensiones" id="extensiones" class="campo"
               value="<?= e((string) $op['extensiones']) ?>"
               placeholder="Ej.: .com, .com.gt, .edu.gt   ·   escribe TODOS para no filtrar"
               autocomplete="off" spellcheck="false">
        <div class="chips-ext" id="chips-ext">
          <button type="button" class="chip-ext chip-todos" data-ext="">Todas</button>
          <?php foreach ($sugeridas as $ext): ?>
            <button type="button" class="chip-ext" data-ext="<?= e($ext) ?>">.<?= e($ext) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="campo-grupo">
        <label for="excluir">Extensiones que NO quiero <span class="suave pequeno">(opcional)</span></label>
        <input type="text" name="excluir" id="excluir" class="campo"
               value="<?= e((string) $op['excluir']) ?>"
               placeholder="Ej.: .ru, .cn, .xyz" autocomplete="off" spellcheck="false">
      </div>

      <div class="campo-grupo">
        <label for="solo">Tipo de buzón</label>
        <select name="solo" id="solo" class="campo">
          <option value="">Todos los buzones</option>
          <option value="generico" <?= $op['solo'] === 'generico' ? 'selected' : '' ?>>Solo genéricos (info@, ventas@…)</option>
          <option value="personal" <?= $op['solo'] === 'personal' ? 'selected' : '' ?>>Solo personales (nombre@…)</option>
        </select>
      </div>

      <div class="campo-grupo">
        <label for="orden">Ordenar por</label>
        <select name="orden" id="orden" class="campo">
          <option value="correo"   <?= $op['orden'] === 'correo'   ? 'selected' : '' ?>>Correo (A-Z)</option>
          <option value="dominio"  <?= $op['orden'] === 'dominio'  ? 'selected' : '' ?>>Dominio (A-Z)</option>
          <option value="original" <?= $op['orden'] === 'original' ? 'selected' : '' ?>>Como venían</option>
        </select>
      </div>
    </div>

    <div class="depurar-casillas">
      <label class="casilla"><input type="checkbox" name="quitar_noreply" value="1" <?= $op['quitar_noreply'] ? 'checked' : '' ?>>
        <span>Quitar <b>noreply@</b> y buzones que no admiten respuesta</span></label>
      <label class="casilla"><input type="checkbox" name="quitar_desechables" value="1" <?= $op['quitar_desechables'] ? 'checked' : '' ?>>
        <span>Quitar correos <b>temporales</b> (usar y tirar)</span></label>
      <label class="casilla"><input type="checkbox" name="quitar_suprimidos" value="1" <?= $op['quitar_suprimidos'] ? 'checked' : '' ?>>
        <span>Quitar los que están en mi <b>lista de bajas</b></span></label>
      <label class="casilla"><input type="checkbox" name="uno_por_dominio" value="1" <?= $op['uno_por_dominio'] ? 'checked' : '' ?>>
        <span>Dejar <b>un solo correo por dominio</b></span></label>
      <label class="casilla"><input type="checkbox" name="verificar_mx" value="1" <?= $op['verificar_mx'] ? 'checked' : '' ?>>
        <span>Comprobar que el dominio <b>recibe correo</b> (MX) <i class="suave pequeno">— tarda más</i></span></label>
    </div>

    <div class="depurar-acciones">
      <button type="submit" class="btn" <?= $puedeUsar ? '' : 'disabled' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 5h18l-7 8v6l-4 2v-8Z"/>
        </svg>
        Depurar lista
      </button>
      <button type="reset" class="btn btn-fantasma" id="btn-limpiar-todo">Vaciar</button>
    </div>
  </form>

  <?php if ($resultado): $r = $resultado['resumen']; ?>

    <div class="depurar-marcador">
      <div class="marca-dato"><b><?= number_format((float) $r['encontrados'], 0, ',', '.') ?></b><span>Pegados</span></div>
      <div class="marca-dato"><b><?= number_format((float) $r['unicos'], 0, ',', '.') ?></b><span>Distintos</span></div>
      <div class="marca-dato malo"><b><?= number_format((float) $r['repetidos'], 0, ',', '.') ?></b><span>Repetidos</span></div>
      <div class="marca-dato malo"><b><?= number_format((float) ($r['invalidos'] + $r['extension'] + $r['rol'] + $r['noreply'] + $r['desechables'] + $r['suprimidos'] + $r['sin_mx'] + $r['por_dominio']), 0, ',', '.') ?></b><span>Descartados</span></div>
      <div class="marca-dato bueno"><b><?= number_format((float) $r['final'], 0, ',', '.') ?></b><span>Lista final</span></div>
    </div>

    <?php if ($resultado['correos']): ?>
      <div class="exportar exportar-lista">
        <button type="button" class="btn btn-fantasma btn-peq" id="btn-copiar-lista">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="9" y="9" width="12" height="12" rx="2.2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
          </svg>
          Copiar los <?= (int) $r['final'] ?>
        </button>
        <span style="flex:1"></span>
        <button type="button" class="btn btn-peq" data-bajar="txt">TXT</button>
        <button type="button" class="btn btn-peq" data-bajar="csv">CSV</button>
        <button type="button" class="btn btn-neon btn-peq" data-bajar="xlsx">Excel</button>
      </div>

      <?php if ($resultado['extensiones']): ?>
        <p class="pequeno suave depurar-reparto">
          Reparto por extensión:
          <?php foreach (array_slice($resultado['extensiones'], 0, 12, true) as $ext => $n): ?>
            <span class="chip chip-gris">.<?= e((string) $ext) ?> · <?= (int) $n ?></span>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>

      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla tabla-compacta">
            <thead><tr><th class="col-num">#</th><th>Correo</th><th>Dominio</th><th>Ext.</th><th>Tipo</th><?= $op['verificar_mx'] ? '<th>MX</th>' : '' ?></tr></thead>
            <tbody>
            <?php foreach (array_slice($resultado['correos'], 0, 2000) as $i => $c): ?>
              <tr>
                <td class="col-num"><?= $i + 1 ?></td>
                <td><span class="celda-correo"><?= e((string) $c['correo']) ?></span></td>
                <td class="suave"><?= e((string) $c['dominio']) ?></td>
                <td><span class="chip chip-gris">.<?= e((string) $c['extension']) ?></span></td>
                <td><span class="chip <?= $c['tipo'] === 'generico' ? '' : 'chip-neon' ?>"><?= $c['tipo'] === 'generico' ? 'Genérico' : 'Personal' ?></span></td>
                <?php if ($op['verificar_mx']): ?>
                  <td><?= $c['mx'] === null ? '<span class="chip chip-gris">—</span>' : '<span class="chip chip-neon">ok</span>' ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if (count($resultado['correos']) > 2000): ?>
        <p class="pequeno suave">Se muestran los primeros 2.000. La descarga incluye los <?= (int) $r['final'] ?>.</p>
      <?php endif; ?>

      <?php $paraCopiar = array_slice(array_column($resultado['correos'], 'correo'), 0, 20000); ?>
      <textarea id="lista-limpia" class="oculto" aria-hidden="true"><?= e(implode("\n", $paraCopiar)) ?></textarea>
      <?php if (count($resultado['correos']) > 20000): ?>
        <p class="pequeno suave">El botón «Copiar» se lleva los primeros 20.000. Para la lista entera usa la descarga en TXT.</p>
      <?php endif; ?>

    <?php else: ?>
      <div class="vacio"><p><b>No quedó ningún correo.</b></p>
        <p class="pequeno">Revisa las extensiones que pediste: si escribes <b>.edu.gt</b> solo salen esos. Deja el campo vacío para no filtrar.</p></div>
    <?php endif; ?>

    <?php if ($resultado['descartados']): ?>
      <details class="depurar-descartes">
        <summary>Ver por qué se descartaron (<?= count($resultado['descartados']) ?> primeros)</summary>
        <ul class="lista-extras">
          <?php foreach ($resultado['descartados'] as $d): ?>
            <li><span class="mono"><?= e((string) $d['valor']) ?></span> <span class="chip chip-gris"><?= e((string) $d['texto']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>

  <?php endif; ?>
</section>

<?php cr_pie(); ?>
