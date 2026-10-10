<?php
/**
 * Lee el contenido actual de la web (opción `sc_site`) desde un volcado .sql de WordPress y lo imprime como JSON.
 * Solo LECTURA: nunca modifica nada. Uso: php herramientas/leer-sc-site.php ruta/al/volcado.sql [--theme-mods]
 * Con --theme-mods muestra también los datos del negocio (sc_telefono, sc_whatsapp, ...) guardados en el tema.
 */
if (PHP_SAPI !== 'cli') { exit; }
$file = $argv[1] ?? '';
if (!is_file($file)) { fwrite(STDERR, "Uso: php herramientas/leer-sc-site.php volcado.sql [--theme-mods]\n"); exit(1); }
$sql = file_get_contents($file);
if ($sql === false) { fwrite(STDERR, "No se pudo leer el archivo.\n"); exit(1); }

/** Valor SQL entre comillas simples que empieza en $pos (la comilla de apertura) → [cadena, posición siguiente]. */
function sql_string(string $s, int $pos): array
{
    $out = ''; $n = strlen($s); $i = $pos + 1;
    $map = ['0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", 'b' => "\x08"];
    while ($i < $n) {
        $c = $s[$i];
        if ($c === '\\' && $i + 1 < $n) { $d = $s[$i + 1]; $out .= $map[$d] ?? $d; $i += 2; continue; }
        if ($c === "'") { if (($s[$i + 1] ?? '') === "'") { $out .= "'"; $i += 2; continue; } return [$out, $i + 1]; }
        $out .= $c; $i++;
    }
    return [$out, $i];
}
function opcion(string $sql, string $nombre): ?string
{
    // INSERT (...),(id,'sc_site','valor','no')  — el valor es el campo siguiente al nombre
    $p = 0; $pat = "'" . $nombre . "',";
    while (($p = strpos($sql, $pat, $p)) !== false) {
        $q = $p + strlen($pat);
        if (($sql[$q] ?? '') === "'") { [$v] = sql_string($sql, $q); return $v; }
        $p = $q;
    }
    return null;
}
$raw = opcion($sql, 'sc_site');
if ($raw === null) { fwrite(STDERR, "No se encontró «sc_site» en el volcado: ¿es una web creada por Servicom?\n"); exit(2); }
$site = @unserialize($raw, ['allowed_classes' => false]);
if (!is_array($site)) { fwrite(STDERR, "«sc_site» no se pudo leer (¿volcado incompleto?).\n"); exit(3); }
$res = ['sc_site' => $site];
if (in_array('--theme-mods', $argv, true)) {
    $tm = []; $p = 0;
    while (($p = strpos($sql, "'theme_mods_", $p)) !== false) {
        [$name, $q] = sql_string($sql, $p);
        if (($sql[$q] ?? '') === ',' && ($sql[$q + 1] ?? '') === "'") {
            [$val] = sql_string($sql, $q + 1);
            $u = @unserialize($val, ['allowed_classes' => false]);
            if (is_array($u)) { $tm[$name] = array_filter($u, fn($k) => str_starts_with((string) $k, 'sc_') || $k === 'custom_logo', ARRAY_FILTER_USE_KEY); }
            $p = $q;
        } else { $p++; }
    }
    $res['theme_mods'] = $tm;
}
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
