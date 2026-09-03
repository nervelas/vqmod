<?php
/**
 * MenúGold · genera database/demo.sql y las imágenes de la demostración.
 *
 *   php tools/generar-demo.php
 *
 * Solo se usa al preparar el paquete: el restaurante que compra el sistema
 * no lo necesita nunca.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit("Solo por línea de comandos.\n"); }

define('MG_ROOT', dirname(__DIR__));
define('MG_APP', MG_ROOT . '/app');
define('MG_STORAGE', MG_ROOT . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core', MG_APP . '/Core');
require __DIR__ . '/lib-arte.php';

use MenuGold\Core\Image;

/* ---------------------------------------------------------------
   Catálogo
   --------------------------------------------------------------- */

// nombre | inglés | romano | descripción | archivo de la foto de portada
$categorias = array(
    array('Para empezar', 'To start',        'I',   'Bocados chicos para abrir el apetito',         'cat1'),
    array('De la brasa',  'From the grill',  'II',  'Cortes al carbón de encino y leña de manzano', 'cat2'),
    array('Del mar',      'From the sea',    'III', 'Pesca del día y mariscos de la costa sur',     'cat3'),
    array('De la huerta', 'From the garden', 'IV',  'Vegetales de temporada, ahumados y frescos',   'cat4'),
    array('Postres',      'Desserts',        'V',   'Lo dulce, con la misma seriedad',              'cat5'),
    array('La barra',     'The bar',         'VI',  'Coctelería de autor y vinos por copa',         'cat6'),
);

