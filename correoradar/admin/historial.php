<?php
/** CorreoRadar - Historial completo de extracciones. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

// ------------------------------------------------------------- acciones
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');

    if ($accion === 'borrar') {
        $id = (int) cr_post('id', 0);
        BD::ejecutar('DELETE FROM `cr_correos` WHERE `escaneo_id` = ?', [$id]);
        BD::ejecutar('DELETE FROM `cr_cola` WHERE `escaneo_id` = ?', [$id]);
        BD::ejecutar('DELETE FROM `cr_escaneos` WHERE `id` = ?', [$id]);
        cr_flash('exito', 'Extracción eliminada.');
    } elseif ($accion === 'limpiar') {
        $dias = max(1, (int) cr_post('dias', 90));
        // El intervalo no admite parámetro preparado: el valor ya viene validado como entero.
        $n = BD::ejecutar(
            'DELETE FROM `cr_escaneos` WHERE `inicio` < (NOW() - INTERVAL ' . $dias . ' DAY)'
        )->rowCount();
        BD::ejecutar('DELETE c FROM `cr_correos` c LEFT JOIN `cr_escaneos` e ON e.`id` = c.`escaneo_id` WHERE e.`id` IS NULL');
        BD::ejecutar('DELETE q FROM `cr_cola` q LEFT JOIN `cr_escaneos` e ON e.`id` = q.`escaneo_id` WHERE e.`id` IS NULL');
        cr_flash('exito', $n . ' extracciones antiguas eliminadas.');
    }
    cr_redirigir('admin/historial.php');
}

// ------------------------------------------------------------- filtros
$buscar = trim((string) cr_get('q', ''));
$estado = (string) cr_get('estado', '');
$pagina = max(1, (int) cr_get('p', 1));
$porPagina = 25;

$where  = [];
$params = [];
if ($buscar !== '') {
    $where[] = '(e.`host` LIKE ? OR e.`url_origen` LIKE ?)';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}
if (in_array($estado, ['completado', 'error', 'ejecutando', 'cancelado'], true)) {
    $where[] = 'e.`estado` = ?';
    $params[] = $estado;
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int) BD::valor('SELECT COUNT(*) FROM `cr_escaneos` e' . $sqlWhere, $params, 0);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina  = min($pagina, $paginas);
$desplaz = ($pagina - 1) * $porPagina;

$lista = BD::todos(
    'SELECT e.*, u.`usuario` FROM `cr_escaneos` e
     LEFT JOIN `cr_usuarios` u ON u.`id` = e.`usuario_id`'
    . $sqlWhere .
    ' ORDER BY e.`inicio` DESC LIMIT ' . $porPagina . ' OFFSET ' . $desplaz,
    $params
);

admin_cabecera(['titulo' => 'Historial', 'activo' => 'historial.php']);
?>

<div class="tarjeta">
  <div class="resultados-cab">
    <div>
      <h3 style="margin:0">Extracciones registradas</h3>
      <p class="sub"><?= cr_numero($total) ?> en total</p>
    </div>

    <form method="get" class="herramientas" style="margin:0">
      <div class="buscador">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" name="q" class="campo" placeholder="Buscar por dominio…" value="<?= e($buscar) ?>">
      </div>
      <select name="estado" class="campo" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach (['completado' => 'Completados', 'error' => 'Con error', 'ejecutando' => 'En curso', 'cancelado' => 'Cancelados'] as $v => $t): ?>
          <option value="<?= e($v) ?>" <?= $estado === $v ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-peq">Filtrar</button>
    </form>
  </div>

  <?php if (!$lista): ?>
    <div class="vacio"><p>No hay extracciones que coincidan con el filtro.</p></div>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead>
          <tr><th>Sitio</th><th>Usuario / IP</th><th>Fecha</th><th>Modo</th><th>Páginas</th><th>Correos</th><th>WhatsApp</th><th>Estado</th><th>Descargar</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $esc): ?>
          <tr>
            <td>
              <a href="detalle.php?id=<?= (int) $esc['id'] ?>"><b><?= e(cr_recortar((string) $esc['host'], 28)) ?></b></a>
              <div class="celda-url"><?= e(cr_recortar((string) $esc['url_origen'], 52)) ?></div>
            </td>
            <td class="pequeno suave">
              <?= e((string) ($esc['usuario'] ?? 'Visitante')) ?><br>
              <span class="mono" style="font-size:.72rem"><?= e((string) $esc['ip']) ?></span>
            </td>
            <td class="pequeno"><?= e(cr_fecha((string) $esc['inicio'])) ?></td>
            <td><span class="chip chip-gris"><?= (int) $esc['profundo'] === 1 ? 'Profundo' : '1 página' ?></span></td>
            <td><?= cr_numero((int) $esc['paginas_ok']) ?><?php if ((int) $esc['paginas_error'] > 0): ?>
                  <span class="suave pequeno">(+<?= (int) $esc['paginas_error'] ?> err.)</span><?php endif; ?></td>
            <td><b class="oro"><?= cr_numero((int) $esc['correos']) ?></b></td>
            <td>
              <b class="neon"><?= cr_numero((int) $esc['whatsapps']) ?></b>
              <?php if ((int) $esc['telefonos_n'] > (int) $esc['whatsapps']): ?>
                <span class="suave pequeno">+<?= cr_numero((int) $esc['telefonos_n'] - (int) $esc['whatsapps']) ?> tel.</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="chip <?= $esc['estado'] === 'completado' ? 'chip-neon' : ($esc['estado'] === 'error' ? 'chip-rojo' : 'chip-gris') ?>">
                <?= e(ucfirst((string) $esc['estado'])) ?>
              </span>
            </td>
            <td>
              <?php if ((int) $esc['correos'] > 0 || (int) $esc['telefonos_n'] > 0): ?>
                <div class="acciones-fila">
                  <?php foreach (['txt' => 'TXT', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $f => $et): ?>
                    <form method="post" action="<?= e(cr_url('api/exportar.php')) ?>">
                      <?= Seguridad::campoCsrf() ?>
                      <input type="hidden" name="escaneo_id" value="<?= (int) $esc['id'] ?>">
                      <input type="hidden" name="formato" value="<?= e($f) ?>">
                          <input type="hidden" name="datos" value="<?= $f === 'xlsx' ? 'todo' : 'correos' ?>">
                      <button class="btn btn-fantasma btn-peq"><?= e($et) ?></button>
                    </form>
                  <?php endforeach; ?>
                </div>
              <?php else: ?><span class="suave">—</span><?php endif; ?>
            </td>
            <td>
              <form method="post">
                <?= Seguridad::campoCsrf() ?>
                <input type="hidden" name="accion" value="borrar">
                <input type="hidden" name="id" value="<?= (int) $esc['id'] ?>">
                <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                        data-confirmar="¿Borrar esta extracción y todos sus correos?">Borrar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($paginas > 1): ?>
      <nav class="paginacion" aria-label="Páginas">
        <?php
        $base = '?q=' . rawurlencode($buscar) . '&estado=' . rawurlencode($estado) . '&p=';
        $desde = max(1, $pagina - 3);
        $hasta = min($paginas, $pagina + 3);
        if ($desde > 1) { echo '<a href="' . e($base . '1') . '">1</a><span>…</span>'; }
        for ($i = $desde; $i <= $hasta; $i++) {
            echo $i === $pagina
                ? '<span class="actual">' . $i . '</span>'
                : '<a href="' . e($base . $i) . '">' . $i . '</a>';
        }
        if ($hasta < $paginas) { echo '<span>…</span><a href="' . e($base . $paginas) . '">' . $paginas . '</a>'; }
        ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="tarjeta" style="margin-top:20px">
  <h3>Mantenimiento</h3>
  <p class="suave pequeno">Borra las extracciones antiguas para mantener la base de datos ligera.</p>
  <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px">
    <?= Seguridad::campoCsrf() ?>
    <input type="hidden" name="accion" value="limpiar">
    <label class="etiqueta" for="dias" style="margin:0">Eliminar las anteriores a</label>
    <input type="number" id="dias" name="dias" class="campo" style="max-width:110px" min="1" max="3650" value="<?= e(Ajustes::obtener('retencion_dias', '90')) ?>">
    <span class="suave pequeno">días</span>
    <button class="btn btn-fantasma btn-peq" data-confirmar="Esta acción no se puede deshacer. ¿Continuar?">Limpiar ahora</button>
  </form>
</div>

<?php admin_pie(); ?>
