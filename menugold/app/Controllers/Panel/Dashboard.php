<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Request;
use MenuGold\Models\Category;
use MenuGold\Models\Order;
use MenuGold\Models\Product;
use MenuGold\Models\Report;
use MenuGold\Models\Restaurant;
use MenuGold\Models\RestaurantTable;

/**
 * Escritorio del restaurante y bienvenida guiada.
 */
class Dashboard extends Base
{
    public function index(): void
    {
        $rep = new Report($this->rid);
        $resumen = $rep->resumen();

        $desde = date('Y-m-d', strtotime('-13 days'));
        $hasta = date('Y-m-d');
        $porDia = $rep->ventasPorDia($desde, $hasta);
        $serie = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $serie[$d] = 0.0;
        }
        foreach ($porDia as $f) $serie[(string)$f['dia']] = round((float)$f['total'], 2);

        $om = (new Order())->forRestaurant($this->rid);
        $recientes = $om->where("estado <> 'anulado'", [], 'creado DESC', 8);
        $abiertos  = $om->where("estado IN ('nuevo','preparando','listo')", [], 'creado ASC', 12);

        $rm = new Restaurant();
        $dias = $rm->diasRestantes($this->r);

        $this->panel('panel/dashboard', [
            'resumen'   => $resumen,
            'serie'     => $serie,
            'top'       => $rep->topProductos(date('Y-m-01'), $hasta, 6),
            'modos'     => $rep->ventasPorModo(date('Y-m-01'), $hasta),
            'recientes' => $recientes,
            'abiertos'  => $abiertos,
            'apertura'  => $rm->estadoApertura($this->r),
            'diasPlan'  => $dias,
            'uso'       => $rm->uso($this->rid),
            'limites'   => $this->limites(),
            'faltantes' => $this->faltantes(),
        ]);
    }

    /** Datos vivos para refrescar el escritorio sin recargar. */
    public function resumen(): void
    {
        $rep = new Report($this->rid);
        $this->ok(['resumen' => $rep->resumen(), 'pendientes' => $this->pendientes()]);
    }

    /** Sugerencias de configuración pendientes. */
    private function faltantes(): array
    {
        $f = [];
        if (empty($this->r['logo']))    $f[] = ['Sube el logo de tu restaurante', 'panel/configuracion', 'image'];
        if (empty($this->r['portada'])) $f[] = ['Agrega una foto de portada', 'panel/configuracion', 'image'];
        $nProd = DB::int('SELECT COUNT(*) FROM products WHERE restaurant_id=:r', ['r' => $this->rid]);
        if ($nProd === 0) $f[] = ['Crea tu primer platillo', 'panel/productos/nuevo', 'utensils'];
        $nMesas = DB::int('SELECT COUNT(*) FROM tables WHERE restaurant_id=:r', ['r' => $this->rid]);
        if ($nMesas === 0) $f[] = ['Registra tus mesas y descarga los QR', 'panel/mesas', 'qr'];
        if (empty($this->r['whatsapp'])) $f[] = ['Agrega tu WhatsApp de contacto', 'panel/configuracion', 'whatsapp'];
        if (empty($this->r['google_reviews'])) $f[] = ['Agrega tu enlace de reseñas de Google', 'panel/configuracion', 'star'];
        return $f;
    }

    // ---------------------------------------------------------------- tema
    public function tema(): void
    {
        $modo = Request::enum('modo', ['claro', 'oscuro', 'auto'], 'auto');
        DB::update('users', ['tema_panel' => $modo], 'id=:i', ['i' => Auth::id()]);
        $this->ok(['modo' => $modo]);
    }

    // ---------------------------------------------------------------- bienvenida guiada
    public function onboarding(): void
    {
        $paso = max(1, min(4, (int)($_GET['paso'] ?? 1)));
        $this->panel('panel/onboarding', [
            'paso'  => $paso,
            'cats'  => (new Category())->forRestaurant($this->rid)->all('orden ASC'),
            'temas' => $this->temas(),
        ]);
    }

    public function onboardingGuardar(): void
    {
        $paso = max(1, min(4, Request::int('paso', 1)));
        $rm = (new Restaurant());

        if ($paso === 1) {
            $datos = [
                'nombre'    => Request::str('nombre', '', 120),
                'eslogan'   => Request::str('eslogan', '', 180),
                'direccion' => Request::str('direccion', '', 255),
                'telefono'  => Request::str('telefono', '', 30),
                'whatsapp'  => preg_replace('/\D/', '', Request::str('whatsapp', '', 30)) ?? '',
                'actualizado' => date('Y-m-d H:i:s'),
            ];
            if ($datos['nombre'] === '') {
                flash('error', 'El nombre del restaurante es obligatorio.');
                redirect('panel/inicio?paso=1');
            }
            $rm->updateById($this->rid, $datos);
            Audit::log('config', 'restaurants', $this->rid, null, $datos);
            redirect('panel/inicio?paso=2');
        }

        if ($paso === 2) {
            $datos = [
                'tema'           => Request::enum('tema', array_keys($this->temas()), 'negro-oro'),
                'tipografia'     => Request::enum('tipografia', ['clasica', 'moderna', 'editorial'], 'clasica'),
                'color_primario' => $this->color(Request::str('color_primario', '#D4AF37', 9), '#D4AF37'),
                'actualizado'    => date('Y-m-d H:i:s'),
            ];
            $logo = Request::file('logo');
            if ($logo) {
                [$ok, $res] = Image::upload($logo, 'logos/' . $this->rid, 600, 600, 88);
                if ($ok) {
                    Image::delete((string)($this->r['logo'] ?? ''));
                    $datos['logo'] = $res;
                } else {
                    flash('error', $res);
                }
            }
            $portada = Request::file('portada');
            if ($portada) {
                [$ok, $res] = Image::upload($portada, 'portadas/' . $this->rid, 1800, 1100, 82);
                if ($ok) {
                    Image::delete((string)($this->r['portada'] ?? ''));
                    $datos['portada'] = $res;
                }
            }
            $rm->updateById($this->rid, $datos);
            redirect('panel/inicio?paso=3');
        }

        if ($paso === 3) {
            $nombre = Request::str('nombre', '', 160);
            $precio = Request::float('precio');
            if ($nombre !== '' && $precio >= 0) {
                $cm = (new Category())->forRestaurant($this->rid);
                $catId = Request::int('category_id');
                if ($catId <= 0) {
                    $existente = $cm->first('1=1', [], 'orden ASC');
                    $catId = $existente ? (int)$existente['id']
                        : $cm->create(['nombre' => 'Nuestra carta', 'orden' => 0, 'activo' => 1]);
                }
                $pm = (new Product())->forRestaurant($this->rid);
                $datos = [
                    'category_id' => $catId,
                    'nombre'      => $nombre,
                    'descripcion' => Request::str('descripcion', '', 600),
                    'precio'      => $precio,
                    'activo'      => 1, 'destacado' => 1,
                    'tiempo_prep' => max(1, Request::int('tiempo_prep', 15)),
                    'orden'       => $pm->maxOrder() + 1,
                ];
                $foto = Request::file('imagen');
                if ($foto) {
                    [$ok, $res] = Image::upload($foto, 'productos/' . $this->rid);
                    if ($ok) $datos['imagen'] = $res;
                }
                $pm->create($datos);
                flash('exito', '¡Tu primer platillo ya está en el menú!');
            }
            redirect('panel/inicio?paso=4');
        }

        // Paso 4: termina la bienvenida
        DB::update('users', ['onboarding' => 1], 'id=:i', ['i' => Auth::id()]);
        flash('exito', 'Todo listo. Tu menú digital ya está en línea.');
        redirect('panel');
    }

    private function color(string $v, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : $fallback;
    }

    public static function temasDisponibles(): array
    {
        return [
            'negro-oro'     => ['Negro & Oro',   '#141414', '#D4AF37', '#F7F3EA'],
            'blanco-oro'    => ['Blanco & Oro',  '#FBF9F5', '#B8912A', '#1D1B17'],
            'verde-botella' => ['Verde Botella', '#10231C', '#C9A961', '#F0F4EF'],
            'borgona'       => ['Borgoña',       '#241014', '#D9AE63', '#F8EFEF'],
            'azul-marino'   => ['Azul Marino',   '#101A2B', '#D2B368', '#EEF3FA'],
            'terracota'     => ['Terracota',     '#FDF6F0', '#B4643C', '#2A1C14'],
            'grafito'       => ['Grafito',       '#1A1C1E', '#C0C6CC', '#EDEFF1'],
            'marfil'        => ['Marfil',        '#F7F3EA', '#8C7A3F', '#211E17'],
        ];
    }

    private function temas(): array { return self::temasDisponibles(); }
}
