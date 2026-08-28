<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Almacen clave/valor por restaurante y de plataforma.
 */
final class Setting
{
    private static array $cacheRest = [];
    private static ?array $cachePlat = null;

    // ------------------------------------------------------- por restaurante
    public static function get(string $clave, $default = null, ?int $restaurantId = null)
    {
        $rid = $restaurantId ?? App::restaurantId() ?: Auth::restaurantId();
        if ($rid <= 0) return $default;
        if (!isset(self::$cacheRest[$rid])) {
            try {
                self::$cacheRest[$rid] = DB::pairs(
                    'SELECT clave, valor FROM restaurant_settings WHERE restaurant_id = :r',
                    ['r' => $rid]
                );
            } catch (\Throwable $e) { self::$cacheRest[$rid] = []; }
        }
        $v = self::$cacheRest[$rid][$clave] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }

    public static function json(string $clave, array $default = [], ?int $restaurantId = null): array
    {
        return jdec(self::get($clave, null, $restaurantId), $default);
    }

    public static function set(string $clave, $valor, ?int $restaurantId = null): void
    {
        $rid = $restaurantId ?? App::restaurantId() ?: Auth::restaurantId();
        if ($rid <= 0) return;
        if (is_array($valor)) $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
        DB::upsert('restaurant_settings', [
            'restaurant_id' => $rid,
            'clave'         => mb_substr($clave, 0, 60),
            'valor'         => (string)$valor,
        ], ['valor']);
        unset(self::$cacheRest[$rid]);
    }

    public static function setMany(array $pares, ?int $restaurantId = null): void
    {
        foreach ($pares as $k => $v) self::set((string)$k, $v, $restaurantId);
    }

    // ------------------------------------------------------- plataforma
    public static function plat(string $clave, $default = null)
    {
        if (self::$cachePlat === null) {
            try {
                self::$cachePlat = DB::pairs('SELECT clave, valor FROM platform_settings');
            } catch (\Throwable $e) { self::$cachePlat = []; }
        }
        $v = self::$cachePlat[$clave] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }

    public static function platJson(string $clave, array $default = []): array
    {
        return jdec(self::plat($clave, null), $default);
    }

    public static function setPlat(string $clave, $valor): void
    {
        if (is_array($valor)) $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
        DB::upsert('platform_settings', [
            'clave' => mb_substr($clave, 0, 60),
            'valor' => (string)$valor,
        ], ['valor']);
        self::$cachePlat = null;
    }

    public static function setPlatMany(array $pares): void
    {
        foreach ($pares as $k => $v) self::setPlat((string)$k, $v);
    }

    public static function flush(): void
    {
        self::$cacheRest = [];
        self::$cachePlat = null;
    }
}
