<?php
/**
 * Kaptor - Extraer correos de un texto y depurar listas.
 *
 * Dos usos con el mismo motor:
 *   1. Pegar un TEXTO LARGO cualquiera (un artículo, un PDF copiado, un correo
 *      reenviado con cien firmas dentro) y sacar los correos que lleve dentro.
 *   2. Pegar una LISTA de correos y depurarla.
 *
 * En ambos casos devuelve la lista limpia:
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

// Kaptor es privado: sin sesión no se entra.
cr_exigir_sesion();

// Las listas muy grandes necesitan algo mas de aire; si el hosting no deja
// subirlo, se sigue trabajando con lo que haya (3 MB caben en 128 MB de PHP).
@ini_set('memory_limit', '512M');
@set_time_limit(120);

$puedeUsar = Auth::puedeExtraer();

// Dos usos con el mismo motor: sacar correos, o sacar páginas web.
$modo    = (string) (cr_post('modo', '') ?: ($_GET['modo'] ?? 'correos'));
$modo    = $modo === 'webs' ? 'webs' : 'correos';
$texto   = '';
$op      = [
    'contiene'           => '',
    'sin_palabra'        => '',
    'solo_raiz'          => true,
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
    $op['contiene']           = trim((string) cr_post('contiene', ''));
    $op['sin_palabra']        = trim((string) cr_post('sin_palabra', ''));
    $op['solo_raiz']          = cr_post('enviado', '') === '' || cr_post('solo_raiz', '') !== '';

    $que = $modo === 'webs' ? 'páginas web' : 'correos';

    if (trim($texto) === '') {
        $error = 'Pega primero el texto del que quieres sacar los ' . $que . '.';
    } elseif (strlen($texto) > Depurador::MAX_ENTRADA) {
        $error = 'El texto es demasiado grande (máximo 3 MB). Divídelo en dos partes.';
    } else {
        $resultado = $modo === 'webs'
            ? Depurador::webs($texto, $op)
            : Depurador::procesar($texto, $op);

        $filas = $modo === 'webs' ? $resultado['webs'] : $resultado['correos'];

        // Descarga directa desde el mismo envío.
        $formato = strtolower((string) cr_post('descargar', ''));
        if (in_array($formato, ['txt', 'csv', 'xlsx'], true)) {
            if (!$filas) {
                $error = 'No quedó nada que descargar con esos filtros.';
            } else {
                $etiqueta  = Depurador::extensiones($op['extensiones']);
                $etiqueta  = $etiqueta ? implode('-', array_slice($etiqueta, 0, 2)) : '';
                $contenido = $modo === 'webs'
                    ? Exportador::listaWebs($filas, $formato)
                    : Exportador::lista($filas, $formato);
                Exportador::descargar(
                    $contenido,
                    Exportador::nombreLista($formato, trim(($modo === 'webs' ? 'webs-' : '') . $etiqueta, '-')),
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
    'titulo'      => $modo === 'webs' ? 'Extraer páginas web de un texto' : 'Extraer correos de un texto',
    'activo'      => $modo === 'webs' ? 'dominios' : 'depurar',
    'descripcion' => 'Pega un texto largo o una lista de correos y Kaptor saca todos los correos que lleve dentro, sin repetidos y filtrados por la terminación de dominio que elijas.',
]);
?>

<section class="contenedor seccion-depurar">

  <header class="depurar-cab">
    <span class="insignia"><span class="punto"></span> Sin salir a internet</span>
    <?php if ($modo === 'webs'): ?>
      <h1>Extraer webs</h1>
      <p class="portada-sub">Pega cualquier listado y salen las páginas web que lleve dentro, sin repetidos.</p>
    <?php else: ?>
      <h1>Extraer correos</h1>
      <p class="portada-sub">Pega cualquier texto y salen los correos que lleve dentro, sin repetidos.</p>
    <?php endif; ?>
  </header>

  <?php if ($error !== ''): ?>
    <div class="aviso aviso-error"><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if (!$puedeUsar): ?>
    <div class="aviso aviso-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
      </svg>
      <span>Para extraer correos de un texto necesitas una cuenta. <a href="<?= e(cr_url('login.php')) ?>">Inicia sesión</a>.</span>
    </div>
  <?php endif; ?>

  <form method="post" class="tarjeta caja-depurar" id="form-depurar">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="descargar" id="descargar" value="">
    <input type="hidden" name="enviado" value="1">

    <input type="hidden" name="modo" value="<?= e($modo) ?>">

    <!-- ¿Qué se quiere sacar del texto? Va por la dirección, no por el envío:
         los filtros de un modo no sirven para el otro. -->
    <div class="modo-depurar">
      <a class="modo-opcion <?= $modo === 'correos' ? 'activa' : '' ?>"
         href="<?= e(cr_url('depurar.php')) ?>" <?= $modo === 'correos' ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 7.6 5.1a1.6 1.6 0 0 0 1.8 0l7.6-5.1"/>
        </svg>
        <span><b>Correos</b><i>info@colegio.edu.gt</i></span>
      </a>
      <a class="modo-opcion" href="<?= e(cr_url('whatsapp.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.5 12.2c0 4-3.8 7.2-8.5 7.2-1 0-2-.15-2.9-.4L4 21l1.4-3.8C4 15.9 3.5 14.1 3.5 12.2c0-4 3.8-7.2 8.5-7.2s8.5 3.2 8.5 7.2Z"/>
        </svg>
        <span><b>WhatsApp</b><i>+502 5555 1234</i></span>
      </a>
      <a class="modo-opcion <?= $modo === 'webs' ? 'activa' : '' ?>"
         href="<?= e(cr_url('dominios.php')) ?>" <?= $modo === 'webs' ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>
        </svg>
        <span><b>Páginas web</b><i>colegio.edu.gt</i></span>
      </a>
    </div>

    <label class="caja-etiqueta" for="lista">Pega aquí tu texto o tu lista</label>
    <?php if ($modo === 'webs'): ?>
      <textarea name="lista" id="lista" class="campo area-lista" rows="9" spellcheck="false" <?= $puedeUsar ? "" : "disabled" ?>
        placeholder=""><?= e($texto) ?></textarea>
    <?php else: ?>
      <textarea name="lista" id="lista" class="campo area-lista" rows="9" spellcheck="false" <?= $puedeUsar ? "" : "disabled" ?>
        placeholder=""><?= e($texto) ?></textarea>
    <?php endif; ?>

    <div class="depurar-filtros">

      <div class="campo-grupo">
        <label for="extensiones">Extensiones que quiero <span class="suave pequeno">(vacío = todas)</span></label>
        <input type="text" name="extensiones" id="extensiones" class="campo"
               value="<?= e((string) $op['extensiones']) ?>"
               placeholder=""
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
               placeholder="" autocomplete="off" spellcheck="false">
      </div>

      <?php if ($modo === 'webs'): ?>
        <div class="campo-grupo">
          <label for="contiene">Solo las webs que digan <span class="suave pequeno">(vacío = todas)</span></label>
          <input type="text" name="contiene" id="contiene" class="campo"
                 value="<?= e((string) $op['contiene']) ?>"
                 placeholder=""
                 autocomplete="off" spellcheck="false">
          <div class="chips-ext" id="chips-palabras">
            <button type="button" class="chip-ext" data-palabras="colegio, liceo, instituto, escuela, educativo, cole">Colegios</button>
            <button type="button" class="chip-ext" data-palabras="universidad, facultad, campus">Universidades</button>
            <button type="button" class="chip-ext" data-palabras="academia, capacitacion, tecnico">Academias</button>
            <button type="button" class="chip-ext chip-todos" data-palabras="">Todas</button>
          </div>
        </div>

        <div class="campo-grupo">
          <label for="sin_palabra">Fuera las que digan <span class="suave pequeno">(opcional)</span></label>
          <input type="text" name="sin_palabra" id="sin_palabra" class="campo"
                 value="<?= e((string) $op['sin_palabra']) ?>"
                 placeholder="" autocomplete="off" spellcheck="false">
        </div>
      <?php else: ?>
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
      <?php endif; ?>
    </div>

    <?php if ($modo === 'webs'): ?>
    <div class="depurar-casillas">
      <label class="casilla"><input type="checkbox" name="solo_raiz" value="1" <?= $op['solo_raiz'] ? 'checked' : '' ?>>
        <span>Dejar solo el <b>dominio principal</b>: <code>www.x.edu.gt</code> y <code>mail.x.edu.gt</code> pasan a ser <code>x.edu.gt</code></span></label>
    </div>
    <?php else: ?>
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
    <?php endif; ?>

    <div class="depurar-acciones">
      <button type="submit" class="btn" <?= $puedeUsar ? '' : 'disabled' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 5h18l-7 8v6l-4 2v-8Z"/>
        </svg>
        <?= $modo === 'webs' ? 'Extraer las páginas web' : 'Extraer los correos' ?>
      </button>
      <button type="reset" class="btn btn-fantasma" id="btn-limpiar-todo">Vaciar</button>
    </div>
  </form>

  <?php if ($resultado): $r = $resultado['resumen'];
        $filas = $modo === 'webs' ? $resultado['webs'] : $resultado['correos'];
        $descartados = $modo === 'webs'
            ? ($r['invalidos'] + $r['extension'] + $r['palabra'])
            : ($r['invalidos'] + $r['extension'] + $r['rol'] + $r['noreply'] + $r['desechables'] + $r['suprimidos'] + $r['sin_mx'] + $r['por_dominio']); ?>

    <div class="depurar-marcador">
      <div class="marca-dato"><b><?= number_format((float) $r['encontrados'], 0, ',', '.') ?></b><span>Pegados</span></div>
      <div class="marca-dato"><b><?= number_format((float) $r['unicos'], 0, ',', '.') ?></b><span>Distintos</span></div>
      <div class="marca-dato malo"><b><?= number_format((float) $r['repetidos'], 0, ',', '.') ?></b><span>Repetidos</span></div>
      <div class="marca-dato malo"><b><?= number_format((float) $descartados, 0, ',', '.') ?></b><span>Descartados</span></div>
      <div class="marca-dato bueno"><b><?= number_format((float) $r['final'], 0, ',', '.') ?></b><span>Lista final</span></div>
    </div>

    <?php if ($filas): ?>
      <div class="exportar exportar-lista">
        <button type="button" class="btn btn-fantasma btn-peq" id="btn-copiar-lista">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="9" y="9" width="12" height="12" rx="2.2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
          </svg>
          Copiar los <?= (int) $r['final'] ?>
        </button>

        <?php if ($modo === 'webs'):
          // De la lista limpia al auditor sin pasos intermedios: es el camino
          // natural (sacar los dominios, revisarlos, y escribirle a cada uno
          // con su diagnóstico en la mano). Se mandan los primeros que quepan
          // en la dirección; para más, está el copiar y pegar.
          $tope    = Ajustes::entero('auditor_max_lote', 50, 1, 300);
          $aAudita = array_slice(array_column($filas, 'web'), 0, $tope); ?>
          <a class="btn btn-fantasma btn-peq"
             href="<?= e(cr_url('auditor.php?sitios=' . rawurlencode(implode("\n", $aAudita)))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
              <circle cx="12" cy="12" r="5.4"/><path d="M12 3.2v2.2M12 18.6v2.2M3.2 12h2.2M18.6 12h2.2"/>
            </svg>
            Auditar <?= count($aAudita) < (int) $r['final'] ? 'las primeras ' . count($aAudita) : 'estas ' . count($aAudita) ?>
          </a>
        <?php endif; ?>

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

      <?php if ($modo === 'webs'): ?>
      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla tabla-compacta">
            <thead><tr><th class="col-num">#</th><th>Página web</th><th>Ext.</th><th>Abrir</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($filas, 0, 2000) as $i => $w): ?>
              <tr>
                <td class="col-num"><?= $i + 1 ?></td>
                <td><span class="celda-correo"><?= e((string) $w['web']) ?></span></td>
                <td><span class="chip chip-gris">.<?= e((string) $w['extension']) ?></span></td>
                <td><a href="https://<?= e((string) $w['web']) ?>" target="_blank" rel="noopener nofollow" class="pequeno">ver →</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla tabla-compacta">
            <thead><tr><th class="col-num">#</th><th>Correo</th><th>Dominio</th><th>Ext.</th><th>Tipo</th><?= $op['verificar_mx'] ? '<th>MX</th>' : '' ?></tr></thead>
            <tbody>
            <?php foreach (array_slice($filas, 0, 2000) as $i => $c): ?>
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
      <?php endif; ?>
      <?php if (count($filas) > 2000): ?>
        <p class="pequeno suave">Se muestran los primeros 2.000. La descarga incluye los <?= (int) $r['final'] ?>.</p>
      <?php endif; ?>

      <?php $paraCopiar = array_slice(array_column($filas, $modo === 'webs' ? 'web' : 'correo'), 0, 20000); ?>
      <textarea id="lista-limpia" class="oculto" aria-hidden="true"><?= e(implode("\n", $paraCopiar)) ?></textarea>
      <?php if (count($filas) > 20000): ?>
        <p class="pequeno suave">El botón «Copiar» se lleva los primeros 20.000. Para la lista entera usa la descarga en TXT.</p>
      <?php endif; ?>

    <?php else: ?>
      <div class="vacio"><p><b>No quedó <?= $modo === 'webs' ? 'ninguna página web' : 'ningún correo' ?>.</b></p>
        <p class="pequeno">Revisa lo que pediste: si escribes <b>.edu.gt</b> solo salen esos<?= $modo === 'webs' ? ', y si pides que digan «colegio» se van los demás' : '' ?>. Deja los campos vacíos para no filtrar.</p></div>
    <?php endif; ?>

    <?php if ($modo !== 'webs' && !empty($resultado['descartados'])): ?>
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
