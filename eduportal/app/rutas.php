<?php
declare(strict_types=1);

/**
 * Tabla de rutas de EduPortal.
 * @var App\Core\Router $r
 */

use App\Controllers\Acceso;
use App\Controllers\Alumnos;
use App\Controllers\Api;
use App\Controllers\Archivos;
use App\Controllers\Asistencias;
use App\Controllers\Avisos;
use App\Controllers\Cobros;
use App\Controllers\Configuracion;
use App\Controllers\Mensajes;
use App\Controllers\Notas;
use App\Controllers\Panel;
use App\Controllers\Perfil;
use App\Controllers\Portal;
use App\Controllers\Reportes;
use App\Controllers\Sitio;
use App\Controllers\Tareas;
use App\Controllers\Usuarios;

// ---------- Sitio publico ----------
$r->get('/', [Sitio::class, 'inicio']);
$r->get('/calendario', [Sitio::class, 'calendario']);
$r->post('/contacto', [Sitio::class, 'contacto']);
$r->get('/inscripcion', [Sitio::class, 'preinscripcionForm']);
$r->post('/inscripcion', [Sitio::class, 'preinscripcion']);
$r->get('/sitemap.xml', [Sitio::class, 'sitemap']);
$r->get('/offline', [Sitio::class, 'offline']);
$r->get('/manifest.webmanifest', [Sitio::class, 'manifiesto']);

// ---------- Acceso ----------
$r->get('/ingresar', [Acceso::class, 'formulario']);
$r->post('/ingresar', [Acceso::class, 'entrar']);
$r->get('/salir', [Acceso::class, 'salir']);
$r->post('/salir', [Acceso::class, 'salir']);
$r->get('/verificar', [Acceso::class, 'verificar']);
$r->post('/verificar', [Acceso::class, 'confirmar2fa']);
$r->get('/recuperar', [Acceso::class, 'recuperar']);
$r->post('/recuperar', [Acceso::class, 'enviarRecuperacion']);
$r->get('/restablecer/{token}', [Acceso::class, 'restablecerForm']);
$r->post('/restablecer', [Acceso::class, 'restablecer']);

// ---------- Panel ----------
$r->get('/panel', [Panel::class, 'index']);
$r->get('/panel/datos', [Panel::class, 'datos']);

// ---------- Perfil ----------
$r->get('/perfil', [Perfil::class, 'index']);
$r->post('/perfil', [Perfil::class, 'guardar']);
$r->post('/perfil/password', [Perfil::class, 'password']);
$r->post('/perfil/apariencia', [Perfil::class, 'apariencia']);
$r->post('/perfil/sesiones', [Perfil::class, 'cerrarSesiones']);
$r->post('/perfil/2fa', [Perfil::class, 'twofa']);

// ---------- Alumnos ----------
$r->get('/alumnos', [Alumnos::class, 'index']);
$r->get('/alumnos/nuevo', [Alumnos::class, 'crear']);
$r->post('/alumnos', [Alumnos::class, 'guardar']);
$r->get('/alumnos/importar', [Alumnos::class, 'importarForm']);
$r->post('/alumnos/importar', [Alumnos::class, 'importar']);
$r->get('/alumnos/plantilla', [Alumnos::class, 'plantilla']);
$r->get('/alumnos/exportar', [Alumnos::class, 'exportar']);
$r->get('/alumnos/{id}', [Alumnos::class, 'ver']);
$r->get('/alumnos/{id}/editar', [Alumnos::class, 'editar']);
$r->post('/alumnos/{id}', [Alumnos::class, 'guardar']);
$r->post('/alumnos/{id}/eliminar', [Alumnos::class, 'eliminar']);
$r->get('/alumnos/{id}/carne', [Alumnos::class, 'carne']);
$r->get('/alumnos/{id}/qr', [Alumnos::class, 'qr']);
$r->get('/carnes/{seccion}', [Alumnos::class, 'carnesSeccion']);
$r->post('/alumnos/{id}/encargado', [Alumnos::class, 'guardarEncargado']);
$r->post('/encargado/{id}/eliminar', [Alumnos::class, 'eliminarEncargado']);
$r->post('/alumnos/{id}/documento', [Alumnos::class, 'subirDocumento']);
$r->post('/documento/{id}/eliminar', [Alumnos::class, 'eliminarDocumento']);

