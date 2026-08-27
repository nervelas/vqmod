<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <div class="texto-3" style="font-size:.9rem">Los avisos llegan al portal y como notificación a los residentes.</div>
  <a class="btn btn-oro" href="<?= e(url('/admin/avisos/nuevo')) ?>"><?= ico('mas', 17) ?> Publicar aviso</a>
</div>

<?php if ($avisos === []): ?>
  <div class="tarjeta">
    <?= Vista::parcial('partials/vacio', ['icono' => 'megafono', 'titulo' => 'Todavía no hay avisos',
        'texto' => 'Publique el primer comunicado para los residentes.',
        'accion' => '/admin/avisos/nuevo', 'accionTexto' => 'Publicar aviso']) ?>
  </div>
<?php else: ?>
  <div class="rejilla rejilla-2">
    <?php foreach ($avisos as $a):
      $vigente = strtotime((string) $a['publicar_en']) <= time()
        && ($a['vence_en'] === null || strtotime((string) $a['vence_en']) >= time());
      $alcances = ['todos' => 'Todo el residencial', 'fase' => 'Una fase', 'calle' => 'Una calle', 'casa' => 'Una vivienda'];
    ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0;font-size:1.02rem"><?= e($a['titulo']) ?></h3>
            <div class="texto-3" style="font-size:.79rem">
              <?= e($alcances[$a['alcance']] ?? '') ?> · <?= e(fechahora((string) $a['publicar_en'])) ?>
              <?= !empty($a['autor']) ? ' · ' . e($a['autor']) : '' ?>
            </div>
          </div>
          <div class="fila" style="gap:6px">
            <?php if ($a['prioridad'] !== 'normal'): ?>
              <span class="chip <?= $a['prioridad'] === 'urgente' ? 'grave' : 'aviso' ?>"><?= e(ucfirst((string) $a['prioridad'])) ?></span>
            <?php endif; ?>
            <span class="chip <?= $vigente ? 'ok' : 'neutro' ?>"><?= $vigente ? 'Vigente' : 'No vigente' ?></span>
          </div>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <p class="texto-2" style="font-size:.9rem"><?= e(recortar((string) $a['cuerpo'], 190)) ?></p>
          <div class="fila-entre">
            <span class="texto-3" style="font-size:.82rem">
              <?= ico('ojo', 14) ?> <?= (int) $a['lecturas'] ?> de <?= (int) $totalCasas ?> viviendas lo leyeron
            </span>
            <div class="fila" style="gap:6px">
              <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/avisos/' . (int) $a['id'] . '/editar')) ?>"
                 aria-label="Editar el aviso <?= e(recortar((string) $a['titulo'], 40)) ?>">
                <?= ico('editar', 15) ?><span class="solo-lectores">Editar</span></a>
              <?php if (esRol('admin')): ?>
                <form method="post" action="<?= e(url('/admin/avisos/' . (int) $a['id'] . '/eliminar')) ?>"
                      data-confirmar="El aviso se eliminará junto con su registro de lecturas."
                      data-confirmar-titulo="¿Eliminar el aviso?" data-confirmar-boton="Sí, eliminar">
                  <?= csrf() ?>
                  <button class="btn btn-sm btn-fantasma" type="submit" aria-label="Eliminar el aviso">
                    <?= ico('basura', 15) ?><span class="solo-lectores">Eliminar</span></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="progreso mt-1"><span style="width:<?= $totalCasas > 0 ? min(100, round((int) $a['lecturas'] * 100 / $totalCasas)) : 0 ?>%"></span></div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
