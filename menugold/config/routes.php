<?php
// Este archivo solo se carga desde el arranque de la aplicación.
if (!defined('MG_ROOT')) { http_response_code(404); exit; }
/**
 * Tabla de rutas. {param} captura un segmento, {param:\d+} lo restringe.
 * @var \MenuGold\Core\Router $router
 */

/* ---------- Sitio de venta ---------- */
$router->get('/',                 'LandingController@index');
$router->get('/demo',             'LandingController@demo');
$router->get('/precios',          'LandingController@pricing');
$router->get('/sitemap.xml',      'LandingController@sitemap');
$router->get('/robots.txt',       'LandingController@robots');

/* ---------- Menú del comensal ---------- */
$router->get('/r/{slug}',                       'MenuController@show');
$router->get('/r/{slug}/m/{token}',             'MenuController@show');
$router->get('/r/{slug}/producto/{id:\d+}',     'MenuController@product');
$router->get('/r/{slug}/buscar',                'MenuController@search');
$router->get('/r/{slug}/idioma/{lang}',         'MenuController@language');
$router->post('/r/{slug}/pedido',               'MenuController@place');
$router->post('/r/{slug}/cotizar',              'MenuController@quote');
$router->post('/r/{slug}/cupon',                'MenuController@coupon');
$router->post('/r/{slug}/llamar',               'MenuController@serviceCall');
$router->get('/r/{slug}/manifest.webmanifest',  'PwaController@manifest');

/* ---------- Seguimiento del pedido ---------- */
$router->get('/pedido/{token}',      'TrackController@show');
$router->get('/api/pedido/{token}',  'TrackController@status');

/* ---------- PWA ---------- */
$router->get('/manifest.webmanifest', 'PwaController@manifest');
$router->get('/sw.js',                'PwaController@serviceWorker');
$router->get('/sin-conexion',         'PwaController@offline');

/* ---------- Códigos QR ---------- */
$router->get('/qr/png',  'QrController@png');
$router->get('/qr/demo', 'QrController@demo');

/* ---------- Acceso al panel ---------- */
$router->any('/panel/entrar', 'Admin/AuthController@login');
$router->get('/panel/salir',  'Admin/AuthController@logout');
$router->any('/panel/pin',    'Admin/AuthController@pin');

/* ---------- Panel del restaurante ---------- */
$router->get('/panel',                       'Admin/DashboardController@index');
$router->any('/panel/inicio-guiado',         'Admin/DashboardController@onboarding');

$router->get('/panel/menu',                  'Admin/MenuController@index');
$router->any('/panel/menu/categoria/{id}',   'Admin/MenuController@category');
$router->post('/panel/menu/categorias/orden','Admin/MenuController@reorderCategories');
$router->post('/panel/menu/categoria/{id:\d+}/eliminar', 'Admin/MenuController@deleteCategory');
$router->any('/panel/menu/producto/{id}',    'Admin/MenuController@product');
$router->post('/panel/menu/productos/orden', 'Admin/MenuController@reorderProducts');
$router->post('/panel/menu/producto/{id:\d+}/agotado',   'Admin/MenuController@toggleStock');
$router->post('/panel/menu/producto/{id:\d+}/duplicar',  'Admin/MenuController@duplicate');
$router->post('/panel/menu/producto/{id:\d+}/eliminar',  'Admin/MenuController@deleteProduct');
$router->post('/panel/menu/imagen/{id:\d+}/eliminar',    'Admin/MenuController@deleteImage');
$router->get('/panel/menu/modificadores',    'Admin/MenuController@modifiers');
$router->any('/panel/menu/modificador/{id}', 'Admin/MenuController@modifier');
$router->post('/panel/menu/modificador/{id:\d+}/eliminar', 'Admin/MenuController@deleteModifier');
$router->any('/panel/menu/promociones',      'Admin/MenuController@promotions');
$router->post('/panel/menu/promocion/{id:\d+}/eliminar',   'Admin/MenuController@deletePromotion');
$router->any('/panel/menu/combos',           'Admin/MenuController@combos');
$router->post('/panel/menu/combo/{id:\d+}/eliminar',       'Admin/MenuController@deleteCombo');
$router->any('/panel/menu/importar',         'Admin/MenuController@import');
$router->get('/panel/menu/plantilla.xlsx',   'Admin/MenuController@importTemplate');
$router->get('/panel/menu/fotos',            'Admin/MenuController@photos');
$router->post('/panel/menu/fotos/lote',      'Admin/MenuController@photosBatch');