// ---------- Cobranza ----------
$r->get('/cobranza', [Cobros::class, 'index']);
$r->get('/cobranza/conceptos', [Cobros::class, 'conceptos']);
$r->post('/cobranza/conceptos', [Cobros::class, 'guardarConcepto']);
$r->post('/cobranza/conceptos/{id}/eliminar', [Cobros::class, 'eliminarConcepto']);
$r->get('/cobranza/generar', [Cobros::class, 'generarForm']);
$r->post('/cobranza/generar', [Cobros::class, 'generar']);
$r->get('/cobranza/estado/{alumno}', [Cobros::class, 'estadoCuenta']);
$r->post('/cobranza/cobrar/{alumno}', [Cobros::class, 'cobrar']);
$r->post('/cobranza/cargo/{alumno}', [Cobros::class, 'cargoManual']);
$r->post('/cargo/{id}/anular', [Cobros::class, 'anularCargo']);
$r->get('/cobranza/pagos', [Cobros::class, 'pagos']);
$r->post('/pago/{id}/aprobar', [Cobros::class, 'aprobarPago']);
$r->post('/pago/{id}/rechazar', [Cobros::class, 'rechazarPago']);
$r->post('/pago/{id}/anular', [Cobros::class, 'anularPago']);
$r->get('/recibo/{id}', [Cobros::class, 'recibo']);
$r->get('/cobranza/morosidad', [Cobros::class, 'morosidad']);
$r->get('/cobranza/morosidad/{formato}', [Cobros::class, 'morosidadExportar']);
$r->get('/cobranza/caja', [Cobros::class, 'caja']);
$r->get('/cobranza/caja/pdf', [Cobros::class, 'cajaPdf']);

// ---------- Notas ----------
$r->get('/notas', [Notas::class, 'index']);
$r->get('/notas/cuadro-honor', [Notas::class, 'cuadroHonor']);
$r->post('/notas/actividad', [Notas::class, 'guardarActividad']);
$r->post('/notas/actividad/{id}/eliminar', [Notas::class, 'eliminarActividad']);
$r->post('/notas/guardar', [Notas::class, 'guardarNota']);
$r->post('/notas/conducta', [Notas::class, 'guardarConducta']);
$r->post('/periodo/{id}/cerrar', [Notas::class, 'cerrarPeriodo']);
$r->get('/notas/{asignacion}', [Notas::class, 'cuadricula']);
$r->get('/boleta/{alumno}', [Notas::class, 'boleta']);
$r->get('/boletas/{seccion}', [Notas::class, 'boletasGrupo']);

// ---------- Asistencia ----------
$r->get('/asistencia', [Asistencias::class, 'index']);
$r->get('/asistencia/{seccion}', [Asistencias::class, 'pase']);
$r->post('/asistencia/{seccion}', [Asistencias::class, 'guardar']);
$r->get('/asistencia/{seccion}/reporte', [Asistencias::class, 'reporte']);
$r->get('/asistencia/{seccion}/reporte/{formato}', [Asistencias::class, 'reporteExportar']);

// ---------- Avisos, calendario y tareas ----------
$r->get('/avisos', [Avisos::class, 'index']);
$r->get('/avisos/nuevo', [Avisos::class, 'crear']);
$r->post('/avisos', [Avisos::class, 'guardar']);
$r->get('/avisos/{id}', [Avisos::class, 'ver']);
$r->get('/avisos/{id}/editar', [Avisos::class, 'editar']);
$r->post('/avisos/{id}', [Avisos::class, 'guardar']);
$r->post('/avisos/{id}/eliminar', [Avisos::class, 'eliminar']);
$r->get('/calendario-escolar', [Avisos::class, 'calendario']);
$r->post('/eventos', [Avisos::class, 'guardarEvento']);
$r->post('/eventos/{id}/eliminar', [Avisos::class, 'eliminarEvento']);
$r->get('/tareas', [Tareas::class, 'index']);
$r->post('/tareas', [Tareas::class, 'guardar']);
$r->post('/tareas/{id}/eliminar', [Tareas::class, 'eliminar']);
$r->get('/tareas/{id}/entregas', [Tareas::class, 'entregas']);
$r->post('/entrega/{id}/revisar', [Tareas::class, 'marcarRevisada']);

