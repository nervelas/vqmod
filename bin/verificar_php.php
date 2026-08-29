<?php
/**
 * Verifica que el codigo funcione en la version minima de PHP que se pide.
 *
 *   php bin/verificar_php.php [8.0]
 *
 * Analiza los archivos con el tokenizador de PHP y busca sintaxis y funciones
 * que solo existan en versiones posteriores a la indicada. Sirve para no
 * depender de tener instalada esa version exacta.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

$objetivo = $argv[1] ?? '8.0';

/** Funciones y clases nativas introducidas despues de PHP 8.0. */
const NATIVAS_POSTERIORES = [
    '8.1' => ['array_is_list', 'enum_exists', 'fsync', 'fdatasync', 'Fiber',
              'ReturnTypeWillChange', 'CurlStringFile'],
    '8.2' => ['mysqli_execute_query', 'curl_upkeep', 'ini_parse_quantity',
              'memory_reset_peak_usage', 'openssl_cipher_key_length',
              'AllowDynamicProperties', 'SensitiveParameter'],
    '8.3' => ['json_validate', 'mb_str_pad', 'str_increment', 'str_decrement',
              'stream_context_set_options', 'ldap_connect_wallet', 'Override'],
    '8.4' => ['array_find', 'array_find_key', 'array_any', 'array_all',
              'mb_trim', 'mb_ltrim', 'mb_rtrim', 'mb_ucfirst', 'mb_lcfirst',
              'request_parse_body', 'http_get_last_response_headers',
              'http_clear_last_response_headers', 'Deprecated'],
];

/** @return list<array{archivo:string,linea:int,version:string,que:string}> */
function analizar(string $archivo): array
{
    $codigo  = (string) file_get_contents($archivo);
    $tokens  = token_get_all($codigo);
    $hallazgos = [];

    $total = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];
        $linea = is_array($token) ? $token[2] : 0;
        $texto = is_array($token) ? $token[1] : $token;

        $agregar = static function (string $version, string $que) use (&$hallazgos, $archivo, $linea): void {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $linea, 'version' => $version, 'que' => $que];
        };

        // ---- sintaxis de 8.1 ----
        if (is_array($token) && $token[0] === T_STRING) {
            $minuscula = strtolower($texto);

            // 'never' como tipo de retorno: viene despues de ':' fuera de una llamada
            if ($minuscula === 'never') {
                for ($j = $i - 1; $j >= 0 && $j > $i - 4; $j--) {
                    if ($tokens[$j] === ':') {
                        $agregar('8.1', "tipo de retorno 'never'");
                        break;
                    }
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    break;
                }
            }

            foreach (NATIVAS_POSTERIORES as $version => $nombres) {
                if (in_array($texto, $nombres, true) || in_array($minuscula, array_map('strtolower', $nombres), true)) {
                    $agregar($version, "usa {$texto}()");
                }
            }
        }

        // enum (8.1)
        if (is_array($token) && defined('T_ENUM') && $token[0] === T_ENUM) {
            $agregar('8.1', 'declara un enum');
        }

        // readonly (8.1 propiedades, 8.2 clases)
        if (is_array($token) && defined('T_READONLY') && $token[0] === T_READONLY) {
            $agregar('8.1', "usa 'readonly'");
        }

        // first-class callable:  algo(...)
        if ($token === '(' && isset($tokens[$i + 1])
            && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_ELLIPSIS
            && isset($tokens[$i + 2]) && $tokens[$i + 2] === ')') {
            $agregar('8.1', 'sintaxis de callable de primera clase  f(...)');
        }

        // octal explicito 0o777 (8.1)
        if (is_array($token) && $token[0] === T_LNUMBER && preg_match('/^0[oO]/', $texto)) {
            $agregar('8.1', "octal explicito {$texto}");
        }

        // constante de clase con tipo:  const int X  (8.3)
        if (is_array($token) && $token[0] === T_CONST) {
            $siguiente = null;
            for ($j = $i + 1; $j < $total; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                $siguiente = $tokens[$j];
                break;
            }
            // const TIPO NOMBRE = ...   ->  dos T_STRING seguidos
            if (is_array($siguiente) && $siguiente[0] === T_STRING) {
                for ($k = $j + 1; $k < $total; $k++) {
                    if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        continue;
                    }
                    if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                        $agregar('8.3', 'constante de clase con tipo declarado');
                    }
                    break;
                }
            }
        }

        // atributos posteriores a 8.0
        if (is_array($token) && $token[0] === T_ATTRIBUTE) {
            $fin = min($i + 8, $total - 1);
            $fragmento = '';
            for ($j = $i; $j <= $fin; $j++) {
                $fragmento .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
            foreach (['ReturnTypeWillChange' => '8.1', 'AllowDynamicProperties' => '8.2',
                      'SensitiveParameter' => '8.2', 'Override' => '8.3', 'Deprecated' => '8.4'] as $attr => $ver) {
                if (str_contains($fragmento, $attr)) {
                    $agregar($ver, "atributo #[{$attr}]");
                }
            }
        }
    }

    // ---- patrones que se ven mejor sobre el texto ----
    $lineas = explode("\n", $codigo);

    foreach ($lineas as $numero => $contenido) {
        $n = $numero + 1;

        // tipos de interseccion en firmas:  function f(A&B $x)
        if (preg_match('/\b(?:function|fn)\s+\w*\s*\([^)]*[A-Z]\w+\s*&\s*[A-Z]\w+\s+\$/', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.1',
                            'que' => 'tipo de interseccion A&B'];
        }

        // property hooks (8.4):  public int $x { get => ... }
        if (preg_match('/\bpublic\b.*\$\w+\s*\{\s*(get|set)\b/', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.4',
                            'que' => 'property hooks'];
        }

        // visibilidad asimetrica (8.4):  public private(set)
        if (preg_match('/\b(public|protected)\s+(private|protected)\(set\)/', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.4',
                            'que' => 'visibilidad asimetrica'];
        }

        // desempaquetado con claves de texto (8.1):  [...$a, 'k' => 1]  dentro del mismo array
        if (preg_match('/\[\s*\.\.\.\$\w+\s*,\s*[\'"]/', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.1',
                            'que' => 'desempaquetado de array con claves de texto'];
        }

        // desempaquetado dentro de una constante (8.1)
        if (preg_match('/^\s*(?:const|final\s+const)\s+\w+\s*=\s*\[[^\]]*\.\.\./', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.1',
                            'que' => 'desempaquetado dentro de una constante'];
        }

        // final const en interfaz (8.1)
        if (preg_match('/^\s*final\s+const\b/', $contenido)) {
            $hallazgos[] = ['archivo' => $archivo, 'linea' => $n, 'version' => '8.1',
                            'que' => "'final const'"];
        }
    }

    return $hallazgos;
}

