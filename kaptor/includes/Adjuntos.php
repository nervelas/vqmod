<?php
/**
 * Kaptor - Archivos que viajan con el correo.
 *
 * Los archivos de una plantilla se guardan en storage/adjuntos y en la fila
 * de la plantilla solo queda su ficha: nombre, tamaño y dónde está. El archivo
 * NO se guarda en la base de datos; un PDF de cinco megas por plantilla deja
 * la tabla inservible en un mes.
 *
 * Qué se deja subir, y por qué tan poco:
 *
 *   · Nada ejecutable. Ni .exe, ni .js, ni .html: un correo masivo con un
 *     adjunto ejecutable es la definición de lo que los filtros buscan, y
 *     bastaría una cuenta comprometida para repartir un virus con tu dominio.
 *   · Siete megas por archivo y diez en total. Por encima de eso el mensaje
 *     lo rechaza medio mundo, y en base64 crece un tercio más.
 *
 * Y una advertencia que conviene no tapar: un adjunto en un envío en frío
 * BAJA la entrega. Los filtros desconfían, y con razón. Para una presentación
 * comercial entra mucho mejor un enlace a un PDF en tu web.
 */
declare(strict_types=1);

final class Adjuntos
{
    /** Archivos como mucho por plantilla. */
    public const MAX_ARCHIVOS = 5;

    /** Dónde viven. */
    public static function carpeta(): string
    {
        return CR_STORAGE . '/adjuntos';
    }