// nombre | inglés | descripción | precio | etiquetas | minutos | destacado | grupos | foto
$platillos = array(
  0 => array(
    array('Tuétano al carbón', 'Charred bone marrow', 'Hueso abierto sobre brasa viva, pan de masa madre y sal de gusano.', 95, 'popular,recomendado', 14, 1, array(), 'tuetano'),
    array('Chorizo de la casa', 'House chorizo', 'Curado once días, con puré de frijol blanco y limón asado.', 78, '', 12, 0, array(), 'chorizo'),
    array('Aguacate a la leña', 'Wood-fired avocado', 'Aguacate ahumado, semilla de calabaza y aceite de cilantro.', 72, 'vegano', 10, 0, array(), 'aguacate'),
    array('Ceviche de la casa', 'House ceviche', 'Corvina, leche de tigre de chile cobán y camote glaseado.', 110, 'nuevo', 12, 1, array(), 'ceviche'),
    array('Croquetas de pernil', 'Pork croquettes', 'Pernil deshebrado doce horas, alioli de ajo negro.', 68, 'popular', 11, 0, array(), 'croquetas'),
    array('Pan de la casa', 'House bread', 'Masa madre de cuatro días, mantequilla de hierbas quemadas.', 38, 'vegetariano', 6, 0, array(), 'pan'),
  ),
  1 => array(
    array('Rib eye maduración 45 días', 'Rib eye, 45-day dry aged', '400 g sobre brasa de encino, mantequilla de tuétano y sal de Maldon.', 320, 'popular,recomendado', 24, 1, array('termino','extras','quitar'), 'ribeye'),
    array('New York de la finca', 'Farm New York strip', '350 g de res de pastoreo, chimichurri de la casa.', 265, 'recomendado', 22, 1, array('termino','extras','quitar'), 'newyork'),
    array('Picaña a la brasa', 'Grilled picanha', 'Corte brasileño con su capa de grasa dorada y farofa de plátano.', 245, '', 24, 0, array('termino','extras'), 'picanha'),
    array('Costilla de res ocho horas', 'Eight-hour short rib', 'Cocida lento y terminada al carbón, con puré de papa ahumada.', 285, 'popular', 18, 1, array('extras','quitar'), 'costillas'),
    array('Cordero de Momostenango', 'Momostenango lamb', 'Rack de cordero con miel de flor de café y romero.', 340, 'recomendado', 26, 0, array('termino','extras'), 'cordero'),
    array('Pollo de campo a la leña', 'Wood-fired free-range chicken', 'Medio pollo marinado en achiote y naranja agria.', 165, '', 28, 0, array('extras','quitar'), 'pollo'),
    array('Chuleta de cerdo ahumada', 'Smoked pork chop', 'Cerdo criollo, salsa de tamarindo y chile pasa.', 195, 'picante', 22, 0, array('extras','quitar'), 'cerdo'),
    array('Parrillada Brasa Negra', 'Grill platter', 'Para dos: rib eye, chorizo, costilla y verduras al carbón.', 620, 'para_compartir,popular', 32, 1, array('termino','extras'), 'parrillada'),
  ),
  2 => array(
    array('Pargo entero a la parrilla', 'Whole grilled snapper', 'Pesca de Champerico, chile guaque y limón quemado.', 275, 'recomendado', 26, 1, array('punto','quitar'), 'pescado'),
    array('Pulpo a la brasa', 'Charred octopus', 'Cocido tres horas y sellado al carbón, papa cambray y pimentón.', 235, 'popular', 20, 1, array('quitar'), 'pulpo'),
    array('Camarones al ajillo', 'Garlic shrimp', 'Camarón jumbo, ajo confitado y guindilla.', 210, 'picante', 16, 0, array('quitar'), 'camarones'),
    array('Atún sellado al carbón', 'Charred tuna', 'Lomo de atún, costra de ajonjolí negro y ponzu de maracuyá.', 255, 'nuevo', 14, 0, array('punto'), 'atun'),
    array('Tiradito de robalo', 'Sea bass tiradito', 'Cortes finos, leche de tigre de rocoto y aceite de albahaca.', 165, '', 12, 0, array(), 'carpaccio'),
  ),
  3 => array(
    array('Coliflor entera al horno', 'Whole roasted cauliflower', 'Rostizada con tahini de ajonjolí y granada.', 135, 'vegetariano,recomendado', 30, 1, array('quitar'), 'coliflor'),
    array('Elote a la parrilla', 'Grilled corn', 'Maíz criollo, mayonesa de chile cobán y queso seco.', 62, 'popular,vegetariano', 10, 0, array(), 'elote'),
    array('Ensalada de tomate criollo', 'Heirloom tomato salad', 'Tomates de Zacapa, albahaca y aceite de oliva del país.', 88, 'vegano', 8, 0, array(), 'tomate'),
    array('Papas al rescoldo', 'Ember-roasted potatoes', 'Enterradas en ceniza caliente, crema agria y cebollín.', 65, 'vegetariano', 22, 0, array(), 'papas'),
    array('Berenjena ahumada', 'Smoked eggplant', 'Con yogur de cabra, miel de agave y menta.', 95, 'vegetariano', 18, 0, array('quitar'), 'berenjena'),
  ),
  4 => array(
    array('Tres leches de café', 'Coffee tres leches', 'Bizcocho empapado en café de Huehuetenango y crema quemada.', 78, 'popular', 8, 1, array(), 'treslehes'),
    array('Flan de coco quemado', 'Burnt coconut flan', 'Textura de crème brûlée, coco tostado y ron añejo.', 72, 'recomendado', 8, 0, array(), 'flan'),
    array('Chocolate de Alta Verapaz', 'Alta Verapaz chocolate', 'Ganache 72 %, sal de mar y aceite de oliva.', 85, '', 10, 0, array(), 'chocolate'),
    array('Helado de leña', 'Wood-smoke ice cream', 'Crema ahumada con madera de manzano y caramelo salado.', 65, 'nuevo', 5, 0, array(), 'helado'),
    array('Plátano al carbón', 'Charred plantain', 'Con crema, canela y helado de vainilla de Cobán.', 58, 'vegetariano', 12, 0, array(), 'platano'),
  ),
  5 => array(
    array('Negroni de la casa', 'House negroni', 'Con vermut infusionado en cardamomo y naranja quemada.', 85, 'popular', 6, 1, array(), 'negroni'),
    array('Mezcal ahumado', 'Smoked mezcal', 'Mezcal espadín, tamarindo, chile de árbol y sal de gusano.', 95, 'picante,recomendado', 6, 0, array(), 'mezcal'),
    array('Ron añejo 12 años', 'Twelve-year aged rum', 'Servido con hielo de una pieza y cáscara de naranja.', 110, '', 3, 0, array(), 'ron'),
    array('Vino tinto por copa', 'Red wine by the glass', 'Malbec de altura, cosecha del año pasado.', 78, '', 3, 0, array(), 'vino'),
    array('Café de olla', 'Spiced pot coffee', 'Café de Huehuetenango con piloncillo y canela.', 35, '', 6, 0, array('leche'), 'cafe'),
    array('Agua de jamaica', 'Hibiscus water', 'Infusión fría con jengibre y limón.', 28, 'vegano', 4, 0, array(), 'jamaica'),
  ),
);

