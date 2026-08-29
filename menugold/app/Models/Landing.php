<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

/** Contenido editable del sitio de venta. */
final class Landing
{
    /** @var array|null */
    private static $cache = null;

    public static function all()
    {
        if (self::$cache === null) {
            self::$cache = array();
            foreach (DB::all('SELECT ckey, cvalue FROM landing_content') as $r) {
                self::$cache[$r['ckey']] = $r['cvalue'];
            }
        }
        return self::$cache;
    }

    public static function get($key, $default = '')
    {
        $all = self::all();
        return (isset($all[$key]) && $all[$key] !== null && $all[$key] !== '') ? $all[$key] : $default;
    }

    public static function put($key, $value)
    {
        DB::run('INSERT INTO landing_content (ckey, cvalue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE cvalue = :v2',
            array('k' => $key, 'v' => $value, 'v2' => $value));
        self::$cache = null;
    }

    public static function plans()
    {
        $rows = DB::all('SELECT * FROM landing_plans WHERE is_active = 1 ORDER BY sort, id');
        foreach ($rows as $i => $r) {
            $rows[$i]['features_list'] = array_values(array_filter(array_map('trim', explode("\n", (string)$r['features']))));
        }
        return $rows;
    }

    public static function testimonials()
    {
        return DB::all('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort, id');
    }

    /** Restaurante que se usa como demostración pública. */
    public static function demoRestaurant()
    {
        $slug = self::get('demo_slug', 'brasa-negra');
        $r = Restaurant::findBySlug($slug);
        if ($r) { return $r; }
        return DB::first("SELECT * FROM restaurants WHERE status <> 'suspended' ORDER BY id LIMIT 1");
    }

    /** Claves con su valor por omisión: la landing nunca se ve vacía. */
    public static function defaults()
    {
        return array(
            'brand_name'      => 'MenúGold',
            'hero_eyebrow'    => 'Menú digital con pedidos',
            'hero_title'      => 'Tu menú merece ser una experiencia',
            'hero_subtitle'   => 'Un código QR en la mesa. Fotografía que da hambre, pedidos que entran solos a la cocina y un dueño que por fin ve sus números.',
            'hero_cta'        => 'Ver demo en vivo',
            'hero_qr_note'    => 'Escanea con tu celular ahora mismo',
            'problem_eyebrow' => 'El problema',
            'problem_1'       => 'El menú plastificado se despinta, se mancha y cuesta Q900 cada vez que suben los precios.',
            'problem_2'       => 'Las fotos tomadas con el celular del sobrino no venden el platillo de Q180.',
            'problem_3'       => 'El mesero anota en papel, la cocina adivina, y la cuenta sale mal a las nueve de la noche.',
            'experience_eyebrow' => 'La experiencia',
            'experience_title'   => 'El comensal escanea y entra a tu restaurante otra vez',
            'experience_text'    => 'Sin descargar nada. La portada se abre a pantalla completa, las fotos ocupan el ancho del teléfono y el precio se lee en dorado. Elige término, extras, quita el cilantro, y el pedido llega a la cocina antes de que el mesero cruce el salón.',
            'gallery_eyebrow'    => 'El menú',
            'gallery_title'      => 'Cada platillo, a pantalla completa',
            'steps_eyebrow'      => 'Cómo funciona',
            'steps_title'        => 'Tres pasos. Nada que instalar.',
            'step_1_title'       => 'Escanea',
            'step_1_text'        => 'El comensal apunta la cámara al QR de su mesa. El menú abre en dos segundos.',
            'step_2_title'       => 'Pide',
            'step_2_text'        => 'Elige, personaliza y confirma. El total se calcula solo, sin errores de suma.',
            'step_3_title'       => 'La cocina lo recibe',
            'step_3_text'        => 'Aparece en la pantalla de cocina con sonido y cronómetro. El mesero solo confirma y cobra.',
            'owner_eyebrow'      => 'Para el dueño',
            'owner_title'        => 'Por fin sabes qué se vende y qué te está costando dinero',
            'owner_text'         => 'Ventas por día, por mesero y por categoría. Los cinco platillos que más salen y los cinco que nadie pide. Tus horas pico reales, no las que crees tener.',
            'stat_1_value'       => '120',
            'stat_1_label'       => 'Restaurantes atendidos',
            'stat_2_value'       => '84000',
            'stat_2_label'       => 'Pedidos procesados',
            'stat_3_value'       => '31',
            'stat_3_label'       => '% más de ticket promedio',
            'stat_4_value'       => '2',
            'stat_4_label'       => 'Minutos para publicar un cambio',
            'pricing_eyebrow'    => 'Planes',
            'pricing_title'      => 'Precios claros, en quetzales',
            'pricing_note'       => 'Sin contrato de permanencia. Incluye instalación y capacitación.',
            'testimonials_eyebrow' => 'Lo que dicen',
            'testimonials_title'   => 'Dueños que ya no imprimen menús',
            'cta_title'          => 'Tu restaurante, en otro nivel',
            'cta_text'           => 'Te lo dejamos funcionando con tus platillos y tus fotos en menos de una semana.',
            'cta_button'         => 'Hablar por WhatsApp',
            'whatsapp'           => '50200000000',
            'whatsapp_message'   => 'Hola, vi MenúGold y quiero el menú digital para mi restaurante.',
            'contact_email'      => 'hola@menugold.gt',
            'contact_phone'      => '+502 0000 0000',
            'contact_city'       => 'Ciudad de Guatemala',
            'instagram'          => '',
            'facebook'           => '',
            'seo_title'          => 'MenúGold · Menú digital QR con pedidos para restaurantes',
            'seo_description'    => 'Menú QR con fotografía, pedidos a la cocina en tiempo real, reportes y panel propio. Instalación en tu dominio, en quetzales, sin comisiones por pedido.',
            'seo_og_image'       => '',
            'demo_slug'          => 'brasa-negra',
            'marquee'            => 'Sin comisiones por pedido · Tu dominio, tu marca · Pedidos a la cocina en tiempo real · Fotografía a pantalla completa · Reportes que sí se entienden · QR por mesa · Español e inglés',
        );
    }

    /** Valor con respaldo en los valores por omisión. */
    public static function v($key)
    {
        $d = self::defaults();
        return self::get($key, isset($d[$key]) ? $d[$key] : '');
    }
}
