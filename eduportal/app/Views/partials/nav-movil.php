<?php
use App\Core\Auth;

$actual = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '', '/');
$base = rtrim(base_path_url(), '/');
if ($base !== '' && str_starts_with($actual, $base)) {
    $actual = '/' . trim(substr($actual, strlen($base)), '/');
}
if (Auth::is('padre')) {
    $items = [
        ['/portal', 'panel', 'Inicio'],
        ['/portal/cuenta', 'dinero', 'Cuenta'],
        ['/portal/notas', 'notas', 'Notas'],
        ['/portal/avisos', 'aviso', 'Avisos'],
        ['/mensajes', 'mensaje', 'Chat'],
    ];
} elseif (Auth::is('docente')) {
    $items = [
        ['/panel', 'panel', 'Inicio'],
        ['/notas', 'notas', 'Notas'],
        ['/asistencia', 'asistencia', 'Lista'],
        ['/tareas', 'tarea', 'Tareas'],
        ['/mensajes', 'mensaje', 'Chat'],
    ];
} else {
    $items = [
        ['/panel', 'panel', 'Panel'],
        ['/alumnos', 'alumnos', 'Alumnos'],
        ['/cobranza', 'dinero', 'Cobros'],
        ['/avisos', 'aviso', 'Avisos'],
        ['/reportes', 'reporte', 'Reportes'],
    ];
}
?>
<nav class="barra-movil" aria-label="Navegación rápida">
  <div class="barra-movil__lista">
    <?php foreach ($items as [$ruta, $ico, $texto]): ?>
      <a href="<?= e(url($ruta)) ?>" class="<?= $actual === $ruta ? 'activo' : '' ?>">
        <?= icono($ico, 21) ?><span><?= e($texto) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
