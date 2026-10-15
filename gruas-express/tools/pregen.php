<?php
declare(strict_types=1);
/** Instala una copia temporal del sitio para generar las páginas estáticas iniciales. Uso: php pregen.php <dir> */
define('GX', 1);
define('GX_ROOT', rtrim((string)($argv[1] ?? ''), '/'));
if (!is_dir(GX_ROOT . '/_core')) {
    fwrite(STDERR, "dir inválido\n");
    exit(1);
}
require GX_ROOT . '/_core/lib/bootstrap.php';
require_once GX_ROOT . '/_core/lib/install.php';
gx_ensure_installed();
echo "pregen OK\n";