$modificadores = array(
  'termino' => array('Término de la carne', 'Doneness', 'single', 1, 1, 1, array(
      array('Azul', 0), array('Rojo inglés', 0), array('Medio', 0), array('Tres cuartos', 0), array('Bien cocido', 0))),
  'extras' => array('Extras de la brasa', 'Grill extras', 'multi', 0, 4, 0, array(
      array('Tuétano adicional', 45), array('Huevo de campo', 18), array('Chimichurri de la casa', 15),
      array('Salsa de chile cobán', 15), array('Queso fundido', 32), array('Cebolla caramelizada', 20))),
  'quitar' => array('Quitar ingredientes', 'Remove ingredients', 'multi', 0, 5, 0, array(
      array('Sin cilantro', 0), array('Sin cebolla', 0), array('Sin picante', 0), array('Sin lácteos', 0), array('Sin ajo', 0))),
  'punto' => array('Punto del pescado', 'Fish doneness', 'single', 1, 1, 1, array(
      array('Sellado por fuera', 0), array('Al punto', 0), array('Bien cocido', 0))),
  'leche' => array('Tipo de leche', 'Milk', 'single', 0, 1, 0, array(
      array('Entera', 0), array('Deslactosada', 5), array('De almendra', 12), array('Sin leche', 0))),
);

$variantes = array(
  'Rib eye maduración 45 días' => array(array('400 g', 0), array('600 g para compartir', 145)),
  'New York de la finca'       => array(array('350 g', 0), array('500 g', 110)),
  'Café de olla'               => array(array('Taza', 0), array('Jarro', 12)),
);

/* ---------------------------------------------------------------
   Utilidades
   --------------------------------------------------------------- */

function limpiarUploads()
{
    $dir = MG_ROOT . '/uploads';
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if ($f->getFilename() === '.htaccess') { continue; }
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
}

/** Rutas de imagen congeladas: ver tools/fotos/rutas.json. */
function mg_rutas_fijas()
{
    static $rutas = null;
    if ($rutas === null) {
        $j = __DIR__ . '/fotos/rutas.json';
        $rutas = is_file($j) ? (array)json_decode((string)file_get_contents($j), true) : array();
    }
    return $rutas;
}

function logo($letras, $tinta)
{
    $ttf = __DIR__ . '/fuentes/Fraunces.ttf';
    $im = mg_arte_logo(900, $letras, '#0C0B09', $tinta, is_file($ttf) ? $ttf : null);
    $tmp = sys_get_temp_dir() . '/mg-logo-' . bin2hex(random_bytes(4)) . '.png';
    imagepng($im, $tmp);
    imagedestroy($im);
    // Ruta congelada igual que las fotos: al actualizar el paquete el logo
    // se reescribe encima y la base de datos no necesita ni un UPDATE.
    $rutas = mg_rutas_fijas();
    $base = Image::storePath($tmp, 'marca', 960, 'logo.png',
        isset($rutas['logo']) ? $rutas['logo'] : null);
    @unlink($tmp);
    Image::generatePwaIcons($base, '#0C0B09');
    return $base;
}

