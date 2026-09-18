<?php
/**
 * Kaptor - Listas de contactos.
 *
 * Los contactos entran de dos formas: directamente desde una extracción hecha
 * con Kaptor, o pegando/subiendo un CSV. En ambos casos se aplican los
 * mismos filtros: formato válido, sin duplicados y nunca los que estén en la
 * lista de supresión.
 */
declare(strict_types=1);

final class Contactos
{
    /** Crea una lista y devuelve su id. */
    public static function crearLista(string $nombre, string $descripcion = ''): int
    {
        return BD::insertar('cr_listas', [
            'nombre'      => mb_substr(trim($nombre) ?: 'Lista sin nombre', 0, 160),
            'descripcion' => mb_substr(trim($descripcion), 0, 500),
            'creado'      => date('Y-m-d H:i:s'),
        ]);
    }

    public static function listas(): array
    {
        return BD::todos('SELECT * FROM `cr_listas` ORDER BY `id` DESC');
    }

    public static function lista(int $id): ?array
    {
        return BD::fila('SELECT * FROM `cr_listas` WHERE `id` = ?', [$id]);
    }

    public static function borrarLista(int $id): void
    {
        BD::ejecutar('DELETE FROM `cr_contactos` WHERE `lista_id` = ?', [$id]);
        BD::ejecutar('DELETE FROM `cr_listas` WHERE `id` = ?', [$id]);
    }

    /**
     * Añade un contacto. Devuelve true si se insertó de verdad.
     */
    public static function agregar(int $listaId, string $correo, array $datos = []): bool
    {
        $correo = mb_strtolower(trim($correo));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) { return false; }
        if (Supresion::esta($correo)) { return false; }

        try {
            BD::ejecutar(
                'INSERT INTO `cr_contactos` (`lista_id`,`correo`,`nombre`,`centro`,`dominio`,`telefono`,`origen`,`estado`,`creado`)
                 VALUES (?,?,?,?,?,?,?,\'activo\',NOW())',
                [
                    $listaId,
                    $correo,
                    mb_substr(trim((string) ($datos['nombre'] ?? '')), 0, 160),
                    mb_substr(trim((string) ($datos['centro'] ?? '')), 0, 190),
                    mb_substr((string) ($datos['dominio'] ?? (explode('@', $correo)[1] ?? '')), 0, 190),
                    mb_substr(trim((string) ($datos['telefono'] ?? '')), 0, 24),
                    mb_substr(trim((string) ($datos['origen'] ?? '')), 0, 255),
                ]
            );
            return true;
        } catch (PDOException) {
            return false;   // ya estaba en la lista
        }
    }

    /**
     * Importa los correos de una extracción.
     *
     * @param array{solo_genericos?:bool,confianza_min?:int,solo_mx?:bool} $opciones
     * @return array{anadidos:int,omitidos:int}
     */
    public static function importarDeEscaneo(int $listaId, int $escaneoId, array $opciones = []): array
    {
        $escaneo = Rastreador::escaneo($escaneoId);
        if (!$escaneo) { return ['anadidos' => 0, 'omitidos' => 0]; }

        $confianzaMin = (int) ($opciones['confianza_min'] ?? 0);
        $soloGenericos = !empty($opciones['solo_genericos']);
        $soloMx = !empty($opciones['solo_mx']);

        // Teléfonos del mismo escaneo, por si se quiere guardar el WhatsApp.
        $telefonoPorDominio = [];
        foreach (Rastreador::telefonos($escaneoId) as $t) {
            $dominio = cr_host_de_url((string) $t['url_origen']);
            if ($dominio !== '' && !isset($telefonoPorDominio[$dominio])) {
                $telefonoPorDominio[$dominio] = (string) $t['numero'];
            }
        }

        $anadidos = 0;
        $omitidos = 0;

        foreach (Rastreador::correos($escaneoId) as $c) {
            if ((int) $c['confianza'] < $confianzaMin) { $omitidos++; continue; }
            if ($soloGenericos && $c['tipo'] !== 'generico') { $omitidos++; continue; }
            if ($soloMx && $c['mx'] !== null && (int) $c['mx'] === 0) { $omitidos++; continue; }

            $ok = self::agregar($listaId, (string) $c['correo'], [
                'centro'   => (string) $escaneo['host'],
                'dominio'  => (string) $c['dominio'],
                'telefono' => $telefonoPorDominio[cr_host_de_url((string) $c['url_origen'])] ?? '',
                'origen'   => (string) $c['url_origen'],
            ]);
            $ok ? $anadidos++ : $omitidos++;
        }

        self::actualizarRecuento($listaId);
        return ['anadidos' => $anadidos, 'omitidos' => $omitidos];
    }

