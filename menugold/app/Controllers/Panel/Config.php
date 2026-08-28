<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Controllers\Panel\Dashboard;
use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Mailer;
use MenuGold\Core\Request;
use MenuGold\Core\Setting;
use MenuGold\Models\DeliveryZone;
use MenuGold\Models\Restaurant;

/**
 * Configuracion del restaurante: marca, horarios, entrega, pagos y correo.
 */
class Config extends Base
{
    public function index(): void
    {
        $this->exigir('config');
        $rm = new Restaurant();
        $this->panel('panel/configuracion', [
            'horarios' => $rm->horarios($this->rid),
            'zonas'    => (new DeliveryZone())->forRestaurant($this->rid)->all('orden ASC'),
            'temas'    => Dashboard::temasDisponibles(),
            'smtp'     => [
                'host'      => (string)Setting::get('smtp_host', '', $this->rid),
                'puerto'    => (string)Setting::get('smtp_puerto', '587', $this->rid),
                'usuario'   => (string)Setting::get('smtp_usuario', '', $this->rid),
                'seguridad' => (string)Setting::get('smtp_seguridad', 'tls', $this->rid),
                'desde'     => (string)Setting::get('smtp_desde', '', $this->rid),
                'nombre'    => (string)Setting::get('smtp_nombre', (string)$this->r['nombre'], $this->rid),
                'tiene_clave' => Setting::get('smtp_clave', '', $this->rid) !== '',
            ],
            'impresora' => [
                'ancho'      => (string)Setting::get('impresora_ancho', '80', $this->rid),
                'copias'     => (int)Setting::get('impresora_copias', 1, $this->rid),
                'auto'       => (int)Setting::get('impresora_auto', 0, $this->rid),
                'encabezado' => (string)Setting::get('impresora_encabezado', '', $this->rid),
            ],
        ]);
    }

    // ---------------------------------------------------------------- general
    public function guardar(): void
    {
        $this->exigir('config');
        $modos = array_values(array_intersect(
            array_map('strval', Request::arr('modos_pedido')),
            ['consulta', 'mesa', 'llevar', 'delivery', 'whatsapp']
        ));
        if (!$modos) $modos = ['consulta'];
        $pagos = array_values(array_intersect(
            array_map('strval', Request::arr('metodos_pago')),
            ['efectivo', 'tarjeta', 'transferencia', 'link']
        ));
        $idiomas = array_values(array_intersect(array_map('strval', Request::arr('idiomas')), ['es', 'en']));
        if (!$idiomas) $idiomas = ['es'];

        $propinas = [];
        foreach (array_slice(Request::arr('propinas'), 0, 4) as $p) {
            $v = (int)$p;
            if ($v >= 0 && $v <= 100) $propinas[] = $v;
        }
        if (!$propinas) $propinas = [0, 10, 15];

        $datos = [
            'nombre'          => Request::str('nombre', '', 120),
            'eslogan'         => Request::str('eslogan', '', 180),
            'descripcion'     => Request::str('descripcion', '', 1200),
            'telefono'        => Request::str('telefono', '', 30),
            'whatsapp'        => preg_replace('/\D/', '', Request::str('whatsapp', '', 30)) ?? '',
            'email'           => Request::email('email'),
            'direccion'       => Request::str('direccion', '', 255),
            'mapa_lat'        => Request::float('mapa_lat') !== 0.0 ? Request::float('mapa_lat') : null,
            'mapa_lng'        => Request::float('mapa_lng') !== 0.0 ? Request::float('mapa_lng') : null,
            'facebook'        => $this->urlValida(Request::str('facebook', '', 190)),
            'instagram'       => $this->urlValida(Request::str('instagram', '', 190)),
            'tiktok'          => $this->urlValida(Request::str('tiktok', '', 190)),
            'google_reviews'  => $this->urlValida(Request::str('google_reviews', '', 255)),
            'link_pago'       => $this->urlValida(Request::str('link_pago', '', 255)),
            'datos_bancarios' => Request::str('datos_bancarios', '', 800),
            'modos_pedido'    => implode(',', $modos),
            'metodos_pago'    => implode(',', $pagos),
            'idioma'          => Request::enum('idioma', ['es', 'en'], 'es'),
            'idiomas'         => implode(',', $idiomas),
            'moneda'          => Request::str('moneda', 'GTQ', 6),
            'simbolo'         => Request::str('simbolo', 'Q', 4) ?: 'Q',
            'impuesto_pct'    => max(0, min(50, Request::float('impuesto_pct'))),
            'impuesto_incluido' => Request::bool('impuesto_incluido', true) ? 1 : 0,
            'propina_sugerida'=> json_encode($propinas),
            'abierto_modo'    => Request::enum('abierto_modo', ['auto', 'abierto', 'cerrado'], 'auto'),
            'mensaje_bienvenida' => Request::str('mensaje_bienvenida', '', 255),
            'mensaje_pie'     => Request::str('mensaje_pie', '', 255),
            'seo_title'       => Request::str('seo_title', '', 190),
            'seo_desc'        => Request::str('seo_desc', '', 255),
            'tiempo_prep_min' => max(1, min(180, Request::int('tiempo_prep_min', 20))),
            'pedido_minimo'   => max(0, Request::float('pedido_minimo')),
            'notas_activas'   => Request::bool('notas_activas', true) ? 1 : 0,
            'actualizado'     => date('Y-m-d H:i:s'),
        ];
        if ($datos['nombre'] === '') {
            flash('error', 'El nombre del restaurante es obligatorio.');
            $this->back('panel/configuracion');
        }

        (new Restaurant())->updateById($this->rid, $datos);
        Audit::diff('config', 'restaurants', $this->rid, $this->r, $datos);
        flash('exito', 'Configuración guardada.');
        $this->back('panel/configuracion');
    }

