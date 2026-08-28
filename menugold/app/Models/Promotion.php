<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\Model;

class Promotion extends Model
{
    protected string $table = 'promotions';
    protected array $fillable = [
        'restaurant_id','nombre','descripcion','tipo','valor','product_ids','category_ids',
        'imagen','desde','hasta','dias','activo','orden',
    ];

    /** Promociones vigentes hoy. */
    public function vigentes(): array
    {
        $hoy = date('Y-m-d');
        $dia = (string)date('w');
        $lista = $this->where(
            'activo = 1 AND (desde IS NULL OR desde <= :h1) AND (hasta IS NULL OR hasta >= :h2)',
            ['h1' => $hoy, 'h2' => $hoy],
            'orden ASC, id ASC'
        );
        return array_values(array_filter($lista, static function ($p) use ($dia) {
            $d = trim((string)$p['dias']);
            return $d === '' || in_array($dia, array_map('trim', explode(',', $d)), true);
        }));
    }

    /** Mapa product_id => promocion aplicable. */
    public function mapaProductos(): array
    {
        $map = [];
        foreach ($this->vigentes() as $p) {
            foreach (array_filter(array_map('intval', explode(',', (string)$p['product_ids']))) as $pid) {
                if (!isset($map[$pid])) $map[$pid] = $p;
            }
        }
        return $map;
    }

    public static function etiquetaTipo(string $tipo, $valor, string $simbolo = 'Q'): string
    {
        switch ($tipo) {
            case '2x1':         return '2 x 1';
            case 'descuento':   return '-' . rtrim(rtrim(number_format((float)$valor, 2, '.', ''), '0'), '.') . '%';
            case 'precio_fijo': return $simbolo . number_format((float)$valor, 2);
            case 'combo':       return 'Combo';
        }
        return 'Promoción';
    }
}
