<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Menu;

$u      = Auth::usuario();
$logo   = Ajustes::get('logo', '');
$nombre = Ajustes::get('nombre', 'ResidencialPro');
$grupos = Menu::panel();
?>
<aside class="barra" id="barra-lateral">
  <div class="barra-marca">
    <span class="escudo">
      <?php if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
        <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="36" height="36">
      <?php else: ?><?= ico('casa', 19) ?><?php endif; ?>
    </span>
    <span class="textos crecer">
      <span class="n"><?= e(recortar($nombre, 20)) ?></span>
      <span class="sub">Administración</span>
    </span>
  </div>

  <nav class="barra-nav" aria-label="Menú principal">
    <?php foreach ($grupos as $g): ?>
      <?php if ($g['grupo'] !== ''): ?><div class="nav-grupo"><?= e($g['grupo']) ?></div><?php endif; ?>
      <?php foreach ($g['items'] as $item): ?>
        <a class="nav-enlace <?= Menu::esActivo($item) ? 'is-activo' : '' ?>" href="<?= e(url($item['url'])) ?>"
           <?= Menu::esActivo($item) ? 'aria-current="page"' : '' ?>>
          <?= ico($item['icono'], 18) ?>
          <span><?= e($item['texto']) ?></span>
          <?php if (!empty($item['pastilla'])): ?>
            <span class="chip <?= !empty($item['rojo']) ? 'grave' : 'oro' ?>"><?= (int) $item['pastilla'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="barra-pie">
    <a class="barra-usuario" href="<?= e(url('/perfil')) ?>">
      <span class="avatar"><?= e(iniciales((string) ($u['nombre'] ?? ''))) ?></span>
      <span class="textos crecer">
        <span class="n"><?= e(recortar((string) ($u['nombre'] ?? ''), 18)) ?></span>
        <span class="sub"><?= e(rolNombre((string) ($u['rol'] ?? ''))) ?></span>
      </span>
    </a>
  </div>
</aside>
