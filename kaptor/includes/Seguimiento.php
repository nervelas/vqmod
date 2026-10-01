<?php
/**
 * Kaptor - Seguimiento de palabras clave.
 *
 * El análisis de una palabra suelta sirve para enseñarle algo a un cliente en
 * una reunión. Esto es otra cosa: llevar cien palabras de varias empresas,
 * medirlas todas cada semana sin tocar nada y poder decir «esta subió seis
 * puestos este mes».
 *
 * TODO ESTÁ MONTADO PARA GASTAR LO MÍNIMO
 * ---------------------------------------
 * Los servicios de búsqueda cobran por CONSULTA, y de ahí salen las tres
 * decisiones de diseño de este archivo:
 *
 *   1. Lo que se guarda no es «la palabra de un cliente», es LA BÚSQUEDA:
 *      consulta + país + idioma + aparato. Si tres clientes pelean por
 *      «colegio bilingüe guatemala», es una sola fila y una sola consulta.
 *
 *   2. De cada búsqueda cuelgan los dominios que se vigilan. Una página de
 *      resultados trae a todo el mundo, así que de una consulta salen todas
 *      las posiciones de golpe: las de tus clientes y las de su competencia.
 *
 *   3. Se piden cien resultados de una vez cuando el servicio lo permite.
 *      Cuesta lo mismo que pedir diez y se ve diez veces más hondo.
 *
 * Con eso, cien palabras medidas una vez por semana son cien consultas por
 * ronda, no las setecientas y pico que costaría hacerlo de la forma obvia.
 *
 * LAS RONDAS LAS EMPUJA EL CRON
 * -----------------------------
 * Medir cien palabras seguidas no cabe en una petición web, así que cada
 * palabra se mide por su cuenta y el cron va avanzando. Si el navegador se
 * cierra, da igual: la ronda sigue sola.
 */
declare(strict_types=1);

final class Seguimiento
{
    /** Palabras que se miden en cada pasada del cron. */
    public const POR_PASADA = 5;

    /** Cada cuántos días se vuelve a medir, si no se dice otra cosa. */
    public const CADA_DIAS = 7;

    // =====================================================================
    //  Alta y baja
    // =====================================================================