$router->get('/panel/pedidos',                    'Admin/OrdersController@index');
$router->get('/panel/pedidos/{id:\d+}',           'Admin/OrdersController@show');
$router->post('/panel/pedidos/{id:\d+}/estado',   'Admin/OrdersController@setStatus');
$router->post('/panel/pedidos/{id:\d+}/cobrar',   'Admin/OrdersController@charge');
$router->post('/panel/pedidos/{id:\d+}/descuento','Admin/OrdersController@discount');
$router->post('/panel/pedidos/{id:\d+}/anular',   'Admin/OrdersController@cancel');
$router->get('/panel/pedidos/{id:\d+}/ticket',    'Admin/OrdersController@ticket');
$router->get('/panel/cocina',                     'Admin/OrdersController@kitchen');
$router->get('/panel/cocina/datos',               'Admin/OrdersController@kitchenData');
$router->get('/panel/mesero',                     'Admin/OrdersController@waiter');
$router->get('/panel/mesero/mesa/{id:\d+}',       'Admin/OrdersController@tableDetail');
$router->post('/panel/mesero/mesa/{id:\d+}/cerrar','Admin/OrdersController@closeTable');
$router->post('/panel/llamadas/{id:\d+}/atender', 'Admin/OrdersController@resolveCall');

$router->get('/panel/mesas',                  'Admin/TablesController@index');
$router->any('/panel/mesas/mesa/{id}',        'Admin/TablesController@edit');
$router->post('/panel/mesas/{id:\d+}/eliminar','Admin/TablesController@delete');
$router->post('/panel/mesas/generar',         'Admin/TablesController@bulk');
$router->get('/panel/mesas/qr',               'Admin/TablesController@qrSheet');
$router->get('/panel/mesas/qr.pdf',           'Admin/TablesController@qrPdf');

$router->get('/panel/clientes',               'Admin/CustomersController@index');
$router->any('/panel/cupones',                'Admin/CustomersController@coupons');
$router->post('/panel/cupon/{id:\d+}/eliminar','Admin/CustomersController@deleteCoupon');

$router->get('/panel/reportes',               'Admin/ReportsController@index');
$router->get('/panel/reportes/datos',         'Admin/ReportsController@data');
$router->get('/panel/reportes/exportar.xlsx', 'Admin/ReportsController@excel');
$router->get('/panel/reportes/exportar.pdf',  'Admin/ReportsController@pdf');

$router->any('/panel/ajustes',                'Admin/SettingsController@index');
$router->any('/panel/ajustes/apariencia',     'Admin/SettingsController@appearance');
$router->any('/panel/ajustes/horarios',       'Admin/SettingsController@hours');
$router->any('/panel/ajustes/entregas',       'Admin/SettingsController@delivery');
$router->any('/panel/ajustes/pagos',          'Admin/SettingsController@payments');
$router->any('/panel/ajustes/correo',         'Admin/SettingsController@mail');
$router->any('/panel/usuarios',               'Admin/SettingsController@users');
$router->post('/panel/usuarios/{id:\d+}/eliminar', 'Admin/SettingsController@deleteUser');
$router->get('/panel/bitacora',               'Admin/SettingsController@audit');

/* ---------- Tiempo real ---------- */
$router->get('/api/stream', 'Api/StreamController@stream');
$router->get('/api/pulso',  'Api/StreamController@pulse');

/* ---------- Superadministrador ---------- */
$router->get('/super',                          'Super/SuperController@index');
$router->get('/super/restaurantes',             'Super/SuperController@restaurants');
$router->any('/super/restaurante/{id}',         'Super/SuperController@restaurant');
$router->post('/super/restaurante/{id:\d+}/estado', 'Super/SuperController@toggleStatus');
$router->get('/super/entrar/{id:\d+}',          'Super/SuperController@impersonate');
$router->get('/super/salir-de-restaurante',     'Super/SuperController@stopImpersonating');
$router->any('/super/planes',                   'Super/SuperController@plans');
$router->post('/super/plan/{id:\d+}/eliminar',  'Super/SuperController@deletePlan');
$router->any('/super/landing',                  'Super/SuperController@landing');
$router->any('/super/landing/planes',           'Super/SuperController@landingPlans');
$router->any('/super/landing/testimonios',      'Super/SuperController@landingTestimonials');
$router->get('/super/respaldo',                 'Super/SuperController@backups');
$router->post('/super/respaldo/crear',          'Super/SuperController@createBackup');
$router->get('/super/respaldo/descargar',       'Super/SuperController@downloadBackup');
$router->get('/super/bitacora',                 'Super/SuperController@audit');