// ---------- Mensajes ----------
$r->get('/mensajes', [Mensajes::class, 'index']);
$r->get('/mensajes/{usuario}', [Mensajes::class, 'conversacion']);
$r->post('/mensajes/{usuario}', [Mensajes::class, 'enviar']);

// ---------- Reportes ----------
$r->get('/reportes', [Reportes::class, 'index']);
$r->get('/reportes/{tipo}', [Reportes::class, 'ver']);
$r->get('/reportes/{tipo}/{formato}', [Reportes::class, 'exportar']);

// ---------- Configuracion ----------
$r->get('/configuracion', [Configuracion::class, 'index']);
$r->post('/configuracion', [Configuracion::class, 'guardar']);
$r->post('/configuracion/iconos', [Configuracion::class, 'regenerarIconos']);
$r->get('/configuracion/cobranza', [Configuracion::class, 'cobranza']);
$r->post('/configuracion/cobranza', [Configuracion::class, 'guardarCobranza']);
$r->post('/configuracion/correo-prueba', [Configuracion::class, 'probarCorreo']);
$r->get('/configuracion/academico', [Configuracion::class, 'academico']);
$r->post('/configuracion/academico/{tipo}', [Configuracion::class, 'guardarAcademico']);
$r->post('/configuracion/academico/{tipo}/{id}/eliminar', [Configuracion::class, 'eliminarAcademico']);
$r->get('/configuracion/sitio', [Configuracion::class, 'sitio']);
$r->post('/configuracion/sitio', [Configuracion::class, 'guardarSitio']);
$r->post('/configuracion/sitio/galeria', [Configuracion::class, 'subirGaleria']);
$r->post('/configuracion/sitio/galeria/{id}/eliminar', [Configuracion::class, 'eliminarGaleria']);
$r->post('/configuracion/sitio/pagina', [Configuracion::class, 'guardarPagina']);
$r->get('/configuracion/bitacora', [Configuracion::class, 'bitacora']);
$r->get('/configuracion/respaldo', [Configuracion::class, 'respaldo']);
$r->post('/configuracion/respaldo', [Configuracion::class, 'generarRespaldo']);
$r->get('/configuracion/respaldo/{nombre}', [Configuracion::class, 'descargarRespaldo']);
$r->post('/configuracion/cron-token', [Configuracion::class, 'regenerarToken']);
$r->get('/configuracion/usuarios', [Usuarios::class, 'index']);
$r->post('/configuracion/usuarios', [Usuarios::class, 'guardar']);
$r->post('/configuracion/usuarios/{id}/estado', [Usuarios::class, 'estado']);
$r->post('/configuracion/usuarios/{id}/restablecer', [Usuarios::class, 'restablecer']);
$r->get('/preinscripciones', [Configuracion::class, 'preinscripciones']);
$r->post('/preinscripciones/{id}/estado', [Configuracion::class, 'estadoPreinscripcion']);

// ---------- Portal de padres ----------
$r->get('/portal', [Portal::class, 'index']);
$r->post('/portal/cambiar/{alumno}', [Portal::class, 'cambiar']);
$r->get('/portal/cuenta', [Portal::class, 'cuenta']);
$r->post('/portal/comprobante', [Portal::class, 'comprobante']);
$r->get('/portal/notas', [Portal::class, 'notas']);
$r->get('/portal/asistencia', [Portal::class, 'asistencia']);
$r->get('/portal/avisos', [Portal::class, 'avisos']);
$r->get('/portal/tareas', [Portal::class, 'tareas']);
$r->post('/portal/tarea/{id}/entregar', [Portal::class, 'entregarTarea']);
$r->get('/portal/alumno', [Portal::class, 'perfilAlumno']);

// ---------- API interna ----------
$r->get('/api/notificaciones', [Api::class, 'notificaciones']);
$r->post('/api/notificaciones/leer', [Api::class, 'marcarLeidas']);
$r->post('/api/push', [Api::class, 'suscribirPush']);
$r->get('/api/push/clave', [Api::class, 'clavePush']);
$r->get('/api/alumnos', [Api::class, 'buscarAlumnos']);

// ---------- Archivos protegidos ----------
$r->get('/archivo/{carpeta}/{nombre}', [Archivos::class, 'servir']);
