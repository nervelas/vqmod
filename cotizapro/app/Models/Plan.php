<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Plan
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM plans' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort, price_month';
        return DB::all($sql);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM plans WHERE id = ? LIMIT 1', [$id]);
    }

    public static function features(array $plan): array
    {
        $f = json_decode((string) ($plan['features'] ?? ''), true);
        return is_array($f) ? $f : [];
    }
}