/**
 * Mete una fotografía de tools/fotos/ en la canalización de imágenes.
 *
 * tools/fotos/rutas.json fija la ruta de cada imagen. Gracias a eso, una
 * actualización del paquete reemplaza las fotos EN SU SITIO: quien ya tiene
 * MenúGold instalado sobrescribe la raíz y ve las fotos nuevas sin tocar la
 * base de datos ni volver a instalar.
 */
function foto($nombre, $carpeta = 'platillos', $ancho = 1600)
{
    $rutas = mg_rutas_fijas();
    $ruta = __DIR__ . '/fotos/' . $nombre . '.jpg';
    if (!is_file($ruta)) {
        fwrite(STDERR, "  ! falta tools/fotos/$nombre.jpg\n");
        return '';
    }
    $fija = isset($rutas[$nombre]) ? $rutas[$nombre] : null;
    return Image::storePath($ruta, $carpeta, $ancho, $nombre . '.jpg', $fija);
}

function q($v)
{
    if ($v === null) { return 'NULL'; }
    if (is_int($v) || is_float($v)) { return (string)$v; }
    if (is_bool($v)) { return $v ? '1' : '0'; }
    return "'" . str_replace(array('\\', "'"), array('\\\\', "''"), (string)$v) . "'";
}

function hash_demo($plain)
{
    // bcrypt: funciona en cualquier PHP 8. La app lo migra a Argon2id al primer acceso.
    return password_hash($plain, PASSWORD_BCRYPT, array('cost' => 11));
}

/* ---------------------------------------------------------------
   Imágenes
   --------------------------------------------------------------- */

echo "Limpiando imágenes anteriores…\n";
limpiarUploads();

echo "Generando logotipo…\n";
$img = array('logo' => logo('BN', '#D8B26E'));

echo "Procesando fotografía…\n";
$img['cover'] = foto('cover1', 'marca', 1600);
$catImg = array();
foreach ($categorias as $c) { $catImg[] = foto($c[4], 'categorias', 960); }
$prodImg = array();
foreach ($platillos as $lista) {
    foreach ($lista as $p) { $prodImg[$p[0]] = foto($p[8]); }
}

/* ---------------------------------------------------------------
   SQL
   --------------------------------------------------------------- */

$sql = array();
$sql[] = "-- MenúGold · menú de demostración. Generado el " . date('Y-m-d H:i') . ".";
$sql[] = "-- Se puede volver a importar cuantas veces haga falta.";
$sql[] = "SET NAMES utf8mb4;";
$sql[] = "";
$sql[] = "DELETE FROM `mg_order_item_modifiers`;";
$sql[] = "DELETE FROM `mg_order_items`;";
$sql[] = "DELETE FROM `mg_orders`;";
$sql[] = "DELETE FROM `mg_service_calls`;";
$sql[] = "DELETE FROM `mg_product_modifier_groups`;";
$sql[] = "DELETE FROM `mg_variants`;";
$sql[] = "DELETE FROM `mg_product_images`;";
$sql[] = "DELETE FROM `mg_products`;";
$sql[] = "DELETE FROM `mg_categories`;";
$sql[] = "DELETE FROM `mg_modifier_options`;";
$sql[] = "DELETE FROM `mg_modifier_groups`;";
$sql[] = "DELETE FROM `mg_coupons`;";
$sql[] = "DELETE FROM `mg_promotions`;";
$sql[] = "DELETE FROM `mg_tables`;";
$sql[] = "DELETE FROM `mg_delivery_zones`;";
$sql[] = "DELETE FROM `mg_customers`;";
$sql[] = "";

