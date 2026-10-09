<?php
declare(strict_types=1);
namespace S5\Services;

/**
 * Biblioteca de fotos de stock por rubro (library/catalog.json). Si no hay foto disponible devuelve null:
 * el constructor usa cajas de icono, jamás imágenes falsas.
 */
final class Stock
{
    private static ?array $cat = null;

    public static function dir(): string
    {
        return S5_ROOT . '/library';
    }

    private static function catalog(): array
    {
        if (self::$cat === null) {
            $f = self::dir() . '/catalog.json';
            $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
            self::$cat = is_array($j) ? $j : [];
        }
        return self::$cat;
    }

    /** @return array{path:string,alt:string,credit:string}|null */
    public static function pick(string $rubro, string $slot, int $seed = 0): ?array
    {
        $c = self::catalog();
        $items = $c['rubros'][$rubro][$slot] ?? ($c['rubros'][$rubro]['servicio'] ?? []);
        if (!$items && $slot !== 'servicio') {
            $items = $c['rubros'][$rubro]['servicio'] ?? [];
        }
        $items = array_values(array_filter($items, fn($i) => is_file(self::dir() . '/' . ($i['file'] ?? ''))));
        if (!$items) {
            return null;
        }
        $i = $items[$seed % count($items)];
        return ['path' => self::dir() . '/' . $i['file'], 'alt' => (string) ($i['alt'] ?? ''), 'credit' => (string) ($i['credit'] ?? '')];
    }

    public static function available(): array
    {
        $out = [];
        foreach (self::catalog()['rubros'] ?? [] as $r => $slots) {
            foreach ($slots as $slot => $items) {
                $out[$r][$slot] = count(array_filter($items, fn($i) => is_file(self::dir() . '/' . ($i['file'] ?? ''))));
            }
        }
        return $out;
    }
}
