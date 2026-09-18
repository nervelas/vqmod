<?php
/**
 * Kaptor - Resumen del panel: métricas, gráficas y estado del sistema.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

// Los escaneos que quedaron a medias (el visitante cerró la pestaña) se marcan
// como cancelados pasados 30 minutos para que el historial no mienta.
BD::ejecutar(
    'UPDATE `cr_escaneos` SET `estado` = \'cancelado\', `fin` = NOW()
     WHERE `estado` = \'ejecutando\' AND `inicio` < (NOW() - INTERVAL 30 MINUTE)'
);

// ----------------------------------------------------------------- métricas
$totalEscaneos = (int) BD::valor('SELECT COUNT(*) FROM `cr_escaneos`', [], 0);
$totalCorreos  = (int) BD::valor('SELECT COUNT(*) FROM `cr_correos`', [], 0);
$totalDominios = (int) BD::valor('SELECT COUNT(DISTINCT `dominio`) FROM `cr_correos`', [], 0);
$totalTelefonos = (int) BD::valor('SELECT COUNT(*) FROM `cr_telefonos`', [], 0);
$totalWhatsapp  = (int) BD::valor('SELECT COUNT(*) FROM `cr_telefonos` WHERE `whatsapp` = 1', [], 0);
$totalUsuarios = (int) BD::valor('SELECT COUNT(*) FROM `cr_usuarios` WHERE `activo` = 1', [], 0);

$hoyEscaneos = (int) BD::valor('SELECT COUNT(*) FROM `cr_escaneos` WHERE DATE(`inicio`) = CURDATE()', [], 0);
$hoyCorreos  = (int) BD::valor(
    'SELECT COUNT(*) FROM `cr_correos` c INNER JOIN `cr_escaneos` e ON e.`id` = c.`escaneo_id` WHERE DATE(e.`inicio`) = CURDATE()',
    [], 0
);
$media = $totalEscaneos > 0 ? round($totalCorreos / $totalEscaneos, 1) : 0;

// ------------------------------------------- serie de los últimos 14 días
$dias = [];
for ($i = 13; $i >= 0; $i--) { $dias[date('Y-m-d', strtotime("-$i day"))] = ['escaneos' => 0, 'correos' => 0]; }

foreach (BD::todos(
    'SELECT DATE(`inicio`) AS d, COUNT(*) AS n FROM `cr_escaneos`
     WHERE `inicio` >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(`inicio`)'
) as $f) {
    if (isset($dias[$f['d']])) { $dias[$f['d']]['escaneos'] = (int) $f['n']; }
}
foreach (BD::todos(
    'SELECT DATE(e.`inicio`) AS d, COUNT(*) AS n FROM `cr_correos` c
     INNER JOIN `cr_escaneos` e ON e.`id` = c.`escaneo_id`
     WHERE e.`inicio` >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(e.`inicio`)'
) as $f) {
    if (isset($dias[$f['d']])) { $dias[$f['d']]['correos'] = (int) $f['n']; }
}

$etiquetas = [];
$serieEscaneos = [];
$serieCorreos  = [];
foreach ($dias as $fecha => $v) {
    $etiquetas[]     = date('d/m', strtotime($fecha));
    $serieEscaneos[] = $v['escaneos'];
    $serieCorreos[]  = $v['correos'];
}

// --------------------------------------------------- dominios y métodos top
$topDominios = BD::todos(
    'SELECT `dominio`, COUNT(*) AS n FROM `cr_correos` GROUP BY `dominio` ORDER BY n DESC LIMIT 8'
);
$topSitios = BD::todos(
    'SELECT `host`, COUNT(*) AS n, SUM(`correos`) AS c FROM `cr_escaneos` GROUP BY `host` ORDER BY n DESC LIMIT 8'
);

// Reparto de métodos de detección (un correo puede tener varios)
$metodos = [];
foreach (BD::todos('SELECT `metodo` FROM `cr_correos` LIMIT 5000') as $f) {
    foreach (array_filter(explode(',', (string) $f['metodo'])) as $m) {
        $metodos[$m] = ($metodos[$m] ?? 0) + 1;
    }
}
arsort($metodos);
$metodos = array_slice($metodos, 0, 7, true);

$tipos = BD::todos('SELECT `tipo`, COUNT(*) AS n FROM `cr_correos` GROUP BY `tipo`');

$ultimos = BD::todos(
    'SELECT e.*, u.`usuario` FROM `cr_escaneos` e LEFT JOIN `cr_usuarios` u ON u.`id` = e.`usuario_id`
     ORDER BY e.`inicio` DESC LIMIT 8'
);

admin_cabecera(['titulo' => 'Resumen', 'activo' => 'index.php']);
?>

<!-- ============================ MÉTRICAS ============================ -->
<div class="rejilla rejilla-4" style="margin-bottom:20px">
  <?php
  $tarjetas = [
      ['Extracciones', cr_numero($totalEscaneos), $hoyEscaneos > 0 ? '+' . cr_numero($hoyEscaneos) . ' hoy' : '', '<path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 4v5h5"/><circle cx="12" cy="12" r="2.4"/>'],
      ['Correos encontrados', cr_numero($totalCorreos), $hoyCorreos > 0 ? '+' . cr_numero($hoyCorreos) . ' hoy' : '', '<path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/><path d="m3.4 6.8 8 5.4a1.2 1.2 0 0 0 1.3 0l8-5.4"/>'],
      ['WhatsApp encontrados', cr_numero($totalWhatsapp), $totalTelefonos > $totalWhatsapp ? '+' . cr_numero($totalTelefonos - $totalWhatsapp) . ' teléfonos' : '', '<path d="M20.5 11.6A8.4 8.4 0 0 1 7.8 19l-4.3 1.2 1.2-4.2A8.4 8.4 0 1 1 20.5 11.6Z"/>'],
      ['Media por extracción', (string) $media, 'correos', '<path d="M4 19V5M4 19h16"/><path d="m7 15 3.5-4 3 2.5L19 8"/>'],
  ];
  foreach ($tarjetas as [$titulo, $valor, $delta, $icono]): ?>
    <div class="metrica">
      <div class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icono ?></svg></div>
      <b><?= e($valor) ?></b>
      <span><?= e($titulo) ?></span>
      <?php if ($delta !== ''): ?><span class="delta"><?= e($delta) ?></span><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- ============================ GRÁFICAS ============================ -->
<div class="rejilla rejilla-2" style="margin-bottom:20px">
  <div class="tarjeta">
    <h3>Actividad de los últimos 14 días</h3>
    <div class="gr-caja"><canvas id="g-actividad" aria-label="Gráfica de actividad"></canvas></div>
    <div class="leyenda">
      <span><i style="background:var(--oro)"></i>Extracciones</span>
      <span><i style="background:var(--neon)"></i>Correos encontrados</span>
    </div>
  </div>

  <div class="tarjeta">
    <h3>Métodos de detección más frecuentes</h3>
    <div class="gr-caja"><canvas id="g-metodos" aria-label="Gráfica de métodos"></canvas></div>
    <div class="leyenda" id="leyenda-metodos"></div>
  </div>
</div>

<div class="rejilla rejilla-2" style="margin-bottom:20px">
  <div class="tarjeta">
    <h3>Dominios con más correos</h3>
    <div class="gr-caja"><canvas id="g-dominios" aria-label="Gráfica de dominios"></canvas></div>
    <?php if (!$topDominios): ?><p class="suave pequeno">Todavía no hay datos.</p><?php endif; ?>
  </div>

  <div class="tarjeta">
    <h3>Sitios más analizados</h3>
    <div class="gr-caja"><canvas id="g-sitios" aria-label="Gráfica de sitios"></canvas></div>
    <?php if (!$topSitios): ?><p class="suave pequeno">Todavía no hay datos.</p><?php endif; ?>
  </div>
</div>

<!-- ======================= ÚLTIMAS EXTRACCIONES ======================= -->
<div class="tarjeta" style="margin-bottom:20px">
  <div class="resultados-cab">
    <h3 style="margin:0">Últimas extracciones</h3>
    <a class="btn btn-fantasma btn-peq" href="historial.php">Ver el historial completo</a>
  </div>

  <?php if (!$ultimos): ?>
    <p class="suave">Todavía no se ha hecho ninguna extracción.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead><tr><th>Sitio</th><th>Usuario</th><th>Fecha</th><th>Páginas</th><th>Correos</th><th>WhatsApp</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($ultimos as $esc): ?>
          <tr>
            <td><a href="detalle.php?id=<?= (int) $esc['id'] ?>"><?= e(cr_recortar((string) $esc['host'], 30)) ?></a></td>
            <td class="pequeno suave"><?= e((string) ($esc['usuario'] ?? 'Visitante')) ?></td>
            <td class="pequeno"><?= e(cr_fecha((string) $esc['inicio'])) ?></td>
            <td><?= cr_numero((int) $esc['paginas_ok']) ?></td>
            <td><b class="oro"><?= cr_numero((int) $esc['correos']) ?></b></td>
            <td><b class="neon"><?= cr_numero((int) $esc['whatsapps']) ?></b></td>
            <td><span class="chip <?= $esc['estado'] === 'completado' ? 'chip-neon' : 'chip-gris' ?>"><?= e(ucfirst((string) $esc['estado'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ======================= ESTADO DEL SISTEMA ======================= -->
<div class="rejilla rejilla-2">
  <div class="tarjeta">
    <h3>Estado del servidor</h3>
    <?php
    $comprobaciones = [
        ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['Base de datos', (string) BD::valor('SELECT VERSION()', [], '—'), true],
        ['Extensión cURL', extension_loaded('curl') ? 'Disponible' : 'No disponible', extension_loaded('curl')],
        ['Extensión zip (Excel)', extension_loaded('zip') ? 'Disponible' : 'No disponible', extension_loaded('zip')],
        ['Extensión mbstring', extension_loaded('mbstring') ? 'Disponible' : 'No disponible', extension_loaded('mbstring')],
        ['Consultas DNS (MX)', function_exists('checkdnsrr') ? 'Disponibles' : 'Se usará DNS sobre HTTPS', true],
        ['Navegador interno', Headless::disponible() ? 'Activo' : (Headless::binario() !== '' ? 'Disponible, sin activar' : 'No disponible en este hosting'), true],
        ['Carpeta storage/', is_writable(CR_STORAGE) ? 'Con permiso de escritura' : 'Sin permiso de escritura', is_writable(CR_STORAGE)],
        ['Instalador install.php', is_file(CR_RAIZ . '/install.php') ? 'PRESENTE: conviene borrarlo' : 'Eliminado (correcto)', !is_file(CR_RAIZ . '/install.php')],
    ];
    foreach ($comprobaciones as [$nombre, $valor, $ok]): ?>
      <div class="estado-linea">
        <span><b><?= e($nombre) ?></b><em><?= e($valor) ?></em></span>
        <span class="chip <?= $ok ? 'chip-neon' : 'chip-rojo' ?>"><?= $ok ? 'OK' : 'Revisar' ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="tarjeta">
    <h3>Reparto por tipo de correo</h3>
    <div class="gr-caja"><canvas id="g-tipos" aria-label="Gráfica de tipos"></canvas></div>
    <div class="leyenda">
      <span><i style="background:var(--oro)"></i>Genéricos (info@, ventas@…)</span>
      <span><i style="background:var(--neon)"></i>Personales</span>
    </div>
    <p class="suave pequeno" style="margin-top:14px">
      <b><?= cr_numero($totalCorreos) ?></b> correos y <b><?= cr_numero($totalTelefonos) ?></b> números en total ·
      <b><?= cr_numero($totalUsuarios) ?></b> usuario<?= $totalUsuarios === 1 ? '' : 's' ?> activo<?= $totalUsuarios === 1 ? '' : 's' ?> ·
      acceso <?= Ajustes::activo('acceso_publico', false) ? 'libre' : 'solo con cuenta' ?> ·
      registro <?= Ajustes::activo('registro_publico', false) ? 'abierto' : 'cerrado' ?>.
    </p>
  </div>
</div>

<script>
/* Las gráficas se registran cuando el documento está listo: graficas.js se
   carga al final de la página, justo después de este bloque. */
