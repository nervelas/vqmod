<?php
/**
 * Kaptor - Construcción del mensaje de correo (MIME).
 *
 * Genera un mensaje multipart/alternative (texto + HTML) correcto y con las
 * cabeceras que los proveedores esperan de un envío legítimo:
 *
 *   Date, Message-ID, MIME-Version, From, To, Reply-To, Subject codificado,
 *   List-Unsubscribe y List-Unsubscribe-Post (baja en un clic).
 *
 * Todas las cabeceras se limpian de saltos de línea para impedir la inyección
 * de cabeceras a través del nombre o del asunto.
 */
declare(strict_types=1);

final class Mensaje
{
    private string $frontera;

    /** @var array<string,string> Cabeceras adicionales. */
    private array $extras = [];

    public function __construct(
        private string $de,
        private string $deNombre,
        private string $para,
        private string $paraNombre,
        private string $asunto,
        private string $html,
        private string $texto = '',
        private string $responderA = ''
    ) {
        $this->frontera = '=_Kaptor_' . bin2hex(random_bytes(12));
        if ($this->texto === '') {
            $this->texto = self::htmlATexto($this->html);
        }
    }

    /** Añade una cabecera personalizada (por ejemplo List-Unsubscribe). */
    public function cabecera(string $nombre, string $valor): self
    {
        $this->extras[self::limpiar($nombre)] = self::limpiar($valor);
        return $this;
    }

    /** Dirección del sobre (MAIL FROM). */
    public function remitente(): string
    {
        return $this->de;
    }

    /** Dirección del destinatario (RCPT TO). */
    public function destinatario(): string
    {
        return $this->para;
    }

    /** Identificador único del mensaje, útil para el seguimiento. */
    public function identificador(): string
    {
        static $id = null;
        if ($id === null) {
            $dominio = substr(strrchr($this->de, '@') ?: '@localhost', 1);
            $id = '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . $dominio . '>';
        }
        return $id;
    }

    /** Devuelve el mensaje completo listo para enviar por SMTP. */
    public function construir(): string
    {
        $cabeceras = [
            'Date'         => date('r'),
            'Message-ID'   => $this->identificador(),
            'From'         => self::direccion($this->de, $this->deNombre),
            'To'           => self::direccion($this->para, $this->paraNombre),
            'Subject'      => self::codificarAsunto($this->asunto),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $this->frontera . '"',
        ];
        if ($this->responderA !== '') {
            $cabeceras['Reply-To'] = self::direccion($this->responderA, '');
        }
        foreach ($this->extras as $nombre => $valor) {
            $cabeceras[$nombre] = $valor;
        }

        $salida = '';
        foreach ($cabeceras as $nombre => $valor) {
            $salida .= $nombre . ': ' . $valor . "\r\n";
        }

        $salida .= "\r\n";
        $salida .= "Este mensaje usa varios formatos. Si lo lees así, tu programa de correo no admite MIME.\r\n\r\n";

        // Parte de texto
        $salida .= '--' . $this->frontera . "\r\n";
        $salida .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $salida .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $salida .= self::quotedPrintable($this->texto) . "\r\n\r\n";

        // Parte HTML
        $salida .= '--' . $this->frontera . "\r\n";
        $salida .= "Content-Type: text/html; charset=UTF-8\r\n";
        $salida .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $salida .= self::quotedPrintable($this->html) . "\r\n\r\n";

        $salida .= '--' . $this->frontera . "--\r\n";

        return $salida;
    }

    // ------------------------------------------------------------- utilidades

    /** Quita saltos de línea: evita la inyección de cabeceras. */
    public static function limpiar(string $valor): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $valor));
    }

    /** Formatea "Nombre <correo@dominio>" con el nombre codificado si hace falta. */
    public static function direccion(string $correo, string $nombre = ''): string
    {
        $correo = self::limpiar($correo);
        $nombre = self::limpiar($nombre);
        if ($nombre === '') { return $correo; }

        // Si el nombre lleva acentos o caracteres especiales se codifica.
        $codificado = preg_match('~[^\x20-\x7E]~', $nombre)
            ? '=?UTF-8?B?' . base64_encode($nombre) . '?='
            : '"' . str_replace(['"', '\\'], '', $nombre) . '"';

        return $codificado . ' <' . $correo . '>';
    }

    /**
     * Codifica el asunto según el RFC 2047, partiéndolo en trozos que no
     * superen los 75 caracteres por línea.
     */
    public static function codificarAsunto(string $asunto): string
    {
        $asunto = self::limpiar($asunto);
        if ($asunto === '') { return '(sin asunto)'; }
        if (!preg_match('~[^\x20-\x7E]~', $asunto) && strlen($asunto) <= 75) {
            return $asunto;
        }

        $trozos = [];
        $actual = '';
        // Se corta por caracteres completos para no romper los acentos.
        foreach (preg_split('~~u', $asunto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $caracter) {
            if (strlen(base64_encode($actual . $caracter)) > 45) {
                $trozos[] = $actual;
                $actual = '';
            }
            $actual .= $caracter;
        }
        if ($actual !== '') { $trozos[] = $actual; }

        $partes = [];
        foreach ($trozos as $t) {
            $partes[] = '=?UTF-8?B?' . base64_encode($t) . '?=';
        }
        return implode("\r\n ", $partes);
    }

    /** Codifica el cuerpo en quoted-printable respetando el límite de 76 columnas. */
    public static function quotedPrintable(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $salida = '';

        foreach (explode("\n", $texto) as $linea) {
            $codificada = '';
            $largo = 0;
            $n = strlen($linea);

            for ($i = 0; $i < $n; $i++) {
                $c = $linea[$i];
                $o = ord($c);
                // Imprimibles seguros (el "=" y los no ASCII se codifican).
                $esSeguro = ($o >= 33 && $o <= 126 && $o !== 61);
                // Espacios y tabuladores: solo se codifican al final de la línea.
                if (($c === ' ' || $c === "\t") && $i !== $n - 1) { $esSeguro = true; }

                $pieza = $esSeguro ? $c : sprintf('=%02X', $o);

                if ($largo + strlen($pieza) > 75) {
                    $codificada .= "=\r\n";
                    $largo = 0;
                }
                $codificada .= $pieza;
                $largo += strlen($pieza);
            }
            $salida .= $codificada . "\r\n";
        }

        return rtrim($salida, "\r\n");
    }

    /** Convierte el HTML en una versión de texto legible. */
    public static function htmlATexto(string $html): string
    {
        $t = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        // Los enlaces se convierten en "texto (url)".
        $t = preg_replace_callback(
            '~<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
            static function (array $m): string {
                $texto = trim(strip_tags($m[2]));
                $url   = trim($m[1]);
                if ($texto === '' || stripos($url, $texto) === 0) { return $url; }
                return $texto . ' (' . $url . ')';
            },
            $t
        ) ?? $t;
        $t = preg_replace('~<br\s*/?>~i', "\n", $t) ?? $t;
        $t = preg_replace('~</(p|div|tr|h[1-6]|li)>~i', "\n\n", $t) ?? $t;
        $t = preg_replace('~<li\b[^>]*>~i', '· ', $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('~[ \t]+~', ' ', $t) ?? $t;
        $t = preg_replace('~\n{3,}~', "\n\n", $t) ?? $t;

        return trim($t);
    }
}
