<?php /** Pestañas de configuración. */
$tabs = array(
    '/panel/ajustes'            => 'General',
    '/panel/ajustes/apariencia' => 'Apariencia',
    '/panel/ajustes/horario'    => 'Horarios',
    '/panel/ajustes/entrega'    => 'Entregas',
    '/panel/ajustes/pagos'      => 'Cobros',
    '/panel/usuarios'           => 'Usuarios',
);
$current = isset($nav_active) ? $nav_active : '';
?>
<nav class="tabs" aria-label="Secciones de configuración">
  <?php foreach ($tabs as $path => $label): ?>
    <a class="tab <?= $current === $path ? 'is-on' : '' ?>" href="<?= e(mg_url($path)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