/* Ajustes */
$ajustes = array(
    'name'            => 'Brasa Negra',
    'tagline'         => 'Parrilla de leña y cortes madurados',
    'description'     => 'Cocina de fuego en el corazón de la zona 10. Carne madurada en casa, brasa de encino y una barra que no se apura.',
    'logo'            => $img['logo'],
    'cover'           => $img['cover'],
    'phone'           => '+502 2360 4500',
    'whatsapp'        => '50223604500',
    'email'           => 'hola@brasanegra.gt',
    'address'         => '6a avenida 12-45, Zona 10',
    'city'            => 'Ciudad de Guatemala',
    'map_url'         => 'https://maps.google.com/?q=Brasa+Negra',
    'review_url'      => 'https://g.page/r/brasa-negra/review',
    'currency'        => 'Q',
    'tax_rate'        => '12',
    'tax_included'    => '1',
    'tip_enabled'     => '1',
    'tip_options'     => '10,15,20',
    'service_modes'   => 'dine_in,takeaway,delivery',
    'order_mode'      => 'order',
    'theme'           => 'brasa',
    'font_combo'      => 'editorial',
    'primary_color'   => '#D8B26E',
    'accent_color'    => '#C4502B',
    'lang_default'    => 'es',
    'langs'           => 'es,en',
    'timezone'        => 'America/Guatemala',
    'payment_methods' => 'efectivo,tarjeta,transferencia',
    'bank_info'       => "Banco Industrial\nCuenta monetaria 123-456789-0\nA nombre de Brasa Negra, S. A.\nNIT 1234567-8",
    'printer_width'   => '80',
    'kds_sound'       => '1',
    'kds_late_min'    => '18',
    'loyalty_points_per_100' => '5',
);
foreach ($ajustes as $k => $v) {
    $sql[] = "INSERT INTO `mg_settings` (`key`,`value`) VALUES (" . q($k) . "," . q($v) . ") ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);";
}
$sql[] = "";

/* Horario */
foreach (range(0, 6) as $d) {
    $cierra = in_array($d, array(5, 6), true) ? '01:00:00' : '23:00:00';
    $sql[] = "INSERT INTO `mg_hours` (`weekday`,`opens_at`,`closes_at`,`is_closed`) VALUES ($d,'12:00:00'," . q($cierra) . ",0)"
           . " ON DUPLICATE KEY UPDATE `opens_at`=VALUES(`opens_at`), `closes_at`=VALUES(`closes_at`), `is_closed`=VALUES(`is_closed`);";
}
$sql[] = "";

/* Personal (además del dueño que crea el instalador) */
$usuarios = array(
    array('manager', 'Diego Pineda',     'gerente', 'gerente@brasanegra.gt', 'Gerente2026!', '2468'),
    array('kitchen', 'Chef Elder Ramos', 'cocina',  'cocina@brasanegra.gt',  'Cocina2026!',  '1357'),
    array('waiter',  'Sofía Marroquín',  'mesero1', 'mesero@brasanegra.gt',  'Mesero2026!',  '2222'),
    array('waiter',  'Julio Cordón',     'mesero2', '',                      'Mesero2026!',  '3333'),
);
foreach ($usuarios as $u) {
    $sql[] = "INSERT INTO `mg_users` (`role`,`name`,`username`,`email`,`password_hash`,`pin`,`is_active`,`created_at`) VALUES ("
        . q($u[0]) . "," . q($u[1]) . "," . q($u[2]) . "," . q($u[3]) . "," . q(hash_demo($u[4])) . "," . q(hash_demo($u[5])) . ",1,NOW())"
        . " ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);";
}
$sql[] = "";

/* Categorías */
foreach ($categorias as $i => $c) {
    $sql[] = "INSERT INTO `mg_categories` (`id`,`name`,`name_en`,`description`,`image`,`roman`,`sort`,`is_active`,`days_mask`) VALUES ("
        . ($i + 1) . "," . q($c[0]) . "," . q($c[1]) . "," . q($c[3]) . "," . q($catImg[$i]) . "," . q($c[2]) . "," . ($i + 1) . ",1,127);";
}
$sql[] = "";

