<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Comunicacion;
use App\Models\Pago;
use App\Models\Reserva;

/** Construcción del menú según el rol. */
final class Menu
{
    public static function panel(): array
    {
        $rol = Auth::rol();
        $contadores = self::contadores();

        $m = [];
        $m[] = ['grupo' => '', 'items' => [
            ['url' => '/admin', 'texto' => 'Tablero', 'icono' => 'panel', 'exacto' => true],
        ]];

        if (in_array($rol, ['admin', 'junta'], true)) {
            $m[] = ['grupo' => 'Residencial', 'items' => array_values(array_filter([
                ['url' => '/admin/casas',      'texto' => 'Viviendas',  'icono' => 'casa'],
                ['url' => '/admin/residentes', 'texto' => 'Residentes', 'icono' => 'usuarios'],
                $rol === 'admin' ? ['url' => '/admin/estructura', 'texto' => 'Fases y calles', 'icono' => 'capas'] : null,
                ['url' => '/admin/mapa',       'texto' => 'Mapa del residencial', 'icono' => 'mapa'],
            ]))];
        }

        if (in_array($rol, ['admin', 'junta', 'contabilidad'], true)) {
            $m[] = ['grupo' => 'Finanzas', 'items' => array_values(array_filter([
                ['url' => '/admin/cuotas',    'texto' => 'Cuotas y conceptos', 'icono' => 'billetera'],
                ['url' => '/admin/cargos',    'texto' => 'Cargos emitidos',    'icono' => 'recibo'],
                ['url' => '/admin/pagos',     'texto' => 'Pagos',              'icono' => 'tarjeta'],
                in_array($rol, ['admin', 'contabilidad'], true)
                    ? ['url' => '/admin/comprobantes', 'texto' => 'Comprobantes', 'icono' => 'archivo',
                       'pastilla' => $contadores['comprobantes'], 'rojo' => true]
                    : null,
                ['url' => '/admin/morosidad', 'texto' => 'Morosidad',          'icono' => 'alerta'],
                ['url' => '/admin/egresos',   'texto' => 'Egresos',            'icono' => 'moneda'],
                ['url' => '/admin/presupuesto', 'texto' => 'Presupuesto',      'icono' => 'barras'],
                ['url' => '/admin/informes',  'texto' => 'Informes',           'icono' => 'grafica'],
            ]))];
        }

        if (in_array($rol, ['admin', 'junta'], true)) {
            $m[] = ['grupo' => 'Operación', 'items' => [
                ['url' => '/admin/visitas',     'texto' => 'Visitas y accesos', 'icono' => 'puerta'],
                ['url' => '/admin/bitacora',    'texto' => 'Bitácora de garita', 'icono' => 'libro'],
                ['url' => '/admin/areas',       'texto' => 'Áreas comunes',     'icono' => 'brillo'],
                ['url' => '/admin/reservas',    'texto' => 'Reservas',          'icono' => 'calendario',
                 'pastilla' => $contadores['reservas']],
                ['url' => '/admin/incidencias', 'texto' => 'Incidencias',       'icono' => 'llave_inglesa',
                 'pastilla' => $contadores['incidencias']],
            ]];
            $m[] = ['grupo' => 'Comunicación', 'items' => [
                ['url' => '/admin/avisos',     'texto' => 'Avisos',      'icono' => 'megafono'],
                ['url' => '/admin/eventos',    'texto' => 'Calendario',  'icono' => 'calendario'],
                ['url' => '/admin/votaciones', 'texto' => 'Votaciones',  'icono' => 'voto'],
                ['url' => '/admin/mensajes',   'texto' => 'Mensajes',    'icono' => 'chat'],
            ]];
        }

        if ($rol === 'admin') {
            $m[] = ['grupo' => 'Configuración', 'items' => [
                ['url' => '/admin/usuarios',  'texto' => 'Usuarios y accesos', 'icono' => 'llave'],
                ['url' => '/admin/ajustes',   'texto' => 'Ajustes del condominio', 'icono' => 'ajustes'],
                ['url' => '/admin/sitio',     'texto' => 'Sitio público',      'icono' => 'mapa'],
                ['url' => '/admin/auditoria', 'texto' => 'Auditoría',          'icono' => 'escudo'],
                ['url' => '/admin/respaldos', 'texto' => 'Respaldos',          'icono' => 'guardar'],
            ]];
        }

        return $m;
    }

