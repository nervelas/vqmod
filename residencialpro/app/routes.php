<?php
declare(strict_types=1);

/**
 * Tabla de rutas. {id:num} restringe a dígitos.
 */

use App\Core\Router;

$r = new Router();

// ------------------------------------------------------------- PÚBLICO
$r->get('/',                       'PublicoControlador@inicio');
$r->cualquiera('/contacto',        'PublicoControlador@contacto');
$r->get('/reglamento',             'PublicoControlador@reglamento');
$r->cualquiera('/verificar',       'PublicoControlador@buscarVerificacion');
$r->get('/verificar/{hash}',       'PublicoControlador@verificar');
$r->get('/verificar/solvencia/{casa:\d+}/{codigo}', 'PublicoControlador@verificarSolvencia');
$r->get('/instalar',              'PwaControlador@instalar');
$r->get('/manifest.json',          'PwaControlador@manifest');
$r->get('/sitemap.xml',            'PublicoControlador@sitemap');
$r->get('/robots.txt',             'PublicoControlador@robots');
$r->get('/sin-conexion',           'PwaControlador@offline');

// --------------------------------------------------------------- ACCESO
$r->cualquiera('/acceso',          'AccesoControlador@entrar');
$r->cualquiera('/acceso/verificar', 'AccesoControlador@dosFactores');
$r->get('/salir',                  'AccesoControlador@salir');
$r->cualquiera('/recuperar',       'AccesoControlador@recuperar');
$r->cualquiera('/restablecer/{token}', 'AccesoControlador@restablecer');

// ------------------------------------------------------------ TABLERO
$r->get('/admin',                  'TableroControlador@inicio');
$r->get('/admin/informes',         'TableroControlador@informes');
$r->cualquiera('/admin/auditoria', 'TableroControlador@auditoria');
$r->cualquiera('/admin/respaldos', 'TableroControlador@respaldos');
$r->cualquiera('/admin/onboarding', 'TableroControlador@onboarding');

// -------------------------------------------------------------- CASAS
$r->get('/admin/casas',                    'CasasControlador@index');
$r->cualquiera('/admin/casas/nueva',       'CasasControlador@nueva');
$r->get('/admin/casas/{id:\d+}',           'CasasControlador@detalle');
$r->cualquiera('/admin/casas/{id:\d+}/editar', 'CasasControlador@editar');
$r->post('/admin/casas/{id:\d+}/eliminar', 'CasasControlador@eliminar');
$r->cualquiera('/admin/casas/importar',    'CasasControlador@importar');
$r->cualquiera('/admin/estructura',        'CasasControlador@estructura');
$r->post('/admin/estructura/fase',         'CasasControlador@guardarFase');
$r->post('/admin/estructura/calle',        'CasasControlador@guardarCalle');
$r->cualquiera('/admin/mapa',              'CasasControlador@mapa');

// --------------------------------------------------------- RESIDENTES
$r->get('/admin/residentes',                     'ResidentesControlador@index');
$r->cualquiera('/admin/residentes/nuevo',        'ResidentesControlador@nuevo');
$r->cualquiera('/admin/residentes/{id:\d+}/editar', 'ResidentesControlador@editar');
$r->post('/admin/residentes/{id:\d+}/baja',      'ResidentesControlador@baja');
$r->post('/admin/residentes/{id:\d+}/acceso',    'ResidentesControlador@crearAcceso');
$r->post('/admin/vehiculos',                     'ResidentesControlador@guardarVehiculo');
$r->post('/admin/vehiculos/{id:\d+}/eliminar',   'ResidentesControlador@eliminarVehiculo');
$r->post('/admin/mascotas',                      'ResidentesControlador@guardarMascota');
$r->post('/admin/empleados',                     'ResidentesControlador@guardarEmpleado');

// ------------------------------------------------------------- CUOTAS
$r->get('/admin/cuotas',                     'CuotasControlador@index');
$r->cualquiera('/admin/cuotas/concepto',     'CuotasControlador@concepto');
$r->cualquiera('/admin/cuotas/concepto/{id:\d+}', 'CuotasControlador@concepto');
$r->cualquiera('/admin/cuotas/generar',      'CuotasControlador@generar');
$r->get('/admin/cargos',                     'CuotasControlador@cargos');
$r->cualquiera('/admin/cargos/nuevo',        'CuotasControlador@nuevoCargo');
$r->post('/admin/cargos/{id:\d+}/anular',    'CuotasControlador@anularCargo');
$r->get('/admin/morosidad',                  'CuotasControlador@morosidad');
$r->post('/admin/morosidad/recordatorios',   'CuotasControlador@recordatorios');

