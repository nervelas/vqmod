<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Company
{
    /** 8 temas técnicos + personalizado. */
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

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM companies WHERE id = ? LIMIT 1', [$id]);
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM companies WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function byDomain(string $host): ?array
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?: '');
        $alt  = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
        return DB::one('SELECT * FROM companies WHERE domain IN (?, ?) AND status IN ("activa","prueba") LIMIT 1', [$host, $alt]);
    }

    public static function all(): array
    {
        return DB::all('SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.name');
    }

    public static function isLive(?array $c): bool
    {
        if (!$c || !in_array($c['status'], ['activa', 'prueba'], true)) {
            return false;
        }
        if (!empty($c['expires_at']) && strtotime((string) $c['expires_at']) < strtotime('today')) {
            return false;
        }
        return true;
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

    /** Contadores usados para validar los límites del plan. */
    public static function usage(int $companyId): array
    {
        return [
            'products' => (int) DB::value('SELECT COUNT(*) FROM products WHERE company_id = ?', [$companyId], 0),
            'users'    => (int) DB::value('SELECT COUNT(*) FROM users WHERE company_id = ?', [$companyId], 0),
            'quotes'   => (int) DB::value('SELECT COUNT(*) FROM quotes WHERE company_id = ? AND created_at >= DATE_FORMAT(NOW(),"%Y-%m-01")', [$companyId], 0),
        ];
    }

    public static function limits(int $companyId): array
    {
        $row = DB::one('SELECT p.max_products, p.max_users, p.max_quotes_month FROM companies c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.id = ? LIMIT 1', [$companyId]);
        return [
            'products' => (int) ($row['max_products'] ?? 0),
            'users'    => (int) ($row['max_users'] ?? 0),
            'quotes'   => (int) ($row['max_quotes_month'] ?? 0),
        ];
    }

    /** true si aún cabe un registro más del tipo indicado (0 = ilimitado). */
    public static function withinLimit(int $companyId, string $what): bool
    {
        $lim = self::limits($companyId)[$what] ?? 0;
        if ($lim <= 0) {
            return true;
        }
        return (self::usage($companyId)[$what] ?? 0) < $lim;
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM companies WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
            $p = $ignoreId ? [$slug, $ignoreId] : [$slug];
            if (!DB::one($sql, $p)) {
                return $slug;
            }
            $slug = slugify($base) . '-' . (++$i);
        }
    }

    /** Vendedor siguiente para asignación rotativa. */
    public static function nextSeller(int $companyId): ?int
    {
        $c = self::find($companyId);
        if (!$c || $c['assign_mode'] !== 'rotativo') {
            return null;
        }
        $sellers = DB::all('SELECT id FROM users WHERE company_id = ? AND status = "activo" AND receives_leads = 1 AND role IN ("vendedor","admin") ORDER BY id', [$companyId]);
        if (!$sellers) {
            return null;
        }
        $ptr = ((int) $c['assign_pointer']) % count($sellers);
        DB::update('companies', ['assign_pointer' => $ptr + 1], 'id = :id', ['id' => $companyId]);
        return (int) $sellers[$ptr]['id'];
    }

    public static function publicUrl(array $c, string $sub = ''): string
    {
        $sub = ltrim($sub, '/');
        return url('/e/' . $c['slug'] . ($sub !== '' ? '/' . $sub : ''));
    }
}
