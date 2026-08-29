<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Logger;
use MenuGold\Core\PhotoFetcher;
use MenuGold\Core\Str;

/**
 * Descarga fotografía real para los platillos que aún no tienen foto.
 *
 * Trabaja por lotes cortos para no chocar con el límite de tiempo de
 * ejecución de los hosting compartidos: el instalador y el panel llaman
 * a procesar() varias veces hasta terminar.
 */
final class PhotoJob
{
    /** Traducciones de cocina para buscar en bancos indexados en inglés. */
    private static $diccionario = array(
        'a la brasa' => 'grilled', 'a la parrilla' => 'grilled', 'al carbón' => 'grilled',
        'a la leña' => 'wood fired', 'ahumado' => 'smoked', 'ahumada' => 'smoked',
        'al horno' => 'roasted', 'entero' => 'whole', 'entera' => 'whole',
        'de la casa' => '', 'del día' => '', 'de la finca' => '', 'de campo' => 'free range',
        'res' => 'beef', 'cerdo' => 'pork', 'pollo' => 'chicken', 'cordero' => 'lamb',
        'pescado' => 'fish', 'camarones' => 'shrimp', 'camarón' => 'shrimp', 'pulpo' => 'octopus',
        'atún' => 'tuna', 'pargo' => 'red snapper', 'robalo' => 'sea bass',
        'costilla' => 'ribs', 'chuleta' => 'chop', 'lomo' => 'loin',
        'papas' => 'potatoes', 'papa' => 'potato', 'elote' => 'corn', 'maíz' => 'corn',
        'coliflor' => 'cauliflower', 'berenjena' => 'eggplant', 'aguacate' => 'avocado',
        'tomate' => 'tomato', 'ensalada' => 'salad', 'sopa' => 'soup', 'pan' => 'bread',
        'queso' => 'cheese', 'huevos' => 'eggs', 'arroz' => 'rice', 'frijol' => 'beans',
        'postre' => 'dessert', 'helado' => 'ice cream', 'pastel' => 'cake', 'flan' => 'flan',
        'chocolate' => 'chocolate', 'café' => 'coffee', 'vino' => 'wine', 'cerveza' => 'beer',
        'cóctel' => 'cocktail', 'jugo' => 'juice', 'agua' => 'water', 'plátano' => 'plantain',
        'tuétano' => 'bone marrow', 'chorizo' => 'chorizo', 'croquetas' => 'croquettes',
        'ceviche' => 'ceviche', 'tacos' => 'tacos', 'hamburguesa' => 'burger', 'pizza' => 'pizza',
        'pasta' => 'pasta', 'mariscos' => 'seafood', 'parrillada' => 'mixed grill',
    );

