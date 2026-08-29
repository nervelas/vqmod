<?php
/**
 * MenúGold · importa fotografía real al menú.
 *
 * Dos maneras, ambas por línea de comandos (SSH o el «Terminal» de cPanel):
 *
 *   1) Desde una carpeta con tus fotos (lo más común):
 *        php tools/importar-fotos.php --carpeta=/home/usuario/fotos --restaurante=1
 *      Cada archivo se asigna al platillo cuyo nombre más se parezca al del
 *      archivo. Ejemplo: "rib-eye.jpg" → «Rib eye maduración 45 días».
 *      Con --portada=archivo.jpg también cambia la portada del restaurante.
 *
 *   2) Desde una lista de direcciones (una por línea, formato: URL | nombre del platillo):
 *        php tools/importar-fotos.php --lista=mis-fotos.txt --restaurante=1
 *
 * Las imágenes pasan por la misma canalización del panel: se recomprimen a
 * WebP con respaldo JPG en tres tamaños, se eliminan los metadatos y se crea
 * el marcador difuminado. Reemplaza las fotografías que trae la demostración.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit("Solo por línea de comandos.\n"); }

define('MG_ROOT', dirname(__DIR__));
define('MG_APP', MG_ROOT . '/app');
define('MG_STORAGE', MG_ROOT . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core',   MG_APP . '/Core');
Autoloader::addNamespace('MenuGold\\Models', MG_APP . '/Models');
require MG_APP . '/Support/helpers.php';

use MenuGold\Core\Config;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Str;

if (!is_file(MG_ROOT . '/config/config.php')) {
    exit("MenúGold no está instalado todavía.\n");
}
Config::load(require MG_ROOT . '/config/config.php');

/* ---- Argumentos ---- */
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : '1';
    }
}
$rid = isset($args['restaurante']) ? (int)$args['restaurante'] : 1;
$seco = isset($args['prueba']);

$rest = DB::first('SELECT * FROM restaurants WHERE id = :id', array('id' => $rid));
if (!$rest) { exit("No existe el restaurante con id $rid.\n"); }
echo "Restaurante: " . $rest['name'] . " (/r/" . $rest['slug'] . ")\n";

$productos = DB::all('SELECT id, name FROM products WHERE restaurant_id = :r ORDER BY id', array('r' => $rid));
if (!$productos) { exit("Ese restaurante no tiene platillos.\n"); }

/** Elige el platillo cuyo nombre más se parece al texto dado. */
function emparejar($texto, array $productos)
{
    $t = Str::slug($texto);
    $mejor = null;
    $mejorPuntaje = 0.0;
    foreach ($productos as $p) {
        $n = Str::slug($p['name']);
        similar_text($t, $n, $pct);
        // Bonus si una cadena contiene a la otra.
        if (strpos($n, $t) !== false || strpos($t, $n) !== false) { $pct += 25; }
        if ($pct > $mejorPuntaje) { $mejorPuntaje = $pct; $mejor = $p; }
    }
    return $mejorPuntaje >= 42 ? array($mejor, $mejorPuntaje) : array(null, $mejorPuntaje);
}

function aplicar($rid, $rutaTmp, $nombreOriginal, $productoId, $seco, $etiqueta)
{
    if ($seco) {
        echo "  [prueba] $etiqueta\n";
        return true;
    }
    try {
        $base = Image::storePath($rutaTmp, $rid, 'platillos', 1600, $nombreOriginal);
        $anterior = DB::value('SELECT image FROM products WHERE id = :id', array('id' => (int)$productoId), '');
        DB::update('products', array('image' => $base), 'id = :id AND restaurant_id = :r',
            array('id' => (int)$productoId, 'r' => $rid));
        if ($anterior && $anterior !== $base) { Image::remove($anterior); }
        echo "  ✓ $etiqueta\n";
        return true;
    } catch (Throwable $e) {
        echo "  ✗ $etiqueta — " . $e->getMessage() . "\n";
        return false;
    }
}

$ok = 0; $fallos = 0; $sinPareja = array();

