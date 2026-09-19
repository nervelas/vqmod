<?php
/**
 * Kaptor - Exportación de resultados.
 *
 * Tres formatos y tres conjuntos de datos:
 *   TXT    una línea por dato (listo para pegar en cualquier sitio)
 *   CSV    separado por punto y coma, con BOM para que Excel respete los acentos
 *   XLSX   libro de Excel real generado con ZipArchive (sin Composer)
 *
 *   correos    solo los correos electrónicos
 *   telefonos  solo los WhatsApp y teléfonos
 *   todo       ambos (en Excel, cada uno en su propia hoja)
 *
 * El nombre del archivo se construye con el dominio analizado y la fecha:
 *   kaptor-midominio-com-2026-09-18.xlsx
 */
declare(strict_types=1);

final class Exportador
{
    /** Columnas de la tabla de correos. */
    private const COL_CORREOS = [
        'Correo', 'Dominio', 'Niveles que imparte', 'Tipo', 'Confianza', 'MX', 'Veces',
        'Método de detección', 'Página donde se encontró',
    ];

    /** Columnas de la tabla de teléfonos y WhatsApp. */
    private const COL_TELEFONOS = [
        'Número', 'Formato internacional', 'País', 'Es WhatsApp', 'Enlace de chat',
        'Confianza', 'Veces', 'Método de detección', 'Página donde se encontró',
    ];

    /** Nombre de archivo según el dominio analizado y la fecha. */
    public static function nombreArchivo(array $escaneo, string $extension, string $datos = 'correos'): string
    {
        $host  = cr_slug((string) ($escaneo['host'] ?? 'sitio'));
        $fecha = date('Y-m-d', strtotime((string) ($escaneo['inicio'] ?? 'now')) ?: time());
        $sufijo = match ($datos) {
            'telefonos' => '-whatsapp',
            'todo'      => '-completo',
            default     => '',
        };
        return 'kaptor-' . $host . $sufijo . '-' . $fecha . '.' . $extension;
    }

    // ------------------------------------------------------------------- TXT

    /** TXT: un dato por línea. */
    public static function txt(array $correos, array $telefonos, string $datos): string
    {
        $lineas = [];

        if ($datos !== 'telefonos') {
            if ($datos === 'todo' && $correos) { $lineas[] = '# Correos electrónicos'; }
            foreach ($correos as $c) { $lineas[] = $c['correo']; }
        }
        if ($datos !== 'correos') {
            if ($datos === 'todo' && $telefonos) {
                if ($lineas) { $lineas[] = ''; }
                $lineas[] = '# WhatsApp y teléfonos';
            }
            foreach ($telefonos as $t) { $lineas[] = $t['numero']; }
        }

        return implode("\r\n", $lineas) . "\r\n";
    }

    // ------------------------------------------------------------------- CSV

    /** CSV con BOM y separador punto y coma (el que espera Excel en español). */
    public static function csv(array $correos, array $telefonos, array $escaneo, string $datos): string
    {
        $salida = fopen('php://temp', 'r+');
        if ($salida === false) { return ''; }

        $linea = static function (array $campos) use ($salida): void {
            fputcsv($salida, $campos, ';', '"', '');
        };

        $linea(['Kaptor - ' . (string) $escaneo['url_origen']]);
        $linea(['Fecha', cr_fecha((string) $escaneo['inicio'])]);
        $linea([]);

        if ($datos !== 'telefonos') {
            $linea(['CORREOS ELECTRÓNICOS (' . count($correos) . ')']);
            $linea(self::COL_CORREOS);
            foreach ($correos as $c) { $linea(self::filaCorreo($c)); }
            if ($datos === 'todo') { $linea([]); }
        }
        if ($datos !== 'correos') {
            $linea(['WHATSAPP Y TELÉFONOS (' . count($telefonos) . ')']);
            $linea(self::COL_TELEFONOS);
            foreach ($telefonos as $t) { $linea(self::filaTelefono($t)); }
        }

        rewind($salida);
        $contenido = (string) stream_get_contents($salida);
        fclose($salida);

        // BOM UTF-8: sin él, Excel destroza los acentos.
        return "\xEF\xBB\xBF" . $contenido;
    }

    // ------------------------------------------------------------------ XLSX