    /** Convierte el nombre de un platillo en un término de búsqueda utilizable. */
    public static function termino(array $producto)
    {
        if (!empty($producto['photo_query'])) {
            return trim($producto['photo_query']);
        }
        if (!empty($producto['name_en'])) {
            return trim($producto['name_en']);
        }
        $t = ' ' . mb_strtolower($producto['name']) . ' ';
        foreach (self::$diccionario as $es => $en) {
            $t = str_replace(' ' . $es . ' ', ' ' . $en . ' ', $t);
        }
        // Se quitan medidas, tiempos y adornos que ensucian la búsqueda.
        $t = preg_replace('/\b\d+\s*(g|gr|ml|cl|kg|min|minutos|días|dias|horas|años|anos)\b/u', ' ', $t);
        $t = preg_replace('/\b(de|del|la|el|los|las|con|y|al|a|en|su)\b/u', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));
        return $t !== '' ? $t . ' food' : 'restaurant food';
    }

    /** Tras tres intentos fallidos se deja de insistir con un platillo. */
    const MAX_INTENTOS = 3;

    /** Platillos sin fotografía, en orden de importancia. */
    public static function pendientes($restaurantId, $limite = 200)
    {
        return DB::all(
            "SELECT id, name, name_en, photo_query, image FROM products
             WHERE restaurant_id = :r AND is_active = 1 AND image = ''
               AND photo_tries < " . self::MAX_INTENTOS . "
             ORDER BY is_featured DESC, photo_tries ASC, sort ASC, id ASC
             LIMIT " . max(1, (int)$limite),
            array('r' => (int)$restaurantId)
        );
    }

    /** Lo que queda por intentar (no cuenta lo que ya se dio por imposible). */
    public static function cuantosFaltan($restaurantId)
    {
        $productos = (int)DB::value(
            "SELECT COUNT(*) FROM products WHERE restaurant_id = :r AND is_active = 1 AND image = ''
             AND photo_tries < " . self::MAX_INTENTOS,
            array('r' => (int)$restaurantId), 0);
        $portada = (string)DB::value('SELECT cover FROM restaurants WHERE id = :r', array('r' => (int)$restaurantId), '');
        $intentosPortada = (int)DB::value("SELECT svalue FROM restaurant_settings WHERE restaurant_id = :r AND skey = 'photo_cover_tries'",
            array('r' => (int)$restaurantId), 0);
        return $productos + (($portada === '' && $intentosPortada < self::MAX_INTENTOS) ? 1 : 0);
    }

    /** Sin fotografía y ya sin más intentos: hay que subirla a mano. */
    public static function rendidos($restaurantId)
    {
        return DB::all(
            "SELECT id, name FROM products WHERE restaurant_id = :r AND is_active = 1 AND image = ''
             AND photo_tries >= " . self::MAX_INTENTOS . " ORDER BY sort",
            array('r' => (int)$restaurantId)
        );
    }

    /** Vuelve a poner a cero los intentos para reintentar más tarde. */
    public static function reiniciarIntentos($restaurantId)
    {
        DB::run('UPDATE products SET photo_tries = 0 WHERE restaurant_id = :r', array('r' => (int)$restaurantId));
        Restaurant::setSetting((int)$restaurantId, 'photo_cover_tries', '0');
    }

    /**
     * Procesa un lote. Devuelve el detalle de lo hecho.
     *
     * @return array{hechas:int,fallidas:int,faltan:int,detalle:array}
     */
    public static function procesar($restaurantId, $cuantas = 3)
    {
        $rid = (int)$restaurantId;
        $detalle = array();
        $hechas = 0;
        $fallidas = 0;

        // La portada del restaurante va primero: es lo que más se ve.
        // Lectura fresca: si otro lote ya la puso, no se vuelve a intentar.
        $r = Restaurant::find($rid, true);
        $intentosPortada = (int)DB::value("SELECT svalue FROM restaurant_settings WHERE restaurant_id = :r AND skey = 'photo_cover_tries'",
            array('r' => $rid), 0);
        if ($r && $r['cover'] === '' && $intentosPortada < self::MAX_INTENTOS && $cuantas > 0) {
            $termino = $r['tagline'] !== '' ? $r['tagline'] . ' restaurant interior' : 'restaurant dining room interior';
            $res = self::unaFoto($rid, $termino, 'marca', 'portada');
            if ($res) {
                DB::update('restaurants', array('cover' => $res['base']), 'id = :id', array('id' => $rid));
                Restaurant::forget($rid);
                self::guardarCredito($rid, $res, 'restaurant', $rid);
                $detalle[] = array('que' => 'Portada del restaurante', 'ok' => true, 'autor' => $res['autor'], 'licencia' => $res['licencia']);
                $hechas++;
            } else {
                Restaurant::setSetting($rid, 'photo_cover_tries', (string)($intentosPortada + 1));
                $detalle[] = array('que' => 'Portada del restaurante', 'ok' => false, 'autor' => '', 'licencia' => '');
                $fallidas++;
            }
            $cuantas--;
        }

        foreach (self::pendientes($rid, $cuantas) as $p) {
            if ($cuantas <= 0) { break; }
            $cuantas--;
            $res = self::unaFoto($rid, self::termino($p), 'platillos', Str::slug($p['name']), array($p['name'], $p['name_en']));
            if ($res) {
                DB::update('products', array('image' => $res['base']), 'id = :id AND restaurant_id = :r',
                    array('id' => (int)$p['id'], 'r' => $rid));
                self::guardarCredito($rid, $res, 'product', (int)$p['id']);
                $detalle[] = array('que' => $p['name'], 'ok' => true, 'autor' => $res['autor'], 'licencia' => $res['licencia']);
                $hechas++;
            } else {
                // Se anota el intento para no quedarse dando vueltas al mismo platillo.
                DB::run('UPDATE products SET photo_tries = photo_tries + 1 WHERE id = :id AND restaurant_id = :r',
                    array('id' => (int)$p['id'], 'r' => $rid));
                $detalle[] = array('que' => $p['name'], 'ok' => false, 'autor' => '', 'licencia' => '');
                $fallidas++;
            }
        }

        return array(
            'hechas'   => $hechas,
            'fallidas' => $fallidas,
            'faltan'   => self::cuantosFaltan($rid),
            'detalle'  => $detalle,
        );
    }

    /** Busca, descarga y mete una foto por la canalización de imágenes de la app. */
    private static function unaFoto($rid, $termino, $carpeta, $nombre, array $sinonimos = array())
    {
        try {
            $candidatos = PhotoFetcher::buscar($termino, 1200, 5, array_filter($sinonimos));
            // Primero las no usadas (para que el menú no repita imagen);
            // si todas están usadas, se acepta repetir antes que quedarse sin foto.
            $frescos = array();
            $repetidos = array();
            foreach ($candidatos as $c) {
                if (empty($c['url'])) { continue; }
                if (self::yaUsada($rid, $c['origen'])) { $repetidos[] = $c; } else { $frescos[] = $c; }
            }
            foreach (array_merge($frescos, $repetidos) as $c) {
                $tmp = PhotoFetcher::descargar($c['url']);
                if ($tmp === null) { continue; }
                try {
                    $base = Image::storePath($tmp, $rid, $carpeta, 1600, $nombre . '.jpg');
                } catch (\Throwable $e) {
                    @unlink($tmp);
                    continue;
                }
                @unlink($tmp);
                return array(
                    'base'     => $base,
                    'titulo'   => $c['titulo'],
                    'autor'    => $c['autor'],
                    'licencia' => $c['licencia'],
                    'fuente'   => $c['fuente'],
                    'origen'   => $c['origen'],
                );
            }
        } catch (\Throwable $e) {
            Logger::warn('PhotoJob(' . $termino . '): ' . $e->getMessage());
        }
        return null;
    }

    private static function yaUsada($rid, $origen)
    {
        if ($origen === '') { return false; }
        return (bool)DB::value('SELECT id FROM photo_credits WHERE restaurant_id = :r AND source_url = :u LIMIT 1',
            array('r' => (int)$rid, 'u' => $origen));
    }

    private static function guardarCredito($rid, array $res, $entidad, $entidadId)
    {
        DB::insert('photo_credits', array(
            'restaurant_id' => (int)$rid,
            'image_base'    => $res['base'],
            'entity'        => $entidad,
            'entity_id'     => (int)$entidadId,
            'title'         => mb_substr($res['titulo'], 0, 255),
            'author'        => mb_substr($res['autor'], 0, 255),
            'license'       => mb_substr($res['licencia'], 0, 120),
            'source'        => mb_substr($res['fuente'], 0, 80),
            'source_url'    => mb_substr($res['origen'], 0, 500),
            'created_at'    => date('Y-m-d H:i:s'),
        ));
    }

    public static function creditos($restaurantId)
    {
        return DB::all('SELECT * FROM photo_credits WHERE restaurant_id = :r ORDER BY id DESC',
            array('r' => (int)$restaurantId));
    }
}
