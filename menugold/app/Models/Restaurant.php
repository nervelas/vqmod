<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\App;
use MenuGold\Core\DB;
use MenuGold\Core\Model;
use MenuGold\Core\Security;

class Restaurant extends Model
{
    protected string $table = 'restaurants';
    protected bool $scoped = false;
    protected array $fillable = [
        'slug','nombre','eslogan','descripcion','plan_id','estado','vence_el','dominio','logo','portada',
        'tema','color_primario','color_fondo','tipografia','moneda','simbolo','impuesto_pct','impuesto_incluido',
        'propina_sugerida','telefono','whatsapp','email','direccion','mapa_lat','mapa_lng','facebook','instagram',
        'tiktok','google_reviews','link_pago','datos_bancarios','modos_pedido','metodos_pago','idioma','idiomas',
        'abierto_modo','mensaje_bienvenida','mensaje_pie','seo_title','seo_desc','og_image','tiempo_prep_min',
        'pedido_minimo','notas_activas','demo','actualizado',
    ];

    public function bySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM restaurants WHERE slug = :s LIMIT 1', ['s' => $slug]);
    }

    public function byDomain(string $host): ?array
    {
        return DB::one('SELECT * FROM restaurants WHERE dominio = :d AND estado <> :e LIMIT 1',
            ['d' => $host, 'e' => 'suspendido']);
    }

    /** Slug unico a partir de un nombre. */
    public function slugUnico(string $nombre, int $excluirId = 0): string
    {
        $base = str_slug($nombre);
        $slug = $base;
        $i = 2;
        while (DB::int('SELECT COUNT(*) FROM restaurants WHERE slug=:s AND id<>:i', ['s' => $slug, 'i' => $excluirId]) > 0) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /** URL publica del menu. */
    public static function urlMenu(array $r, ?string $mesa = null, ?string $token = null): string
    {
        if (!empty($r['dominio'])) {
            $base = 'https://' . $r['dominio'];
            $p = $mesa !== null ? '/m/' . rawurlencode($mesa) : '/';
            return $base . $p . ($token ? '?t=' . $token : '');
        }
        $p = 'r/' . $r['slug'] . ($mesa !== null ? '/m/' . rawurlencode($mesa) : '');
        return App::url($p, $token ? ['t' => $token] : []);
    }

    /** @return array{abierto:bool,texto:string,proximo:string} */
    public function estadoApertura(array $r): array
    {
        $modo = (string)($r['abierto_modo'] ?? 'auto');
        if ($modo === 'abierto') return ['abierto' => true,  'texto' => __('abierto'), 'proximo' => ''];
        if ($modo === 'cerrado') return ['abierto' => false, 'texto' => __('cerrado'), 'proximo' => ''];

        $dia = (int)date('w');
        $ahora = date('H:i:s');
        $h = DB::one('SELECT * FROM schedules WHERE restaurant_id=:r AND dia=:d LIMIT 1',
            ['r' => (int)$r['id'], 'd' => $dia]);
        if (!$h || (int)$h['cerrado'] === 1) {
            return ['abierto' => false, 'texto' => __('cerrado'), 'proximo' => $this->proximaApertura((int)$r['id'], $dia)];
        }
        $abre = (string)$h['abre'];
        $cierra = (string)$h['cierra'];
        // Horario que cruza la medianoche
        $abierto = $cierra > $abre
            ? ($ahora >= $abre && $ahora < $cierra)
            : ($ahora >= $abre || $ahora < $cierra);
        return [
            'abierto' => $abierto,
            'texto'   => $abierto ? __('abierto') : __('cerrado'),
            'proximo' => $abierto ? __('cierra_a_las', ['hora' => substr($cierra, 0, 5)])
                                  : __('abre_a_las', ['hora' => substr($abre, 0, 5)]),
        ];
    }

    private function proximaApertura(int $rid, int $diaActual): string
    {
        for ($i = 1; $i <= 7; $i++) {
            $d = ($diaActual + $i) % 7;
            $h = DB::one('SELECT * FROM schedules WHERE restaurant_id=:r AND dia=:d AND cerrado=0 LIMIT 1',
                ['r' => $rid, 'd' => $d]);
            if ($h) {
                $nombres = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
                return 'Abre el ' . $nombres[$d] . ' a las ' . substr((string)$h['abre'], 0, 5);
            }
        }
        return '';
    }

    /** Modos de pedido habilitados. @return array<int,string> */
    public static function modos(array $r): array
    {
        $m = array_filter(array_map('trim', explode(',', (string)($r['modos_pedido'] ?? ''))));
        return array_values(array_intersect($m, ['consulta','mesa','llevar','delivery','whatsapp']));
    }

    public static function permiteModo(array $r, string $modo): bool
    {
        return in_array($modo, self::modos($r), true);
    }

    /** Aceptar pedidos en linea (cualquier modo distinto de solo consulta). */
    public static function aceptaPedidos(array $r): bool
    {
        $m = self::modos($r);
        return (bool)array_intersect($m, ['mesa','llevar','delivery','whatsapp']);
    }

    public static function propinas(array $r): array
    {
        $p = jdec($r['propina_sugerida'] ?? '[0,10,15]', [0, 10, 15]);
        $p = array_values(array_filter(array_map('intval', $p), static fn($x) => $x >= 0 && $x <= 100));
        return $p ?: [0, 10, 15];
    }

    /** Crea el juego de horarios por defecto. */
    public function horarioPorDefecto(int $rid): void
    {
        for ($d = 0; $d <= 6; $d++) {
            DB::upsert('schedules', [
                'restaurant_id' => $rid, 'dia' => $d,
                'abre' => '08:00:00', 'cierra' => '22:00:00',
                'cerrado' => 0,
            ], ['abre', 'cierra', 'cerrado']);
        }
    }

    public function horarios(int $rid): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM schedules WHERE restaurant_id=:r ORDER BY dia', ['r' => $rid]) as $h) {
            $out[(int)$h['dia']] = $h;
        }
        for ($d = 0; $d <= 6; $d++) {
            if (!isset($out[$d])) $out[$d] = ['dia' => $d, 'abre' => '08:00:00', 'cierra' => '22:00:00', 'cerrado' => 1];
        }
        ksort($out);
        return $out;
    }

    /** Uso actual frente a los limites del plan. */
    public function uso(int $rid): array
    {
        return [
            'productos' => DB::int('SELECT COUNT(*) FROM products WHERE restaurant_id=:r', ['r' => $rid]),
            'mesas'     => DB::int('SELECT COUNT(*) FROM tables WHERE restaurant_id=:r', ['r' => $rid]),
            'usuarios'  => DB::int('SELECT COUNT(*) FROM users WHERE restaurant_id=:r', ['r' => $rid]),
        ];
    }

    public function vencido(array $r): bool
    {
        return !empty($r['vence_el']) && strtotime((string)$r['vence_el']) < strtotime(date('Y-m-d'));
    }

    public function diasRestantes(array $r): ?int
    {
        if (empty($r['vence_el'])) return null;
        $dif = strtotime((string)$r['vence_el']) - strtotime(date('Y-m-d'));
        return (int)floor($dif / 86400);
    }
}