/* Modificadores */
$gid = 0; $gidMap = array();
foreach ($modificadores as $clave => $g) {
    $gid++; $gidMap[$clave] = $gid;
    $sql[] = "INSERT INTO `mg_modifier_groups` (`id`,`name`,`name_en`,`help`,`type`,`min_select`,`max_select`,`is_required`,`sort`) VALUES ("
        . $gid . "," . q($g[0]) . "," . q($g[1]) . ",''," . q($g[2]) . "," . (int)$g[3] . "," . (int)$g[4] . "," . (int)$g[5] . "," . $gid . ");";
    foreach ($g[6] as $oi => $o) {
        $sql[] = "INSERT INTO `mg_modifier_options` (`group_id`,`name`,`price_delta`,`is_default`,`is_active`,`sort`) VALUES ("
            . $gid . "," . q($o[0]) . "," . number_format((float)$o[1], 2, '.', '') . "," . ($oi === 0 && $g[5] ? 1 : 0) . ",1," . $oi . ");";
    }
}
$sql[] = "";

/* Platillos */
$pid = 0; $idPorNombre = array();
foreach ($platillos as $ci => $lista) {
    foreach ($lista as $p) {
        $pid++;
        $idPorNombre[$p[0]] = $pid;
        $sql[] = "INSERT INTO `mg_products` (`id`,`category_id`,`name`,`name_en`,`description`,`description_en`,`price`,`image`,`prep_minutes`,`tags`,`is_active`,`is_featured`,`sort`,`created_at`) VALUES ("
            . $pid . "," . ($ci + 1) . "," . q($p[0]) . "," . q($p[1]) . "," . q($p[2]) . ",'',"
            . number_format((float)$p[3], 2, '.', '') . "," . q(isset($prodImg[$p[0]]) ? $prodImg[$p[0]] : '') . ","
            . (int)$p[5] . "," . q($p[4]) . ",1," . (int)$p[6] . "," . $pid . ",NOW());";
        foreach ($p[7] as $clave) {
            if (isset($gidMap[$clave])) {
                $sql[] = "INSERT INTO `mg_product_modifier_groups` (`product_id`,`group_id`,`sort`) VALUES ($pid," . $gidMap[$clave] . ",0);";
            }
        }
        if (isset($variantes[$p[0]])) {
            foreach ($variantes[$p[0]] as $vi => $v) {
                $sql[] = "INSERT INTO `mg_variants` (`product_id`,`name`,`price_delta`,`is_default`,`sort`) VALUES ("
                    . $pid . "," . q($v[0]) . "," . number_format((float)$v[1], 2, '.', '') . "," . ($v[1] == 0 ? 1 : 0) . "," . $vi . ");";
            }
        }
    }
}
$sql[] = "";

/* Mesas */
$zonas = array(array('Salón', 6), array('Terraza', 4), array('Barra', 2));
$tid = 0;
foreach ($zonas as $z) {
    for ($i = 0; $i < $z[1]; $i++) {
        $tid++;
        $token = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        $sql[] = "INSERT INTO `mg_tables` (`id`,`name`,`zone`,`seats`,`qr_token`,`status`,`is_active`,`sort`) VALUES ("
            . $tid . ",'Mesa " . $tid . "'," . q($z[0]) . "," . ($z[0] === 'Barra' ? 2 : 4) . "," . q($token) . ",'free',1," . $tid . ");";
    }
}
$sql[] = "";

/* Zonas de entrega */
$entregas = array(array('Zona 10', 25, 150, 30), array('Zona 14', 30, 200, 35), array('Zona 15', 35, 200, 45), array('Zona 4', 25, 150, 30));
foreach ($entregas as $i => $z) {
    $sql[] = "INSERT INTO `mg_delivery_zones` (`name`,`fee`,`min_total`,`minutes`,`is_active`,`sort`) VALUES ("
        . q($z[0]) . "," . number_format((float)$z[1], 2, '.', '') . "," . number_format((float)$z[2], 2, '.', '') . "," . (int)$z[3] . ",1," . $i . ");";
}
$sql[] = "";

/* Promociones y cupones */
$sql[] = "INSERT INTO `mg_promotions` (`name`,`type`,`value`,`scope`,`target_id`,`starts_at`,`ends_at`,`days_mask`,`is_active`) VALUES ("
       . q('Martes de la huerta') . ",'percent',15.00,'category',4,NULL,NULL,4,1);";
