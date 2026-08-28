<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Definicion central de rutas de MenuGold.
 */
final class Routes
{
    public static function define(Router $r): void
    {
        // ==============================================================
        //  Publico: plataforma y menu del cliente
        // ==============================================================
        $r->get('/',                       'Home@index');
        $r->post('/contacto',              'Home@contacto');
        $r->get('/planes',                 'Home@planes');
        $r->get('/demo',                   'Home@demo');
        $r->get('/qr-demo.png',            'Home@qrDemo');
        $r->get('/sitemap.xml',            'Home@sitemap');
        $r->get('/offline',                'Home@offline');
        $r->get('/manifest.webmanifest',   'Pwa@manifest');
        $r->get('/sw.js',                  'Pwa@serviceWorker');
        $r->get('/icono/{tam}',            'Pwa@icono');
        $r->get('/favicon.ico',            'Pwa@favicon');
        $r->get('/r/{slug}/manifest.webmanifest', 'Pwa@manifest');
        $r->get('/r/{slug}/icono/{tam}',   'Pwa@icono');
        $r->get('/archivo/{carpeta}/{nombre}', 'Archivo@servir');
        $r->get('/archivo/{carpeta}/{sub}/{nombre}', 'Archivo@servir');

        // Menu del cliente
        $r->get('/r/{slug}',               'Menu@index');
        $r->get('/r/{slug}/m/{mesa}',      'Menu@index');
        $r->get('/r/{slug}/carta',         'Menu@index');
        $r->get('/r/{slug}/pedido/{codigo}', 'Menu@seguimiento');
        $r->get('/r/{slug}/gracias/{codigo}', 'Menu@gracias');
        // Dominio propio (sin prefijo /r/{slug})
        $r->get('/m/{mesa}',               'Menu@porDominio');
        $r->get('/pedido/{codigo}',        'Menu@seguimientoDominio');

        // API publica del menu
        $r->group('api', [], static function (Router $r): void {
            $r->get('/producto/{slug}/{id}',  'PedidoApi@producto');
            $r->post('/calcular',             'PedidoApi@calcular');
            $r->post('/pedido',               'PedidoApi@crear');
            $r->post('/cupon',                'PedidoApi@cupon');
            $r->post('/llamar',               'PedidoApi@llamar');
            $r->post('/resena',               'PedidoApi@resena');
            $r->get('/estado/{slug}/{codigo}','PedidoApi@estado');
            $r->get('/sse/{slug}/{codigo}',   'PedidoApi@sse');
        });

        // ==============================================================
        //  Autenticacion
        // ==============================================================
        $r->get('/ingresar',   'Acceso@formulario', ['middleware' => ['instalado', 'invitado']]);
        $r->post('/ingresar',  'Acceso@entrar',     ['middleware' => ['instalado']]);
        $r->any('/salir',      'Acceso@salir');
        $r->get('/recuperar',  'Acceso@recuperarForm',  ['middleware' => ['invitado']]);
        $r->post('/recuperar', 'Acceso@recuperarEnviar');
        $r->get('/restablecer/{token}',  'Acceso@restablecerForm');
        $r->post('/restablecer/{token}', 'Acceso@restablecerGuardar');

        // ==============================================================
        //  Panel del restaurante
        // ==============================================================
        $mwPanel = ['middleware' => ['instalado', 'auth', 'restaurante', 'csrf']];

        $r->group('panel', $mwPanel, static function (Router $r): void {
            $r->get('/',                  'Panel\Dashboard@index');
            $r->get('/inicio',            'Panel\Dashboard@onboarding');
            $r->post('/inicio',           'Panel\Dashboard@onboardingGuardar');
            $r->post('/tema',             'Panel\Dashboard@tema');
            $r->get('/resumen.json',      'Panel\Dashboard@resumen');

            // --- Menu ---
            $r->get('/categorias',        'Panel\Menu@categorias');
            $r->post('/categorias/guardar','Panel\Menu@categoriaGuardar');
            $r->post('/categorias/borrar','Panel\Menu@categoriaBorrar');
            $r->post('/categorias/ordenar','Panel\Menu@categoriaOrdenar');

            $r->get('/productos',         'Panel\Menu@productos');
            $r->get('/productos/nuevo',   'Panel\Menu@productoForm');
            $r->get('/productos/{id}',    'Panel\Menu@productoForm');
            $r->post('/productos/guardar','Panel\Menu@productoGuardar');
            $r->post('/productos/borrar', 'Panel\Menu@productoBorrar');
            $r->post('/productos/duplicar','Panel\Menu@productoDuplicar');
            $r->post('/productos/agotado','Panel\Menu@productoAgotado');
            $r->post('/productos/ordenar','Panel\Menu@productoOrdenar');
            $r->post('/productos/imagen-borrar','Panel\Menu@productoImagenBorrar');

            $r->get('/importar',          'Panel\Importar@index');
            $r->post('/importar',         'Panel\Importar@procesar');
            $r->get('/importar/plantilla','Panel\Importar@plantilla');

            $r->get('/modificadores',     'Panel\Menu@modificadores');
            $r->post('/modificadores/guardar','Panel\Menu@modificadorGuardar');
            $r->post('/modificadores/borrar','Panel\Menu@modificadorBorrar');
            $r->post('/modificadores/opcion','Panel\Menu@opcionGuardar');
            $r->post('/modificadores/opcion-borrar','Panel\Menu@opcionBorrar');

            $r->get('/promociones',       'Panel\Menu@promociones');
            $r->post('/promociones/guardar','Panel\Menu@promocionGuardar');
            $r->post('/promociones/borrar','Panel\Menu@promocionBorrar');

            // --- Mesas y QR ---
            $r->get('/mesas',             'Panel\Mesas@index');
            $r->post('/mesas/guardar',    'Panel\Mesas@guardar');
            $r->post('/mesas/borrar',     'Panel\Mesas@borrar');
            $r->post('/mesas/lote',       'Panel\Mesas@lote');
            $r->post('/mesas/zona',       'Panel\Mesas@zonaGuardar');
            $r->post('/mesas/zona-borrar','Panel\Mesas@zonaBorrar');
            $r->get('/qr',                'Panel\Qr@index');
            $r->get('/qr/png/{id}',       'Panel\Qr@png');
            $r->get('/qr/pdf',            'Panel\Qr@pdf');

            // --- Operacion ---
            $r->get('/cocina',            'Panel\Cocina@index');
            $r->get('/cocina/datos',      'Panel\Cocina@datos');
            $r->get('/cocina/sse',        'Panel\Cocina@sse');
            $r->post('/cocina/avanzar',   'Panel\Cocina@avanzar');

            $r->get('/mesero',            'Panel\Mesero@index');
            $r->get('/mesero/datos',      'Panel\Mesero@datos');
            $r->get('/mesero/sse',        'Panel\Mesero@sse');
            $r->get('/mesero/mesa/{id}',  'Panel\Mesero@mesa');
            $r->post('/mesero/abrir',     'Panel\Mesero@abrir');
            $r->post('/mesero/pedido',    'Panel\Mesero@pedidoManual');
            $r->post('/mesero/cobrar',    'Panel\Mesero@cobrar');
            $r->post('/mesero/cerrar',    'Panel\Mesero@cerrarMesa');
            $r->post('/mesero/llamada',   'Panel\Mesero@atenderLlamada');
            $r->post('/mesero/descuento', 'Panel\Mesero@descuento');
            $r->get('/mesero/ticket/{id}','Panel\Mesero@ticket');
            $r->get('/mesero/precuenta/{id}','Panel\Mesero@precuenta');

            $r->get('/pedidos',           'Panel\Pedidos@index');
            $r->get('/pedidos/{id}',      'Panel\Pedidos@ver');
            $r->post('/pedidos/estado',   'Panel\Pedidos@estado');
            $r->post('/pedidos/anular',   'Panel\Pedidos@anular');

            // --- Clientes y fidelidad ---
            $r->get('/clientes',          'Panel\Clientes@index');
            $r->get('/clientes/{id}',     'Panel\Clientes@ver');
            $r->post('/clientes/guardar', 'Panel\Clientes@guardar');
            $r->post('/clientes/borrar',  'Panel\Clientes@borrar');
            $r->get('/cupones',           'Panel\Clientes@cupones');
            $r->post('/cupones/guardar',  'Panel\Clientes@cuponGuardar');
            $r->post('/cupones/borrar',   'Panel\Clientes@cuponBorrar');

            // --- Reportes ---
            $r->get('/reportes',          'Panel\Reportes@index');
            $r->get('/reportes/datos',    'Panel\Reportes@datos');
            $r->get('/reportes/pdf',      'Panel\Reportes@pdf');
            $r->get('/reportes/excel',    'Panel\Reportes@excel');

            // --- Configuracion ---
            $r->get('/configuracion',     'Panel\Config@index');
            $r->post('/configuracion',    'Panel\Config@guardar');
            $r->post('/configuracion/marca','Panel\Config@marca');
            $r->post('/configuracion/horarios','Panel\Config@horarios');
            $r->post('/configuracion/entrega','Panel\Config@entrega');
            $r->post('/configuracion/smtp','Panel\Config@smtp');
            $r->post('/configuracion/probar-correo','Panel\Config@probarCorreo');

            $r->get('/usuarios',          'Panel\Usuarios@index');
            $r->post('/usuarios/guardar', 'Panel\Usuarios@guardar');
            $r->post('/usuarios/borrar',  'Panel\Usuarios@borrar');
            $r->get('/perfil',            'Panel\Usuarios@perfil');
            $r->post('/perfil',           'Panel\Usuarios@perfilGuardar');

            $r->get('/auditoria',         'Panel\Auditoria@index');
            $r->get('/respaldo',          'Panel\Auditoria@respaldos');
            $r->post('/respaldo/crear',   'Panel\Auditoria@respaldoCrear');
            $r->get('/respaldo/bajar/{archivo}', 'Panel\Auditoria@respaldoBajar');
            $r->post('/respaldo/borrar',  'Panel\Auditoria@respaldoBorrar');
        });

        // ==============================================================
        //  Superadministrador de la plataforma
        // ==============================================================
        $mwSuper = ['middleware' => ['instalado', 'auth', 'super', 'csrf']];
        $r->group('super', $mwSuper, static function (Router $r): void {
            $r->get('/',                    'Super\Panel@index');
            $r->get('/restaurantes',        'Super\Restaurantes@index');
            $r->get('/restaurantes/nuevo',  'Super\Restaurantes@form');
            $r->get('/restaurantes/{id}',   'Super\Restaurantes@form');
            $r->post('/restaurantes/guardar','Super\Restaurantes@guardar');
            $r->post('/restaurantes/estado','Super\Restaurantes@estado');
            $r->post('/restaurantes/borrar','Super\Restaurantes@borrar');
            $r->get('/entrar/{id}',         'Super\Restaurantes@entrar');
            $r->get('/salir-restaurante',   'Super\Restaurantes@salirRestaurante');

            $r->get('/planes',              'Super\Planes@index');
            $r->post('/planes/guardar',     'Super\Planes@guardar');
            $r->post('/planes/borrar',      'Super\Planes@borrar');

            $r->get('/mensajes',            'Super\Ajustes@mensajes');
            $r->post('/mensajes/leido',     'Super\Ajustes@mensajeLeido');
            $r->get('/ajustes',             'Super\Ajustes@index');
            $r->post('/ajustes',            'Super\Ajustes@guardar');
            $r->get('/respaldos',           'Super\Ajustes@respaldos');
            $r->post('/respaldos/crear',    'Super\Ajustes@respaldoCrear');
            $r->get('/respaldos/bajar/{archivo}', 'Super\Ajustes@respaldoBajar');
        });
    }
}
