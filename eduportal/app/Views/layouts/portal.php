<?php
use App\Controllers\Configuracion;
use App\Core\Auth;
use App\Core\Settings;

$u = Auth::user();
$temaClave = (string)($u['tema'] ?? Settings::get('tema', 'default'));
if (!isset(Configuracion::TEMAS[$temaClave])) {
    $temaClave = 'default';
}
$oscuro = (int)($u['modo_oscuro'] ?? 0) === 1 ? '1' : '0';
$actual = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '', '/');
$base = rtrim(base_path_url(), '/');
if ($base !== '' && str_starts_with($actual, $base)) {
    $actual = '/' . trim(substr($actual, strlen($base)), '/');
}
$menu = [
    ['/portal', 'panel', 'Inicio'],
    ['/portal/cuenta', 'dinero', 'Estado de cuenta'],
    ['/portal/notas', 'notas', 'Calificaciones'],
    ['/portal/asistencia', 'asistencia', 'Asistencia'],
    ['/portal/tareas', 'tarea', 'Tareas'],
    ['/portal/avisos', 'aviso', 'Avisos'],
    ['/mensajes', 'mensaje', 'Mensajes'],
    ['/portal/alumno', 'alumnos', 'Ficha del alumno'],
];
?>
<!doctype html>
<html lang="es-GT" data-tema="<?= e($temaClave) ?>" data-oscuro="<?= e($oscuro) ?>"
      data-base="<?= e(base_path_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<head><?= App\Core\View::partial('partials/head', ['titulo' => $titulo ?? '']) ?></head>
<body>
<a class="saltar" href="#contenido">Ir al contenido principal</a>
<div class="app">
  <?= App\Core\View::partial('partials/barra') ?>
  <div class="cuerpo">
    <aside class="lateral">
      <div class="lateral__perfil">
        <div class="lateral__nombre"><?= e(Auth::nombre()) ?></div>
        <div class="lateral__rol">Portal de padres</div>
      </div>
      <nav class="nav" aria-label="Navegación del portal">
        <?php foreach ($menu as [$ruta, $ico, $texto]): ?>
          <a href="<?= e(url($ruta)) ?>" class="<?= $actual === $ruta ? 'activo' : '' ?>">
            <?= icono($ico) ?><span><?= e($texto) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>
    <main class="principal" id="contenido">
      <div class="contenedor">
        <?php if (!empty($hijos) && count($hijos) > 1): ?>
          <div class="selector-hijo mb-4">
            <?php foreach ($hijos as $h): ?>
              <form method="post" action="<?= e(url('portal/cambiar/' . (int)$h['id'])) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="<?= (int)$h['id'] === (int)($alumno['id'] ?? 0) ? 'activo' : '' ?>">
                  <?php if (!empty($h['foto'])): ?>
                    <img class="avatar" src="<?= e(archivo_url($h['foto'])) ?>" alt="">
                  <?php endif; ?>
                  <?= e(trim($h['nombres'] . ' ' . $h['apellidos'])) ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?= $contenido ?? '' ?>
      </div>
    </main>
  </div>
  <?= App\Core\View::partial('partials/nav-movil') ?>
</div>
<?= App\Core\View::partial('partials/flash') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
