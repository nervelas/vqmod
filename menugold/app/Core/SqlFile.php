<?php
namespace MenuGold\Core;

/**
 * Ejecuta un archivo .sql sentencia por sentencia.
 *
 * Se usa en el instalador y al reinstalar los datos de demostración desde el
 * panel. Divide respetando comillas y comentarios, porque los datos de la demo
 * traen textos con punto y coma dentro.
 */
class SqlFile
{
    /**
     * @param string   $file
     * @param string[] $saltar expresiones regulares; la sentencia que coincida no se ejecuta
     * @return int número de sentencias ejecutadas
     */
    public static function run($file, array $saltar = array())
    {
        if (!is_file($file)) {
            throw new \RuntimeException('No se encontró el archivo: ' . basename($file));
        }
        $hechas = 0;
        foreach (self::split((string)file_get_contents($file)) as $s) {
            $s = trim($s);
            if ($s === '') { continue; }
            foreach ($saltar as $re) {
                if (preg_match($re, $s)) { continue 2; }
            }
            DB::pdo()->exec($s);
            $hechas++;
        }
        return $hechas;
    }

    public static function split($sql)
    {
        $out = array();
        $buffer = '';
        $inString = false;
        $quote = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($inString) {
                $buffer .= $ch;
                if ($ch === '\\') { $i++; if ($i < $len) { $buffer .= $sql[$i]; } continue; }
                if ($ch === $quote) { $inString = false; }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') { $inString = true; $quote = $ch; $buffer .= $ch; continue; }
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") { $i++; }
                continue;
            }
            if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
                $i++;
                continue;
            }
            if ($ch === ';') { $out[] = $buffer; $buffer = ''; continue; }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') { $out[] = $buffer; }
        return $out;
    }
}
