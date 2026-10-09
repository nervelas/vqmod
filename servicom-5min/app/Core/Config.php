<?php
declare(strict_types=1);
namespace S5\Core;

/** Configuración de archivo (credenciales BD, llave maestra, rutas). Los demás ajustes viven en Settings. */
final class Config
{
    private static ?array $data = null;
    private static ?string $file = null;

    public static function file(): ?string
    {
        return self::$file;
    }

    /** Busca config.php: variable de entorno > carpeta hermana superior al docroot > app/config.php */
    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $cands = [];
        $env = getenv('S5_CONFIG_FILE');
        if ($env) {
            $cands[] = $env;
        }
        $cands[] = dirname(S5_ROOT) . '/servicom-secrets/config.php';
        $cands[] = S5_ROOT . '/app/config.php';
        foreach ($cands as $f) {
            if (is_file($f)) {
                $d = require $f;
                if (is_array($d)) {
                    self::$data = $d;
                    self::$file = $f;
                    return $d;
                }
            }
        }
        self::$data = [];
        return self::$data;
    }

    public static function installed(): bool
    {
        $c = self::load();
        return !empty($c['db']['name']) && !empty($c['secret_key']);
    }

    public static function get(string $key, $default = null)
    {
        $c = self::load();
        foreach (explode('.', $key) as $part) {
            if (!is_array($c) || !array_key_exists($part, $c)) {
                return $default;
            }
            $c = $c[$part];
        }
        return $c;
    }

    /** Solo para pruebas / instalador */
    public static function override(array $data): void
    {
        self::$data = $data;
    }
}