    /**
     * Accesos rápidos de la barra inferior en el teléfono.
     *
     * Los roles de cada destino son los mismos que exige su controlador: si
     * aquí se ensanchan, el usuario toca el icono y recibe un 403.
     */
    public static function rapido(): array
    {
        $rol = Auth::rol();
        $candidatos = [
            ['url' => '/admin',            'texto' => 'Tablero',  'icono' => 'panel', 'exacto' => true,
             'roles' => ['admin', 'junta', 'contabilidad']],
            ['url' => '/admin/morosidad',  'texto' => 'Cobros',   'icono' => 'billetera',
             'roles' => ['admin', 'junta', 'contabilidad']],
            ['url' => '/admin/comprobantes', 'texto' => 'Revisar', 'icono' => 'archivo',
             'roles' => ['admin', 'contabilidad']],
            ['url' => '/admin/visitas',    'texto' => 'Visitas',  'icono' => 'puerta',
             'roles' => ['admin', 'junta']],
            ['url' => '/admin/avisos',     'texto' => 'Avisos',   'icono' => 'megafono',
             'roles' => ['admin', 'junta']],
            ['url' => '/admin/cargos',     'texto' => 'Cargos',   'icono' => 'recibo',
             'roles' => ['admin', 'junta', 'contabilidad']],
            ['url' => '/admin/informes',   'texto' => 'Informes', 'icono' => 'grafica',
             'roles' => ['admin', 'junta', 'contabilidad']],
        ];
        $items = [];
        foreach ($candidatos as $item) {
            if (!in_array($rol, $item['roles'], true)) {
                continue;
            }
            unset($item['roles']);
            $items[] = $item;
            if (count($items) === 5) {
                break;
            }
        }
        return $items;
    }

    public static function portal(): array
    {
        return [
            ['url' => '/portal',                'texto' => 'Inicio',      'icono' => 'inicio', 'exacto' => true],
            ['url' => '/portal/estado-cuenta',  'texto' => 'Mi cuenta',   'icono' => 'billetera'],
            ['url' => '/portal/visitas',        'texto' => 'Visitas',     'icono' => 'qr'],
            ['url' => '/portal/reservas',       'texto' => 'Reservas',    'icono' => 'calendario'],
            ['url' => '/portal/avisos',         'texto' => 'Avisos',      'icono' => 'megafono'],
        ];
    }

    public static function portalCompleto(): array
    {
        return array_merge(self::portal(), [
            ['url' => '/portal/incidencias', 'texto' => 'Reportes',    'icono' => 'llave_inglesa'],
            ['url' => '/portal/votaciones',  'texto' => 'Votaciones',  'icono' => 'voto'],
            ['url' => '/portal/mensajes',    'texto' => 'Mensajes',    'icono' => 'chat'],
            ['url' => '/portal/documentos',  'texto' => 'Documentos',  'icono' => 'archivo'],
        ]);
    }

    public static function garita(): array
    {
        return [
            ['url' => '/garita',            'texto' => 'Accesos',   'icono' => 'escanear', 'exacto' => true],
            ['url' => '/garita/visitas',    'texto' => 'Adentro',   'icono' => 'usuarios'],
            ['url' => '/garita/bitacora',   'texto' => 'Bitácora',  'icono' => 'libro'],
            ['url' => '/garita/directorio', 'texto' => 'Directorio', 'icono' => 'telefono'],
        ];
    }

    /** ¿La ruta actual corresponde a este elemento? */
    public static function esActivo(array $item): bool
    {
        $uri = Peticion::uri();
        if (!empty($item['exacto'])) {
            return $uri === $item['url'];
        }
        return $uri === $item['url'] || str_starts_with($uri, rtrim($item['url'], '/') . '/');
    }

    private static function contadores(): array
    {
        try {
            return [
                'comprobantes' => Pago::pendientesRevision(),
                'reservas'     => Reserva::pendientes(),
                'incidencias'  => Comunicacion::abiertas(),
            ];
        } catch (\Throwable) {
            return ['comprobantes' => 0, 'reservas' => 0, 'incidencias' => 0];
        }
    }
}
