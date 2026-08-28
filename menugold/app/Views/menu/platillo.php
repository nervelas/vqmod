<?php
/** Tarjeta de platillo. @var array $p, string $simbolo, bool $soloConsulta */
use MenuGold\Models\Product;

$etiquetas = Product::etiquetasArray($p);
$precio    = Product::precioVigente($p);
$descuento = Product::tieneDescuento($p);
$agotado   = (int)$p['agotado'] === 1;
?>
<button class="platillo <?= $agotado ? 'platillo--agotado' : '' ?>" type="button"
        data-producto="<?= (int)$p['id'] ?>"
        data-nombre="<?= e(mb_strtolower(t($p, 'nombre') . ' ' . t($p, 'descripcion'))) ?>"
        <?= $agotado ? 'aria-disabled="true"' : '' ?>>
  <div class="platillo__foto">
    <?php if ($descuento && !$agotado): ?><span class="cinta cinta--promo">Promo</span><?php endif; ?>
    <?php if (!empty($p['imagen'])): ?>
      <img src="<?= e(uploaded((string)$p['imagen'])) ?>" alt="<?= e(t($p, 'nombre')) ?>" loading="lazy" decoding="async" width="400" height="300">
    <?php else: ?>
      <span class="platillo__foto--vacia" style="display:grid;height:100%"><?= icon('utensils') ?></span>
    <?php endif; ?>
    <?php if ($agotado): ?><span class="velo-agotado"><span><?= e(__('agotado')) ?></span></span><?php endif; ?>
  </div>

  <div class="platillo__cuerpo">
    <?php if ($etiquetas): ?>
      <div class="etiquetas">
        <?php foreach (array_slice($etiquetas, 0, 3) as $t): ?>
          <?php [$txt, $ic] = Product::ETIQUETAS[$t]; ?>
          <span class="etiqueta etiqueta--<?= e($t) ?>"><?= icon($ic) ?><?= e(__($t) !== $t ? __($t) : $txt) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h3 class="platillo__nombre"><?= e(t($p, 'nombre')) ?></h3>
    <?php if (!empty($p['descripcion'])): ?>
      <p class="platillo__desc"><?= e(t($p, 'descripcion')) ?></p>
    <?php endif; ?>

    <div class="platillo__pie">
      <span class="precio">
        <?php if ($descuento): ?><span class="precio__antes"><?= e(money($p['precio'], $simbolo)) ?></span><?php endif; ?>
        <?= e(money($precio, $simbolo)) ?>
      </span>
      <?php if (!$soloConsulta && !$agotado): ?>
        <span class="btn-agregar" aria-hidden="true"><?= icon('plus') ?></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($p['tiempo_prep']) || !empty($p['calorias'])): ?>
      <div class="platillo__extra">
        <?php if (!empty($p['tiempo_prep'])): ?><span><?= icon('clock') ?><?= (int)$p['tiempo_prep'] ?> <?= e(__('minutos')) ?></span><?php endif; ?>
        <?php if (!empty($p['calorias'])): ?><span><?= icon('fire') ?><?= (int)$p['calorias'] ?> kcal</span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</button>
