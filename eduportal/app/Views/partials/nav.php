<?php
use App\Core\Auth;

$actual = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '', '/');
$base = rtrim(base_path_url(), '/');
if ($base !== '' && str_starts_with($actual, $base)) {
    $actual = '/' . trim(substr($actual, strlen($base)), '/');
}
$activo = static function (string $ruta) use ($actual): string {
    if ($ruta === '/panel') {
        return $actual === '/panel' ? 'activo' : '';
    }
    return str_starts_with($actual, $ruta) ? 'activo' : '';
};

$menu = [];
if (Auth::is('superadmin', 'secretaria')) {
    $menu[] = ['Gestión', [
        ['/panel', 'panel', 'Panel'],
        ['/alumnos', 'alumnos', 'Alumnos'],
        ['/cobranza', 'dinero', 'Cobranza'],
        ['/preinscripciones', 'escuela', 'Pre-inscripciones'],
    ]];
}
if (Auth::is('superadmin')) {
    $menu[] = ['Académico', [
        ['/notas', 'notas', 'Notas'],
        ['/asistencia', 'asistencia', 'Asistencia'],
        ['/tareas', 'tarea', 'Tareas'],
    ]];
} elseif (Auth::is('docente')) {
    $menu[] = ['Mis grupos', [
        ['/panel', 'panel', 'Inicio'],
        ['/notas', 'notas', 'Notas'],
        ['/asistencia', 'asistencia', 'Asistencia'],
        ['/tareas', 'tarea', 'Tareas'],
        ['/alumnos', 'alumnos', 'Mis alumnos'],
    ]];
} elseif (Auth::is('secretaria')) {
    $menu[] = ['Académico', [
        ['/asistencia', 'asistencia', 'Asistencia'],
    ]];
}
$comunicacion = [
    ['/avisos', 'aviso', 'Avisos'],
    ['/calendario-escolar', 'calendario', 'Calendario'],
    ['/mensajes', 'mensaje', 'Mensajes'],
];
$menu[] = ['Comunicación', $comunicacion];
if (Auth::can('reportes.ver')) {
    $menu[] = ['Análisis', [
        ['/reportes', 'reporte', 'Reportes'],
        ['/notas/cuadro-honor', 'estrella', 'Cuadro de honor'],
    ]];
}
if (Auth::is('superadmin')) {
    $menu[] = ['Sistema', [
        ['/configuracion', 'config', 'Configuración'],
        ['/configuracion/academico', 'libro', 'Estructura académica'],
        ['/configuracion/usuarios', 'usuarios', 'Usuarios'],
        ['/configuracion/sitio', 'escuela', 'Sitio web'],
        ['/configuracion/bitacora', 'escudo', 'Bitácora'],
        ['/configuracion/respaldo', 'respaldo', 'Respaldo'],
    ]];
}
?>
<nav class="nav" aria-label="Navegación principal">
  <?php foreach ($menu as [$grupo, $items]): ?>
    <div class="nav__titulo"><?= e($grupo) ?></div>
    <?php foreach ($items as [$ruta, $ico, $texto]): ?>
      <a href="<?= e(url($ruta)) ?>" class="<?= $activo($ruta) ?>" title="<?= e($texto) ?>">
        <?= icono($ico) ?><span><?= e($texto) ?></span>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
</nav>
