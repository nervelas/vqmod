<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

use App\Controllers\AuthController;
use App\Controllers\LandingController;
use App\Controllers\MediaController;
use App\Controllers\Panel\BoardController;
use App\Controllers\Panel\CatalogAdminController;
use App\Controllers\Panel\CustomerController;
use App\Controllers\Panel\DashboardController;
use App\Controllers\Panel\ImportController;
use App\Controllers\Panel\QuoteController;
use App\Controllers\Panel\ReportController;
use App\Controllers\Panel\SettingsController;
use App\Controllers\Panel\UserController;
use App\Controllers\SiteController;
use App\Controllers\Super\CompanyAdminController;
use App\Controllers\Super\PlatformController;
use App\Controllers\TrackController;

// --------------------------------------------------------------- plataforma
$router->get('/', [LandingController::class, 'home']);
$router->get('/planes', [LandingController::class, 'plans']);
$router->get('/demo', [LandingController::class, 'demo']);
$router->post('/contacto', [LandingController::class, 'contact']);

// ------------------------------------------------------------------ sesión
$router->any('/entrar', [AuthController::class, 'login']);
$router->any('/verificar', [AuthController::class, 'twoFactor']);
$router->get('/salir', [AuthController::class, 'logout']);
$router->any('/recuperar', [AuthController::class, 'forgot']);
$router->any('/restablecer/{token:[a-f0-9]+}', [AuthController::class, 'reset']);

// ------------------------------------------------------- sitio de la empresa
$router->get('/e/{slug:[a-z0-9\-]+}', [SiteController::class, 'home']);
$router->get('/e/{slug:[a-z0-9\-]+}/catalogo', [SiteController::class, 'catalog']);
$router->get('/e/{slug:[a-z0-9\-]+}/categoria/{cat:[a-z0-9\-]+}', [SiteController::class, 'catalog']);
$router->get('/e/{slug:[a-z0-9\-]+}/producto/{prod:[a-z0-9\-]+}', [SiteController::class, 'product']);
$router->get('/e/{slug:[a-z0-9\-]+}/nosotros', [SiteController::class, 'about']);
$router->get('/e/{slug:[a-z0-9\-]+}/contacto', [SiteController::class, 'contact']);
$router->any('/e/{slug:[a-z0-9\-]+}/cotizacion', [SiteController::class, 'cart']);
$router->post('/e/{slug:[a-z0-9\-]+}/enviar', [SiteController::class, 'submit']);
$router->get('/e/{slug:[a-z0-9\-]+}/recibida/{token:[a-f0-9]+}', [SiteController::class, 'received']);
$router->get('/e/{slug:[a-z0-9\-]+}/sitemap.xml', [SiteController::class, 'sitemap']);
$router->post('/e/{slug:[a-z0-9\-]+}/carrito', [SiteController::class, 'cartApi']);
$router->get('/e/{slug:[a-z0-9\-]+}/sugerencias', [SiteController::class, 'suggest']);

// ------------------------------------------------ seguimiento público /c/{token}
$router->get('/c/{token:[a-f0-9]+}', [TrackController::class, 'show']);
$router->get('/c/{token:[a-f0-9]+}/pdf', [TrackController::class, 'pdf']);
$router->post('/c/{token:[a-f0-9]+}/aprobar', [TrackController::class, 'approve']);
$router->post('/c/{token:[a-f0-9]+}/cambios', [TrackController::class, 'changes']);

// ------------------------------------------------------------------- panel
$router->get('/panel', [DashboardController::class, 'index']);
$router->get('/panel/notificaciones/leer', [DashboardController::class, 'readNotifications']);

$router->get('/panel/tablero', [BoardController::class, 'index']);
$router->post('/panel/tablero/mover', [BoardController::class, 'move']);

$router->get('/panel/cotizaciones', [QuoteController::class, 'index']);
$router->any('/panel/cotizaciones/nueva', [QuoteController::class, 'create']);
$router->get('/panel/cotizaciones/{id:\d+}', [QuoteController::class, 'edit']);
$router->post('/panel/cotizaciones/{id:\d+}/guardar', [QuoteController::class, 'save']);
$router->post('/panel/cotizaciones/{id:\d+}/item', [QuoteController::class, 'item']);
$router->post('/panel/cotizaciones/{id:\d+}/estado', [QuoteController::class, 'status']);
$router->post('/panel/cotizaciones/{id:\d+}/nota', [QuoteController::class, 'note']);
$router->post('/panel/cotizaciones/{id:\d+}/enviar', [QuoteController::class, 'send']);
$router->post('/panel/cotizaciones/{id:\d+}/duplicar', [QuoteController::class, 'duplicate']);
$router->post('/panel/cotizaciones/{id:\d+}/version', [QuoteController::class, 'version']);
$router->post('/panel/cotizaciones/{id:\d+}/asignar', [QuoteController::class, 'assign']);
$router->post('/panel/cotizaciones/{id:\d+}/eliminar', [QuoteController::class, 'destroy']);
$router->get('/panel/cotizaciones/{id:\d+}/pdf', [QuoteController::class, 'pdf']);
$router->get('/panel/cotizaciones/{id:\d+}/orden', [QuoteController::class, 'orderPdf']);
$router->get('/panel/productos/buscar', [QuoteController::class, 'productSearch']);