document.addEventListener('DOMContentLoaded', function () {
  CRGrafica('areas', 'g-actividad', {
    etiquetas: <?= json_encode($etiquetas) ?>,
    series: [
      { nombre: 'Extracciones', valores: <?= json_encode($serieEscaneos) ?> },
      { nombre: 'Correos',      valores: <?= json_encode($serieCorreos) ?> }
    ]
  });

  CRGrafica('donut', 'g-metodos', {
    pie: 'detecciones',
    filas: <?= json_encode(array_map(static fn($k, $v) => ['etiqueta' => $k, 'valor' => $v], array_keys($metodos), array_values($metodos))) ?>
  });

  CRGrafica('barras', 'g-dominios', {
    filas: <?= json_encode(array_map(static fn($f) => ['etiqueta' => $f['dominio'], 'valor' => (int) $f['n']], $topDominios)) ?>
  });

  CRGrafica('barras', 'g-sitios', {
    filas: <?= json_encode(array_map(static fn($f) => ['etiqueta' => $f['host'], 'valor' => (int) $f['n']], $topSitios)) ?>
  });

  CRGrafica('donut', 'g-tipos', {
    pie: 'correos',
    filas: <?= json_encode(array_map(static fn($f) => ['etiqueta' => $f['tipo'] === 'generico' ? 'Genéricos' : 'Personales', 'valor' => (int) $f['n']], $tipos)) ?>
  });

  /* Leyenda de la gráfica de métodos */
  var datos = <?= json_encode(array_keys($metodos)) ?>;
  var paleta = ['var(--oro)', 'var(--neon)', '#8FA2C4', '#C98F6A', '#9B7FD4', '#6AC9C0', '#D46A9B'];
  var caja = document.getElementById('leyenda-metodos');
  if (caja) {
    caja.innerHTML = datos.map(function (m, i) {
      return '<span><i style="background:' + paleta[i % paleta.length] + '"></i>' + m + '</span>';
    }).join('');
  }
});
</script>

<?php admin_pie([cr_url('admin/assets/graficas.js')]); ?>
