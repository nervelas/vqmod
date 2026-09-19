<?php
/**
 * Kaptor - Depurador de listas de correo.
 *
 * Recibe cualquier texto pegado (una lista suelta, un CSV exportado de otro
 * programa, una columna copiada de Excel, la firma de mil mensajes...) y
 * devuelve una lista LIMPIA: correos reales, en minúsculas, sin repetidos y
 * filtrados por las extensiones de dominio que se pidan (.com, .com.gt, .edu.gt...).
 *
 * La misma clase la usan la página pública "Depurar lista" y la descarga
 * filtrada de los resultados de un escaneo, para que ambas se comporten igual.
 */
declare(strict_types=1);

final class Depurador
{
    /** Tamano máximo del texto pegado (3 MB ~ 100.000 correos). */
    public const MAX_ENTRADA = 3145728;

    /** Cuántos dominios distintos se comprueban por MX como máximo. */
    private const MAX_MX = 300;

    /**
     * Un dominio suelto o dentro de un enlace. Pide al menos dos etiquetas y
     * una terminación de 2 a 24 letras, que luego se valida contra la lista
     * de extensiones reales.
     */
    public const RE_DOMINIO = '~(?<![A-Za-z0-9.@\-])((?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,24})(?![A-Za-z0-9\-])~';

    /** Cuántos descartes se guardan para ensenar en pantalla. */
    private const MAX_DESCARTES = 400;

    /**
     * Segundos niveles habituales en dominios de dos letras: sirven para que
     * "colegio.edu.gt" cuente como extensión ".edu.gt" y no solo ".gt".
     */
    private const SEGUNDOS_NIVEL = [
        'com', 'edu', 'gob', 'gov', 'org', 'net', 'mil', 'int', 'ac', 'co',
        'or', 'ne', 'go', 'nom', 'info', 'web', 'sld', 'jur', 'ind', 'firm',
    ];

    /** Motivos de descarte en palabras entendibles. */
    public const MOTIVOS = [
        'formato'     => 'No es un correo válido',
        'longitud'    => 'Demasiado largo',
        'buzon'       => 'Buzón mal escrito',
        'dominio'     => 'Dominio mal escrito',
        'archivo'     => 'Es un nombre de archivo, no un correo',
        'tld'         => 'Extensión de dominio inexistente',
        'ejemplo'     => 'Dominio de ejemplo o técnico',
        'repetido'    => 'Repetido',
        'extension'   => 'Fuera de las extensiones pedidas',
        'excluida'    => 'Extensión excluida',
        'rol'         => 'Buzón genérico (info@, ventas@…)',
        'personal'    => 'Buzón personal',
        'noreply'     => 'No admite respuestas (noreply@)',
        'desechable'  => 'Correo temporal o de usar y tirar',
        'suprimido'   => 'Está en la lista de bajas',
        'sin_mx'      => 'El dominio no recibe correo (sin MX)',
        'por_dominio' => 'Ya había otro correo de ese dominio',
    ];

    /** @var array<string,bool> Caché de MX dentro de la misma petición. */
    private static array $mx = [];

    // ------------------------------------------------------------ extensiones