$router->get('/panel/productos', [CatalogAdminController::class, 'products']);
$router->any('/panel/productos/nuevo', [CatalogAdminController::class, 'productForm']);
$router->any('/panel/productos/{id:\d+}', [CatalogAdminController::class, 'productForm']);
$router->post('/panel/productos/{id:\d+}/eliminar', [CatalogAdminController::class, 'productDelete']);
$router->post('/panel/productos/{id:\d+}/duplicar', [CatalogAdminController::class, 'productDuplicate']);
$router->post('/panel/productos/imagen/{id:\d+}/eliminar', [CatalogAdminController::class, 'imageDelete']);
$router->post('/panel/productos/documento/{id:\d+}/eliminar', [CatalogAdminController::class, 'documentDelete']);
$router->any('/panel/categorias', [CatalogAdminController::class, 'categories']);
$router->post('/panel/categorias/orden', [CatalogAdminController::class, 'categoryOrder']);
$router->post('/panel/categorias/{id:\d+}/eliminar', [CatalogAdminController::class, 'categoryDelete']);
$router->any('/panel/marcas', [CatalogAdminController::class, 'brands']);
$router->post('/panel/marcas/{id:\d+}/eliminar', [CatalogAdminController::class, 'brandDelete']);
$router->any('/panel/atributos', [CatalogAdminController::class, 'attributes']);
$router->post('/panel/atributos/{id:\d+}/eliminar', [CatalogAdminController::class, 'attributeDelete']);
$router->any('/panel/listas-precios', [CatalogAdminController::class, 'priceLists']);
$router->post('/panel/listas-precios/{id:\d+}/eliminar', [CatalogAdminController::class, 'priceListDelete']);

$router->get('/panel/clientes', [CustomerController::class, 'index']);
$router->any('/panel/clientes/nuevo', [CustomerController::class, 'form']);
$router->any('/panel/clientes/{id:\d+}', [CustomerController::class, 'form']);
$router->post('/panel/clientes/{id:\d+}/eliminar', [CustomerController::class, 'destroy']);
$router->post('/panel/clientes/{id:\d+}/contacto', [CustomerController::class, 'contact']);
$router->post('/panel/clientes/contacto/{id:\d+}/eliminar', [CustomerController::class, 'contactDelete']);
$router->get('/panel/clientes/exportar', [CustomerController::class, 'export']);

$router->any('/panel/usuarios', [UserController::class, 'index']);
$router->any('/panel/usuarios/{id:\d+}', [UserController::class, 'form']);
$router->post('/panel/usuarios/{id:\d+}/eliminar', [UserController::class, 'destroy']);
$router->any('/panel/perfil', [UserController::class, 'profile']);

$router->get('/panel/reportes', [ReportController::class, 'index']);
$router->get('/panel/reportes/excel', [ReportController::class, 'excel']);
$router->get('/panel/reportes/pdf', [ReportController::class, 'pdf']);

$router->any('/panel/importar', [ImportController::class, 'index']);
$router->post('/panel/importar/analizar', [ImportController::class, 'analyze']);
$router->post('/panel/importar/ejecutar', [ImportController::class, 'run']);
$router->get('/panel/importar/plantilla', [ImportController::class, 'template']);
$router->get('/panel/importar/plantilla-clientes', [ImportController::class, 'templateCustomers']);

$router->any('/panel/ajustes', [SettingsController::class, 'index']);
$router->get('/panel/bitacora', [SettingsController::class, 'audit']);

// ------------------------------------------------------------- superadmin
$router->get('/super', [PlatformController::class, 'dashboard']);
$router->any('/super/ajustes', [PlatformController::class, 'settings']);
$router->any('/super/landing', [PlatformController::class, 'landing']);
$router->post('/super/landing/{id:\d+}/eliminar', [PlatformController::class, 'landingDelete']);
$router->any('/super/planes', [PlatformController::class, 'plans']);
$router->post('/super/planes/{id:\d+}/eliminar', [PlatformController::class, 'planDelete']);
$router->get('/super/respaldos', [PlatformController::class, 'backups']);
$router->post('/super/respaldos/crear', [PlatformController::class, 'backupCreate']);
$router->get('/super/respaldos/descargar/{name:[A-Za-z0-9\.\-_]+}', [PlatformController::class, 'backupDownload']);
$router->post('/super/respaldos/eliminar', [PlatformController::class, 'backupDelete']);
$router->get('/super/bitacora', [PlatformController::class, 'audit']);
$router->get('/super/empresas', [CompanyAdminController::class, 'index']);
$router->any('/super/empresas/nueva', [CompanyAdminController::class, 'form']);
$router->any('/super/empresas/{id:\d+}', [CompanyAdminController::class, 'form']);
$router->post('/super/empresas/{id:\d+}/eliminar', [CompanyAdminController::class, 'destroy']);
$router->get('/super/empresas/{id:\d+}/entrar', [CompanyAdminController::class, 'impersonate']);

// ------------------------------------------------------------- utilitarios
$router->get('/media/{path:.+}', [MediaController::class, 'serve']);
$router->get('/sitemap.xml', [LandingController::class, 'sitemap']);
$router->get('/robots.txt', [LandingController::class, 'robots']);
$router->get('/manifest.webmanifest', [LandingController::class, 'manifest']);
$router->get('/sw.js', [LandingController::class, 'serviceWorker']);
$router->get('/offline', [LandingController::class, 'offline']);