// -------------------------------------------------------------- PAGOS
$r->get('/admin/pagos',                    'PagosControlador@index');
$r->cualquiera('/admin/pagos/nuevo',       'PagosControlador@nuevo');
$r->get('/admin/pagos/{id:\d+}',           'PagosControlador@detalle');
$r->post('/admin/pagos/{id:\d+}/anular',   'PagosControlador@anular');
$r->get('/admin/comprobantes',             'PagosControlador@comprobantes');
$r->post('/admin/comprobantes/{id:\d+}/aprobar',  'PagosControlador@aprobar');
$r->post('/admin/comprobantes/{id:\d+}/rechazar', 'PagosControlador@rechazar');

// ------------------------------------------------------------ EGRESOS
$r->get('/admin/egresos',                   'EgresosControlador@index');
$r->cualquiera('/admin/egresos/nuevo',      'EgresosControlador@nuevo');
$r->cualquiera('/admin/egresos/{id:\d+}/editar', 'EgresosControlador@nuevo');
$r->post('/admin/egresos/{id:\d+}/anular',  'EgresosControlador@anular');
$r->cualquiera('/admin/proveedores',        'EgresosControlador@proveedores');
$r->cualquiera('/admin/categorias',         'EgresosControlador@categorias');
$r->cualquiera('/admin/cuentas',            'EgresosControlador@cuentas');
$r->cualquiera('/admin/presupuesto',        'EgresosControlador@presupuesto');

// ------------------------------------------------------------- GARITA
$r->get('/garita',                      'GaritaControlador@panel');
$r->cualquiera('/garita/ingreso',       'GaritaControlador@ingreso');
$r->post('/garita/salida/{id:\d+}',     'GaritaControlador@salida');
$r->cualquiera('/garita/bitacora',      'GaritaControlador@bitacora');
$r->cualquiera('/garita/turno',         'GaritaControlador@turno');
$r->post('/garita/panico',              'GaritaControlador@panico');
$r->get('/garita/directorio',           'GaritaControlador@directorio');
$r->get('/garita/visitas',              'GaritaControlador@visitas');
$r->get('/admin/visitas',               'GaritaControlador@reporteVisitas');
$r->get('/admin/bitacora',              'GaritaControlador@bitacoraAdmin');

// -------------------------------------------------------------- ÁREAS
$r->cualquiera('/admin/areas',                 'AreasControlador@index');
$r->cualquiera('/admin/areas/{id:\d+}/editar', 'AreasControlador@editar');
$r->get('/admin/reservas',                     'AreasControlador@reservas');
$r->post('/admin/reservas/{id:\d+}/aprobar',   'AreasControlador@aprobar');
$r->post('/admin/reservas/{id:\d+}/rechazar',  'AreasControlador@rechazar');

// ------------------------------------------------------ COMUNICACIÓN
$r->get('/admin/avisos',                    'ComunicacionControlador@avisos');
$r->cualquiera('/admin/avisos/nuevo',       'ComunicacionControlador@nuevoAviso');
$r->cualquiera('/admin/avisos/{id:\d+}/editar', 'ComunicacionControlador@nuevoAviso');
$r->post('/admin/avisos/{id:\d+}/eliminar', 'ComunicacionControlador@eliminarAviso');
$r->cualquiera('/admin/eventos',            'ComunicacionControlador@eventos');
$r->get('/admin/votaciones',                'ComunicacionControlador@votaciones');
$r->cualquiera('/admin/votaciones/nueva',   'ComunicacionControlador@nuevaVotacion');
$r->get('/admin/votaciones/{id:\d+}',       'ComunicacionControlador@verVotacion');
$r->post('/admin/votaciones/{id:\d+}/estado', 'ComunicacionControlador@estadoVotacion');
$r->get('/admin/incidencias',               'ComunicacionControlador@incidencias');
$r->cualquiera('/admin/incidencias/{id:\d+}', 'ComunicacionControlador@verIncidencia');
$r->cualquiera('/admin/mensajes',           'ComunicacionControlador@mensajes');
$r->cualquiera('/admin/emergencia',         'ComunicacionControlador@emergencia');