    // ---------------------------------------------------------------- marca
    public function marca(): void
    {
        $this->exigir('config');
        $datos = [
            'tema'           => Request::enum('tema', array_keys(Dashboard::temasDisponibles()), 'negro-oro'),
            'tipografia'     => Request::enum('tipografia', ['clasica', 'moderna', 'editorial'], 'clasica'),
            'color_primario' => $this->color(Request::str('color_primario', '', 9), (string)$this->r['color_primario']),
            'color_fondo'    => $this->color(Request::str('color_fondo', '', 9), (string)$this->r['color_fondo']),
            'actualizado'    => date('Y-m-d H:i:s'),
        ];

        foreach ([
            'logo'     => ['logos', 700, 700, 88],
            'portada'  => ['portadas', 1900, 1150, 82],
            'og_image' => ['og', 1200, 630, 84],
        ] as $campo => $cfg) {
            $f = Request::file($campo);
            if ($f) {
                [$ok, $res] = Image::upload($f, $cfg[0] . '/' . $this->rid, $cfg[1], $cfg[2], $cfg[3]);
                if ($ok) {
                    Image::delete((string)($this->r[$campo] ?? ''));
                    $datos[$campo] = $res;
                } else {
                    flash('error', $res);
                }
            } elseif (Request::bool('quitar_' . $campo)) {
                Image::delete((string)($this->r[$campo] ?? ''));
                $datos[$campo] = '';
            }
        }

        (new Restaurant())->updateById($this->rid, $datos);

        // Regenera los iconos de la aplicación instalable a partir del logo
        $logo = $datos['logo'] ?? (string)($this->r['logo'] ?? '');
        if ($logo !== '') {
            @array_map('unlink', glob(MG_ROOT . '/storage/cache/icono-*.png') ?: []);
            try { Image::generarIconosPwa($logo, $this->rid, $datos['color_fondo']); } catch (\Throwable $e) {}
        }

        Audit::log('config.marca', 'restaurants', $this->rid, null, $datos);
        flash('exito', 'Identidad visual actualizada. Tu menú ya luce diferente.');
        $this->back('panel/configuracion');
    }