// ------------------------------------------------------------------ recorrido

$raiz    = dirname(__DIR__);
// Este mismo archivo queda fuera: contiene como texto los patrones que busca.
$excluir = ['/.git/', '/vendor/', '/node_modules/', '/bin/verificar_php.php'];

$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
);

$archivos = [];
foreach ($iterador as $archivo) {
    $ruta = $archivo->getPathname();

    if ($archivo->getExtension() !== 'php') {
        continue;
    }
    foreach ($excluir as $patron) {
        if (str_contains($ruta, $patron)) {
            continue 2;
        }
    }
    $archivos[] = $ruta;
}

sort($archivos);

$todos = [];
$erroresSintaxis = [];

foreach ($archivos as $ruta) {
    $salida = [];
    $codigo = 0;
    exec('php -l ' . escapeshellarg($ruta) . ' 2>&1', $salida, $codigo);
    if ($codigo !== 0) {
        $erroresSintaxis[] = $ruta . ': ' . implode(' ', $salida);
    }

    $todos = array_merge($todos, analizar($ruta));
}

// Solo interesan los hallazgos posteriores a la version objetivo.
$relevantes = array_filter(
    $todos,
    static fn (array $h): bool => version_compare($h['version'], $objetivo, '>')
);

echo "Verificacion de compatibilidad con PHP {$objetivo}\n";
echo str_repeat('=', 58), "\n\n";
echo 'Archivos analizados: ', count($archivos), "\n\n";

if ($erroresSintaxis !== []) {
    echo "ERRORES DE SINTAXIS:\n";
    foreach ($erroresSintaxis as $error) {
        echo '  ', $error, "\n";
    }
    echo "\n";
}

if ($relevantes === []) {
    echo "No se encontro nada que exija una version posterior a PHP {$objetivo}.\n";
    exit($erroresSintaxis === [] ? 0 : 1);
}

echo 'INCOMPATIBILIDADES (', count($relevantes), "):\n\n";

foreach ($relevantes as $h) {
    printf(
        "  PHP %s  %s:%d\n           %s\n",
        $h['version'],
        str_replace($raiz . '/', '', $h['archivo']),
        $h['linea'],
        $h['que']
    );
}

exit(1);
