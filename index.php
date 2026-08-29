<?php
/**
 * Redireccion de respaldo.
 *
 * Si el hosting tiene mod_rewrite, el .htaccess de esta carpeta ya sirve el
 * sistema desde public/ y este archivo nunca llega a ejecutarse. Si no lo
 * tiene, esta redireccion hace que el sistema funcione igual.
 */
declare(strict_types=1);

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

header('Location: ' . $base . '/public/', true, 302);

echo '<!doctype html><meta charset="utf-8">'
   . '<title>Sistema de Facturación FEL</title>'
   . '<p>Abriendo el sistema… '
   . '<a href="' . htmlspecialchars($base . '/public/', ENT_QUOTES, 'UTF-8') . '">'
   . 'entrar</a></p>';