    /**
     * Importa desde texto pegado o un CSV.
     *
     * Acepta una dirección por línea, o CSV con cabecera. Se reconocen las
     * columnas correo/email, nombre, centro/empresa/organizacion y telefono.
     *
     * @return array{anadidos:int,omitidos:int}
     */
    public static function importarTexto(int $listaId, string $texto): array
    {
        $anadidos = 0;
        $omitidos = 0;

        $lineas = preg_split('~\r\n|\r|\n~', trim($texto)) ?: [];
        if (!$lineas) { return ['anadidos' => 0, 'omitidos' => 0]; }

        // ¿La primera línea es una cabecera CSV?
        $columnas = [];
        $primera = strtolower($lineas[0]);
        if (str_contains($primera, 'correo') || str_contains($primera, 'email') || str_contains($primera, 'e-mail')) {
            foreach (str_getcsv($lineas[0], self::separador($lineas[0]), '"', '') as $i => $nombre) {
                $columnas[self::normalizarColumna((string) $nombre)] = $i;
            }
            array_shift($lineas);
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') { continue; }

            if ($columnas) {
                $campos = str_getcsv($linea, self::separador($linea), '"', '');
                $correo = trim((string) ($campos[$columnas['correo'] ?? 0] ?? ''));
                $datos = [
                    'nombre'   => isset($columnas['nombre'])   ? (string) ($campos[$columnas['nombre']] ?? '')   : '',
                    'centro'   => isset($columnas['centro'])   ? (string) ($campos[$columnas['centro']] ?? '')   : '',
                    'telefono' => isset($columnas['telefono']) ? (string) ($campos[$columnas['telefono']] ?? '') : '',
                ];
            } else {
                // Línea suelta: puede venir como "Nombre <correo@dominio>"
                if (preg_match('~<([^>]+)>~', $linea, $m)) {
                    $correo = trim($m[1]);
                    $datos = ['nombre' => trim(str_replace($m[0], '', $linea), " \t\"'")];
                } else {
                    $correo = $linea;
                    $datos = [];
                }
            }

            self::agregar($listaId, $correo, $datos) ? $anadidos++ : $omitidos++;
        }

        self::actualizarRecuento($listaId);
        return ['anadidos' => $anadidos, 'omitidos' => $omitidos];
    }

    /** Detecta si el CSV usa coma o punto y coma. */
    private static function separador(string $linea): string
    {
        return substr_count($linea, ';') > substr_count($linea, ',') ? ';' : ',';
    }

    /** Traduce el nombre de una columna a un campo conocido. */
    private static function normalizarColumna(string $nombre): string
    {
        $n = strtolower(trim($nombre, " \t\"'\xEF\xBB\xBF"));
        return match (true) {
            str_contains($n, 'correo') || str_contains($n, 'mail') => 'correo',
            str_contains($n, 'nombre') || str_contains($n, 'contacto') => 'nombre',
            str_contains($n, 'centro') || str_contains($n, 'empresa')
                || str_contains($n, 'organiza') || str_contains($n, 'colegio') => 'centro',
            str_contains($n, 'tel') || str_contains($n, 'whats') => 'telefono',
            default => $n,
        };
    }

    /** Contactos de una lista, con paginación. */
    public static function deLista(int $listaId, int $limite = 100, int $desplazamiento = 0, string $buscar = ''): array
    {
        $sql = 'SELECT * FROM `cr_contactos` WHERE `lista_id` = ?';
        $params = [$listaId];
        if ($buscar !== '') {
            $sql .= ' AND (`correo` LIKE ? OR `nombre` LIKE ? OR `centro` LIKE ?)';
            $params[] = '%' . $buscar . '%';
            $params[] = '%' . $buscar . '%';
            $params[] = '%' . $buscar . '%';
        }
        $sql .= ' ORDER BY `id` DESC LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $desplazamiento);
        return BD::todos($sql, $params);
    }

    /** Recalcula y guarda cuántos contactos activos tiene la lista. */
    public static function actualizarRecuento(int $listaId): int
    {
        $n = (int) BD::valor('SELECT COUNT(*) FROM `cr_contactos` WHERE `lista_id` = ? AND `estado` = \'activo\'', [$listaId], 0);
        BD::actualizar('cr_listas', ['contactos' => $n], '`id` = ?', [$listaId]);
        return $n;
    }

    /** Quita de la lista los contactos que estén suprimidos. */
    public static function limpiarSuprimidos(int $listaId): int
    {
        $n = BD::ejecutar(
            'UPDATE `cr_contactos` c INNER JOIN `cr_supresion` s ON s.`correo` = c.`correo`
             SET c.`estado` = \'suprimido\' WHERE c.`lista_id` = ? AND c.`estado` = \'activo\'',
            [$listaId]
        )->rowCount();
        self::actualizarRecuento($listaId);
        return $n;
    }
}
