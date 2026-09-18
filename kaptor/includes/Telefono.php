<?php
/**
 * Kaptor - Validación y normalización de números de teléfono y WhatsApp.
 *
 * Todo número detectado se lleva al formato internacional E.164 (+<país><número>)
 * y se comprueba contra la tabla de prefijos: si el país no existe o la longitud
 * nacional no cuadra, se descarta. Además se filtran los falsos positivos
 * típicos de una página web: fechas, precios, identificadores, marcas de tiempo,
 * códigos de barras, versiones y coordenadas.
 */
declare(strict_types=1);

final class Telefono
{
    /** @var array<string,array{0:string,1:string,2:int,3:int}>|null */
    private static ?array $prefijos = null;

    /** Tabla de prefijos internacionales. */
    public static function prefijos(): array
    {
        if (self::$prefijos === null) {
            $archivo = CR_INCLUDES . '/datos/paises.php';
            $lista = is_file($archivo) ? require $archivo : [];
            self::$prefijos = is_array($lista) ? $lista : [];
        }
        return self::$prefijos;
    }

    // ------------------------------------------------------------ normalización

    /**
     * Deja solo los dígitos de un número, resolviendo los prefijos de salida
     * internacional (+, 00, 011) y los ceros de marcación nacional.
     *
     * @return array{digitos:string,internacional:bool}
     */
    public static function limpiar(string $bruto): array
    {
        $bruto = html_entity_decode($bruto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bruto = str_replace(["\u{00A0}", "\u{200B}", "\u{2011}", "\u{2013}", "\u{2014}"], [' ', '', '-', '-', '-'], $bruto);
        $bruto = trim($bruto);

        $internacional = false;

        // %2B es un "+" codificado en una URL.
        $bruto = str_ireplace(['%2b', '&#43;'], '+', $bruto);

        // El "+" puede venir precedido de una etiqueta ("WhatsApp: +57 300…").
        if (preg_match('~\+\s*\d~', $bruto)) {
            $internacional = true;
            // Nos quedamos con lo que hay a partir del "+".
            $bruto = substr($bruto, (int) strpos($bruto, '+'));
        }

        $digitos = preg_replace('~\D+~', '', $bruto) ?? '';
        if ($digitos === '') { return ['digitos' => '', 'internacional' => false]; }

        // Prefijos de salida internacional.
        if (!$internacional) {
            if (str_starts_with($digitos, '00')) {
                $digitos = substr($digitos, 2);
                $internacional = true;
            } elseif (str_starts_with($digitos, '011') && strlen($digitos) >= 13) {
                $digitos = substr($digitos, 3);
                $internacional = true;
            }
        }

        return ['digitos' => ltrim($digitos, '0') !== '' ? $digitos : '', 'internacional' => $internacional];
    }

    /**
     * Valida un número y devuelve su forma internacional.
     *
     * @param bool   $forzarInternacional true cuando la fuente garantiza que el
     *                                    número ya lleva prefijo de país
     *                                    (wa.me, api.whatsapp.com, widgets…).
     * @param string $contexto            Fragmento alrededor del hallazgo.
     * @param bool   $revisarContexto      false cuando la fuente ya es fiable
     *                                    (enlaces wa.me, tel:, widgets…).
     * @return array{ok:bool,motivo?:string,e164?:string,nacional?:string,pais?:string,iso?:string,prefijo?:string,formato?:string}
     */
    public static function validar(string $bruto, bool $forzarInternacional = false, string $contexto = '', bool $revisarContexto = true): array
    {
        $limpio = self::limpiar($bruto);
        $digitos = $limpio['digitos'];

        if ($digitos === '') { return ['ok' => false, 'motivo' => 'vacio']; }
        if (strlen($digitos) < 7 || strlen($digitos) > 15) {
            return ['ok' => false, 'motivo' => 'longitud'];
        }
        // El análisis del contexto solo tiene sentido cuando el número se ha
        // encontrado en texto libre. Si viene de un enlace wa.me, de un widget
        // o de un tel:, la fuente ya garantiza que es un teléfono.
        if (self::esFalsoPositivo($digitos, $bruto, $revisarContexto ? $contexto : '')) {
            return ['ok' => false, 'motivo' => 'falso'];
        }

        $internacional = $forzarInternacional || $limpio['internacional'];

        // Un número nacional puede completarse con el prefijo configurado.
        if (!$internacional) {
            $porDefecto = preg_replace('~\D~', '', Ajustes::obtener('prefijo_pais', '')) ?? '';
            if ($porDefecto !== '' && isset(self::prefijos()[$porDefecto])) {
                [, , $min, $max] = self::prefijos()[$porDefecto];
                $nacional = ltrim($digitos, '0');
                if (strlen($nacional) >= $min && strlen($nacional) <= $max) {
                    $digitos = $porDefecto . $nacional;
                    $internacional = true;
                }
            }
        }
        if (!$internacional) {
            // Sin prefijo de país no se puede saber a quién pertenece.
            return ['ok' => false, 'motivo' => 'sin_prefijo'];
        }

        $pais = self::pais($digitos);
        if ($pais === null) {
            return ['ok' => false, 'motivo' => 'pais'];
        }

        [$prefijo, $iso, $nombre, $min, $max] = $pais;
        $nacional = substr($digitos, strlen($prefijo));

        // Tolerancia de un dígito: hay países con numeración mixta.
        if (strlen($nacional) < $min - 1 || strlen($nacional) > $max + 1) {
            return ['ok' => false, 'motivo' => 'longitud_pais'];
        }

        return [
            'ok'       => true,
            'e164'     => '+' . $digitos,
            'nacional' => $nacional,
            'pais'     => $nombre,
            'iso'      => $iso,
            'prefijo'  => $prefijo,
            'formato'  => self::formatear($prefijo, $nacional),
        ];
    }

    /**
     * Busca el país por el prefijo más largo que coincida.
     *
     * @return array{0:string,1:string,2:string,3:int,4:int}|null [prefijo, iso, nombre, min, max]
     */
    public static function pais(string $digitos): ?array
    {
        $tabla = self::prefijos();
        // Se prueba de 4 a 1 dígitos: así 1787 (Puerto Rico) gana a 1 (EE. UU.).
        for ($largo = 4; $largo >= 1; $largo--) {
            $prefijo = substr($digitos, 0, $largo);
            if (isset($tabla[$prefijo])) {
                [$iso, $nombre, $min, $max] = $tabla[$prefijo];
                return [$prefijo, $iso, $nombre, $min, $max];
            }
        }
        return null;
    }

    /** Presenta el número agrupado y legible: +502 2222 3333 */
    public static function formatear(string $prefijo, string $nacional): string
    {
        $grupos = match (true) {
            strlen($nacional) === 10 => [3, 3, 4],
            strlen($nacional) === 9  => [3, 3, 3],
            strlen($nacional) === 8  => [4, 4],
            strlen($nacional) === 11 => [3, 4, 4],
            strlen($nacional) === 7  => [3, 4],
            default                  => [3, 3, 3, 3, 3],
        };

        $partes = [];
        $pos = 0;
        foreach ($grupos as $g) {
            if ($pos >= strlen($nacional)) { break; }
            $partes[] = substr($nacional, $pos, $g);
            $pos += $g;
        }
        if ($pos < strlen($nacional)) { $partes[] = substr($nacional, $pos); }

        return '+' . $prefijo . ' ' . implode(' ', $partes);
    }

    // ------------------------------------------------------ falsos positivos

    /** Descarta secuencias numéricas que no son teléfonos. */
    public static function esFalsoPositivo(string $digitos, string $bruto, string $contexto = ''): bool
    {
        // Todos los dígitos iguales: 0000000000, 1111111111…
        if (preg_match('~^(\d)\1+$~', $digitos)) { return true; }

        // Secuencias de teclado o de relleno más habituales.
        if (in_array($digitos, ['1234567890', '0123456789', '1234567891', '9876543210', '12345678', '87654321'], true)) {
            return true;
        }

        // Secuencias ascendentes o descendentes: 123456789, 987654321
        $asc = $desc = true;
        for ($i = 1, $n = strlen($digitos); $i < $n; $i++) {
            if ((int) $digitos[$i] !== (int) $digitos[$i - 1] + 1) { $asc = false; }
            if ((int) $digitos[$i] !== (int) $digitos[$i - 1] - 1) { $desc = false; }
        }
        if ($asc || $desc) { return true; }

        // Marca de tiempo Unix (10 dígitos que empiezan por 15-19) o en milisegundos (13).
        if (preg_match('~^1[5-9]\d{8}$~', $digitos) || preg_match('~^1[5-9]\d{11}$~', $digitos)) {
            return true;
        }

        // Códigos de barras EAN-13 / ISBN-13.
        if (strlen($digitos) === 13 && preg_match('~^97[89]~', $digitos)) { return true; }

        // El texto original parece una fecha, una versión, un precio o un rango.
        if (preg_match('~\d{1,2}[/.\-]\d{1,2}[/.\-]\d{2,4}~', $bruto)) { return true; }
        if (preg_match('~^\s*v?\d+(\.\d+){2,}\s*$~', $bruto)) { return true; }
        if (preg_match('~[€$£¥₡₲₱%]~u', $bruto)) { return true; }
        if (preg_match('~\b(19|20)\d{2}\s*[-–]\s*(19|20)\d{2}\b~', $bruto)) { return true; }

        // Contexto claramente técnico (rutas, versiones, hashes, medidas CSS).
        if ($contexto !== '') {
            $pos = strpos($contexto, trim($bruto));
            if ($pos !== false) {
                $antes = substr($contexto, max(0, $pos - 40), min(40, $pos));
                if (preg_match('~(src|href|url|content|width|height|viewBox|d)\s*=\s*["\'(][^"\')]{0,60}$~i', $antes)) {
                    return true;
                }
                if (preg_match('~(px|em|rem|%|ms|s|deg|fr)\s*$~i', $antes)) { return true; }
            }
        }

        return false;
    }

    // ------------------------------------------------------------- utilidades

    /** Enlace directo para abrir el chat de WhatsApp. */
    public static function enlaceWhatsapp(string $e164): string
    {
        return 'https://wa.me/' . ltrim($e164, '+');
    }

    /**
     * Puntuación de confianza 5-99 según de dónde salió el número.
     */
    public static function confianza(string $metodo, bool $esWhatsapp, string $urlOrigen = ''): int
    {
        $p = 58;

        if (str_contains($metodo, 'wa.me') || str_contains($metodo, 'api-whatsapp')) { $p += 32; }
        if (str_contains($metodo, 'widget'))    { $p += 24; }
        if (str_contains($metodo, 'tel'))       { $p += 18; }
        if (str_contains($metodo, 'jsonld') || str_contains($metodo, 'meta')) { $p += 14; }
        if (str_contains($metodo, 'atributo'))  { $p += 8; }
        if (str_contains($metodo, 'texto'))     { $p -= 6; }
        if (str_contains($metodo, 'js') || str_contains($metodo, 'css')) { $p -= 10; }
        if ($esWhatsapp)                        { $p += 6; }

        if ($urlOrigen !== '' && preg_match('~(contact|contacto|nosotros|about|equipo|team|soporte|ayuda|ubicacion|sucursal)~i', $urlOrigen)) {
            $p += 6;
        }
        return max(5, min(99, $p));
    }
}