    /**
     * Convierte lo que escriba el usuario en una lista de extensiones limpias.
     *
     * Acepta cualquier formato:  ".com, com.gt"  ·  "*.edu.gt"  ·  "gt net org"
     * y una línea por extensión. "todos", "todo", "*" o vacío = sin filtro.
     *
     * @return string[] Ej. ['com', 'com.gt', 'edu.gt']
     */
    public static function extensiones(string $texto): array
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '' || in_array($texto, ['*', 'todos', 'todas', 'todo', 'all'], true)) {
            return [];
        }

        $piezas = preg_split('~[\s,;|/]+~u', $texto) ?: [];
        $salida = [];

        foreach ($piezas as $pieza) {
            $pieza = trim($pieza);
            if ($pieza === '' || in_array($pieza, ['*', 'todos', 'todas', 'todo', 'all'], true)) { continue; }

            // "*.com.gt" y "@com" se quedan en "com.gt" / "com".
            $pieza = ltrim($pieza, '*@');
            $pieza = trim($pieza, '.');
            // Solo letras, números, guiones y puntos.
            $pieza = preg_replace('~[^a-z0-9.\-]~', '', $pieza) ?? '';
            if ($pieza === '' || !preg_match('~^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$~', $pieza)) { continue; }
            if (strlen($pieza) > 60) { continue; }

            $salida[$pieza] = true;
        }

        return array_keys($salida);
    }

    /**
     * ¿El dominio termina en alguna de las extensiones pedidas?
     * Lista vacía = todo vale.
     *
     * @param string[] $extensiones
     */
    public static function coincide(string $dominio, array $extensiones): bool
    {
        if (!$extensiones) { return true; }
        $dominio = mb_strtolower($dominio, 'UTF-8');

        foreach ($extensiones as $ext) {
            if ($dominio === $ext || str_ends_with($dominio, '.' . $ext)) { return true; }
        }
        return false;
    }

    /**
     * Extensión "bonita" de un dominio: ".com.gt" para colegio.edu.gt sale
     * como "edu.gt" y para tienda.com sale como "com".
     */
    public static function extensionDe(string $dominio): string
    {
        $partes = explode('.', mb_strtolower($dominio, 'UTF-8'));
        $n      = count($partes);
        if ($n < 2) { return $partes[0] ?? ''; }

        $tld = $partes[$n - 1];
        $sld = $partes[$n - 2];

        if ($n >= 3 && strlen($tld) <= 3 && in_array($sld, self::SEGUNDOS_NIVEL, true)) {
            return $sld . '.' . $tld;
        }
        return $tld;
    }

    // -------------------------------------------------------------- depuración

    /**
     * Depura un texto pegado y devuelve la lista limpia mas las estadísticas.
     *
     * Opciones admitidas:
     *   extensiones        string[]  solo estas extensiones (vacío = todas)
     *   excluir            string[]  nunca estas extensiones
     *   solo               string    ''|'generico'|'personal'
     *   quitar_noreply     bool
     *   quitar_desechables bool
     *   quitar_suprimidos  bool      cruza con la lista de bajas
     *   verificar_mx       bool      comprueba que el dominio reciba correo
     *   uno_por_dominio    bool      deja un único correo por dominio
     *   orden              string    'correo'|'dominio'|'original'
     *   limite             int       máximo de correos devueltos (0 = sin tope)
     *
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    public static function procesar(string $texto, array $op = []): array
    {
        $extensiones = self::normalizarLista($op['extensiones'] ?? []);
        $excluir     = self::normalizarLista($op['excluir'] ?? []);
        $solo        = in_array($op['solo'] ?? '', ['generico', 'personal'], true) ? (string) $op['solo'] : '';
        $sinNoreply  = !empty($op['quitar_noreply']);
        $sinTemp     = !empty($op['quitar_desechables']);
        $sinBajas    = !empty($op['quitar_suprimidos']);
        $conMx       = !empty($op['verificar_mx']);
        $unoPorDom   = !empty($op['uno_por_dominio']);
        $orden       = in_array($op['orden'] ?? '', ['correo', 'dominio', 'original'], true) ? (string) $op['orden'] : 'correo';
        $limite      = max(0, (int) ($op['limite'] ?? 0));

        if (strlen($texto) > self::MAX_ENTRADA) {
            $texto = substr($texto, 0, self::MAX_ENTRADA);
        }
        // Desescapa las formas mas comunes de ocultar la arroba en listados.
        $texto = str_ireplace(
            [' (at) ', '(at)', ' [at] ', '[at]', ' at ', '&#64;', '&#x40;', '%40'],
            ['@', '@', '@', '@', '@', '@', '@', '@'],
            $texto
        );
        $texto = str_ireplace([' (dot) ', '(dot)', ' [dot] ', '[dot]'], ['.', '.', '.', '.'], $texto);

        // Solo interesa la coincidencia completa: PREG_PATTERN_ORDER guarda un
        // tercio de memoria frente a PREG_SET_ORDER en listas de cien mil correos.
        $crudos = [];
        if (preg_match_all(Extractor::RE_CORREO, $texto, $m)) {
            $crudos = $m[0];
        }
        unset($m, $texto);

        $stats = [
            'encontrados' => count($crudos),
            'repetidos'   => 0,
            'invalidos'   => 0,
            'extension'   => 0,
            'rol'         => 0,
            'noreply'     => 0,
            'desechables' => 0,
            'suprimidos'  => 0,
            'sin_mx'      => 0,
            'por_dominio' => 0,
            'final'       => 0,
        ];

        $vistos      = [];
        $porDominio  = [];
        $limpios     = [];
        $descartados = [];
        $dominios    = [];
        $extVistas   = [];

        $bajas = [];
        if ($sinBajas) {
            // Se resuelve correo a correo: la tabla de bajas suele ser pequena,
            // pero no queremos cargarla entera en memoria si es enorme.
            $bajas = null;
        }

        foreach ($crudos as $indice => $crudo) {
            unset($crudos[$indice]);   // la lista cruda se va vaciando al avanzar
            $res = Validador::validar($crudo);
            if (!($res['ok'] ?? false)) {
                $stats['invalidos']++;
                self::anotar($descartados, $crudo, (string) ($res['motivo'] ?? 'formato'));
                continue;
            }

            $correo  = (string) $res['correo'];
            $dominio = (string) $res['dominio'];

            if (isset($vistos[$correo])) {
                $stats['repetidos']++;
                continue;
            }
            $vistos[$correo] = true;

            $ext = self::extensionDe($dominio);

            if ($extensiones && !self::coincide($dominio, $extensiones)) {
                $stats['extension']++;
                self::anotar($descartados, $correo, 'extension');
                continue;
            }
            if ($excluir && self::coincide($dominio, $excluir)) {
                $stats['extension']++;
                self::anotar($descartados, $correo, 'excluida');
                continue;
            }

            $tipo = Validador::tipo($correo);
            if ($solo !== '' && $tipo !== $solo) {
                $stats['rol']++;
                self::anotar($descartados, $correo, $solo === 'personal' ? 'rol' : 'personal');
                continue;
            }
            if ($sinNoreply && Validador::esNoReply($correo)) {
                $stats['noreply']++;
                self::anotar($descartados, $correo, 'noreply');
                continue;
            }
            if ($sinTemp && Validador::esDesechable($dominio)) {
                $stats['desechables']++;
                self::anotar($descartados, $correo, 'desechable');
                continue;
            }
            if ($sinBajas && self::enBajas($correo)) {
                $stats['suprimidos']++;
                self::anotar($descartados, $correo, 'suprimido');
                continue;
            }
            if ($unoPorDom && isset($porDominio[$dominio])) {
                $stats['por_dominio']++;
                self::anotar($descartados, $correo, 'por_dominio');
                continue;
            }

            $mx = null;
            if ($conMx) {
                $mx = self::mx($dominio);
                if ($mx === false) {
                    $stats['sin_mx']++;
                    self::anotar($descartados, $correo, 'sin_mx');
                    continue;
                }
            }

            $porDominio[$dominio]   = true;
            $dominios[$dominio]     = ($dominios[$dominio] ?? 0) + 1;
            $extVistas[$ext]        = ($extVistas[$ext] ?? 0) + 1;

            $limpios[] = [
                'correo'    => $correo,
                'buzon'     => (string) $res['buzon'],
                'dominio'   => $dominio,
                'extension' => $ext,
                'tipo'      => $tipo,
                'mx'        => $mx,
            ];
        }

        if ($orden === 'correo') {
            usort($limpios, static fn($a, $b) => strcmp($a['correo'], $b['correo']));
        } elseif ($orden === 'dominio') {
            usort($limpios, static function ($a, $b) {
                return $a['dominio'] === $b['dominio']
                    ? strcmp($a['correo'], $b['correo'])
                    : strcmp($a['dominio'], $b['dominio']);
            });
        }

        if ($limite > 0 && count($limpios) > $limite) {
            $limpios = array_slice($limpios, 0, $limite);
        }

        $stats['final']  = count($limpios);
        $stats['unicos'] = count($vistos);

        arsort($dominios);
        arsort($extVistas);

        return [
            'correos'     => $limpios,
            'resumen'     => $stats,
            'dominios'    => $dominios,
            'extensiones' => $extVistas,
            'descartados' => $descartados,
        ];
    }

    // ---------------------------------------------------------------- dominios

    /**
     * Saca las páginas web que haya dentro de un texto cualquiera.
     *
     * Está pensada para lo que de verdad se pega: el JSON de crt.sh (los
     * registros públicos de certificados), una lista de resultados de Google,
     * un directorio copiado o unos dominios sueltos. Entiende las formas en
     * las que aparecen:
     *
     *   "name_value":"colegio.edu.gt\nwww.colegio.edu.gt"   (JSON con saltos)
     *   *.colegio.edu.gt                                     (comodín de los certificados)
     *   https://www.colegio.edu.gt/contacto                  (enlace completo)
     *   colegio.edu.gt                                       (a secas)
     *
     * Opciones:
     *   extensiones   string[]  solo estas terminaciones
     *   excluir       string[]  nunca estas terminaciones
     *   contiene      string    solo los que lleven alguna de estas palabras
     *   sin_palabra   string    fuera los que lleven alguna de estas palabras
     *   solo_raiz     bool      www.x.edu.gt y mail.x.edu.gt => x.edu.gt
     *   limite        int
     *
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    public static function webs(string $texto, array $op = []): array
    {
        $extensiones = self::normalizarLista($op['extensiones'] ?? []);
        $excluir     = self::normalizarLista($op['excluir'] ?? []);
        $contiene    = self::palabras($op['contiene'] ?? '');
        $sinPalabra  = self::palabras($op['sin_palabra'] ?? '');
        $soloRaiz    = !array_key_exists('solo_raiz', $op) || !empty($op['solo_raiz']);
        $limite      = max(0, (int) ($op['limite'] ?? 0));

        if (strlen($texto) > self::MAX_ENTRADA) {
            $texto = substr($texto, 0, self::MAX_ENTRADA);
        }

        // Los saltos escapados del JSON separan dominios: hay que deshacerlos
        // antes de buscar, o "a.com\nb.com" se leería como un solo dominio.
        $texto = str_replace(['\\n', '\\r', '\\/', '\\u002f'], [" ", " ", '/', '/'], $texto);
        $texto = str_replace(['*.', '"', "'", '<', '>', '(', ')', '[', ']', '{', '}', ','], ' ', $texto);

        $encontrados = [];
        if (preg_match_all(self::RE_DOMINIO, $texto, $m)) {
            $encontrados = $m[0];
        }
        unset($m, $texto);

        $stats = [
            'encontrados' => count($encontrados),
            'repetidos'   => 0,
            'invalidos'   => 0,
            'extension'   => 0,
            'palabra'     => 0,
            'final'       => 0,
        ];

        $vistos = [];
        $lista  = [];
        $extVistas = [];

        foreach ($encontrados as $indice => $crudo) {
            unset($encontrados[$indice]);

            $host = self::limpiarHost($crudo);
            if ($host === '') { $stats['invalidos']++; continue; }

            $ext = self::extensionDe($host);
            if ($ext === '' || !in_array(self::tldDe($host), Validador::listaTlds(), true)) {
                $stats['invalidos']++;
                continue;
            }

            if ($soloRaiz) { $host = self::raizDe($host, $ext); }

            if (isset($vistos[$host])) { $stats['repetidos']++; continue; }
            $vistos[$host] = true;

            if ($extensiones && !self::coincide($host, $extensiones)) { $stats['extension']++; continue; }
            if ($excluir && self::coincide($host, $excluir))          { $stats['extension']++; continue; }

            if ($contiene && !self::llevaPalabra($host, $contiene))   { $stats['palabra']++; continue; }
            if ($sinPalabra && self::llevaPalabra($host, $sinPalabra)) { $stats['palabra']++; continue; }

            $extVistas[$ext] = ($extVistas[$ext] ?? 0) + 1;
            $lista[] = ['web' => $host, 'extension' => $ext];
        }

        usort($lista, static fn($a, $b) => strcmp($a['web'], $b['web']));
        if ($limite > 0 && count($lista) > $limite) { $lista = array_slice($lista, 0, $limite); }

        $stats['final']  = count($lista);
        $stats['unicos'] = count($vistos);
        arsort($extVistas);

        return ['webs' => $lista, 'resumen' => $stats, 'extensiones' => $extVistas];
    }

    /** "colegio, liceo, instituto" => ['colegio','liceo','instituto'] */
    private static function palabras($valor): array
    {
        if (is_array($valor)) { $valor = implode(',', $valor); }
        $valor = mb_strtolower(trim((string) $valor), 'UTF-8');
        if ($valor === '') { return []; }

        $piezas = preg_split('~[\s,;|]+~u', $valor) ?: [];
        $salida = [];
        foreach ($piezas as $pieza) {
            $pieza = preg_replace('~[^a-z0-9\-]~', '', self::sinAcentos($pieza)) ?? '';
            if ($pieza !== '') { $salida[$pieza] = true; }
        }
        return array_keys($salida);
    }

    /** ¿El dominio lleva alguna de esas palabras? */
    private static function llevaPalabra(string $host, array $palabras): bool
    {
        $plano = str_replace(['-', '.'], '', self::sinAcentos($host));
        foreach ($palabras as $p) {
            if (str_contains($host, $p) || str_contains($plano, str_replace('-', '', $p))) { return true; }
        }
        return false;
    }

    /** Deja el host en limpio: sin esquema, sin ruta, sin www, en minúsculas. */
    private static function limpiarHost(string $crudo): string
    {
        $h = mb_strtolower(trim($crudo), 'UTF-8');
        $h = preg_replace('~^[a-z][a-z0-9+.\-]*://~', '', $h) ?? $h;
        $h = explode('/', $h)[0];
        $h = explode('?', $h)[0];
        $h = explode('@', $h)[count(explode('@', $h)) - 1];   // por si venía un correo
        $h = explode(':', $h)[0];
        $h = trim($h, ".-*\t ");
        if ($h === '' || !str_contains($h, '.')) { return ''; }
        if (strlen($h) > 190) { return ''; }
        if (!preg_match('~^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$~', $h)) { return ''; }
        return $h;
    }

    /** Última etiqueta del dominio: colegio.edu.gt => gt */
    private static function tldDe(string $host): string
    {
        $p = explode('.', $host);
        return end($p) ?: '';
    }

    /** www.mail.colegio.edu.gt => colegio.edu.gt */
    private static function raizDe(string $host, string $ext): string
    {
        if ($ext === '' || !str_ends_with($host, '.' . $ext)) { return $host; }
        $sinExt = substr($host, 0, -strlen($ext) - 1);
        $partes = explode('.', $sinExt);
        $nombre = end($partes);
        return $nombre !== '' ? $nombre . '.' . $ext : $host;
    }

    /** Quita las tildes para que "colegío" también valga como "colegio". */
    private static function sinAcentos(string $t): string
    {
        return strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    }

    /**
     * Extensiones presentes en una lista de correos, con su cuenta.
     * Sirve para ofrecer sugerencias antes de filtrar.
     *
     * @param string[] $correos
     * @return array<string,int>
     */
    public static function extensionesDe(array $correos): array
    {
        $cuenta = [];
        foreach ($correos as $correo) {
            $dominio = explode('@', (string) $correo)[1] ?? '';
            if ($dominio === '') { continue; }
            $ext = self::extensionDe($dominio);
            if ($ext === '') { continue; }
            $cuenta[$ext] = ($cuenta[$ext] ?? 0) + 1;
        }
        arsort($cuenta);
        return $cuenta;
    }

    // ------------------------------------------------------------------ apoyo

    /**
     * Acepta una lista ya hecha o un texto suelto y devuelve extensiones limpias.
     *
     * @param mixed $valor
     * @return string[]
     */
    private static function normalizarLista($valor): array
    {
        if (is_array($valor)) { $valor = implode(',', $valor); }
        return self::extensiones((string) $valor);
    }

    /** @param array<int,array<string,string>> $lista */
    private static function anotar(array &$lista, string $valor, string $motivo): void
    {
        if (count($lista) >= self::MAX_DESCARTES) { return; }
        $lista[] = [
            'valor'  => mb_substr($valor, 0, 120),
            'motivo' => $motivo,
            'texto'  => self::MOTIVOS[$motivo] ?? $motivo,
        ];
    }

    /** ¿El correo está dado de baja? Tolera que la tabla no exista todavía. */
    private static function enBajas(string $correo): bool
    {
        try {
            return Supresion::esta($correo);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** MX con caché por dominio y tope de consultas para no bloquear la página. */
    private static function mx(string $dominio): ?bool
    {
        if (isset(self::$mx[$dominio])) { return self::$mx[$dominio]; }
        if (count(self::$mx) >= self::MAX_MX) { return null; }

        try {
            self::$mx[$dominio] = Validador::tieneMx($dominio);
        } catch (Throwable $e) {
            return null;
        }
        return self::$mx[$dominio];
    }
}
