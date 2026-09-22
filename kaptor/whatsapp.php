<?php
/**
 * Kaptor - Extraer números de WhatsApp y teléfonos de un texto.
 *
 * Hermana de depurar.php (correos) y dominios.php (páginas web), pero con
 * página propia porque sus filtros no se parecen a los de las otras dos: aquí
 * lo que importa es el país y si el número tiene WhatsApp, no la extensión del
 * dominio.
 *
 * Se le pega cualquier cosa —una lista de contactos, un directorio copiado, un
 * grupo de WhatsApp exportado, una columna de Excel— y devuelve los números
 * limpios, sin repetidos, en formato internacional y con el enlace de chat ya
 * montado. La validación es la misma del extractor, así que descarta fechas,
 * NIT, precios y números de factura, que es lo que ensucia cualquier listado.
 *
 * Funciona sin JavaScript: el formulario se envía a sí mismo y las descargas
 * salen del mismo POST.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();

@ini_set('memory_limit', '512M');
@set_time_limit(120);

$texto = '';
$op = [
    'paises'        => '',
    'solo_whatsapp' => false,
    'internacional' => false,
];
$resultado = null;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Seguridad::exigirCsrf();

    $texto = (string) cr_post('texto', '');
    $op['paises']        = trim((string) cr_post('paises', ''));
    $op['solo_whatsapp'] = cr_post('solo_whatsapp', '') !== '';
    $op['internacional'] = cr_post('internacional', '') !== '';

    if (trim($texto) === '') {
        $error = 'Pega primero el texto del que quieres sacar los números.';
    } elseif (strlen($texto) > Depurador::MAX_ENTRADA) {
        $error = 'El texto es demasiado grande (el tope son 3 MB). Pártelo en dos.';
    } else {
        $resultado = Depurador::telefonos($texto, $op);
        $filas = $resultado['telefonos'];

        // La descarga sale del mismo envío: así no depende de la sesión ni de
        // volver a calcular nada.
        $formato = strtolower((string) cr_post('descargar', ''));
        if (in_array($formato, ['txt', 'csv', 'xlsx'], true)) {
            if (!$filas) {
                $error = 'No quedó ningún número que descargar con esos filtros.';
            } else {
                $etiqueta = trim('whatsapp-' . (string) preg_replace('~[^a-z0-9]+~i', '-', $op['paises']), '-');
                Exportador::descargar(
                    Exportador::listaTelefonos($filas, $formato),
                    Exportador::nombreLista($formato, $etiqueta),
                    Exportador::mime($formato)
                );
            }
        }
    }
}

$prefijoPropio = trim(Ajustes::obtener('prefijo_pais'));

cr_cabecera([
    'titulo'      => 'Extraer WhatsApp de un texto',
    'descripcion' => 'Pega cualquier texto y saca los números de WhatsApp y teléfono que lleve dentro.',
    'activo'      => 'whatsapp',
]);
?>

<section class="contenedor seccion-depurar">

  <header class="depurar-cab">
    <span class="insignia"><span class="punto"></span> Sin salir a internet</span>
    <h1>Extraer WhatsApp de un texto</h1>
    <p class="portada-sub">
      Pega <b>cualquier cosa</b> y Kaptor saca los números que lleve dentro: una
      lista de contactos, un directorio copiado, un grupo de WhatsApp exportado,
      una columna de Excel. Salen sin repetidos, en formato internacional y con
      el <b>enlace de chat ya montado</b>. Las fechas, los NIT, los precios y los
      números de factura se quedan fuera, que es lo que siempre ensucia estas listas.
    </p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="aviso aviso-error"><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <form method="post" class="tarjeta caja-depurar" id="form-depurar">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="descargar" id="descargar" value="">

    <!-- El mismo selector de las otras dos páginas, para no perderse. -->
    <div class="modo-depurar">
      <a class="modo-opcion" href="<?= e(cr_url('depurar.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 7.6 5.1a1.6 1.6 0 0 0 1.8 0l7.6-5.1"/>
        </svg>
        <span><b>Correos</b><i>info@colegio.edu.gt</i></span>
      </a>
      <a class="modo-opcion activa" href="<?= e(cr_url('whatsapp.php')) ?>" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.5 12.2c0 4-3.8 7.2-8.5 7.2-1 0-2-.15-2.9-.4L4 21l1.4-3.8C4 15.9 3.5 14.1 3.5 12.2c0-4 3.8-7.2 8.5-7.2s8.5 3.2 8.5 7.2Z"/>
        </svg>
        <span><b>WhatsApp</b><i>+502 5555 1234</i></span>
      </a>
      <a class="modo-opcion" href="<?= e(cr_url('dominios.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>
        </svg>
        <span><b>Páginas web</b><i>colegio.edu.gt</i></span>
      </a>
    </div>

    <div class="campo-grupo">
      <label for="texto">Pega aquí el texto</label>
      <textarea id="texto" name="texto" rows="11" spellcheck="false" class="campo area-lista"
        placeholder=""><?= e($texto) ?></textarea>
    </div>

    <div class="depurar-filtros">
      <div class="campo-grupo">
        <label for="paises">Solo de estos países <span class="suave pequeno">(vacío = todos)</span></label>
        <input type="text" name="paises" id="paises" class="campo"
               value="<?= e((string) $op['paises']) ?>"
               placeholder=""
               autocomplete="off" spellcheck="false">
        <p class="pequeno suave">
          Vale el prefijo (<b>502</b>) o el código del país (<b>gt</b>). Varios, separados por comas.
        </p>
      </div>
    </div>

    <div class="depurar-casillas">
      <label class="casilla">
        <input type="checkbox" name="solo_whatsapp" value="1" <?= $op['solo_whatsapp'] ? 'checked' : '' ?>>
        <span>Solo los que tienen <b>WhatsApp confirmado</b></span>
      </label>
      <label class="casilla">
        <input type="checkbox" name="internacional" value="1" <?= $op['internacional'] ? 'checked' : '' ?>>
        <span>Dar por buenos los números <b>sin prefijo de país</b></span>
      </label>
    </div>

    <div class="aviso aviso-info">
      <span>
        <b>WhatsApp confirmado</b> es el número que aparece en un enlace <code>wa.me</code>:
        de ese se sabe seguro. Un teléfono suelto puede tener WhatsApp igualmente, pero
        desde un texto no hay forma de comprobarlo.
        <?php if ($prefijoPropio !== ''): ?>
          A los números sin prefijo se les pone <b>+<?= e($prefijoPropio) ?></b>, que es el que tienes en Ajustes.
        <?php else: ?>
          No tienes prefijo de país en Ajustes, así que los números sin prefijo se descartan; ponlo y se completarán solos.
        <?php endif; ?>
      </span>
    </div>

    <div class="depurar-acciones">
      <button type="submit" class="btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.5 12.2c0 4-3.8 7.2-8.5 7.2-1 0-2-.15-2.9-.4L4 21l1.4-3.8C4 15.9 3.5 14.1 3.5 12.2c0-4 3.8-7.2 8.5-7.2s8.5 3.2 8.5 7.2Z"/>
        </svg>
        Extraer los números
      </button>
    </div>
  </form>

  <?php if ($resultado): $r = $resultado['resumen']; $filas = $resultado['telefonos']; ?>

    <div class="depurar-marcador">
      <div class="marca-dato"><b><?= number_format((float) $r['encontrados'], 0, ',', '.') ?></b><span>Encontrados</span></div>
      <div class="marca-dato"><b><?= number_format((float) $r['repetidos'], 0, ',', '.') ?></b><span>Repetidos</span></div>
      <div class="marca-dato malo"><b><?= number_format((float) $r['invalidos'], 0, ',', '.') ?></b><span>Descartados</span></div>
      <div class="marca-dato bueno"><b><?= number_format((float) $r['final'], 0, ',', '.') ?></b><span>Lista final</span></div>
    </div>

    <?php if ($resultado['paises']): ?>
      <p class="pequeno suave depurar-reparto">
        Reparto por país:
        <?php foreach (array_slice($resultado['paises'], 0, 10, true) as $pais => $n): ?>
          <span class="chip chip-gris"><?= e($pais) ?> · <?= e((string) $n) ?></span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <?php if ($filas): ?>
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

      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla tabla-compacta">
            <thead><tr>
              <th class="col-num">#</th><th>Número</th><th>País</th>
              <th>WhatsApp</th><th>Veces</th><th>Abrir</th>
            </tr></thead>
            <tbody>
            <?php foreach (array_slice($filas, 0, 2000) as $i => $t): ?>
              <tr>
                <td class="col-num"><?= $i + 1 ?></td>
                <td><span class="celda-correo"><?= e((string) $t['formato']) ?></span></td>
                <td class="suave"><?= e((string) $t['pais']) ?></td>
                <td>
                  <span class="chip <?= !empty($t['whatsapp']) ? 'chip-neon' : 'chip-gris' ?>">
                    <?= !empty($t['whatsapp']) ? 'Sí' : '—' ?>
                  </span>
                </td>
                <td class="suave"><?= (int) ($t['veces'] ?? 1) ?></td>
                <td>
                  <a href="<?= e(Telefono::enlaceWhatsapp((string) $t['numero'])) ?>"
                     target="_blank" rel="noopener nofollow" class="pequeno">chat →</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (count($filas) > 2000): ?>
        <p class="pequeno suave">Se muestran los primeros 2.000. La descarga incluye los <?= (int) $r['final'] ?>.</p>
      <?php endif; ?>

      <?php $paraCopiar = array_slice(array_column($filas, 'numero'), 0, 20000); ?>
      <textarea id="lista-limpia" class="oculto" aria-hidden="true"><?= e(implode("\n", $paraCopiar)) ?></textarea>

    <?php else: ?>
      <div class="vacio">
        <p><b>No quedó ningún número.</b></p>
        <p class="pequeno">
          Revisa los filtros: si pediste solo los de WhatsApp confirmado, se van todos
          los teléfonos sueltos. Y si los números vienen sin prefijo de país, hace falta
          marcar la casilla para darlos por buenos.
        </p>
      </div>
    <?php endif; ?>

    <?php if (!empty($resultado['descartados'])): ?>
      <details class="depurar-descartes">
        <summary>Ver qué se descartó (<?= count($resultado['descartados']) ?> primeros)</summary>
        <ul>
          <?php foreach ($resultado['descartados'] as $d): ?>
            <li><code><?= e(mb_substr((string) $d['numero'], 0, 40)) ?></code>
                <span class="suave"><?= e(cr_motivo_telefono((string) $d['motivo'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>

  <?php endif; ?>

</section>

<?php cr_pie(); ?>