/* ---- Modo carpeta ---- */
if (!empty($args['carpeta'])) {
    $dir = rtrim($args['carpeta'], '/');
    if (!is_dir($dir)) { exit("No existe la carpeta $dir\n"); }
    $archivos = array();
    foreach ((array)scandir($dir) as $f) {
        if (preg_match('/\.(jpe?g|png|webp)$/i', (string)$f)) { $archivos[] = $dir . '/' . $f; }
    }
    if (!$archivos) { exit("No hay imágenes en $dir\n"); }
    echo count($archivos) . " imágenes encontradas.\n";

    // Portada del restaurante
    if (!empty($args['portada'])) {
        $p = $dir . '/' . basename($args['portada']);
        if (is_file($p) && !$seco) {
            try {
                $base = Image::storePath($p, $rid, 'marca', 1600, 'portada.jpg');
                DB::update('restaurants', array('cover' => $base), 'id = :id', array('id' => $rid));
                echo "  ✓ portada actualizada\n";
            } catch (Throwable $e) { echo "  ✗ portada — " . $e->getMessage() . "\n"; }
        }
    }

    foreach ($archivos as $ruta) {
        $nombre = pathinfo($ruta, PATHINFO_FILENAME);
        if (!empty($args['portada']) && basename($ruta) === basename($args['portada'])) { continue; }
        list($prod, $pct) = emparejar($nombre, $productos);
        if (!$prod) {
            $sinPareja[] = basename($ruta) . ' (parecido ' . round($pct) . '%)';
            continue;
        }
        aplicar($rid, $ruta, basename($ruta), (int)$prod['id'], $seco, basename($ruta) . ' → ' . $prod['name']) ? $ok++ : $fallos++;
    }
}

/* ---- Modo lista de direcciones ---- */
elseif (!empty($args['lista'])) {
    $archivo = $args['lista'];
    if (!is_file($archivo)) { exit("No existe el archivo $archivo\n"); }
    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') { continue; }
        $partes = array_map('trim', explode('|', $linea));
        $url = $partes[0];
        $destino = isset($partes[1]) ? $partes[1] : '';
        if (!preg_match('#^https?://#i', $url)) { echo "  ✗ dirección no válida: $url\n"; $fallos++; continue; }

        $prod = null;
        if ($destino !== '') {
            foreach ($productos as $p) {
                if (mb_strtolower($p['name']) === mb_strtolower($destino)) { $prod = $p; break; }
            }
            if (!$prod) { list($prod, ) = emparejar($destino, $productos); }
        }
        if (!$prod) { $sinPareja[] = $url; continue; }

        $tmp = tempnam(sys_get_temp_dir(), 'mgf');
        $bin = @file_get_contents($url, false, stream_context_create(array(
            'http' => array('timeout' => 25, 'user_agent' => 'MenuGold/1.0 (importador de fotos)', 'follow_location' => 1),
            'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
        )));
        if ($bin === false || strlen($bin) < 2048) {
            echo "  ✗ no se pudo descargar: $url\n";
            @unlink($tmp);
            $fallos++;
            continue;
        }
        file_put_contents($tmp, $bin);
        aplicar($rid, $tmp, basename(parse_url($url, PHP_URL_PATH) ?: 'foto.jpg'), (int)$prod['id'], $seco, $prod['name']) ? $ok++ : $fallos++;
        @unlink($tmp);
    }
}

else {
    echo "Uso:\n";
    echo "  php tools/importar-fotos.php --carpeta=/ruta/con/fotos [--restaurante=1] [--portada=portada.jpg] [--prueba]\n";
    echo "      Importa tus propias fotos emparejándolas por el nombre del archivo.\n\n";
    echo "  php tools/importar-fotos.php --lista=fotos.txt [--restaurante=1] [--prueba]\n";
    echo "      Una línea por foto:  DIRECCIÓN | Nombre del platillo\n";
    exit(1);
}

echo "\n$ok imágenes aplicadas";
if ($fallos)     { echo ", $fallos con error"; }
if ($sinPareja)  { echo ", " . count($sinPareja) . " sin platillo que coincida"; }
echo ".\n";
foreach ($sinPareja as $s) { echo "  · sin pareja: $s\n"; }