    // ---------------------------------------------------------------- horarios
    public function horarios(): void
    {
        $this->exigir('config');
        $abre = Request::arr('abre');
        $cierra = Request::arr('cierra');
        $cerrado = array_map('intval', Request::arr('cerrado'));
        for ($d = 0; $d <= 6; $d++) {
            DB::upsert('schedules', [
                'restaurant_id' => $this->rid,
                'dia'           => $d,
                'abre'          => $this->hora((string)($abre[$d] ?? '08:00')) ?? '08:00:00',
                'cierra'        => $this->hora((string)($cierra[$d] ?? '22:00')) ?? '22:00:00',
                'cerrado'       => in_array($d, $cerrado, true) ? 1 : 0,
            ], ['abre', 'cierra', 'cerrado']);
        }
        Audit::log('config.horarios', 'schedules', $this->rid);
        flash('exito', 'Horarios actualizados.');
        $this->back('panel/configuracion');
    }

    // ---------------------------------------------------------------- entrega
    public function entrega(): void
    {
        $this->exigir('config');
        $m = (new DeliveryZone())->forRestaurant($this->rid);
        $accion = Request::str('accion', 'guardar', 20);

        if ($accion === 'borrar') {
            $id = Request::int('id');
            $m->findOrFail($id);
            $m->deleteById($id);
            $this->ok([], 'Zona eliminada');
        }

        $id = Request::int('id');
        $datos = [
            'nombre'     => Request::str('nombre', '', 120),
            'costo'      => round(max(0, Request::float('costo')), 2),
            'minimo'     => round(max(0, Request::float('minimo')), 2),
            'tiempo_min' => max(0, min(240, Request::int('tiempo_min', 30))),
            'activo'     => Request::bool('activo', true) ? 1 : 0,
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre de la zona.');

        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            $this->ok([], 'Zona actualizada');
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $this->ok(['id' => $m->create($datos)], 'Zona de entrega creada');
    }

    // ---------------------------------------------------------------- correo
    public function smtp(): void
    {
        $this->exigir('config');
        $pares = [
            'smtp_host'      => Request::str('smtp_host', '', 190),
            'smtp_puerto'    => (string)max(1, min(65535, Request::int('smtp_puerto', 587))),
            'smtp_usuario'   => Request::str('smtp_usuario', '', 190),
            'smtp_seguridad' => Request::enum('smtp_seguridad', ['tls', 'ssl', ''], 'tls'),
            'smtp_desde'     => Request::email('smtp_desde'),
            'smtp_nombre'    => Request::str('smtp_nombre', '', 120),
        ];
        $clave = (string)Request::input('smtp_clave', '');
        if ($clave !== '') $pares['smtp_clave'] = $clave;

        // Impresora
        $pares['impresora_ancho'] = Request::enum('impresora_ancho', ['58', '80'], '80');
        $pares['impresora_copias'] = (string)max(1, min(4, Request::int('impresora_copias', 1)));
        $pares['impresora_auto'] = Request::bool('impresora_auto') ? '1' : '0';
        $pares['impresora_encabezado'] = Request::str('impresora_encabezado', '', 190);

        Setting::setMany($pares, $this->rid);
        Audit::log('config.correo', 'restaurant_settings', $this->rid);
        flash('exito', 'Configuración de correo e impresión guardada.');
        $this->back('panel/configuracion');
    }

    public function probarCorreo(): void
    {
        $this->exigir('config');
        $para = Request::email('para');
        if ($para === '') $this->fail('Escribe un correo válido para la prueba.');
        $ok = Mailer::send(
            $para,
            'Prueba de correo · ' . (string)$this->r['nombre'],
            '<p>¡Funciona! Este es un correo de prueba enviado desde el panel de <strong>'
            . e((string)$this->r['nombre']) . '</strong>.</p>'
            . '<p>Si lo estás leyendo, tu configuración SMTP quedó correcta.</p>',
            $this->rid
        );
        if (!$ok) $this->fail('No se pudo enviar. Revisa el servidor, el puerto y las credenciales.');
        $this->ok([], 'Correo de prueba enviado a ' . $para);
    }

    // ----------------------------------------------------------------
    private function color(string $v, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : $fallback;
    }

    private function hora(string $v): ?string
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v)) {
            return strlen($v) === 5 ? $v . ':00' : $v;
        }
        return null;
    }

    private function urlValida(string $v): string
    {
        if ($v === '') return '';
        if (!preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
        return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 255) : '';
    }
}
