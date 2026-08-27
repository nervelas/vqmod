<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Menu;

$u    = Auth::usuario();
$logo = Ajustes::get('logo', '');
$grupos = Menu::panel();
?>
<aside class="barra" id="barra-lateral">
  <div class="barra-marca">
    <?php if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
      <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="38" height="38">
    <?php else: ?>
      <span class="escudo"><?= ico('casa', 21) ?></span>
    <?php endif; ?>
    <div class="crecer">
      <b><?= e(recortar(Ajustes::get('nombre', 'ResidencialPro'), 22)) ?></b>
      <span>Administración</span>
    </div>
  </div>

  <nav class="barra-nav" aria-label="Menú principal">
    <?php foreach ($grupos as $g): ?>
      <div class="nav-grupo">
        <?php if ($g['grupo'] !== ''): ?><span><?= e($g['grupo']) ?></span><?php endif; ?>
        <?php foreach ($g['items'] as $item): ?>
          <a class="nav-enlace <?= Menu::esActivo($item) ? 'is-activo' : '' ?>" href="<?= e(url($item['url'])) ?>"
             <?= Menu::esActivo($item) ? 'aria-current="page"' : '' ?>>
            <?= ico($item['icono'], 19) ?>
            <span><?= e($item['texto']) ?></span>
            <?php if (!empty($item['pastilla'])): ?>
              <span class="nav-pastilla <?= !empty($item['rojo']) ? 'rojo' : '' ?>"><?= (int) $item['pastilla'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>

  <div class="barra-pie">
    <a class="barra-usuario" href="<?= e(url('/perfil')) ?>">
      <span class="avatar"><?= e(iniciales((string) ($u['nombre'] ?? ''))) ?></span>
      <span class="crecer">
        <b><?= e(recortar((string) ($u['nombre'] ?? ''), 20)) ?></b>
        <span><?= e(rolNombre((string) ($u['rol'] ?? ''))) ?></span>
      </span>
    </a>
  </div>
</aside>