// ----------------------------------------------------------- USUARIOS
$r->get('/admin/usuarios',                      'UsuariosControlador@index');
$r->cualquiera('/admin/usuarios/nuevo',         'UsuariosControlador@nuevo');
$r->cualquiera('/admin/usuarios/{id:\d+}/editar', 'UsuariosControlador@nuevo');
$r->cualquiera('/admin/ajustes',                'UsuariosControlador@ajustes');
$r->cualquiera('/admin/sitio',                  'UsuariosControlador@sitio');
$r->cualquiera('/perfil',                       'UsuariosControlador@perfil');

// ------------------------------------------------------------- PORTAL
$r->get('/portal',                          'PortalControlador@inicio');
$r->get('/portal/estado-cuenta',            'PortalControlador@estadoCuenta');
$r->cualquiera('/portal/pagar',             'PortalControlador@pagar');
$r->get('/portal/visitas',                  'PortalControlador@visitas');
$r->cualquiera('/portal/visitas/nueva',     'PortalControlador@nuevaVisita');
$r->post('/portal/visitas/{id:\d+}/cancelar', 'PortalControlador@cancelarVisita');
$r->cualquiera('/portal/reservas',          'PortalControlador@reservas');
$r->post('/portal/reservas/{id:\d+}/cancelar', 'PortalControlador@cancelarReserva');
$r->get('/portal/avisos',                   'PortalControlador@avisos');
$r->get('/portal/avisos/{id:\d+}',          'PortalControlador@verAviso');
$r->cualquiera('/portal/incidencias',       'PortalControlador@incidencias');
$r->cualquiera('/portal/votaciones',        'PortalControlador@votaciones');
$r->cualquiera('/portal/mensajes',          'PortalControlador@mensajes');
$r->get('/portal/documentos',               'PortalControlador@documentos');
$r->post('/portal/casa/{id:\d+}',           'PortalControlador@cambiarCasa');

// --------------------------------------------------------- DOCUMENTOS
$r->get('/doc/recibo/{id:\d+}',          'DocumentosControlador@recibo');
$r->get('/doc/estado-cuenta/{casa:\d+}', 'DocumentosControlador@estadoCuenta');
$r->get('/doc/solvencia/{casa:\d+}',     'DocumentosControlador@solvencia');
$r->get('/doc/carta/{casa:\d+}',         'DocumentosControlador@carta');
$r->get('/doc/morosidad',                'DocumentosControlador@morosidad');
$r->get('/doc/informe/{periodo}',        'DocumentosControlador@informe');
$r->get('/doc/acta/{id:\d+}',            'DocumentosControlador@acta');
$r->get('/doc/pase/{id:\d+}',            'DocumentosControlador@pase');
$r->get('/qr/pase/{id:\d+}',             'DocumentosControlador@qrPase');
$r->get('/excel/{tipo}',                 'DocumentosControlador@excel');
$r->get('/excel/estado-cuenta/{casa:\d+}', 'DocumentosControlador@excelEstadoCuenta');
$r->get('/archivo/{carpeta}/{nombre}',   'DocumentosControlador@archivo');

// ------------------------------------------------------------------ API
$r->get('/api/tablero',              'ApiControlador@tablero');
$r->get('/api/notificaciones',       'ApiControlador@notificaciones');
$r->post('/api/notificaciones/leer', 'ApiControlador@marcarLeidas');
$r->get('/api/push/clave',           'ApiControlador@clavePush');
$r->post('/api/push/suscribir',      'ApiControlador@suscribirPush');
$r->post('/api/push/cancelar',       'ApiControlador@cancelarPush');
$r->post('/api/garita/validar',      'ApiControlador@validarCodigo');
$r->get('/api/garita/placa',         'ApiControlador@buscarPlaca');
$r->post('/api/garita/sincronizar',  'ApiControlador@sincronizarGarita');
$r->get('/api/casas',                'ApiControlador@casas');
$r->get('/api/casa/{id:\d+}/cargos', 'ApiControlador@cargosCasa');
$r->post('/api/tema',                'ApiControlador@tema');

return $r;