    /**
     * Guarda un archivo recién subido y devuelve su ficha.
     *
     * @param array $archivo una entrada de $_FILES
     * @return array{ok:bool,error?:string,ficha?:array}
     */
    public static function subir(array $archivo, array $yaHay = []): array
    {
        $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No se eligió ningún archivo.'];
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'El archivo pesa más de lo que admite este servidor.'];
        }
        if ($codigo !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'La subida falló (código ' . $codigo . ').'];
        }

        $tmp = (string) ($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'El archivo no llegó completo. Prueba otra vez.'];
        }

        if (count($yaHay) >= self::MAX_ARCHIVOS) {
            return ['ok' => false, 'error' => 'Ya hay ' . self::MAX_ARCHIVOS . ' archivos, que es el máximo.'];
        }

        $nombre = Mensaje::nombreSeguro((string) ($archivo['name'] ?? 'archivo'));
        $ext    = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));

        if (!isset(Mensaje::TIPOS[$ext])) {
            return ['ok' => false, 'error' => 'No se puede adjuntar un archivo .' . ($ext ?: '?')
                . '. Se admiten: ' . implode(', ', array_keys(Mensaje::TIPOS)) . '.'];
        }

        $peso = (int) filesize($tmp);
        if ($peso <= 0) {
            return ['ok' => false, 'error' => 'El archivo está vacío.'];
        }
        if ($peso > Mensaje::MAX_ADJUNTO) {
            return ['ok' => false, 'error' => 'El archivo pesa ' . self::enMegas($peso)
                . ' y el máximo por archivo es ' . self::enMegas(Mensaje::MAX_ADJUNTO) . '.'];
        }

        $suma = $peso;
        foreach ($yaHay as $f) { $suma += (int) ($f['peso'] ?? 0); }
        if ($suma > Mensaje::MAX_TOTAL) {
            return ['ok' => false, 'error' => 'Entre todos sumarían ' . self::enMegas($suma)
                . ' y el máximo del mensaje es ' . self::enMegas(Mensaje::MAX_TOTAL)
                . '. Muchos servidores rechazan más que eso.'];
        }

        // El tipo real del archivo, no el que diga su nombre: un .exe renombrado
        // a .pdf se queda fuera.
        $real = self::tipoReal($tmp);
        if ($real !== '' && !self::compatible($real, Mensaje::TIPOS[$ext])) {
            return ['ok' => false, 'error' => 'El archivo dice ser .' . $ext . ' pero su contenido es '
                . $real . '. No se sube.'];
        }

        $dir = self::carpeta();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'No se pudo crear storage/adjuntos. Revisa los permisos.'];
        }

        $guardado = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!@move_uploaded_file($tmp, $dir . '/' . $guardado)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor.'];
        }
        @chmod($dir . '/' . $guardado, 0644);

        return ['ok' => true, 'ficha' => [
            'nombre'   => $nombre,
            'archivo'  => $guardado,
            'tipo'     => Mensaje::TIPOS[$ext],
            'peso'     => $peso,
            'subido'   => date('Y-m-d H:i:s'),
        ]];
    }

    /** Borra el archivo de disco. */
    public static function borrar(array $ficha): void
    {
        $archivo = (string) ($ficha['archivo'] ?? '');
        if ($archivo === '' || !preg_match('~^[0-9a-f]{32}\.[a-z0-9]{1,5}$~', $archivo)) { return; }
        @unlink(self::carpeta() . '/' . $archivo);
    }

    /**
     * Lee los archivos de una plantilla, listos para meterlos en el mensaje.
     *
     * Se leen UNA vez por tanda de envío y se reparten entre todos los correos
     * de esa tanda: abrir el mismo PDF cien veces no tiene sentido.
     *
     * @return array<int,array{nombre:string,tipo:string,datos:string}>
     */
    public static function cargar(array $fichas): array
    {
        $salida = [];
        foreach ($fichas as $f) {
            $archivo = (string) ($f['archivo'] ?? '');
            if ($archivo === '' || !preg_match('~^[0-9a-f]{32}\.[a-z0-9]{1,5}$~', $archivo)) { continue; }

            $ruta = self::carpeta() . '/' . $archivo;
            if (!is_file($ruta)) { continue; }

            $datos = @file_get_contents($ruta);
            if (!is_string($datos) || $datos === '') { continue; }

            $salida[] = [
                'nombre' => Mensaje::nombreSeguro((string) ($f['nombre'] ?? 'archivo')),
                'tipo'   => (string) ($f['tipo'] ?? 'application/octet-stream'),
                'datos'  => $datos,
            ];
        }
        return $salida;
    }

    /** Las fichas guardadas en una plantilla. */
    public static function deLaPlantilla(array $plantilla): array
    {
        $j = json_decode((string) ($plantilla['adjuntos'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    /** Cuánto suman todos. */
    public static function peso(array $fichas): int
    {
        $n = 0;
        foreach ($fichas as $f) { $n += (int) ($f['peso'] ?? 0); }
        return $n;
    }

    /** "2,4 MB". */
    public static function enMegas(int $bytes): string
    {
        if ($bytes < 1024) { return $bytes . ' B'; }
        if ($bytes < 1048576) { return number_format($bytes / 1024, 0, ',', '.') . ' KB'; }
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    /** El tipo que dice el contenido del archivo, o '' si no se puede saber. */
    private static function tipoReal(string $ruta): string
    {
        if (!function_exists('finfo_open')) { return ''; }
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f === false) { return ''; }
        $tipo = (string) @finfo_file($f, $ruta);
        finfo_close($f);
        return $tipo;
    }

    /**
     * ¿El contenido real cuadra con lo que dice la extensión?
     *
     * Los formatos de Office son archivos zip por dentro, y hay servidores que
     * los reconocen como zip a secas. Eso no es un engaño, así que se admite.
     */
    private static function compatible(string $real, string $esperado): bool
    {
        if ($real === $esperado) { return true; }

        $zip = ['application/zip', 'application/x-zip', 'application/octet-stream'];
        $ofi = str_contains($esperado, 'openxmlformats') || $esperado === 'application/zip';
        if ($ofi && in_array($real, $zip, true)) { return true; }

        $viejoOffice = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint'];
        if (in_array($esperado, $viejoOffice, true)
            && in_array($real, ['application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'], true)) {
            return true;
        }

        // El texto plano y el CSV se confunden entre sí constantemente.
        if (str_starts_with($esperado, 'text/') && str_starts_with($real, 'text/')) { return true; }

        return false;
    }
}
