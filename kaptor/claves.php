<?php
/**
 * Kaptor - Las palabras clave que un sitio tiene configuradas.
 *
 * El informe del modo «claves». Lo que lo hace exacto es que no interpreta
 * nada: enseña, página por página, LO QUE ESTÁ ESCRITO en el código, con el
 * nombre de la etiqueta de la que salió cada cosa. Si una página declara
 * «colegio bilingüe» en su meta keywords, aquí sale «colegio bilingüe» y al
 * lado pone «meta keywords». Ni más ni menos.
 *
 * Y dice en voz alta lo que NO se puede saber, que es la otra mitad de ser
 * exacto: la palabra clave objetivo de Yoast o de Rank Math vive en la base de
 * datos del cliente y no sale en el código, así que nadie que mire la web
 * desde fuera puede leerla. Quien diga lo contrario se la está inventando.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

cr_exigir_sesion();

$id   = (int) cr_get('id', 0);
$fila = $id > 0 ? Auditor::porId($id) : null;

if (!$fila || !cr_puede_ver_escaneo(['usuario_id' => $fila['usuario_id']])) {
    cr_cabecera(['titulo' => 'Palabras clave', 'activo' => 'palabras']);
    echo '<section class="contenedor seccion-posiciones"><div class="aviso aviso-mal">'
       . '<span>Ese análisis no existe o no es tuyo.</span></div>'
       . '<p><a class="btn btn-fantasma" href="' . e(cr_url('palabras.php')) . '">Volver</a></p></section>';
    cr_pie(false);
    exit;
}

if ($fila['estado'] !== 'listo') {
    cr_cabecera(['titulo' => 'Palabras clave', 'activo' => 'palabras']);
    echo '<section class="contenedor seccion-posiciones"><div class="aviso aviso-info">'
       . '<span>El análisis todavía no ha terminado.</span></div>'
       . '<p><a class="btn btn-fantasma" href="' . e(cr_url('palabras.php')) . '">Volver</a></p></section>';
    cr_pie(false);
    exit;
}

$datos   = Informe::datosDe($fila);
$paginas = array_values(array_filter($datos['paginas'] ?? [], static fn($p) => empty($p['error'])));

// Si no hubo rastreo (un sitio de una sola página), la portada sigue valiendo.
if (!$paginas && !empty($datos['claves_portada'])) {
    $paginas = [[
        'url'         => (string) ($datos['url'] ?? ''),
        'titulo'      => (string) ($datos['titulo'] ?? ''),
        'descripcion' => '',
        'h1_texto'    => '',
        'palabras'    => 0,
        'claves'      => $datos['claves_portada']['claves'] ?? [],
        'claves_meta' => $datos['claves_portada']['claves_meta'] ?? [],
    ]];
}

$resumen = is_array($datos['claves'] ?? null) ? $datos['claves'] : null;

// ---------------------------------------------------------------------------
//  El inventario exacto: cada palabra declarada, con quién la declara y dónde.
// ---------------------------------------------------------------------------
$inventario = [];   // palabra => ['origenes' => [], 'paginas' => [urls]]
$sinDeclarar = [];  // páginas que no declaran ninguna

foreach ($paginas as $p) {
    $meta  = is_array($p['claves_meta'] ?? null) ? $p['claves_meta'] : [];
    $lista = is_array($meta['lista'] ?? null) ? $meta['lista'] : [];

    if (!$lista) { $sinDeclarar[] = (string) $p['url']; continue; }

    foreach ($lista as $k) {
        $clave = Claves::normalizar((string) $k);
        if ($clave === '') { continue; }
        if (!isset($inventario[$clave])) {
            $inventario[$clave] = ['texto' => (string) $k, 'origenes' => [], 'paginas' => [], 'usada' => 0];
        }
        $inventario[$clave]['paginas'][] = (string) $p['url'];
        // El origen va atado a LA PALABRA, no a la página: así la tabla dice
        // de qué etiqueta salió cada una y no hay que creérselo.
        foreach (($meta['fuentes'][$k] ?? $meta['origen'] ?? []) as $o) {
            if (!in_array($o, $inventario[$clave]['origenes'], true)) {
                $inventario[$clave]['origenes'][] = (string) $o;
            }
        }
        if (in_array($k, $meta['usadas'] ?? [], true)) { $inventario[$clave]['usada']++; }
    }
}
uasort($inventario, static fn($a, $b) => count($b['paginas']) <=> count($a['paginas']));

$conDeclaradas = count($paginas) - count($sinDeclarar);

// ---------------------------------------------------------------------------
//  Descarga en CSV, para pegarlo en una hoja de cálculo.
// ---------------------------------------------------------------------------
if (cr_get('csv', '') !== '') {
    $nombre = 'palabras-clave-' . cr_slug((string) $fila['host']) . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");   // para que Excel respete los acentos
    // Una fila por palabra clave y página: así se ordena y se filtra en la
    // hoja de cálculo, que es para lo que se descarga.
    fputcsv($f, ['Dirección', 'Título', 'H1', 'Descripción', 'Palabra clave declarada',
                 'De dónde sale', '¿La menciona el texto?', 'Temas del texto', 'Palabras']);
    foreach ($paginas as $p) {
        $meta  = is_array($p['claves_meta'] ?? null) ? $p['claves_meta'] : [];
        $temas = implode(' · ', array_slice(array_column($p['claves'] ?? [], 't'), 0, 8));
        $base  = [
            (string) $p['url'],
            (string) ($p['titulo'] ?? ''),
            (string) ($p['h1_texto'] ?? ''),
            (string) ($p['descripcion'] ?? ''),
        ];
        $lista = $meta['lista'] ?? [];
        if (!$lista) {
            fputcsv($f, array_merge($base, ['', '', '', $temas, (string) ($p['palabras'] ?? 0)]));
            continue;
        }
        foreach ($lista as $k) {
            fputcsv($f, array_merge($base, [
                (string) $k,
                implode(' · ', $meta['fuentes'][$k] ?? []),
                in_array($k, $meta['usadas'] ?? [], true) ? 'sí' : 'no',
                $temas,
                (string) ($p['palabras'] ?? 0),
            ]));
        }
    }
    fclose($f);
    exit;
}

cr_cabecera([
    'titulo'      => 'Palabras clave de ' . $fila['host'],
    'activo'      => 'palabras',
    'descripcion' => 'Las palabras clave que el sitio tiene configuradas, página por página.',
    'css'         => ['auditor.css'],
]);
?>

<section class="contenedor seccion-posiciones">

  <header class="pos-cab">
    <span class="insignia"><span class="punto"></span> Leído del código, sin interpretar</span>
    <h1><?= cr_titulo_brillo('Palabras clave del sitio', 2) ?></h1>
    <p class="pos-sub"><b><?= e((string) $fila['host']) ?></b> ·
      <?= e(cr_numero(count($paginas))) ?> páginas leídas ·
      <?= e(cr_fecha((string) $fila['actualizado'])) ?></p>
  </header>

  <!-- ===================== Resumen ===================== -->
  <section class="seg-resumen">
    <div class="seg-dato"><b><?= e(cr_numero(count($paginas))) ?></b><span>páginas leídas</span></div>
    <div class="seg-dato"><b><?= e(cr_numero(count($inventario))) ?></b><span>palabras clave distintas</span></div>
    <div class="seg-dato <?= $conDeclaradas > 0 ? 'verde' : '' ?>"><b><?= e(cr_numero($conDeclaradas)) ?></b><span>páginas que declaran</span></div>
    <div class="seg-dato"><b><?= e(cr_numero(count($sinDeclarar))) ?></b><span>páginas sin declarar</span></div>
  </section>

  <p class="cl-acciones">
    <a class="btn btn-fantasma btn-peq" href="<?= e(cr_url('claves.php?id=' . $id . '&csv=1')) ?>">Descargar en CSV</a>
    <a class="btn btn-fantasma btn-peq" href="<?= e(cr_url('palabras.php')) ?>">Analizar otro sitio</a>
    <a class="btn btn-fantasma btn-peq" href="<?= e(cr_url('seguimiento.php')) ?>">Ponerlas en seguimiento</a>
  </p>

  <!-- ===================== Inventario exacto ===================== -->
  <section class="tarjeta cl-caja">
    <h2>Palabras clave configuradas</h2>
    <p class="inf-sub">Esto es literal: lo que está escrito en el código del sitio, con la etiqueta
      de la que salió cada una. Nada aquí es deducido.</p>

    <?php if ($inventario): ?>
      <div class="tabla-scroll">
        <table class="tabla cl-tabla">
          <thead>
            <tr><th>Palabra clave</th><th>Páginas</th><th class="col-conf">De dónde sale</th><th>¿La menciona el texto?</th></tr>
          </thead>
          <tbody>
            <?php foreach ($inventario as $k => $v): ?>
              <tr>
                <td><b><?= e($v['texto']) ?></b></td>
                <td><?= e(cr_numero(count($v['paginas']))) ?></td>
                <td class="col-conf suave"><?= e(implode(' · ', $v['origenes'])) ?></td>
                <td>
                  <?php if ($v['usada'] === count($v['paginas'])): ?>
                    <span class="cl-si">en todas</span>
                  <?php elseif ($v['usada'] > 0): ?>
                    <span class="cl-medio">en <?= e((string) $v['usada']) ?> de <?= e((string) count($v['paginas'])) ?></span>
                  <?php else: ?>
                    <span class="cl-no">en ninguna</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      $huecas = array_filter($inventario, static fn($v) => $v['usada'] === 0);
      if ($huecas): ?>
        <p class="claves-nota" style="margin-top:14px">
          <b>Declaradas pero sin respaldo:</b>
          <?= e(implode(', ', array_slice(array_column($huecas, 'texto'), 0, 10))) ?>.
          Están puestas en el código pero el texto de esas páginas no las menciona, así que no
          posicionan por sí solas.
        </p>
      <?php endif; ?>
    <?php else: ?>
      <p class="claves-vacio">Ninguna página de este sitio declara palabras clave en su código.</p>
      <p class="claves-nota">No es un fallo: Google dejó de leer la etiqueta <span class="mono">keywords</span>
        hace años y la mayoría de sitios serios ya no la ponen. Lo que sí decide el tema de cada
        página es su título, su H1 y su texto, y eso está en la tabla de abajo.</p>
    <?php endif; ?>
  </section>

  <!-- ===================== Lo que no se puede saber ===================== -->
  <div class="aviso aviso-info cl-limite">
    <span><b>Lo que no se puede leer desde fuera, y por qué:</b> la «palabra clave objetivo» que se
      escribe en Yoast o en Rank Math se guarda en la base de datos de ese WordPress y <b>no sale en
      el código de la página</b>. Nadie que mire el sitio desde fuera puede leerla —ni Kaptor ni
      ninguna otra herramienta—, así que aquí no se inventa. Lo que sí está escrito en el código es
      todo lo de arriba, y lo que decide de verdad el posicionamiento es lo de abajo.</span>
  </div>

  <!-- ===================== Página por página ===================== -->
  <section class="tarjeta cl-caja">
    <h2>Página por página</h2>
    <p class="inf-sub">Lo que cada página tiene puesto y de qué habla su texto. Esta tabla es el
      inventario completo del sitio.</p>

    <div class="tabla-scroll">
      <table class="tabla cl-paginas">
        <thead>
          <tr>
            <th>Página</th>
            <th>Palabras clave declaradas</th>
            <th>De qué habla su texto</th>
            <th class="col-conf">Palabras</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paginas as $p):
            $meta  = is_array($p['claves_meta'] ?? null) ? $p['claves_meta'] : [];
            $lista = $meta['lista'] ?? [];
            $temas = array_slice(array_column($p['claves'] ?? [], 't'), 0, 6); ?>
            <tr>
              <td class="cl-pagina">
                <a href="<?= e((string) $p['url']) ?>" target="_blank" rel="noopener nofollow" class="cl-url"><?= e(cr_recortar((string) $p['url'], 70)) ?></a>
                <span class="cl-titulo"><?= e(cr_recortar((string) ($p['titulo'] ?? ''), 90)) ?></span>
                <?php if (trim((string) ($p['h1_texto'] ?? '')) !== ''): ?>
                  <span class="cl-h1">H1: <?= e(cr_recortar((string) $p['h1_texto'], 70)) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($lista): ?>
                  <ul class="claves-nube">
                    <?php foreach ($lista as $k):
                      $usada = in_array($k, $meta['usadas'] ?? [], true);
                      $de    = implode(' · ', $meta['fuentes'][$k] ?? []); ?>
                      <li<?= $usada ? '' : ' class="sin-uso"' ?> title="<?= e($de !== '' ? 'Leída de: ' . $de : '') ?><?= $usada ? '' : ' — el texto de esta página no la menciona' ?>"><?= e((string) $k) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <span class="cl-origen"><?= e(implode(' · ', $meta['origen'] ?? [])) ?></span>
                <?php else: ?>
                  <span class="suave pequeno">no declara ninguna</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($temas): ?>
                  <span class="cl-temas"><?= e(implode(' · ', $temas)) ?></span>
                <?php else: ?>
                  <span class="suave pequeno">texto insuficiente</span>
                <?php endif; ?>
              </td>
              <td class="col-conf suave"><?= e(cr_numero((int) ($p['palabras'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ===================== Temas del sitio entero ===================== -->
  <?php if ($resumen && $resumen['reales']):
    $tope = max(1, (int) $resumen['reales'][0]['p']);
    $reales = array_slice(array_filter($resumen['reales'], static fn($r) => (int) $r['p'] * 100 >= $tope * 10), 0, 20); ?>
    <section class="tarjeta cl-caja">
      <h2>Para qué está posicionado el sitio entero</h2>
      <p class="inf-sub">Contando todos los términos de todas las páginas con el peso que les da un
        buscador: el título pesa ocho veces más que un párrafo, el H1 seis, los encabezados cuatro.</p>
      <ol class="claves-barras">
        <?php foreach ($reales as $r): $ancho = max(6, (int) round($r['p'] * 100 / $tope)); ?>
          <li>
            <span class="cb-texto"><?= e((string) $r['t']) ?></span>
            <span class="cb-barra"><i style="width:<?= e((string) $ancho) ?>%"></i></span>
            <span class="cb-dato"><?= e((string) $ancho) ?>&nbsp;%<?php
              if ((int) $r['paginas'] > 1): ?> · <?= e((string) $r['paginas']) ?> pág.<?php endif; ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

</section>

<?php cr_pie(false); ?>