    /** XLSX real; con "todo" genera una hoja por cada conjunto de datos. */
    public static function xlsx(array $correos, array $telefonos, array $escaneo, string $datos): string
    {
        $libro = new XlsxEscritor('Kaptor · ' . (string) $escaneo['host']);

        if ($datos !== 'telefonos') {
            $filas = [];
            foreach ($correos as $c) {
                $fila = self::filaCorreo($c);
                $fila[4] = (int) $c['confianza'];     // la confianza va como número
                $fila[6] = (int) $c['veces'];
                $filas[] = $fila;
            }
            $libro->agregarHoja('Correos', self::COL_CORREOS, $filas, [34, 24, 30, 12, 11, 7, 8, 40, 52]);
        }

        if ($datos !== 'correos') {
            $filas = [];
            foreach ($telefonos as $t) {
                $fila = self::filaTelefono($t);
                $fila[5] = (int) $t['confianza'];
                $fila[6] = (int) $t['veces'];
                $filas[] = $fila;
            }
            $libro->agregarHoja('WhatsApp y teléfonos', self::COL_TELEFONOS, $filas, [18, 20, 24, 12, 34, 11, 8, 36, 50]);
        }

        return $libro->generar();
    }


    // ------------------------------------------------- lista depurada

    /** Columnas de una lista depurada. */
    private const COL_LISTA = ['Correo', 'Buzón', 'Dominio', 'Extensión', 'Tipo', 'Recibe correo (MX)'];

    /**
     * Exporta una lista ya depurada (la que devuelve Depurador::procesar).
     *
     * @param array<int,array<string,mixed>> $correos
     */
    public static function lista(array $correos, string $formato): string
    {
        if ($formato === 'txt') {
            $lineas = [];
            foreach ($correos as $c) { $lineas[] = (string) $c['correo']; }
            return implode("\r\n", $lineas) . "\r\n";
        }

        $filas = [];
        foreach ($correos as $c) {
            $filas[] = [
                (string) $c['correo'],
                (string) ($c['buzon'] ?? ''),
                (string) ($c['dominio'] ?? ''),
                '.' . (string) ($c['extension'] ?? ''),
                ($c['tipo'] ?? '') === 'generico' ? 'Genérico' : 'Personal',
                $c['mx'] === null ? 'Sin verificar' : ($c['mx'] ? 'Sí' : 'No'),
            ];
        }

        if ($formato === 'xlsx') {
            $libro = new XlsxEscritor('Kaptor · lista depurada');
            $libro->agregarHoja('Lista depurada', self::COL_LISTA, $filas, [34, 22, 26, 13, 12, 18]);
            return $libro->generar();
        }

        $salida = fopen('php://temp', 'r+');
        fputcsv($salida, self::COL_LISTA, ';', '"', '');
        foreach ($filas as $fila) { fputcsv($salida, $fila, ';', '"', ''); }
        rewind($salida);
        $contenido = (string) stream_get_contents($salida);
        fclose($salida);

        return "\xEF\xBB\xBF" . $contenido;
    }

    /** Nombre del archivo de una lista depurada. */
    public static function nombreLista(string $formato, string $etiqueta = ''): string
    {
        $etiqueta = $etiqueta !== '' ? '-' . cr_slug($etiqueta) : '';
        return 'kaptor-lista-depurada' . $etiqueta . '-' . date('Y-m-d') . '.' . $formato;
    }

    // --------------------------------------------------------------- utilidades

    /** Fila común para CSV y XLSX (correos). */
    private static function filaCorreo(array $c): array
    {
        return [
            (string) $c['correo'],
            (string) $c['dominio'],
            Niveles::etiqueta((string) ($c['niveles'] ?? '')),
            ($c['tipo'] ?? '') === 'generico' ? 'Genérico' : 'Personal',
            (string) (int) ($c['confianza'] ?? 0),
            $c['mx'] === null ? 'Sin verificar' : ((int) $c['mx'] === 1 ? 'Sí' : 'No'),
            (string) (int) ($c['veces'] ?? 1),
            (string) ($c['metodo'] ?? ''),
            (string) ($c['url_origen'] ?? ''),
        ];
    }

    /** Fila común para CSV y XLSX (teléfonos y WhatsApp). */
    private static function filaTelefono(array $t): array
    {
        $esWa = (int) ($t['whatsapp'] ?? 0) === 1;
        return [
            (string) $t['numero'],
            (string) ($t['formato'] ?? ''),
            (string) ($t['pais'] ?? ''),
            $esWa ? 'Sí' : 'No',
            $esWa ? Telefono::enlaceWhatsapp((string) $t['numero']) : '',
            (string) (int) ($t['confianza'] ?? 0),
            (string) (int) ($t['veces'] ?? 1),
            (string) ($t['metodo'] ?? ''),
            (string) ($t['url_origen'] ?? ''),
        ];
    }

    /**
     * Envía el archivo al navegador con las cabeceras adecuadas.
     * Limpia cualquier salida previa para que el archivo no se corrompa.
     */
    public static function descargar(string $contenido, string $nombre, string $mime): void
    {
        while (ob_get_level() > 0) { ob_end_clean(); }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . strlen($contenido));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        echo $contenido;
        exit;
    }

    /** Tipo MIME de cada formato. */
    public static function mime(string $formato): string
    {
        return match ($formato) {
            'csv'  => 'text/csv; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/plain; charset=utf-8',
        };
    }
}
