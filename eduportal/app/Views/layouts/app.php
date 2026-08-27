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
        <div class="lateral__rol"><?= e(rol_nombre(Auth::rol())) ?></div>
      </div>
      <?= App\Core\View::partial('partials/nav') ?>
      <div class="lateral__pie">
        <a href="<?= e(url('perfil')) ?>" class="nav" style="padding:0">
          <span style="display:flex;align-items:center;gap:12px;padding:10px 12px;color:rgba(255,255,255,.8);font-size:.86rem">
            <?= icono('config', 18) ?><span>Mi perfil</span>
          </span>
        </a>
      </div>
    </aside>
    <main class="principal" id="contenido">
      <div class="contenedor"><?= $contenido ?? '' ?></div>
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
