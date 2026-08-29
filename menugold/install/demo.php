<?php
/**
 * MenúGold · Generador de datos de demostración
 *
 * Crea dos restaurantes completos para probar el sistema y comprobar
 * el aislamiento de datos entre negocios:
 *   · La Terraza Gold  (restaurante de alta cocina, demo principal)
 *   · Café Central     (cafetería, segundo restaurante)
 *
 * Se ejecuta desde el instalador o desde la consola:
 *   php install/demo.php
 */
declare(strict_types=1);

if (!defined('MG_ROOT')) {
    define('MG_ROOT', dirname(__DIR__));
    require MG_ROOT . '/app/Core/Autoloader.php';
    \MenuGold\Core\Autoloader::register();
    require MG_ROOT . '/app/Core/helpers.php';
    \MenuGold\Core\App::boot(MG_ROOT);
}

use MenuGold\Core\DB;
use MenuGold\Core\Security;

final class DemoSeeder
{
    /** Paletas para las fotos generadas (fondo, acento). */
    private const PALETAS = [
        ['#2A1F16', '#D4AF37'], ['#1C2B24', '#C9A961'], ['#2B1A1E', '#D9AE63'],
        ['#1A2233', '#D2B368'], ['#2E2119', '#C98A5A'], ['#232323', '#BFAF8C'],
    ];

    public function run(bool $silencioso = false): array
    {
        $log = [];
        DB::ejecutar('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['order_events','order_items','orders','waiter_calls','product_modifiers','modifier_options',
                  'modifier_groups','products','categories','promotions','coupons','customers','tables','zones',
                  'delivery_zones','schedules','restaurant_settings','users','restaurants','contact_messages'] as $t) {
            try { DB::ejecutar('DELETE FROM `' . $t . '` WHERE 1'); } catch (\Throwable $e) {}
        }
        DB::ejecutar('SET FOREIGN_KEY_CHECKS = 1');

