<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Datos de la empresa. La instalación atiende a una sola empresa, por lo que
 * la tabla `company` guarda siempre una única fila con id = 1.
 */
final class Company
{
    public const ID = 1;

    private static ?array $cache = null;

    /** 8 temas técnicos + colores personalizados. */
    public const THEMES = [
        'acero'    => ['label' => 'Acero / Naranja maquinaria', 'accent' => '#E8590C', 'ink' => '#1C1F22', 'paper' => '#F5F6F4'],
        'grafito'  => ['label' => 'Grafito / Ámbar',            'accent' => '#C77800', 'ink' => '#17191C', 'paper' => '#F4F4F2'],
        'cobalto'  => ['label' => 'Cobalto técnico',            'accent' => '#1F5FBF', 'ink' => '#141A22', 'paper' => '#F3F5F8'],
        'oliva'    => ['label' => 'Oliva industrial',           'accent' => '#4C7A2F', 'ink' => '#1A1E19', 'paper' => '#F4F6F1'],
        'oxido'    => ['label' => 'Óxido / Ladrillo',           'accent' => '#B4442A', 'ink' => '#201A18', 'paper' => '#F6F3F0'],
        'turquesa' => ['label' => 'Turquesa de planta',         'accent' => '#0E7C86', 'ink' => '#141F21', 'paper' => '#F1F6F6'],
        'plomo'    => ['label' => 'Plomo / Amarillo seguridad', 'accent' => '#B99400', 'ink' => '#1B1D1E', 'paper' => '#F5F5F3'],
        'violeta'  => ['label' => 'Violeta de precisión',       'accent' => '#6B3FA0', 'ink' => '#1A1722', 'paper' => '#F5F3F8'],
    ];

    public const LOST_REASONS = ['precio', 'tiempo de entrega', 'competencia', 'sin presupuesto', 'sin respuesta', 'cambio de proyecto', 'otro'];

    /** Fila única de la empresa; null si aún no se ha instalado. */
    public static function get(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache ?: null;
        }
        $row = DB::one('SELECT * FROM company WHERE id = ? LIMIT 1', [self::ID]);
        self::$cache = $row ?: [];
        return $row;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function save(array $data): void
    {
        DB::update('company', $data + ['updated_at' => nowSql()], 'id = :id', ['id' => self::ID]);
        self::forget();
    }

    public static function theme(array $c): array
    {
        $t = (string) ($c['theme'] ?? 'acero');
        $base = self::THEMES[$t] ?? self::THEMES['acero'];
        return [
            'accent' => self::hex($c['color_accent'] ?? '', $base['accent']),
            'ink'    => self::hex($c['color_ink'] ?? '', $base['ink']),
            'paper'  => self::hex($c['color_paper'] ?? '', $base['paper']),
        ];
    }

    public static function hex(?string $v, string $fallback): string
    {
        $v = trim((string) $v);
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? strtoupper($v) : $fallback;
    }

    /** Vendedor siguiente para asignación rotativa. */
    public static function nextSeller(): ?int
    {
        $c = self::get();
        if (!$c || $c['assign_mode'] !== 'rotativo') {
            return null;
        }
        $sellers = DB::all('SELECT id FROM users WHERE status = "activo" AND receives_leads = 1 AND role IN ("vendedor","admin") ORDER BY id');
        if (!$sellers) {
            return null;
        }
        $ptr = ((int) $c['assign_pointer']) % count($sellers);
        DB::update('company', ['assign_pointer' => $ptr + 1], 'id = :id', ['id' => self::ID]);
        self::forget();
        return (int) $sellers[$ptr]['id'];
    }

    /** URL pública del sitio (la empresa vive en la raíz de la instalación). */
    public static function publicUrl(string $sub = ''): string
    {
        $sub = ltrim($sub, '/');
        return url('/' . $sub);
    }
}
