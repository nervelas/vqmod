<?php
declare(strict_types=1);
namespace S5\Services;

/** Datos de ejemplo (ficticios y rotulados como demo) para el botón "Crear demo" del panel. */
final class DemoData
{
    public static function brief(string $plan, string $rubro, int $style): array
    {
        $b = Brief::blank($plan);
        $names = ['abogado' => 'Bufete Demo', 'clinica' => 'Clínica Demo', 'taller' => 'Taller Demo', 'ropa' => 'Boutique Demo', 'restaurante' => 'Restaurante Demo', 'transporte' => 'Transportes Demo', 'contabilidad' => 'Contadores Demo', 'importaciones' => 'Importadora Demo', 'otro' => 'Negocio Demo'];
        $services = [
            'abogado' => ['Derecho civil', 'Derecho mercantil', 'Derecho laboral'],
            'clinica' => ['Consulta general', 'Pediatría', 'Laboratorio'],
            'taller' => ['Mecánica general', 'Alineación y balanceo', 'Cambio de aceite'],
            'ropa' => ['Colección de temporada', 'Accesorios', 'Ajustes a medida'],
            'restaurante' => ['Almuerzos ejecutivos', 'Eventos y banquetes', 'Pedidos para llevar'],
            'transporte' => ['Fletes locales', 'Transporte de carga', 'Mudanzas'],
            'contabilidad' => ['Contabilidad mensual', 'Declaraciones de impuestos', 'Planillas'],
            'importaciones' => ['Importación de mercadería', 'Asesoría aduanal', 'Distribución'],
            'otro' => ['Servicio uno', 'Servicio dos', 'Servicio tres'],
        ];
        $r = isset($names[$rubro]) ? $rubro : 'otro';
        $b['negocio'] = ['nombre' => $names[$r], 'rubro' => $r, 'rubro_otro' => '', 'idioma' => 'es', 'estilo' => max(1, min(5, $style)), 'logo' => null];
        $b['contacto']['whatsapp'] = '50200000000';
        $b['contacto']['telefono'] = '2000-0000';
        $b['contacto']['direccion'] = 'Ciudad de Guatemala (dirección de ejemplo)';
        $b['contacto']['horario'] = 'Lunes a viernes, 8:00 a 17:00';
        $b['correo_contacto'] = 'demo@example.com';
        $b['contenido']['quienes'] = 'Esta es una página de demostración creada por Servicom para mostrar el diseño.';
        if ($plan === 'tienda') {
            $b['tienda']['categorias'] = [['nombre' => 'Destacados', 'padre' => '']];
            foreach ([1, 2, 3, 4] as $i) {
                $b['tienda']['productos'][] = ['nombre' => 'Producto de ejemplo ' . $i, 'categoria' => 'Destacados', 'precio' => 50.0 * $i, 'descripcion' => 'Producto de demostración.', 'foto' => null, 'stock' => 10, 'origen' => 'form'];
            }
            $b['tienda']['correo_pedidos'] = 'demo@example.com';
            $b['tienda']['correo_alertas'] = 'demo@example.com';
            $b['tienda']['banco'] = ['banco' => 'Banco de ejemplo', 'numero' => '000-000000-0', 'titular' => 'Demo', 'tipo' => 'Monetaria'];
        }
        foreach ($services[$r] as $s) {
            $b['contenido']['servicios'][] = ['nombre' => $s, 'descripcion' => 'Descripción de ejemplo del servicio.', 'foto' => null, 'origen' => 'form'];
        }
        return $b;
    }
}
