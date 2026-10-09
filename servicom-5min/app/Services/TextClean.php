<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Saneo de texto proveniente de archivos del cliente o de la IA.
 *
 * Garantiza: UTF-8 válido, sin caracteres de control ni invisibles
 * peligrosos, sin etiquetas HTML/script, sin secuencias tipo "<?php",
 * espacios normalizados y longitud acotada. Todo lo extraído de archivos
 * debe pasar por aquí antes de guardarse o mostrarse.
 */
class TextClean
{
    /** Convierte a UTF-8 válido descartando bytes inválidos. */
    public static function utf8(string $s): string
    {
        if ($s === '') {
            return '';
        }
        // BOM
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            $s = substr($s, 3);
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        if (function_exists('mb_convert_encoding')) {
            $prev = mb_substitute_character();
            mb_substitute_character('none');
            $r = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
            mb_substitute_character($prev);
            if (is_string($r) && $r !== '') {
                return $r;
            }
        }
        $r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if (is_string($r)) {
            return $r;
        }
        return (string)preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $s);
    }

    /**
     * Limpia un texto.
     *
     * @param string $s          Texto de entrada (cualquier codificación).
     * @param int    $max        Longitud máxima en caracteres (0 = sin límite).
     * @param bool   $multilinea true conserva saltos de línea (máx. 2 seguidos).
     */
    public static function limpiar($s, int $max = 0, bool $multilinea = false): string
    {
        if (is_array($s) || is_object($s) || $s === null) {
            return '';
        }
        $s = self::utf8((string)$s);
        if ($s === '') {
            return '';
        }
        // Un tope duro previo evita trabajo excesivo con entradas gigantes.
        $hard = $max > 0 ? max($max * 8, 4000) : 2000000;
        if (strlen($s) > $hard * 4) {
            $s = self::cortarBytesUtf8($s, $hard * 4);
        }
        $s = str_replace("\0", '', $s);

        // Varias pasadas: decodificar entidades puede revelar etiquetas.
        for ($i = 0; $i < 4; $i++) {
            $antes = $s;
            $s = self::quitarMarcado($s);
            $dec = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $s = self::utf8($dec);
            if ($s === $antes) {
                break;
            }
        }
        $s = self::quitarMarcado($s);
        // Ningún ángulo restante: garantiza "sin HTML".
        $s = str_replace(['<', '>'], '', $s);
        // Entidades numéricas/nombradas sin decodificar que podrían reconstruir marcado.
        $s = (string)preg_replace('/&(?:#x?0*(?:60|3c|62|3e)|lt|gt|quot|apos|amp)\s*;?/i', ' ', $s);

        // Controles e invisibles (conserva \n y \t, que se normalizan después).
        $s = (string)preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', '', $s);
        $s = (string)preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{206F}\x{FEFF}\x{00AD}\x{FFF9}-\x{FFFB}\x{E000}-\x{F8FF}]/u', '', $s);
        $s = (string)preg_replace('/[\x{2028}\x{2029}\x{0085}]/u', "\n", $s);
        // Caracteres no asignados/plano privado inválidos.
        $s = (string)preg_replace('/[\x{FFFE}\x{FFFF}]/u', '', $s);

        // Patrones de código peligrosos que sobrevivan como texto.
        $s = (string)preg_replace('/(?:javascript|vbscript|data)\s*:\s*(?=[a-z\/])/iu', '', $s);
        $s = (string)preg_replace('/\bon[a-z]{3,20}\s*=\s*(?=["\'a-z(])/iu', '', $s);

        if ($multilinea) {
            $s = str_replace(["\r\n", "\r"], "\n", $s);
            $s = (string)preg_replace('/[ \t\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);
            $s = (string)preg_replace('/ ?\n ?/', "\n", $s);
            $s = (string)preg_replace('/\n{3,}/', "\n\n", $s);
        } else {
            $s = (string)preg_replace('/[\s\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);
        }
        $s = trim($s);
        if ($max > 0) {
            $s = self::recortar($s, $max);
        }
        return $s;
    }

    /** Quita scripts, estilos, comentarios, PHP y cualquier etiqueta. */
    private static function quitarMarcado(string $s): string
    {
        $s = (string)preg_replace('#<\s*(script|style|iframe|object|embed|noscript|template|svg|math)\b[^>]*>.*?<\s*/\s*\1\s*>#is', ' ', $s);
        $s = (string)preg_replace('#<\s*(script|style|iframe|object|embed|noscript|template|svg|math)\b.*$#is', ' ', $s);
        $s = (string)preg_replace('/<!--.*?(?:-->|$)/s', ' ', $s);
        $s = (string)preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $s);
        $s = (string)preg_replace('/<\?.*?(?:\?>|$)/s', ' ', $s);
        $s = (string)preg_replace('/<%.*?(?:%>|$)/s', ' ', $s);
        $s = (string)preg_replace('/<![a-z][^>]*>?/i', ' ', $s);
        // Etiquetas: <a ...>, </a>, <br/>; el texto se separa con espacio.
        $s = (string)preg_replace('/<\s*\/?\s*[a-z][^>]*>?/i', ' ', $s);
        $s = (string)preg_replace('/<\s*\/[^>]*>?/', ' ', $s);
        return $s;
    }

    /** Recorta a $max caracteres, preferiblemente en un límite de palabra. */
    public static function recortar(string $s, int $max): string
    {
        if ($max <= 0 || mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        $cut = mb_substr($s, 0, $max, 'UTF-8');
        $pos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($pos !== false && $pos >= (int)($max * 0.6)) {
            $cut = mb_substr($cut, 0, $pos, 'UTF-8');
        }
        // Evita dejar una secuencia ZWJ/selector de variación colgando.
        $cut = (string)preg_replace('/[\x{200D}\x{FE0E}\x{FE0F}\x{1F3FB}-\x{1F3FF}]+$/u', '', $cut);
        return rtrim($cut, " \t\n,;:-");
    }

    /** Corta una cadena a N bytes sin partir un carácter UTF-8. */
    private static function cortarBytesUtf8(string $s, int $bytes): string
    {
        $c = substr($s, 0, $bytes);
        for ($i = 0; $i < 4 && $c !== '' && !mb_check_encoding($c, 'UTF-8'); $i++) {
            $c = substr($c, 0, -1);
        }
        return $c;
    }

    /**
     * Limpia una lista de textos: descarta vacíos y duplicados (sin distinguir
     * mayúsculas) y respeta cantidad y longitud máximas.
     *
     * @param mixed $lista
     * @return string[]
     */
    public static function limpiarLista($lista, int $maxItems = 50, int $maxLen = 120): array
    {
        if (!is_array($lista)) {
            return [];
        }
        $out = [];
        $vistos = [];
        foreach ($lista as $it) {
            if (is_array($it) || is_object($it)) {
                continue;
            }
            $t = self::limpiar((string)$it, $maxLen);
            if ($t === '') {
                continue;
            }
            $k = mb_strtolower($t, 'UTF-8');
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $out[] = $t;
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return $out;
    }
}