        $planes = DB::pairs('SELECT slug, id FROM plans');
        $log[] = $this->superadmin();
        $log[] = $this->terraza((int)($planes['premium'] ?? 3));
        $log[] = $this->cafe((int)($planes['pro'] ?? 2));
        if (!$silencioso) foreach ($log as $l) echo $l . "\n";
        return $log;
    }

    // =================================================================
    private function superadmin(): string
    {
        DB::insert('users', [
            'restaurant_id' => null,
            'nombre'        => 'Administrador de plataforma',
            'email'         => 'admin@plataforma.gt',
            'usuario'       => 'superadmin',
            'password_hash' => Security::hashPassword('Admin2026!'),
            'rol'           => 'superadmin',
            'activo'        => 1,
            'onboarding'    => 1,
            'creado'        => date('Y-m-d H:i:s'),
        ]);
        return 'Superadministrador creado: admin@plataforma.gt';
    }

    // =================================================================
    private function terraza(int $planId): string
    {
        $rid = DB::insert('restaurants', [
            'slug' => 'la-terraza-gold', 'nombre' => 'La Terraza Gold',
            'eslogan' => 'Cocina de autor · Antigua Guatemala',
            'descripcion' => 'Una terraza con vista al volcán donde la cocina guatemalteca se sirve con técnica contemporánea. Producto local, fuego vivo y una carta que cambia con la estación.',
            'plan_id' => $planId, 'estado' => 'activo',
            'vence_el' => date('Y-m-d', strtotime('+11 months')),
            'tema' => 'negro-oro', 'color_primario' => '#D4AF37', 'color_fondo' => '#141414',
            'tipografia' => 'clasica', 'moneda' => 'GTQ', 'simbolo' => 'Q',
            'impuesto_pct' => 12.00, 'impuesto_incluido' => 1,
            'propina_sugerida' => '[0,10,15,20]',
            'telefono' => '+502 7832 4500', 'whatsapp' => '50278324500',
            'email' => 'reservas@laterrazagold.gt',
            'direccion' => '5a Avenida Norte #12, Antigua Guatemala',
            'mapa_lat' => 14.5619000, 'mapa_lng' => -90.7343000,
            'instagram' => 'https://instagram.com/laterrazagold',
            'facebook' => 'https://facebook.com/laterrazagold',
            'google_reviews' => 'https://g.page/r/laterrazagold/review',
            'datos_bancarios' => "Banco Industrial\nCuenta monetaria 123-456789-0\nLa Terraza Gold, S.A.\nNIT 1234567-8",
            'modos_pedido' => 'consulta,mesa,llevar,delivery',
            'metodos_pago' => 'efectivo,tarjeta,transferencia',
            'idioma' => 'es', 'idiomas' => 'es,en',
            'abierto_modo' => 'auto',
            'mensaje_bienvenida' => 'Bienvenido a nuestra mesa. Tómate tu tiempo: aquí todo se cocina al momento.',
            'mensaje_pie' => 'Gracias por acompañarnos. Que vuelvas pronto.',
            'seo_title' => 'La Terraza Gold · Menú de alta cocina en Antigua Guatemala',
            'seo_desc' => 'Descubre la carta de La Terraza Gold: cocina de autor, fuego vivo y producto local en el corazón de Antigua. Pide desde tu mesa escaneando el QR.',
            'tiempo_prep_min' => 22, 'pedido_minimo' => 0, 'notas_activas' => 1, 'demo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);

        DB::update('restaurants', [
            'logo'     => $this->logo('la-terraza-gold', 'La Terraza Gold', '#141414', '#D4AF37'),
            'portada'  => $this->portada('la-terraza-gold', '#3A2C1C', '#D4AF37'),
            'og_image' => $this->portada('la-terraza-gold', '#3A2C1C', '#D4AF37'),
        ], 'id = :i', ['i' => $rid]);

        // Horarios
        foreach ([0,1,2,3,4,5,6] as $d) {
            DB::insert('schedules', [
                'restaurant_id' => $rid, 'dia' => $d,
                'abre' => $d === 1 ? '12:00:00' : '07:00:00',
                'cierra' => in_array($d, [5,6], true) ? '23:30:00' : '22:00:00',
                'cerrado' => 0,
            ]);
        }

        // Usuarios
        $dueno = DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Mariana Solís', 'email' => 'dueno@laterraza.gt',
            'usuario' => 'mariana', 'password_hash' => Security::hashPassword('Terraza2026!'),
            'rol' => 'dueno', 'telefono' => '+502 5544 1122', 'activo' => 1, 'onboarding' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Cocina · Estación caliente', 'email' => 'cocina@laterraza.gt',
            'usuario' => 'cocina1', 'password_hash' => Security::hashPassword('Cocina2026!'),
            'rol' => 'cocina', 'activo' => 1, 'onboarding' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);
        $mesero1 = DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Diego Ramírez', 'email' => 'mesero1@laterraza.gt',
            'usuario' => 'mesero1', 'password_hash' => Security::hashPassword('Mesero2026!'),
            'rol' => 'mesero', 'activo' => 1, 'onboarding' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);
        $mesero2 = DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Lucía Pérez', 'email' => 'mesero2@laterraza.gt',
            'usuario' => 'mesero2', 'password_hash' => Security::hashPassword('Mesero2026!'),
            'rol' => 'mesero', 'activo' => 1, 'onboarding' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);

        // Zonas y mesas
        $zTerraza = DB::insert('zones', ['restaurant_id' => $rid, 'nombre' => 'Terraza', 'orden' => 0]);
        $zSalon   = DB::insert('zones', ['restaurant_id' => $rid, 'nombre' => 'Salón principal', 'orden' => 1]);
        $zBarra   = DB::insert('zones', ['restaurant_id' => $rid, 'nombre' => 'Barra', 'orden' => 2]);
        for ($i = 1; $i <= 12; $i++) {
            DB::insert('tables', [
                'restaurant_id' => $rid,
                'zone_id' => $i <= 5 ? $zTerraza : ($i <= 10 ? $zSalon : $zBarra),
                'nombre' => 'Mesa ' . $i,
                'capacidad' => $i <= 5 ? 4 : ($i <= 10 ? 6 : 2),
                'estado' => 'libre', 'orden' => $i, 'activo' => 1,
                'creado' => date('Y-m-d H:i:s'),
            ]);
        }

        // Zonas de entrega
        foreach ([
            ['Centro de Antigua', 20.00, 100.00, 25],
            ['San Pedro El Panorama', 35.00, 150.00, 40],
            ['Ciudad Vieja', 45.00, 200.00, 50],
        ] as $i => $z) {
            DB::insert('delivery_zones', [
                'restaurant_id' => $rid, 'nombre' => $z[0], 'costo' => $z[1],
                'minimo' => $z[2], 'tiempo_min' => $z[3], 'activo' => 1, 'orden' => $i,
            ]);
        }

        // ------------------------------------------------ modificadores
        $gTermino = DB::insert('modifier_groups', [
            'restaurant_id' => $rid, 'nombre' => 'Término de la carne', 'nombre_en' => 'Cooking preference',
            'tipo' => 'unico', 'obligatorio' => 1, 'min_sel' => 1, 'max_sel' => 1, 'orden' => 0, 'activo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        foreach ([['Término medio', 0, 1], ['Tres cuartos', 0, 0], ['Bien cocido', 0, 0], ['Rojo inglés', 0, 0]] as $i => $o) {
            DB::insert('modifier_options', [
                'group_id' => $gTermino, 'restaurant_id' => $rid, 'nombre' => $o[0],
                'precio_extra' => $o[1], 'orden' => $i, 'activo' => 1, 'predeterminado' => $o[2],
            ]);
        }

        $gTamano = DB::insert('modifier_groups', [
            'restaurant_id' => $rid, 'nombre' => 'Tamaño', 'nombre_en' => 'Size',
            'tipo' => 'unico', 'obligatorio' => 1, 'min_sel' => 1, 'max_sel' => 1, 'orden' => 1, 'activo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        foreach ([['Individual', 0, 1], ['Para compartir', 65, 0]] as $i => $o) {
            DB::insert('modifier_options', [
                'group_id' => $gTamano, 'restaurant_id' => $rid, 'nombre' => $o[0],
                'precio_extra' => $o[1], 'orden' => $i, 'activo' => 1, 'predeterminado' => $o[2],
            ]);
        }

        $gExtras = DB::insert('modifier_groups', [
            'restaurant_id' => $rid, 'nombre' => 'Extras', 'nombre_en' => 'Add-ons',
            'tipo' => 'multiple', 'obligatorio' => 0, 'min_sel' => 0, 'max_sel' => 4, 'orden' => 2, 'activo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        foreach ([['Queso de Zacapa', 22], ['Aguacate hass', 18], ['Tocino artesanal', 25], ['Huevo de campo', 15], ['Chile cobanero', 8]] as $i => $o) {
            DB::insert('modifier_options', [
                'group_id' => $gExtras, 'restaurant_id' => $rid, 'nombre' => $o[0],
                'precio_extra' => $o[1], 'orden' => $i, 'activo' => 1,
            ]);
        }

        $gQuitar = DB::insert('modifier_groups', [
            'restaurant_id' => $rid, 'nombre' => 'Quitar ingredientes', 'nombre_en' => 'Remove',
            'tipo' => 'multiple', 'obligatorio' => 0, 'min_sel' => 0, 'max_sel' => 5, 'orden' => 3, 'activo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        foreach (['Sin cebolla', 'Sin cilantro', 'Sin picante', 'Sin lácteos', 'Sin ajo'] as $i => $o) {
            DB::insert('modifier_options', [
                'group_id' => $gQuitar, 'restaurant_id' => $rid, 'nombre' => $o,
                'precio_extra' => 0, 'orden' => $i, 'activo' => 1,
            ]);
        }

        $gLeche = DB::insert('modifier_groups', [
            'restaurant_id' => $rid, 'nombre' => 'Tipo de leche', 'nombre_en' => 'Milk',
            'tipo' => 'unico', 'obligatorio' => 0, 'min_sel' => 0, 'max_sel' => 1, 'orden' => 4, 'activo' => 1,
            'creado' => date('Y-m-d H:i:s'),
        ]);
        foreach ([['Entera', 0, 1], ['Deslactosada', 6, 0], ['De almendra', 12, 0], ['De avena', 12, 0]] as $i => $o) {
            DB::insert('modifier_options', [
                'group_id' => $gLeche, 'restaurant_id' => $rid, 'nombre' => $o[0],
                'precio_extra' => $o[1], 'orden' => $i, 'activo' => 1, 'predeterminado' => $o[2],
            ]);
        }

        // ------------------------------------------------ categorías y platillos
        $menu = $this->cartaTerraza();
        $catIds = [];
        $prodIds = [];
        $orden = 0;
        foreach ($menu as $cat) {
            $cid = DB::insert('categories', [
                'restaurant_id' => $rid, 'nombre' => $cat['nombre'], 'nombre_en' => $cat['en'],
                'descripcion' => $cat['desc'], 'icono' => $cat['icono'],
                'orden' => $orden++, 'activo' => 1,
                'hora_inicio' => $cat['desde'] ?? null, 'hora_fin' => $cat['hasta'] ?? null,
                'creado' => date('Y-m-d H:i:s'),
            ]);
            $catIds[$cat['nombre']] = $cid;
            $po = 0;
            foreach ($cat['platillos'] as $p) {
                $img = $this->foto($p[0], $po + $orden);
                $pid = DB::insert('products', [
                    'restaurant_id' => $rid, 'category_id' => $cid,
                    'nombre' => $p[0], 'nombre_en' => $p[5] ?? '',
                    'descripcion' => $p[1], 'precio' => $p[2],
                    'precio_promo' => $p[6] ?? null,
                    'imagen' => $img, 'orden' => $po++, 'activo' => 1,
                    'agotado' => !empty($p[7]) ? 1 : 0,
                    'destacado' => !empty($p[8]) ? 1 : 0,
                    'tiempo_prep' => $p[3], 'calorias' => $p[9] ?? null,
                    'etiquetas' => $p[4], 'alergenos' => $p[10] ?? '',
                    'estacion' => $cat['estacion'],
                    'vendidos' => random_int(4, 180),
                    'creado' => date('Y-m-d H:i:s'),
                ]);
                $prodIds[] = ['id' => $pid, 'nombre' => $p[0], 'precio' => (float)$p[2], 'estacion' => $cat['estacion']];

                // Modificadores según el tipo de platillo
                $grupos = [];
                if (strpos($cat['nombre'], 'Fuertes') !== false) { $grupos = [$gTermino, $gExtras, $gQuitar]; }
                elseif (strpos($cat['nombre'], 'Entradas') !== false) { $grupos = [$gTamano, $gQuitar]; }
                elseif (strpos($cat['nombre'], 'Bebidas') !== false || strpos($cat['nombre'], 'Café') !== false) { $grupos = [$gLeche]; }
                elseif (strpos($cat['nombre'], 'Desayunos') !== false) { $grupos = [$gExtras, $gQuitar]; }
                foreach ($grupos as $k => $g) {
                    DB::insert('product_modifiers', ['product_id' => $pid, 'group_id' => $g, 'orden' => $k]);
                }
            }
        }

        // ------------------------------------------------ promociones y cupones
        DB::insert('promotions', [
            'restaurant_id' => $rid, 'nombre' => 'Martes de 2x1 en cócteles',
            'descripcion' => 'Pide un cóctel de autor y el segundo va por nuestra cuenta.',
            'tipo' => '2x1', 'valor' => 0, 'dias' => '2',
            'desde' => date('Y-m-d', strtotime('-20 days')), 'hasta' => date('Y-m-d', strtotime('+60 days')),
            'activo' => 1, 'orden' => 0, 'creado' => date('Y-m-d H:i:s'),
        ]);
        DB::insert('promotions', [
            'restaurant_id' => $rid, 'nombre' => 'Menú del día · 20% en entradas',
            'descripcion' => 'De lunes a viernes antes de las 3:00 pm.',
            'tipo' => 'descuento', 'valor' => 20, 'dias' => '1,2,3,4,5',
            'category_ids' => (string)($catIds['Entradas'] ?? ''),
            'desde' => date('Y-m-d', strtotime('-10 days')), 'hasta' => date('Y-m-d', strtotime('+90 days')),
            'activo' => 1, 'orden' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);
        DB::insert('promotions', [
            'restaurant_id' => $rid, 'nombre' => 'Postre de cortesía',
            'descripcion' => 'En pedidos mayores a Q400, el postre va incluido.',
            'tipo' => 'combo', 'valor' => 0,
            'desde' => date('Y-m-d', strtotime('-5 days')), 'hasta' => date('Y-m-d', strtotime('+45 days')),
            'activo' => 1, 'orden' => 2, 'creado' => date('Y-m-d H:i:s'),
        ]);

        DB::insert('coupons', [
            'restaurant_id' => $rid, 'codigo' => 'BIENVENIDO10',
            'descripcion' => '10% de descuento en tu primer pedido',
            'tipo' => 'porcentaje', 'valor' => 10, 'min_compra' => 100, 'usos_max' => 0, 'usos' => 7,
            'desde' => date('Y-m-d', strtotime('-30 days')), 'hasta' => date('Y-m-d', strtotime('+120 days')),
            'activo' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);
        DB::insert('coupons', [
            'restaurant_id' => $rid, 'codigo' => 'ENVIOGRATIS',
            'descripcion' => 'Envío gratis en pedidos a domicilio',
            'tipo' => 'envio_gratis', 'valor' => 0, 'min_compra' => 250, 'usos_max' => 100, 'usos' => 12,
            'desde' => date('Y-m-d', strtotime('-15 days')), 'hasta' => date('Y-m-d', strtotime('+60 days')),
            'activo' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);

        // ------------------------------------------------ clientes e historial
        $clientes = [];
        foreach ([
            ['Ana Gutiérrez', '50255512340', 'Calle del Arco 22, Antigua'],
            ['Carlos Méndez', '50255512341', '3a Calle Poniente 8, Antigua'],
            ['Sofía Ramírez', '50255512342', 'Residencial El Panorama, casa 14'],
            ['Julio Estrada', '50255512343', '6a Avenida Sur 40, Antigua'],
            ['Paola Cifuentes', '50255512344', 'Ciudad Vieja, km 4.5'],
        ] as $c) {
            $clientes[] = DB::insert('customers', [
                'restaurant_id' => $rid, 'nombre' => $c[0], 'telefono' => $c[1],
                'direccion' => $c[2], 'puntos' => random_int(0, 340),
                'creado' => date('Y-m-d H:i:s', strtotime('-' . random_int(30, 200) . ' days')),
            ]);
        }

        $this->historial($rid, $prodIds, $clientes, [$mesero1, $mesero2], 40);

        return 'Restaurante demo creado: La Terraza Gold (' . count($prodIds) . ' platillos, 12 mesas)';
    }

    // =================================================================
    private function cafe(int $planId): string
    {
        $rid = DB::insert('restaurants', [
            'slug' => 'cafe-central', 'nombre' => 'Café Central',
            'eslogan' => 'Tostaduría & panadería · Ciudad de Guatemala',
            'descripcion' => 'Café de origen guatemalteco tostado cada semana, pan de masa madre y desayunos todo el día.',
            'plan_id' => $planId, 'estado' => 'activo',
            'vence_el' => date('Y-m-d', strtotime('+5 months')),
            'tema' => 'marfil', 'color_primario' => '#8C7A3F', 'color_fondo' => '#F7F3EA',
            'tipografia' => 'moderna', 'moneda' => 'GTQ', 'simbolo' => 'Q',
            'impuesto_pct' => 12.00, 'impuesto_incluido' => 1,
            'propina_sugerida' => '[0,10,15]',
            'telefono' => '+502 2360 8877', 'whatsapp' => '50223608877',
            'email' => 'hola@cafecentral.gt',
            'direccion' => 'Zona 4, 4 Grados Norte, Ciudad de Guatemala',
            'instagram' => 'https://instagram.com/cafecentralgt',
            'datos_bancarios' => "Banrural\nCuenta monetaria 987-654321-0\nCafé Central",
            'modos_pedido' => 'consulta,mesa,llevar,whatsapp',
            'metodos_pago' => 'efectivo,tarjeta',
            'idioma' => 'es', 'idiomas' => 'es',
            'abierto_modo' => 'auto',
            'mensaje_bienvenida' => 'Buenos días. El café de hoy es un Huehuetenango lavado.',
            'seo_title' => 'Café Central · Café de origen y panadería en Ciudad de Guatemala',
            'seo_desc' => 'Café de origen guatemalteco, pan de masa madre y desayunos todo el día en 4 Grados Norte.',
            'tiempo_prep_min' => 10, 'notas_activas' => 1, 'demo' => 0,
            'creado' => date('Y-m-d H:i:s'),
        ]);

        DB::update('restaurants', [
            'logo'     => $this->logo('cafe-central', 'Café Central', '#F7F3EA', '#8C7A3F'),
            'portada'  => $this->portada('cafe-central', '#C9BFA4', '#8C7A3F'),
            'og_image' => $this->portada('cafe-central', '#C9BFA4', '#8C7A3F'),
        ], 'id = :i', ['i' => $rid]);

        foreach ([0,1,2,3,4,5,6] as $d) {
            DB::insert('schedules', [
                'restaurant_id' => $rid, 'dia' => $d,
                'abre' => '06:30:00', 'cierra' => $d === 0 ? '15:00:00' : '20:00:00', 'cerrado' => 0,
            ]);
        }

        DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Roberto Ixcot', 'email' => 'dueno@cafecentral.gt',
            'usuario' => 'roberto', 'password_hash' => Security::hashPassword('Central2026!'),
            'rol' => 'dueno', 'activo' => 1, 'onboarding' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);
        $meseroC = DB::insert('users', [
            'restaurant_id' => $rid, 'nombre' => 'Karla Xuyá', 'email' => 'barra@cafecentral.gt',
            'usuario' => 'karla', 'password_hash' => Security::hashPassword('Central2026!'),
            'rol' => 'mesero', 'activo' => 1, 'onboarding' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);

        $zona = DB::insert('zones', ['restaurant_id' => $rid, 'nombre' => 'Salón', 'orden' => 0]);
        for ($i = 1; $i <= 6; $i++) {
            DB::insert('tables', [
                'restaurant_id' => $rid, 'zone_id' => $zona, 'nombre' => 'Mesa ' . $i,
                'capacidad' => 2, 'estado' => 'libre', 'orden' => $i, 'activo' => 1,
                'creado' => date('Y-m-d H:i:s'),
            ]);
        }

        $carta = [
            ['Cafetería', 'utensils', 'bar', [
                ['Espresso doble', 'Huehuetenango lavado, notas de panela y cacao.', 18, 3, 'popular'],
                ['Cappuccino', 'Leche texturizada y arte latte de la casa.', 26, 5, ''],
                ['Latte de vainilla', 'Vainilla natural de Alta Verapaz.', 30, 5, 'nuevo'],
                ['Cold brew 12 h', 'Extracción en frío, servido sobre hielo.', 28, 2, 'popular'],
                ['Chocolate de metate', 'Cacao guatemalteco molido en piedra.', 32, 6, ''],
            ]],
            ['Panadería', 'cake', 'postres', [
                ['Concha de masa madre', 'Fermentación de 24 horas, cubierta de vainilla.', 16, 1, 'popular'],
                ['Croissant de mantequilla', 'Hojaldre de 27 capas, horneado cada mañana.', 22, 1, ''],
                ['Pan de banano y nuez', 'Con plátano maduro de la costa sur.', 24, 1, 'sin_gluten'],
                ['Cardamomo roll', 'Nuestro pan más pedido los domingos.', 28, 1, 'nuevo,popular'],
            ]],
            ['Desayunos', 'chef', 'cocina', [
                ['Desayuno chapín', 'Huevos al gusto, frijol volteado, plátano y queso fresco.', 58, 12, 'popular'],
                ['Tostada de aguacate', 'Masa madre, aguacate hass, huevo pochado y chile cobanero.', 62, 10, 'vegetariano'],
                ['Granola de la casa', 'Yogurt natural, frutas de temporada y miel de abeja.', 48, 4, 'vegetariano'],
            ]],
        ];
        $orden = 0;
        foreach ($carta as $c) {
            $cid = DB::insert('categories', [
                'restaurant_id' => $rid, 'nombre' => $c[0], 'icono' => $c[1],
                'orden' => $orden++, 'activo' => 1, 'creado' => date('Y-m-d H:i:s'),
            ]);
            $po = 0;
            foreach ($c[3] as $p) {
                DB::insert('products', [
                    'restaurant_id' => $rid, 'category_id' => $cid,
                    'nombre' => $p[0], 'descripcion' => $p[1], 'precio' => $p[2],
                    'imagen' => $this->foto($p[0], $po + $orden + 10),
                    'orden' => $po++, 'activo' => 1, 'tiempo_prep' => $p[3],
                    'etiquetas' => $p[4], 'estacion' => $c[2],
                    'destacado' => $po <= 2 ? 1 : 0,
                    'vendidos' => random_int(10, 220),
                    'creado' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        DB::insert('coupons', [
            'restaurant_id' => $rid, 'codigo' => 'CAFE15', 'descripcion' => '15% en cafetería',
            'tipo' => 'porcentaje', 'valor' => 15, 'min_compra' => 50, 'usos_max' => 200, 'usos' => 31,
            'desde' => date('Y-m-d', strtotime('-10 days')), 'hasta' => date('Y-m-d', strtotime('+40 days')),
            'activo' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);

        $prods = DB::all('SELECT id, nombre, precio, estacion FROM products WHERE restaurant_id = :r', ['r' => $rid]);
        $this->historial($rid, $prods, [], [$meseroC], 18);

        return 'Segundo restaurante creado: Café Central (prueba de aislamiento de datos)';
    }

    // =================================================================
    /** Genera pedidos históricos repartidos en los últimos 30 días. */
    private function historial(int $rid, array $productos, array $clientes, array $meseros, int $cantidad): void
    {
        if (!$productos) return;
        $mesas = DB::all('SELECT id, nombre FROM tables WHERE restaurant_id = :r', ['r' => $rid]);
        $modos = ['mesa', 'mesa', 'mesa', 'llevar', 'delivery'];
        $pagos = ['efectivo', 'tarjeta', 'transferencia'];
        $rest  = DB::one('SELECT * FROM restaurants WHERE id = :r', ['r' => $rid]);

        for ($n = 0; $n < $cantidad; $n++) {
            $diasAtras = (int)floor(($n / max(1, $cantidad)) * 29);
            $hora = [8, 9, 12, 13, 13, 14, 19, 19, 20, 20, 21][random_int(0, 10)];
            $ts = strtotime("-{$diasAtras} days " . str_pad((string)$hora, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)random_int(0, 59), 2, '0', STR_PAD_LEFT) . ':00');
            $creado = date('Y-m-d H:i:s', $ts);
            $modo = $modos[array_rand($modos)];
            if (!in_array($modo, array_map('trim', explode(',', (string)$rest['modos_pedido'])), true)) $modo = 'mesa';
            $mesa = $modo === 'mesa' && $mesas ? $mesas[array_rand($mesas)] : null;

            $lineas = [];
            $subtotal = 0.0;
            $nItems = random_int(1, 4);
            $usados = [];
            for ($i = 0; $i < $nItems; $i++) {
                $p = $productos[array_rand($productos)];
                if (in_array((int)$p['id'], $usados, true)) continue;
                $usados[] = (int)$p['id'];
                $cant = random_int(1, 3);
                $sub = round((float)$p['precio'] * $cant, 2);
                $subtotal += $sub;
                $lineas[] = [$p, $cant, $sub];
            }
            if (!$lineas) continue;

            $envio = $modo === 'delivery' ? 20.00 : 0.00;
            $propina = round($subtotal * (random_int(0, 3) === 0 ? 0.10 : 0) , 2);
            $impuesto = round($subtotal - ($subtotal / 1.12), 2);
            $total = round($subtotal + $envio + $propina, 2);
            $anulado = random_int(1, 20) === 1;
            $mesero = $meseros ? $meseros[array_rand($meseros)] : null;
            $prep = random_int(9, 34);

            $orderId = DB::insert('orders', [
                'restaurant_id' => $rid,
                'codigo' => date('md', $ts) . '-' . str_pad((string)(1000 + $n), 4, '0', STR_PAD_LEFT),
                'table_id' => $mesa ? (int)$mesa['id'] : null,
                'mesa_nombre' => $mesa ? (string)$mesa['nombre'] : '',
                'modo' => $modo,
                'estado' => $anulado ? 'anulado' : 'pagado',
                'customer_id' => ($modo === 'delivery' && $clientes) ? $clientes[array_rand($clientes)] : null,
                'cliente_nombre' => $modo !== 'mesa' ? ['Ana Gutiérrez','Carlos Méndez','Sofía Ramírez','Julio Estrada'][random_int(0,3)] : '',
                'cliente_telefono' => $modo !== 'mesa' ? '5551' . random_int(1000, 9999) : '',
                'cliente_direccion' => $modo === 'delivery' ? 'Calle del Arco ' . random_int(1, 60) . ', Antigua' : '',
                'costo_envio' => $envio,
                'subtotal' => round($subtotal, 2),
                'descuento' => 0,
                'impuesto' => $impuesto,
                'propina' => $propina,
                'total' => $total,
                'metodo_pago' => $pagos[array_rand($pagos)],
                'motivo_anulacion' => $anulado ? 'El cliente cambió de opinión' : '',
                'user_id' => $mesero,
                'creado_por' => random_int(0, 1) ? 'cliente' : 'mesero',
                'minutos_prep' => $anulado ? null : $prep,
                'calificacion' => (!$anulado && random_int(0, 2) === 0) ? random_int(4, 5) : null,
                'creado' => $creado,
                'listo_en' => $anulado ? null : date('Y-m-d H:i:s', $ts + $prep * 60),
                'entregado_en' => $anulado ? null : date('Y-m-d H:i:s', $ts + ($prep + 4) * 60),
                'pagado_en' => $anulado ? null : date('Y-m-d H:i:s', $ts + ($prep + 40) * 60),
            ]);

            foreach ($lineas as $l) {
                DB::insert('order_items', [
                    'order_id' => $orderId, 'restaurant_id' => $rid,
                    'product_id' => (int)$l[0]['id'], 'nombre' => (string)$l[0]['nombre'],
                    'precio_unit' => (float)$l[0]['precio'], 'cantidad' => $l[1],
                    'modificadores' => '[]', 'subtotal' => $l[2],
                    'estacion' => (string)($l[0]['estacion'] ?? 'cocina'),
                    'estado' => $anulado ? 'anulado' : 'entregado',
                    'creado' => $creado,
                ]);
            }
            DB::insert('order_events', [
                'order_id' => $orderId, 'estado' => 'nuevo', 'usuario' => 'cliente', 'creado' => $creado,
            ]);
            if (!$anulado) {
                DB::insert('order_events', ['order_id' => $orderId, 'estado' => 'pagado', 'usuario' => 'sistema',
                    'creado' => date('Y-m-d H:i:s', $ts + ($prep + 40) * 60)]);
            }
        }

        // Actualiza el acumulado de los clientes
        foreach ($clientes as $cid) {
            $r = DB::one('SELECT COUNT(*) n, COALESCE(SUM(total),0) t, MAX(creado) u FROM orders WHERE customer_id = :c AND estado = "pagado"', ['c' => $cid]);
            DB::update('customers', [
                'pedidos' => (int)$r['n'], 'total_gastado' => (float)$r['t'], 'ultimo_pedido' => $r['u'],
            ], 'id = :c', ['c' => $cid]);
        }
    }

    // =================================================================
    /** Foto de demostración generada con GD: degradado elegante + inicial. */
    private function foto(string $nombre, int $semilla): string
    {
        if (!function_exists('imagecreatetruecolor')) return '';
        $dir = MG_ROOT . '/storage/uploads/demo';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $archivo = 'demo/' . str_slug($nombre) . '.jpg';
        $ruta = MG_ROOT . '/storage/uploads/' . $archivo;
        if (is_file($ruta)) return $archivo;

        $w = 800; $h = 600;
        $img = imagecreatetruecolor($w, $h);
        $pal = self::PALETAS[$semilla % count(self::PALETAS)];
        [$r1, $g1, $b1] = $this->rgb($pal[0]);
        [$r2, $g2, $b2] = $this->rgb($pal[1]);

        // Degradado diagonal
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img,
                (int)round($r1 + ($r1 * 0.5) * $t),
                (int)round($g1 + ($g1 * 0.5) * $t),
                (int)round($b1 + ($b1 * 0.5) * $t));
            imagefilledrectangle($img, 0, $y, $w, $y + 1, $c);
        }
        // Halo de luz
        $halo = imagecolorallocatealpha($img, $r2, $g2, $b2, 108);
        for ($i = 0; $i < 9; $i++) {
            imagefilledellipse($img, (int)($w * 0.72), (int)($h * 0.28), 420 - $i * 32, 420 - $i * 32, $halo);
        }
        // Marco dorado
        $oro = imagecolorallocate($img, $r2, $g2, $b2);
        imagesetthickness($img, 3);
        imagerectangle($img, 26, 26, $w - 27, $h - 27, $oro);
        // Monograma central grande
        $palabras = preg_split('/\\s+/u', trim($nombre)) ?: [];
        $ini = '';
        foreach ($palabras as $pal) {
            if (mb_strlen($ini) >= 2) break;
            $c = mb_substr($pal, 0, 1);
            if (preg_match('/\\p{L}/u', $c) && mb_strlen($pal) > 2) $ini .= mb_strtoupper($c);
        }
        if ($ini === '') $ini = mb_strtoupper(mb_substr($nombre, 0, 1));
        $ini = $this->ascii($ini);
        $tmp = imagecreatetruecolor(imagefontwidth(5) * strlen($ini) + 4, imagefontheight(5) + 4);
        imagefill($tmp, 0, 0, imagecolorallocate($tmp, $r1, $g1, $b1));
        imagestring($tmp, 5, 2, 2, $ini, $oro);
        $dw = (int)($w * 0.24 * strlen($ini)); $dh = (int)($h * 0.42);
        imagecopyresampled($img, $tmp, (int)(($w - $dw) / 2), (int)(($h - $dh) / 2), 0, 0, $dw, $dh, imagesx($tmp), imagesy($tmp));
        imagedestroy($tmp);

        imagejpeg($img, $ruta, 82);
        imagedestroy($img);
        @chmod($ruta, 0644);
        return $archivo;
    }


    /** Portada panorámica generada: degradado nocturno con luz dorada. */
    private function portada(string $slug, string $fondoHex, string $oroHex): string
    {
        if (!function_exists('imagecreatetruecolor')) return '';
        $archivo = 'demo/portada-' . $slug . '.jpg';
        $ruta = MG_ROOT . '/storage/uploads/' . $archivo;
        if (is_file($ruta)) return $archivo;
        $dir = dirname($ruta);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);

        $w = 1600; $h = 900;
        $img = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = $this->rgb($fondoHex);
        [$r2, $g2, $b2] = $this->rgb($oroHex);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img,
                (int)max(0, min(255, $r1 * (0.55 + 0.9 * $t))),
                (int)max(0, min(255, $g1 * (0.55 + 0.9 * $t))),
                (int)max(0, min(255, $b1 * (0.55 + 0.9 * $t))));
            imagefilledrectangle($img, 0, $y, $w, $y + 1, $c);
        }
        // Luz cálida en la esquina superior derecha
        $halo = imagecolorallocatealpha($img, $r2, $g2, $b2, 116);
        for ($i = 0; $i < 14; $i++) {
            imagefilledellipse($img, (int)($w * 0.76), (int)($h * 0.16), 1100 - $i * 62, 900 - $i * 52, $halo);
        }
        // Líneas finas de acento
        $oro = imagecolorallocatealpha($img, $r2, $g2, $b2, 92);
        imagesetthickness($img, 2);
        for ($i = 0; $i < 5; $i++) {
            imageline($img, 0, (int)($h * 0.72) + $i * 26, $w, (int)($h * 0.58) + $i * 26, $oro);
        }
        imagejpeg($img, $ruta, 84);
        imagedestroy($img);
        @chmod($ruta, 0644);
        return $archivo;
    }

    /** Logo circular con el monograma del restaurante. */
    private function logo(string $slug, string $nombre, string $fondoHex, string $oroHex): string
    {
        if (!function_exists('imagecreatetruecolor')) return '';
        $archivo = 'demo/logo-' . $slug . '.jpg';
        $ruta = MG_ROOT . '/storage/uploads/' . $archivo;
        if (is_file($ruta)) return $archivo;
        $dir = dirname($ruta);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);

        $s = 512;
        $img = imagecreatetruecolor($s, $s);
        [$r1, $g1, $b1] = $this->rgb($fondoHex);
        [$r2, $g2, $b2] = $this->rgb($oroHex);
        imagefill($img, 0, 0, imagecolorallocate($img, $r1, $g1, $b1));
        $oro = imagecolorallocate($img, $r2, $g2, $b2);
        imagesetthickness($img, 8);
        imageellipse($img, $s / 2, $s / 2, $s - 60, $s - 60, $oro);
        imagesetthickness($img, 3);
        imageellipse($img, $s / 2, $s / 2, $s - 92, $s - 92, $oro);

        $palabras = preg_split('/\s+/u', trim($nombre)) ?: [];
        $ini = '';
        foreach ($palabras as $pal) {
            if (mb_strlen($ini) >= 2) break;
            $c = mb_substr($pal, 0, 1);
            if (preg_match('/\p{L}/u', $c)) $ini .= mb_strtoupper($c);
        }
        $ini = $this->ascii($ini ?: 'M');
        $tmp = imagecreatetruecolor(imagefontwidth(5) * mb_strlen($ini) + 4, imagefontheight(5) + 4);
        imagefill($tmp, 0, 0, imagecolorallocate($tmp, $r1, $g1, $b1));
        imagestring($tmp, 5, 2, 2, $ini, imagecolorallocate($tmp, $r2, $g2, $b2));
        $dw = (int)($s * 0.44); $dh = (int)($s * 0.30);
        imagecopyresampled($img, $tmp, (int)(($s - $dw) / 2), (int)(($s - $dh) / 2), 0, 0, $dw, $dh, imagesx($tmp), imagesy($tmp));
        imagedestroy($tmp);

        imagejpeg($img, $ruta, 88);
        imagedestroy($img);
        @chmod($ruta, 0644);
        return $archivo;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [(int)hexdec(substr($hex,0,2)), (int)hexdec(substr($hex,2,2)), (int)hexdec(substr($hex,4,2))];
    }

    private function ascii(string $s): string
    {
        return (string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
    }

    // =================================================================
    /** Carta completa de La Terraza Gold: 6 categorías, 35 platillos. */
    private function cartaTerraza(): array
    {
        return [
            [
                'nombre' => 'Desayunos de la casa', 'en' => 'Breakfast', 'icono' => 'sun', 'estacion' => 'cocina',
                'desc' => 'Servidos hasta las 11:00 de la mañana',
                'desde' => '06:00:00', 'hasta' => '11:00:00',
                'platillos' => [
                    ['Huevos rancheros de la abuela', 'Huevos de campo sobre tortilla de maíz criollo, salsa de chile guaque y queso fresco de Zacapa.', 78.00, 14, 'popular', 'Grandma\'s ranch eggs', null, 0, 1, 520, 'huevo,lacteos'],
                    ['Plato chapín completo', 'Frijol volteado, plátano frito, crema, queso, huevos al gusto y tortillas hechas a mano.', 92.00, 16, 'popular', 'Full Guatemalan breakfast', null, 0, 0, 740, 'huevo,lacteos'],
                    ['Tostada de aguacate y salmón', 'Masa madre de la casa, aguacate hass, salmón curado y eneldo.', 118.00, 12, 'nuevo', 'Avocado & salmon toast', null, 0, 1, 480, 'gluten,pescado'],
                    ['Pancakes de banano y cardamomo', 'Tres pancakes esponjosos con miel de caña y nuez caramelizada.', 74.00, 15, 'vegetariano', 'Banana pancakes', null, 0, 0, 620, 'gluten,huevo,lacteos'],
                    ['Bowl de frutas y granola', 'Frutas de temporada, yogurt griego, granola artesanal y miel de abeja.', 68.00, 6, 'vegetariano,sin_gluten', 'Fruit & granola bowl', null, 0, 0, 340, 'lacteos,frutos secos'],
                ],
            ],
            [
                'nombre' => 'Entradas', 'en' => 'Starters', 'icono' => 'sparkles', 'estacion' => 'cocina',
                'desc' => 'Para empezar, algo que se comparte',
                'platillos' => [
                    ['Ceviche del chef', 'Corvina fresca del Pacífico, leche de tigre de chile cobanero, camote y cilantro criollo.', 145.00, 12, 'nuevo,popular', 'Chef\'s ceviche', null, 0, 1, 280, 'pescado'],
                    ['Tártar de atún aleta amarilla', 'Atún curado, aguacate, ajonjolí tostado y aceite de cilantro.', 165.00, 10, 'popular', 'Yellowfin tuna tartare', null, 0, 1, 310, 'pescado,sesamo'],
                    ['Carpaccio de res angus', 'Láminas finas, alcaparras, rúcula y lascas de parmesano curado 24 meses.', 138.00, 9, '', 'Angus beef carpaccio', null, 0, 0, 290, 'lacteos'],
                    ['Tostadas de tuétano', 'Tuétano asado al fuego, chimichurri de hierbas y sal de gusano.', 128.00, 18, 'nuevo', 'Bone marrow toast', null, 0, 0, 460, 'gluten'],
                    ['Croquetas de plátano y queso', 'Plátano macho relleno de queso de capas, sobre crema agria de la casa.', 88.00, 12, 'vegetariano,popular', 'Plantain croquettes', null, 0, 0, 420, 'gluten,lacteos,huevo'],
                    ['Hongos silvestres al ajillo', 'Hongos de temporada de San Juan Sacatepéquez, ajo confitado y perejil.', 96.00, 11, 'vegano', 'Wild mushrooms', null, 0, 0, 210, ''],
                    ['Ostras de la bahía', 'Media docena con mignonette de vinagre de jamaica.', 185.00, 8, '', 'Fresh oysters', null, 1, 0, 90, 'mariscos'],
                ],
            ],
            [
                'nombre' => 'Sopas y ensaladas', 'en' => 'Soups & salads', 'icono' => 'leaf', 'estacion' => 'cocina',
                'desc' => 'Frescura del huerto y caldos de fuego lento',
                'platillos' => [
                    ['Kak\'ik de pavo', 'Caldo ceremonial q\'eqchi\' de pavo criollo, achiote y chile cobanero. Nuestra receta de siempre.', 132.00, 20, 'popular,picante', 'Traditional turkey broth', null, 0, 1, 380, ''],
                    ['Crema de güisquil y cardamomo', 'Aterciopelada, con aceite de semilla de ayote tostada.', 78.00, 12, 'vegetariano', 'Chayote cream soup', null, 0, 0, 240, 'lacteos'],
                    ['Ensalada de la terraza', 'Hojas del huerto, tomate riñón, aguacate, semillas y vinagreta de limón persa.', 86.00, 8, 'vegano,sin_gluten', 'House garden salad', null, 0, 0, 190, ''],
                    ['Ensalada César con pollo al carbón', 'Lechuga romana, aderezo clásico, crotones de masa madre y parmesano.', 108.00, 12, 'popular', 'Caesar salad', null, 0, 0, 430, 'gluten,lacteos,huevo,pescado'],
                    ['Caprese de queso fresco', 'Tomates heirloom, queso fresco de Zacapa, albahaca y aceite de oliva.', 94.00, 7, 'vegetariano,sin_gluten', 'Caprese salad', null, 0, 0, 260, 'lacteos'],
                ],
            ],
            [
                'nombre' => 'Platos fuertes', 'en' => 'Main courses', 'icono' => 'fire', 'estacion' => 'cocina',
                'desc' => 'Cocinados a fuego vivo, como debe ser',
                'platillos' => [
                    ['Lomito Wellington', 'Res premium en hojaldre de mantequilla, duxelles de hongos y salsa de vino tinto. 350 g.', 325.00, 32, 'popular', 'Beef Wellington', 285.00, 0, 1, 890, 'gluten,huevo,lacteos'],
                    ['Cordero en cocción lenta', 'Ocho horas al horno, puré de camote y jugo de romero.', 298.00, 28, '', 'Slow-cooked lamb', null, 0, 1, 780, 'lacteos'],
                    ['Pollo al carbón con recado', 'Medio pollo criollo marinado 24 horas, recado rojo y verduras al fuego.', 178.00, 26, 'popular', 'Charcoal chicken', null, 0, 0, 690, ''],
                    ['Pescado del día a la talla', 'Según la pesca: abierto, untado de adobo y terminado a las brasas.', 265.00, 24, '', 'Catch of the day', null, 0, 0, 520, 'pescado'],
                    ['Costilla de cerdo glaseada', 'Glaseada con miel de caña y chile pasa, sobre puré de frijol.', 215.00, 30, 'picante', 'Glazed pork ribs', null, 0, 0, 830, ''],
                    ['Risotto de hongos y trufa', 'Arroz carnaroli, hongos silvestres y aceite de trufa negra.', 195.00, 25, 'vegetariano,popular', 'Truffle mushroom risotto', null, 0, 1, 610, 'lacteos'],
                    ['Pepián de res de la casa', 'El clásico guatemalteco con carne de res, arroz blanco y tortillas.', 168.00, 24, 'popular', 'Traditional beef pepián', null, 0, 0, 640, 'sesamo'],
                    ['Ravioles de ayote y salvia', 'Pasta fresca hecha en casa, mantequilla noisette y avellana.', 172.00, 20, 'vegetariano', 'Pumpkin ravioli', null, 0, 0, 570, 'gluten,huevo,lacteos,frutos secos'],
                    ['Camarones al ajillo', 'Camarón jumbo, ajo confitado, chile guaque y pan de la casa.', 245.00, 18, '', 'Garlic shrimp', null, 0, 0, 460, 'mariscos,gluten'],
                    ['Hamburguesa Gold', 'Blend de res 200 g, queso de capas, tocino artesanal y pan brioche.', 158.00, 18, 'popular', 'Gold burger', null, 0, 0, 920, 'gluten,lacteos,huevo'],
                ],
            ],
            [
                'nombre' => 'Postres', 'en' => 'Desserts', 'icono' => 'cake', 'estacion' => 'postres',
                'desc' => 'El final que se recuerda',
                'platillos' => [
                    ['Crème brûlée de vainilla', 'Vainilla de Alta Verapaz y azúcar quemada al momento.', 82.00, 8, 'popular', 'Vanilla crème brûlée', null, 0, 1, 390, 'lacteos,huevo'],
                    ['Volcán de chocolate 70%', 'Cacao guatemalteco, centro líquido y helado de canela.', 88.00, 12, 'popular', 'Chocolate lava cake', null, 0, 0, 520, 'gluten,lacteos,huevo'],
                    ['Tres leches de la casa', 'Esponjoso, con merengue tostado y fresas de Sacatepéquez.', 76.00, 6, '', 'Tres leches cake', null, 0, 0, 480, 'gluten,lacteos,huevo'],
                    ['Helado artesanal', 'Tres bolas: vainilla, cardamomo o cacao. Hecho en casa.', 58.00, 3, 'sin_gluten', 'Artisanal ice cream', null, 0, 0, 280, 'lacteos'],
                    ['Tarta de limón persa', 'Base de galleta de mantequilla y merengue italiano.', 74.00, 6, '', 'Persian lime tart', null, 0, 0, 410, 'gluten,lacteos,huevo'],
                ],
            ],
            [
                'nombre' => 'Bebidas y coctelería', 'en' => 'Drinks & cocktails', 'icono' => 'bar', 'estacion' => 'bar',
                'desc' => 'Coctelería de autor con destilados de la región',
                'platillos' => [
                    ['Café de Antigua', 'Tostado de la semana, preparado en prensa francesa.', 32.00, 5, 'popular', 'Antigua coffee', null, 0, 0, 5, ''],
                    ['Old Fashioned de ron añejo', 'Ron guatemalteco 12 años, amargo de cacao y naranja quemada.', 95.00, 6, 'popular', 'Aged rum old fashioned', null, 0, 1, 210, ''],
                    ['Margarita de rosa de jamaica', 'Tequila, jamaica de la casa y sal de gusano en el borde.', 88.00, 5, 'nuevo', 'Hibiscus margarita', null, 0, 0, 190, ''],
                    ['Limonada con hierbabuena', 'Limón persa, hierbabuena del huerto y hielo frappé.', 38.00, 4, 'vegano', 'Mint lemonade', null, 0, 0, 90, ''],
                    ['Copa de vino tinto', 'Selección del sommelier, consulta la etiqueta del día.', 78.00, 2, '', 'Glass of red wine', null, 0, 0, 125, 'sulfitos'],
                ],
            ],
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    (new DemoSeeder())->run();
}
