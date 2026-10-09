<?php
declare(strict_types=1);
/** Detecta sintaxis/funciones de PHP 8.1+ en el código del proyecto. Uso: php tests/compat80.php [ruta...] */
$roots = array_slice($argv, 1) ?: [dirname(__DIR__)];
$funcs81 = ['array_is_list', 'fsync', 'fdatasync', 'enum_exists', 'mysqli_execute_query', 'ini_parse_quantity', 'json_validate', 'mb_str_pad', 'mb_trim', 'str_contains_any', 'memory_reset_peak_usage', 'curl_upkeep', 'imagecreatefromavif', 'libxml_get_external_entity_loader', 'array_find', 'array_any', 'array_all', 'array_find_key', 'openssl_cipher_key_length', 'sodium_crypto_stream_xchacha20_xor_ic'];
$bad = 0; $n = 0;
$it = function (string $d) use (&$it) {
    foreach (scandir($d) ?: [] as $f) {
        if ($f === '.' || $f === '..' || in_array($f, ['node_modules', '.git', 'dist', 'fonts'], true)) { continue; }
        $p = "$d/$f";
        if (is_dir($p)) { yield from $it($p); } elseif (str_ends_with($f, '.php')) { yield $p; }
    }
};
foreach ($roots as $r) {
    foreach (is_file($r) ? [$r] : $it($r) as $file) {
        $n++;
        $src = (string) file_get_contents($file);
        $tk = token_get_all($src);
        $cnt = count($tk);
        for ($i = 0; $i < $cnt; $i++) {
            $t = $tk[$i];
            if (!is_array($t)) { continue; }
            [$id, $txt, $line] = $t;
            $why = null;
            if (defined('T_ENUM') && $id === T_ENUM) { $nx = $i + 1; while (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_WHITESPACE) { $nx++; } if (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_STRING) { $why = 'enum'; } }
            if ($id === T_READONLY) { $why = 'readonly'; }
            if ($id === T_STRING && strtolower($txt) === 'never') { $pv = $i - 1; while (isset($tk[$pv]) && is_array($tk[$pv]) && $tk[$pv][0] === T_WHITESPACE) { $pv--; } if (($tk[$pv] ?? '') === ':') { $why = 'tipo never'; } }
            if ($id === T_ELLIPSIS) { $pv = $i - 1; $nx = $i + 1; while (isset($tk[$pv]) && is_array($tk[$pv]) && $tk[$pv][0] === T_WHITESPACE) { $pv--; } while (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_WHITESPACE) { $nx++; } if (($tk[$pv] ?? '') === '(' && ($tk[$nx] ?? '') === ')') { $why = 'first-class callable'; } }
            if ($id === T_STRING && in_array(strtolower($txt), $funcs81, true)) { $nx = $i + 1; while (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_WHITESPACE) { $nx++; } $pv = $i - 1; while (isset($tk[$pv]) && is_array($tk[$pv]) && $tk[$pv][0] === T_WHITESPACE) { $pv--; } $prev = $tk[$pv] ?? ''; if (($tk[$nx] ?? '') === '(' && !(is_array($prev) && in_array($prev[0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) && !function_exists_guard($tk, $i)) { $why = "función 8.1+ {$txt}()"; } }
            if ($id === T_LNUMBER && preg_match('/^0[oO]\d/', $txt)) { $why = 'octal 0o'; }
            if ($id === T_FINAL) { $nx = $i + 1; while (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_WHITESPACE) { $nx++; } if (isset($tk[$nx]) && is_array($tk[$nx]) && $tk[$nx][0] === T_CONST) { $why = 'final const'; } }
            if ($id === T_NEW) { /* new en inicializadores: heurística en parámetros por defecto */ $k = $i - 1; while (isset($tk[$k]) && is_array($tk[$k]) && $tk[$k][0] === T_WHITESPACE) { $k--; } if (($tk[$k] ?? '') === '=') { $depth = 0; for ($j = $k - 1; $j > 0 && $j > $k - 40; $j--) { $x = $tk[$j]; if ($x === ')') { $depth++; } elseif ($x === '(') { if ($depth === 0) { $q = $j - 1; while (isset($tk[$q]) && is_array($tk[$q]) && $tk[$q][0] === T_WHITESPACE) { $q--; } if (isset($tk[$q]) && is_array($tk[$q]) && in_array($tk[$q][0], [T_STRING], true)) { $q2 = $q - 1; while (isset($tk[$q2]) && is_array($tk[$q2]) && $tk[$q2][0] === T_WHITESPACE) { $q2--; } if (isset($tk[$q2]) && is_array($tk[$q2]) && $tk[$q2][0] === T_FUNCTION) { $why = 'new en inicializador de parámetro'; } } break; } $depth--; } } } }
            if ($why) { $bad++; echo "$file:$line: $why\n"; }
        }
    }
}
function function_exists_guard(array $tk, int $i): bool
{
    for ($j = max(0, $i - 12); $j < $i; $j++) { if (is_array($tk[$j]) && $tk[$j][1] === 'function_exists') { return true; } }
    return false;
}
echo "Compatibilidad PHP 8.0: $n archivos, $bad hallazgos\n";
exit($bad ? 1 : 0);
