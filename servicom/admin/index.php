<?php
declare(strict_types=1);

require __DIR__ . '/includes/admin.php';
require __DIR__ . '/includes/Crud.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/fields.php';

Auth::requireLogin();

$page   = preg_replace('/[^a-z0-9_-]/', '', get('p', 'dashboard')) ?: 'dashboard';
$menu   = admin_menu();
$schema = admin_schema();

if (!array_key_exists($page, $menu)) {
    $page = 'dashboard';
}

// Paginas con logica propia
$custom = ['dashboard', 'general', 'temas', 'seo', 'media', 'mensajes'];

if (in_array($page, $custom, true)) {
    require __DIR__ . '/pages/' . $page . '.php';
    return;
}

if (isset($schema[$page])) {
    $crud = new Crud($page, $schema[$page]);
    require __DIR__ . '/pages/crud.php';
    return;
}

admin_header('Sección no encontrada', 'dashboard');
echo '<div class="notice notice--error">' . icon('cerrar', 19) . '<span>La sección solicitada no existe.</span></div>';
admin_footer();