$sql[] = "INSERT INTO `mg_promotions` (`name`,`type`,`value`,`scope`,`target_id`,`starts_at`,`ends_at`,`days_mask`,`is_active`) VALUES ("
       . q('Postre de la casa') . ",'amount',15.00,'category',5,NULL,NULL,127,1);";
$cupones = array(array('BIENVENIDA', 'percent', 10, 0, 0), array('BRASA50', 'amount', 50, 300, 100), array('LEALTAD', 'percent', 15, 200, 0));
foreach ($cupones as $c) {
    $sql[] = "INSERT INTO `mg_coupons` (`code`,`type`,`value`,`min_total`,`max_uses`,`used_count`,`is_active`) VALUES ("
        . q($c[0]) . "," . q($c[1]) . "," . number_format((float)$c[2], 2, '.', '') . ","
        . number_format((float)$c[3], 2, '.', '') . "," . (int)$c[4] . ",0,1);";
}
$sql[] = "";

/* Clientes */
$clientes = array(
    array('Ana Lucía Ortega', '50255512233', 'ana@correo.gt', '12 calle 4-30, Zona 14'),
    array('Rodrigo Barrios',  '50255544556', '', '5a avenida 8-12, Zona 10'),
    array('Karla Méndez',     '50255577889', 'karla@correo.gt', 'Boulevard Vista Hermosa 22, Zona 15'),
    array('Óscar Ruiz',       '50255511224', '', '3a calle 1-05, Zona 4'),
);
foreach ($clientes as $i => $c) {
    $sql[] = "INSERT INTO `mg_customers` (`id`,`name`,`phone`,`email`,`address`,`orders_count`,`total_spent`,`points`,`last_order_at`,`created_at`) VALUES ("
        . ($i + 1) . "," . q($c[0]) . "," . q($c[1]) . "," . q($c[2]) . "," . q($c[3]) . ",0,0.00,0,NULL,NOW());";
}
$sql[] = "";

/* ---------------------------------------------------------------
   Pedidos históricos + tres en vivo
   --------------------------------------------------------------- */

mt_srand(20260829);
$catalogo = array();
foreach ($platillos as $ci => $lista) {
    foreach ($lista as $p) { $catalogo[] = array('id' => $idPorNombre[$p[0]], 'name' => $p[0], 'price' => (float)$p[3]); }
}

