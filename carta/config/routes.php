<?php
/**
 * Rutas de MenúGold · edición de un solo restaurante.
 *
 * El menú del comensal vive en la raíz del dominio: no hay prefijo de
 * restaurante porque solo hay uno.
 *
 * @var MenuGold\Core\Router $router
 */

/* ---------- Menú del comensal ---------- */
$router->get('/',                     'MenuController@index');
$router->get('/mesa/{token}',         'MenuController@table');
$router->get('/producto/{id:\d+}',    'MenuController@product');
$router->get('/buscar',               'MenuController@search');
$router->get('/idioma/{lang}',        'MenuController@language');
$router->post('/cotizar',             'MenuController@quote');
$router->post('/cupon',               'MenuController@coupon');
$router->post('/pedido',              'MenuController@place');
$router->post('/llamar',              'MenuController@serviceCall');
$router->get('/pedido/{token}',       'TrackController@show');
$router->get('/pedido/{token}/estado','TrackController@status');

/* ---------- PWA y utilidades públicas ---------- */
$router->get('/manifest.webmanifest', 'PwaController@manifest');
$router->get('/sw.js',                'PwaController@serviceWorker');
$router->get('/sin-conexion',         'PwaController@offline');
$router->get('/qr.png',               'QrController@png');
$router->get('/sitemap.xml',          'SeoController@sitemap');
$router->get('/robots.txt',           'SeoController@robots');

/* ---------- Acceso al panel ---------- */
$router->any('/panel/entrar',         'Admin/AuthController@login');
$router->any('/panel/pin',            'Admin/AuthController@pin');
$router->post('/panel/salir',         'Admin/AuthController@logout');

/* ---------- Panel ---------- */
$router->get('/panel',                       'Admin/DashboardController@index');

$router->get('/panel/menu',                          'Admin/MenuController@index');
$router->any('/panel/menu/categoria/{id}',           'Admin/MenuController@category');
$router->post('/panel/menu/categorias/orden',        'Admin/MenuController@reorderCategories');
$router->post('/panel/menu/categoria/{id:\d+}/eliminar', 'Admin/MenuController@deleteCategory');
$router->any('/panel/menu/producto/{id}',            'Admin/MenuController@product');
$router->post('/panel/menu/productos/orden',         'Admin/MenuController@reorderProducts');
$router->post('/panel/menu/producto/{id:\d+}/agotado',   'Admin/MenuController@toggleStock');
$router->post('/panel/menu/producto/{id:\d+}/duplicar',  'Admin/MenuController@duplicate');
$router->post('/panel/menu/producto/{id:\d+}/eliminar',  'Admin/MenuController@deleteProduct');
$router->post('/panel/menu/imagen/{id:\d+}/eliminar',    'Admin/MenuController@deleteImage');
$router->any('/panel/menu/modificadores',            'Admin/MenuController@modifiers');
$router->any('/panel/menu/modificador/{id}',         'Admin/MenuController@modifierGroup');
$router->post('/panel/menu/modificador/{id:\d+}/eliminar', 'Admin/MenuController@deleteModifierGroup');
$router->any('/panel/menu/promociones',              'Admin/MenuController@promotions');
$router->post('/panel/menu/promocion/{id:\d+}/eliminar', 'Admin/MenuController@deletePromotion');
$router->any('/panel/menu/cupones',                  'Admin/MenuController@coupons');
$router->post('/panel/menu/cupon/{id:\d+}/eliminar', 'Admin/MenuController@deleteCoupon');
$router->any('/panel/menu/importar',                 'Admin/MenuController@import');
$router->get('/panel/menu/plantilla.xlsx',           'Admin/MenuController@importTemplate');
$router->get('/panel/menu/exportar.xlsx',            'Admin/MenuController@export');

$router->get('/panel/pedidos',                    'Admin/OrdersController@index');
$router->get('/panel/pedido/{id:\d+}',            'Admin/OrdersController@show');
$router->post('/panel/pedido/{id:\d+}/estado',    'Admin/OrdersController@setStatus');
$router->post('/panel/pedido/{id:\d+}/cobrar',    'Admin/OrdersController@charge');
$router->get('/panel/pedido/{id:\d+}/ticket',     'Admin/OrdersController@ticket');
$router->get('/panel/pedido/{id:\d+}/precuenta',  'Admin/OrdersController@preBill');
$router->get('/panel/cocina',                     'Admin/KitchenController@index');
$router->get('/panel/cocina/datos',               'Admin/KitchenController@data');
$router->get('/panel/mesero',                     'Admin/WaiterController@index');
$router->get('/panel/mesero/datos',               'Admin/WaiterController@data');
$router->any('/panel/mesero/mesa/{id:\d+}',       'Admin/WaiterController@table');
$router->post('/panel/mesero/llamada/{id:\d+}',   'Admin/WaiterController@resolveCall');
$router->get('/api/pulso',                        'Api/StreamController@pulse');
$router->get('/api/stream',                       'Api/StreamController@stream');

$router->any('/panel/mesas',                    'Admin/TablesController@index');
$router->any('/panel/mesa/{id}',                'Admin/TablesController@edit');
$router->post('/panel/mesa/{id:\d+}/eliminar',  'Admin/TablesController@delete');
$router->post('/panel/mesas/generar',           'Admin/TablesController@bulk');
$router->get('/panel/mesas/qr.pdf',             'Admin/TablesController@qrPdf');
$router->get('/panel/mesas/qr/{id:\d+}.png',    'Admin/TablesController@qrPng');

$router->get('/panel/clientes',              'Admin/CustomersController@index');
$router->get('/panel/cliente/{id:\d+}',      'Admin/CustomersController@show');

$router->get('/panel/reportes',              'Admin/ReportsController@index');
$router->get('/panel/reportes/exportar.xlsx','Admin/ReportsController@excel');
$router->get('/panel/reportes/exportar.pdf', 'Admin/ReportsController@pdf');

$router->any('/panel/ajustes',               'Admin/SettingsController@index');
$router->any('/panel/ajustes/horario',       'Admin/SettingsController@hours');
$router->any('/panel/ajustes/entrega',       'Admin/SettingsController@delivery');
$router->any('/panel/ajustes/apariencia',    'Admin/SettingsController@appearance');
$router->any('/panel/ajustes/pagos',         'Admin/SettingsController@payments');
$router->any('/panel/usuarios',              'Admin/UsersController@index');
$router->any('/panel/usuario/{id}',          'Admin/UsersController@edit');
$router->post('/panel/usuario/{id:\d+}/eliminar', 'Admin/UsersController@delete');
$router->get('/panel/respaldo',              'Admin/BackupController@index');
$router->post('/panel/respaldo/crear',       'Admin/BackupController@create');
$router->get('/panel/respaldo/descargar',    'Admin/BackupController@download');
$router->get('/panel/bitacora',              'Admin/BackupController@audit');
