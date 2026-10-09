<?php
declare(strict_types=1);
namespace S5\Core;

/** Utilidades de archivos con comprobaciones de ruta estrictas. */
final class Fs
{
    /** ¿$path (existente o no) está estrictamente DENTRO de $root? Resuelve .. y enlaces. */
    public static function inside(string $path, string $root): bool
    {
        $rootR = realpath($root);
        if ($rootR === false) {
            return false;
        }
        $rootR = rtrim($rootR, '/');
        // resolver el ancestro existente más cercano
        $p = $path;
        $tail = [];
        while (!file_exists($p) && !is_link($p)) {
            $tail[] = basename($p);
            $parent = dirname($p);
            if ($parent === $p) {
                return false;
            }
            $p = $parent;
        }
        $real = realpath($p);
        if ($real === false) {
            return false;
        }
        foreach (array_reverse($tail) as $t) {
            if ($t === '..' || $t === '' || str_contains($t, "\0")) {
                return false;
            }
            $real .= '/' . $t;
        }
        return $real !== $rootR && str_starts_with($real, $rootR . '/');
    }

    public static function mkdir(string $dir, int $mode = 0750): bool
    {
        return is_dir($dir) || @mkdir($dir, $mode, true) || is_dir($dir);
    }

    /** Borra un árbol SOLO si está dentro de alguna de las raíces permitidas. Devuelve bytes liberados o lanza. */
    public static function rmTreeSafe(string $path, array $allowedRoots): void
    {
        if ($path === '' || $path === '/' || str_contains($path, "\0")) {
            throw new \RuntimeException('Ruta no permitida.');
        }
        $ok = false;
        foreach ($allowedRoots as $r) {
            if ($r !== '' && self::inside($path, $r)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            throw new \RuntimeException('Borrado rechazado: ruta fuera de las zonas permitidas.');
        }
        if (is_link($path)) {
            @unlink($path);
            return;
        }
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            if ($f->isLink() || $f->isFile()) {
                @unlink($f->getPathname());
            } else {
                @rmdir($f->getPathname());
            }
        }
        @rmdir($path);
    }

    public static function freeSpace(string $path): float
    {
        $f = @disk_free_space($path);
        return $f === false ? -1.0 : (float) $f;
    }

    public static function randomName(string $ext = ''): string
    {
        return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    }
}