$oid = 0; $itemId = 0;
$hechos = 0;
for ($d = 27; $d >= 0; $d--) {
    $cuantos = ($d % 7 === 5 || $d % 7 === 6) ? mt_rand(3, 5) : mt_rand(1, 3);
    for ($k = 0; $k < $cuantos; $k++) {
        $oid++; $hechos++;
        $cuando = date('Y-m-d H:i:s', strtotime('-' . $d . ' days ' . mt_rand(12, 22) . ':' . sprintf('%02d', mt_rand(0, 59))));
        $modo = array('dine_in', 'dine_in', 'dine_in', 'takeaway', 'delivery')[mt_rand(0, 4)];
        $mesa = $modo === 'dine_in' ? mt_rand(1, 12) : 'NULL';
        $cliente = $modo === 'dine_in' ? 'NULL' : mt_rand(1, 4);

        $lineas = array(); $subtotal = 0.0;
        $n = mt_rand(1, 4);
        for ($j = 0; $j < $n; $j++) {
            $p = $catalogo[mt_rand(0, count($catalogo) - 1)];
            $qty = mt_rand(1, 2);
            $total = $p['price'] * $qty;
            $subtotal += $total;
            $lineas[] = array($p, $qty, $total);
        }
        $envio = $modo === 'delivery' ? 25.0 : 0.0;
        $propina = round($subtotal * (array(0, 0.10, 0.15)[mt_rand(0, 2)]), 2);
        $totalPedido = round($subtotal + $envio + $propina, 2);
        $metodo = array('efectivo', 'tarjeta', 'transferencia')[mt_rand(0, 2)];

        $sql[] = "INSERT INTO `mg_orders` (`id`,`code`,`public_token`,`mode`,`table_id`,`customer_id`,`customer_name`,`customer_phone`,`status`,`subtotal`,`discount`,`delivery_fee`,`tip`,`tax`,`total`,`payment_method`,`payment_status`,`waiter_id`,`placed_at`,`ready_at`,`closed_at`) VALUES ("
            . $oid . ",'" . strtoupper(substr(md5((string)$oid . 'mg'), 0, 6)) . "',"
            . q(rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=')) . ","
            . q($modo) . "," . $mesa . "," . $cliente . ",''," . "''," . "'closed',"
            . number_format($subtotal, 2, '.', '') . ",0.00," . number_format($envio, 2, '.', '') . ","
            . number_format($propina, 2, '.', '') . ",0.00," . number_format($totalPedido, 2, '.', '') . ","
            . q($metodo) . ",'paid',NULL," . q($cuando) . ","
            . q(date('Y-m-d H:i:s', strtotime($cuando) + 60 * mt_rand(9, 22))) . ","
            . q(date('Y-m-d H:i:s', strtotime($cuando) + 60 * mt_rand(35, 90))) . ");";
        foreach ($lineas as $l) {
            $itemId++;
            $sql[] = "INSERT INTO `mg_order_items` (`id`,`order_id`,`product_id`,`name`,`qty`,`unit_price`,`line_total`,`status`) VALUES ("
                . $itemId . "," . $oid . "," . $l[0]['id'] . "," . q($l[0]['name']) . "," . $l[1] . ","
                . number_format($l[0]['price'], 2, '.', '') . "," . number_format($l[2], 2, '.', '') . ",'done');";
        }
    }
}

/* Tres pedidos en vivo para que la cocina no arranque vacía */
$vivos = array(array('new', 3), array('cooking', 8), array('ready', 14));
foreach ($vivos as $i => $v) {
    $oid++;
    $cuando = date('Y-m-d H:i:s', time() - $v[1] * 60);
    $p1 = $catalogo[mt_rand(0, count($catalogo) - 1)];
    $p2 = $catalogo[mt_rand(0, count($catalogo) - 1)];
    $subtotal = $p1['price'] + $p2['price'];
    $sql[] = "INSERT INTO `mg_orders` (`id`,`code`,`public_token`,`mode`,`table_id`,`status`,`subtotal`,`total`,`placed_at`) VALUES ("
        . $oid . ",'" . strtoupper(substr(md5((string)$oid . 'live'), 0, 6)) . "',"
        . q(rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=')) . ",'dine_in'," . ($i + 1) . ","
        . q($v[0]) . "," . number_format($subtotal, 2, '.', '') . "," . number_format($subtotal, 2, '.', '') . "," . q($cuando) . ");";
    foreach (array($p1, $p2) as $p) {
        $itemId++;
        $sql[] = "INSERT INTO `mg_order_items` (`id`,`order_id`,`product_id`,`name`,`qty`,`unit_price`,`line_total`,`status`) VALUES ("
            . $itemId . "," . $oid . "," . $p['id'] . "," . q($p['name']) . ",1,"
            . number_format($p['price'], 2, '.', '') . "," . number_format($p['price'], 2, '.', '') . ",'pending');";
    }
    $sql[] = "UPDATE `mg_tables` SET `status`='busy' WHERE `id` = " . ($i + 1) . ";";
}

/* Una llamada al mesero abierta */
$sql[] = "INSERT INTO `mg_service_calls` (`table_id`,`type`,`status`,`created_at`) VALUES (4,'waiter','open',NOW());";

file_put_contents(MG_ROOT . '/database/demo.sql', implode("\n", $sql) . "\n");

echo "Listo:\n";
echo "  database/demo.sql (" . number_format(filesize(MG_ROOT . '/database/demo.sql') / 1024, 0) . " KB)\n";
echo "  " . $pid . " platillos, " . $tid . " mesas, " . $hechos . " pedidos históricos y 3 en vivo\n";