    /**
     * Da de alta una búsqueda y le cuelga los dominios que se vigilan.
     *
     * Si la búsqueda ya existe no se duplica: se le añaden los dominios que
     * falten. Es lo que hace que dos clientes con la misma palabra cuesten
     * una sola consulta.
     *
     * @param string[] $dominios
     * @return int el id de la búsqueda
     */
    public static function anotar(string $consulta, array $dominios, array $op = [], ?int $usuarioId = null): int
    {
        $consulta = trim(preg_replace('~\s+~u', ' ', $consulta) ?? '');
        if ($consulta === '') { return 0; }

        $pais    = self::dosLetras((string) ($op['pais'] ?? Ajustes::obtener('buscador_pais', 'gt')), 'gt');
        $idioma  = self::dosLetras((string) ($op['idioma'] ?? Ajustes::obtener('buscador_idioma', 'es')), 'es');
        $aparato = ($op['dispositivo'] ?? 'escritorio') === 'movil' ? 'movil' : 'escritorio';
        $motor   = (string) ($op['motor'] ?? 'google');
        if (!isset(Buscador::motores()[$motor])) { $motor = 'google'; }

        $fila = BD::fila(
            'SELECT `id` FROM `cr_claves`
              WHERE `consulta` = ? AND `pais` = ? AND `idioma` = ? AND `dispositivo` = ? AND `motor` = ?',
            [$consulta, $pais, $idioma, $aparato, $motor]
        );

        if ($fila) {
            $id = (int) $fila['id'];
            BD::actualizar('cr_claves', ['activa' => 1], '`id` = ?', [$id]);
        } else {
            $id = BD::insertar('cr_claves', [
                'usuario_id'  => $usuarioId,
                'consulta'    => mb_substr($consulta, 0, 190),
                'pais'        => $pais,
                'idioma'      => $idioma,
                'dispositivo' => $aparato,
                'motor'       => $motor,
                'profundidad' => max(10, min((int) ($op['profundidad'] ?? 100), 100)),
                'cada_dias'   => max(1, min((int) ($op['cada_dias'] ?? self::CADA_DIAS), 90)),
                'activa'      => 1,
                'creado'      => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ($dominios as $d) {
            self::vigilar($id, (string) $d, (string) ($op['cliente'] ?? ''));
        }
        return $id;
    }

    /** Añade un dominio a una búsqueda ya anotada. */
    public static function vigilar(int $claveId, string $dominio, string $cliente = ''): bool
    {
        $d = Posiciones::normalizarDominio($dominio);
        if ($claveId <= 0 || $d === '') { return false; }

        try {
            BD::ejecutar(
                'INSERT INTO `cr_claves_dominios` (`clave_id`, `dominio`, `cliente`, `creado`)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `cliente` = IF(? <> \'\', ?, `cliente`)',
                [$claveId, $d, mb_substr($cliente, 0, 120), date('Y-m-d H:i:s'),
                 mb_substr($cliente, 0, 120), mb_substr($cliente, 0, 120)]
            );
            return true;
        } catch (Throwable $e) {
            error_log('Kaptor / seguimiento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Alta en bloque: un bloque de texto con una palabra por línea.
     *
     * Admite también «palabra ; dominio» por si cada palabra es de un cliente
     * distinto; si no se pone dominio, se usan los generales.
     *
     * @param string[] $dominios los que valen para todas las líneas
     * @return array{altas:int,palabras:int,dominios:int}
     */
    public static function altaEnBloque(string $texto, array $dominios, array $op = [], ?int $usuarioId = null): array
    {
        $lineas = preg_split('~[\r\n]+~', $texto) ?: [];
        $altas = 0; $palabras = 0; $cuantosDominios = 0;

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') { continue; }

            $suyos = $dominios;
            // «palabra clave ; midominio.com» o «palabra clave | midominio.com»
            if (preg_match('~^(.*?)\s*[;|]\s*(\S+)\s*$~u', $linea, $m)) {
                $linea = trim($m[1]);
                $suyos = array_merge($dominios, [$m[2]]);
            }
            if ($linea === '') { continue; }

            $palabras++;
            $id = self::anotar($linea, $suyos, $op, $usuarioId);
            if ($id > 0) { $altas++; $cuantosDominios += count($suyos); }
        }

        return ['altas' => $altas, 'palabras' => $palabras, 'dominios' => $cuantosDominios];
    }

    /** Quita un dominio de una búsqueda, y la búsqueda si se queda sin ninguno. */
    public static function dejarDeVigilar(int $claveId, string $dominio): void
    {
        BD::ejecutar('DELETE FROM `cr_claves_dominios` WHERE `clave_id` = ? AND `dominio` = ?',
            [$claveId, Posiciones::normalizarDominio($dominio)]);

        $quedan = (int) BD::valor('SELECT COUNT(*) FROM `cr_claves_dominios` WHERE `clave_id` = ?', [$claveId], 0);
        if ($quedan === 0) { self::borrar($claveId); }
    }

    /** Borra una búsqueda vigilada con todo lo suyo. */
    public static function borrar(int $claveId): void
    {
        foreach (BD::todos('SELECT `id` FROM `cr_posiciones` WHERE `clave_id` = ?', [$claveId]) as $f) {
            Posiciones::borrar((int) $f['id'], null);
        }
        BD::ejecutar('DELETE FROM `cr_claves_dominios` WHERE `clave_id` = ?', [$claveId]);
        BD::ejecutar('DELETE FROM `cr_claves` WHERE `id` = ?', [$claveId]);
    }

    // =====================================================================
    //  Medir
    // =====================================================================

    /**
     * Mide UNA búsqueda y guarda el puesto de cada dominio que cuelga de ella.
     *
     * @return array{ok:bool,medidos:int,error:string,consulta:string,creditos:int}
     */
    public static function medirUna(int $claveId): array
    {
        $c = BD::fila('SELECT * FROM `cr_claves` WHERE `id` = ?', [$claveId]);
        if (!$c) { return ['ok' => false, 'medidos' => 0, 'error' => 'La búsqueda ya no existe.', 'consulta' => '', 'creditos' => 0]; }

        $dominios = array_column(
            BD::todos('SELECT `dominio` FROM `cr_claves_dominios` WHERE `clave_id` = ?', [$claveId]),
            'dominio'
        );
        if (!$dominios) {
            self::borrar($claveId);
            return ['ok' => false, 'medidos' => 0, 'error' => 'No tenía ningún dominio.', 'consulta' => (string) $c['consulta'], 'creditos' => 0];
        }

        $m = Posiciones::medirVarios([
            'consulta'    => (string) $c['consulta'],
            'dominios'    => $dominios,
            'motor'       => (string) $c['motor'],
            'pais'        => (string) $c['pais'],
            'idioma'      => (string) $c['idioma'],
            'dispositivo' => (string) $c['dispositivo'],
            'profundidad' => (int) $c['profundidad'],
        ]);

        $ahora = date('Y-m-d H:i:s');
        $creditos = (int) ($m['paginas_vistas'] ?? 0);

        if (empty($m['medible'])) {
            BD::actualizar('cr_claves', [
                'ultimo_error' => mb_substr((string) $m['error'], 0, 255),
                'medida_en'    => $ahora,
            ], '`id` = ?', [$claveId]);
            return ['ok' => false, 'medidos' => 0, 'error' => (string) $m['error'],
                    'consulta' => (string) $c['consulta'], 'creditos' => $creditos];
        }

        // La lista de resultados se guarda UNA vez por ronda, no una por
        // dominio: es la misma página para todos, y repetirla multiplicaría
        // por diez el tamaño de la tabla sin añadir ni un dato.
        $primero = true;
        $medidos = 0;

        foreach ($dominios as $d) {
            $r = $m['medidas'][$d] ?? ['encontrado' => false];
            $uno = array_merge($m, [
                'dominio'     => $d,
                'encontrado'  => !empty($r['encontrado']),
                'posicion'    => $r['puesto']    ?? null,
                'pagina'      => $r['pagina']    ?? null,
                'en_pagina'   => $r['en_pagina'] ?? null,
                'url_hallada' => $r['url']       ?? '',
                'titulo'      => $r['titulo']    ?? '',
            ]);
            // Solo la primera fila se lleva la lista entera y las pruebas.
            if (!$primero) { $uno['resultados'] = []; $uno['snapshots'] = []; }

            if (Posiciones::guardar($uno, $claveId, $c['usuario_id'] !== null ? (int) $c['usuario_id'] : null) > 0) {
                $medidos++;
            }
            $primero = false;
        }

        BD::actualizar('cr_claves', [
            'medida_en'    => $ahora,
            'ultimo_error' => '',
            'creditos'     => (int) $c['creditos'] + $creditos,
        ], '`id` = ?', [$claveId]);

        return ['ok' => true, 'medidos' => $medidos, 'error' => '',
                'consulta' => (string) $c['consulta'], 'creditos' => $creditos];
    }

    /**
     * Las búsquedas a las que les toca medirse.
     *
     * @return array<int,array>
     */
    public static function pendientes(int $limite = self::POR_PASADA): array
    {
        return BD::todos(
            'SELECT `id`, `consulta`, `cada_dias`, `medida_en`
               FROM `cr_claves`
              WHERE `activa` = 1
                AND (`medida_en` IS NULL OR `medida_en` < (NOW() - INTERVAL `cada_dias` DAY))
              ORDER BY `medida_en` IS NOT NULL, `medida_en` ASC, `id` ASC
              LIMIT ' . max(1, min($limite, 100))
        );
    }

    /**
     * Una pasada del cron: mide las que tocan y se va.
     *
     * @return array{medidas:int,dominios:int,creditos:int,lineas:string[]}
     */
    public static function pasada(int $limite = self::POR_PASADA): array
    {
        $lineas = []; $medidas = 0; $dominios = 0; $creditos = 0;

        foreach (self::pendientes($limite) as $c) {
            $r = self::medirUna((int) $c['id']);
            $medidas++;
            $dominios += $r['medidos'];
            $creditos += $r['creditos'];
            $lineas[] = ($r['ok'] ? '  · ' : '  ! ') . $r['consulta']
                      . ($r['ok'] ? ' — ' . $r['medidos'] . ' dominios' : ' — ' . $r['error']);
        }

        return ['medidas' => $medidas, 'dominios' => $dominios, 'creditos' => $creditos, 'lineas' => $lineas];
    }

    /** Vuelve a poner en cola todas las búsquedas: la ronda empieza ya. */
    public static function rondaYa(?int $usuarioId = null): int
    {
        $donde = $usuarioId !== null ? ' WHERE `usuario_id` = ?' : '';
        $args  = $usuarioId !== null ? [$usuarioId] : [];
        BD::ejecutar('UPDATE `cr_claves` SET `medida_en` = NULL, `activa` = 1' . $donde, $args);
        return (int) BD::valor('SELECT COUNT(*) FROM `cr_claves`' . $donde, $args, 0);
    }

    // =====================================================================
    //  Lo que se enseña
    // =====================================================================

    /**
     * El cuadro completo: cada palabra con cada dominio y su puesto de hoy,
     * más el de la vez anterior para poder decir si subió o bajó.
     *
     * @return array<int,array>
     */
    public static function cuadro(?int $usuarioId = null, string $cliente = ''): array
    {
        $donde = []; $args = [];
        if ($usuarioId !== null) { $donde[] = 'c.`usuario_id` = ?'; $args[] = $usuarioId; }
        if ($cliente !== '')     { $donde[] = 'd.`cliente` = ?';    $args[] = $cliente; }
        $sql = $donde ? ' WHERE ' . implode(' AND ', $donde) : '';

        $filas = BD::todos(
            'SELECT c.`id` AS clave_id, c.`consulta`, c.`pais`, c.`idioma`, c.`dispositivo`,
                    c.`motor`, c.`profundidad`, c.`cada_dias`, c.`medida_en`, c.`ultimo_error`,
                    c.`creditos`, d.`dominio`, d.`cliente`
               FROM `cr_claves` c
               JOIN `cr_claves_dominios` d ON d.`clave_id` = c.`id`' . $sql . '
              ORDER BY d.`cliente` ASC, c.`consulta` ASC, d.`dominio` ASC',
            $args
        );
        if (!$filas) { return []; }

        foreach ($filas as &$f) {
            $hist = BD::todos(
                'SELECT `id`, `posicion`, `creado` FROM `cr_posiciones`
                  WHERE `clave_id` = ? AND `dominio` = ?
                  ORDER BY `id` DESC LIMIT 2',
                [(int) $f['clave_id'], (string) $f['dominio']]
            );
            $f['medicion_id'] = isset($hist[0]) ? (int) $hist[0]['id'] : null;
            $f['posicion']    = isset($hist[0]) && $hist[0]['posicion'] !== null ? (int) $hist[0]['posicion'] : null;
            $f['anterior']    = isset($hist[1]) && $hist[1]['posicion'] !== null ? (int) $hist[1]['posicion'] : null;
            $f['cambio']      = ($f['posicion'] !== null && $f['anterior'] !== null)
                ? $f['anterior'] - $f['posicion']    // positivo = subió puestos
                : null;
        }
        unset($f);

        return $filas;
    }

    /** Los clientes que hay dados de alta, para el filtro. */
    public static function clientes(?int $usuarioId = null): array
    {
        $donde = $usuarioId !== null ? ' WHERE c.`usuario_id` = ?' : '';
        $args  = $usuarioId !== null ? [$usuarioId] : [];
        return array_values(array_filter(array_column(BD::todos(
            'SELECT DISTINCT d.`cliente` FROM `cr_claves_dominios` d
               JOIN `cr_claves` c ON c.`id` = d.`clave_id`' . $donde . '
              ORDER BY d.`cliente` ASC', $args
        ), 'cliente')));
    }

    /**
     * Resumen de arriba: cuántas palabras, cuántas en los diez primeros, etc.
     */
    public static function resumen(array $cuadro): array
    {
        $r = ['filas' => count($cuadro), 'busquedas' => [], 'top3' => 0, 'top10' => 0,
              'top30' => 0, 'fuera' => 0, 'suben' => 0, 'bajan' => 0, 'creditos' => 0,
              'sin_medir' => 0];

        $vistas = [];
        foreach ($cuadro as $f) {
            $r['busquedas'][$f['clave_id']] = true;
            if (!isset($vistas[$f['clave_id']])) {
                $vistas[$f['clave_id']] = true;
                $r['creditos'] += (int) $f['creditos'];
            }
            if ($f['medicion_id'] === null) { $r['sin_medir']++; continue; }
            $p = $f['posicion'];
            if ($p === null)      { $r['fuera']++; }
            elseif ($p <= 3)      { $r['top3']++; $r['top10']++; $r['top30']++; }
            elseif ($p <= 10)     { $r['top10']++; $r['top30']++; }
            elseif ($p <= 30)     { $r['top30']++; }
            if (($f['cambio'] ?? 0) > 0) { $r['suben']++; }
            if (($f['cambio'] ?? 0) < 0) { $r['bajan']++; }
        }
        $r['busquedas'] = count($r['busquedas']);
        return $r;
    }

    private static function dosLetras(string $v, string $defecto): string
    {
        $v = strtolower(trim($v));
        return preg_match('~^[a-z]{2}$~', $v) ? $v : $defecto;
    }
}
