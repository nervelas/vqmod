<?php
/**
 * MenúGold · genera los datos y las imágenes de demostración.
 *
 *   php tools/generar-demo.php
 *
 * Escribe database/database_demo.sql y las imágenes en /uploads.
 * Solo se usa al preparar el paquete: el usuario final no lo necesita.
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
   Catálogo de la demostración
   --------------------------------------------------------------- */

// El último campo es la mezcla de ambientes de esa categoría: así la parrilla
// de imágenes tiene variedad cromática en lugar de repetir el mismo tono.
$categorias = array(
    array('Para empezar', 'To start',       'I',   'Bocados chicos para abrir el apetito',        array('cobre', 'marfil', 'brasa')),
    array('De la brasa',  'From the grill', 'II',  'Cortes al carbón de encino y leña de manzano', array('brasa', 'vino', 'cobre')),
    array('Del mar',      'From the sea',   'III', 'Pesca del día y mariscos de la costa sur',     array('humo', 'noche', 'marfil')),
    array('De la huerta', 'From the garden','IV',  'Vegetales de temporada, ahumados y frescos',   array('verde', 'noche', 'marfil')),
    array('Postres',      'Desserts',       'V',   'Lo dulce, con la misma seriedad',              array('marfil', 'cobre', 'vino')),
    array('La barra',     'The bar',        'VI',  'Coctelería de autor y vinos por copa',         array('vino', 'cobre', 'noche')),
);

// nombre | inglés | descripción | precio | etiquetas | minutos | destacado | grupos de modificadores
$platillos = array(
  0 => array(
    array('Tuétano al carbón', 'Charred bone marrow', 'Hueso abierto sobre brasa viva, pan de masa madre y sal de gusano.', 95, 'popular,recomendado', 14, 1, array()),
    array('Chorizo de la casa', 'House chorizo', 'Curado once días, con puré de frijol blanco y limón asado.', 78, '', 12, 0, array()),
    array('Aguacate a la leña', 'Wood-fired avocado', 'Aguacate ahumado, semilla de calabaza y aceite de cilantro.', 72, 'vegano', 10, 0, array()),
    array('Ceviche de la casa', 'House ceviche', 'Corvina, leche de tigre de chile cobán y camote glaseado.', 110, 'nuevo', 12, 1, array()),
    array('Croquetas de pernil', 'Pork croquettes', 'Pernil deshebrado doce horas, alioli de ajo negro.', 68, 'popular', 11, 0, array()),
    array('Pan de la casa', 'House bread', 'Masa madre de cuatro días, mantequilla de hierbas quemadas.', 38, 'vegetariano', 6, 0, array()),
  ),
  1 => array(
    array('Rib eye maduración 45 días', 'Rib eye, 45-day dry aged', '400 g sobre brasa de encino, mantequilla de tuétano y sal de Maldon.', 320, 'popular,recomendado', 24, 1, array('termino','extras','quitar')),
    array('New York de la finca', 'Farm New York strip', '350 g de res de pastoreo, chimichurri de la casa.', 265, 'recomendado', 22, 1, array('termino','extras','quitar')),
    array('Picaña a la brasa', 'Grilled picanha', 'Corte brasileño con su capa de grasa dorada y farofa de plátano.', 245, '', 24, 0, array('termino','extras')),
    array('Costilla de res ocho horas', 'Eight-hour short rib', 'Cocida lento y terminada al carbón, con puré de papa ahumada.', 285, 'popular', 18, 1, array('extras','quitar')),
    array('Cordero de Momostenango', 'Momostenango lamb', 'Rack de cordero con miel de flor de café y romero.', 340, 'recomendado', 26, 0, array('termino','extras')),
    array('Pollo de campo a la leña', 'Wood-fired free-range chicken', 'Medio pollo marinado en achiote y naranja agria.', 165, '', 28, 0, array('extras','quitar')),
    array('Chuleta de cerdo ahumada', 'Smoked pork chop', 'Cerdo criollo, salsa de tamarindo y chile pasa.', 195, 'picante', 22, 0, array('extras','quitar')),
    array('Parrillada Brasa Negra', 'Brasa Negra grill platter', 'Para dos: rib eye, chorizo, costilla y verduras al carbón.', 620, 'para_compartir,popular', 32, 1, array('termino','extras')),
  ),
  2 => array(
    array('Pargo entero a la parrilla', 'Whole grilled snapper', 'Pesca de Champerico, chile guaque y limón quemado.', 275, 'recomendado', 26, 1, array('punto','quitar')),
    array('Pulpo a la brasa', 'Charred octopus', 'Cocido tres horas y sellado al carbón, papa cambray y pimentón.', 235, 'popular', 20, 1, array('quitar')),
    array('Camarones al ajillo', 'Garlic shrimp', 'Camarón jumbo, ajo confitado y guindilla.', 210, 'picante', 16, 0, array('quitar')),
    array('Atún sellado al carbón', 'Charred tuna', 'Lomo de atún, costra de ajonjolí negro y ponzu de maracuyá.', 255, 'nuevo', 14, 0, array('punto')),
    array('Tiradito de robalo', 'Sea bass tiradito', 'Cortes finos, leche de tigre de rocoto y aceite de albahaca.', 165, '', 12, 0, array()),
  ),
  3 => array(
    array('Coliflor entera al horno', 'Whole roasted cauliflower', 'Rostizada con tahini de ajonjolí y granada.', 135, 'vegetariano,recomendado', 30, 1, array('quitar')),
    array('Elote a la parrilla', 'Grilled corn', 'Maíz criollo, mayonesa de chile cobán y queso seco.', 62, 'popular,vegetariano', 10, 0, array()),
    array('Ensalada de tomate criollo', 'Heirloom tomato salad', 'Tomates de Zacapa, albahaca y aceite de oliva del país.', 88, 'vegano', 8, 0, array()),
    array('Papas al rescoldo', 'Ember-roasted potatoes', 'Enterradas en ceniza caliente, crema agria y cebollín.', 65, 'vegetariano', 22, 0, array()),
    array('Berenjena ahumada', 'Smoked eggplant', 'Con yogur de cabra, miel de agave y menta.', 95, 'vegetariano', 18, 0, array('quitar')),
  ),
  4 => array(
    array('Tres leches de café', 'Coffee tres leches', 'Bizcocho empapado en café de Huehuetenango y crema quemada.', 78, 'popular', 8, 1, array()),
    array('Flan de coco quemado', 'Burnt coconut flan', 'Textura de crème brûlée, coco tostado y ron añejo.', 72, 'recomendado', 8, 0, array()),
    array('Chocolate de Alta Verapaz', 'Alta Verapaz chocolate', 'Ganache 72 %, sal de mar y aceite de oliva.', 85, '', 10, 0, array()),
    array('Helado de leña', 'Wood-smoke ice cream', 'Crema ahumada con madera de manzano y caramelo salado.', 65, 'nuevo', 5, 0, array()),
    array('Plátano al carbón', 'Charred plantain', 'Con crema, canela y helado de vainilla de Cobán.', 58, 'vegetariano', 12, 0, array()),
  ),
  5 => array(
    array('Negroni de la casa', 'House negroni', 'Con vermut infusionado en cardamomo y naranja quemada.', 85, 'popular', 6, 1, array()),
    array('Mezcal ahumado', 'Smoked mezcal', 'Mezcal espadín, tamarindo, chile de árbol y sal de gusano.', 95, 'picante,recomendado', 6, 0, array()),
    array('Ron añejo 12 años', 'Twelve-year aged rum', 'Servido con hielo de una pieza y cáscara de naranja.', 110, '', 3, 0, array()),
    array('Vino tinto por copa', 'Red wine by the glass', 'Malbec de altura, cosecha del año pasado.', 78, '', 3, 0, array()),
    array('Café de olla', 'Spiced pot coffee', 'Café de Huehuetenango con piloncillo y canela.', 35, '', 6, 0, array('leche')),
    array('Agua de jamaica', 'Hibiscus water', 'Infusión fría con jengibre y limón.', 28, 'vegano', 4, 0, array()),
  ),
);

$modificadores = array(
  'termino' => array('Término de la carne', 'Doneness', 'single', 1, 1, 1, array(
      array('Azul', 0), array('Rojo inglés', 0), array('Medio', 0), array('Tres cuartos', 0), array('Bien cocido', 0),
  )),
  'extras' => array('Extras de la brasa', 'Grill extras', 'multi', 0, 4, 0, array(
      array('Tuétano adicional', 45), array('Huevo de campo', 18), array('Chimichurri de la casa', 15),
      array('Salsa de chile cobán', 15), array('Queso fundido', 32), array('Cebolla caramelizada', 20),
  )),
  'quitar' => array('Quitar ingredientes', 'Remove ingredients', 'multi', 0, 5, 0, array(
      array('Sin cilantro', 0), array('Sin cebolla', 0), array('Sin picante', 0),
      array('Sin lácteos', 0), array('Sin ajo', 0),
  )),
  'punto' => array('Punto del pescado', 'Fish doneness', 'single', 1, 1, 1, array(
      array('Sellado por fuera', 0), array('Al punto', 0), array('Bien cocido', 0),
  )),
  'leche' => array('Tipo de leche', 'Milk', 'single', 0, 1, 0, array(
      array('Entera', 0), array('Deslactosada', 5), array('De almendra', 12), array('Sin leche', 0),
  )),
);

$variantes = array(
    'Rib eye maduración 45 días' => array(array('250 g', -70), array('400 g', 0), array('600 g', 120)),
    'New York de la finca'       => array(array('300 g', -40), array('350 g', 0), array('500 g', 95)),
    'Picaña a la brasa'          => array(array('Media', -80), array('Entera', 0)),
);

$cafe = array(
    array('Cafetería', 'Coffee', 'I', 'Grano de altura tostado cada semana', array('cobre', 'marfil'), array(
        array('Espresso doble', 'Double espresso', 'Grano de Huehuetenango, tueste medio.', 22, '', 4, 1),
        array('Cortado', 'Cortado', 'Espresso con un toque de leche vaporizada.', 26, 'popular', 5, 0),
        array('Latte de cardamomo', 'Cardamom latte', 'Con cardamomo molido en casa.', 34, 'nuevo', 6, 1),
        array('Cold brew', 'Cold brew', 'Extracción en frío de dieciocho horas.', 32, '', 3, 0),
    )),
    array('Panadería', 'Bakery', 'II', 'Horneado cada mañana', array('marfil', 'noche'), array(
        array('Croissant de mantequilla', 'Butter croissant', 'Masa laminada en tres días.', 24, 'popular', 3, 1),
        array('Pan de banano', 'Banana bread', 'Con nuez y canela.', 28, 'vegetariano', 3, 0),
        array('Concha de chocolate', 'Chocolate concha', 'Receta de la abuela, con cacao de Alta Verapaz.', 20, '', 3, 0),
    )),
    array('Desayunos', 'Breakfast', 'III', 'Hasta las once y media', array('verde', 'marfil'), array(
        array('Huevos rancheros', 'Ranchero eggs', 'Con frijol volteado, plátano y queso fresco.', 62, 'popular', 12, 1),
        array('Tostada de aguacate', 'Avocado toast', 'Pan de masa madre, aguacate y semillas.', 55, 'vegano', 8, 0),
        array('Avena de la casa', 'House oatmeal', 'Con fruta de temporada y miel.', 42, 'vegetariano', 6, 0),
    )),
);

/* ---------------------------------------------------------------
   Generación de imágenes
   --------------------------------------------------------------- */

function limpiarUploads($rid)
{
    $dir = MG_ROOT . '/uploads/' . $rid;
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
}

/** Crea una imagen ambiental y la mete por la canalización real de la app. */
function ambiente($rid, $carpeta, $nombre, $seed, $ambiente, $w = 1600, $h = 1067, $densidad = 1.0)
{
    $im = mg_arte_ambiente($w, $h, $seed, array('ambiente' => $ambiente, 'densidad' => $densidad));
    $tmp = sys_get_temp_dir() . '/mg-' . bin2hex(random_bytes(5)) . '.jpg';
    imagejpeg($im, $tmp, 90);
    imagedestroy($im);
    $base = Image::storePath($tmp, $rid, $carpeta, 1600, $nombre . '.jpg');
    @unlink($tmp);
    return $base;
}

function logo($rid, $letras, $tinta)
{
    $ttf = __DIR__ . '/fuentes/Fraunces.ttf';
    $im = mg_arte_logo(900, $letras, '#0C0B09', $tinta, is_file($ttf) ? $ttf : null);
    $tmp = sys_get_temp_dir() . '/mg-logo-' . bin2hex(random_bytes(4)) . '.png';
    imagepng($im, $tmp);
    imagedestroy($im);
    $base = Image::storePath($tmp, $rid, 'marca', 960, 'logo.png');
    @unlink($tmp);
    Image::generatePwaIcons($base, $rid, '#0C0B09');
    return $base;
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

echo "Limpiando imágenes anteriores…\n";
limpiarUploads(1);
limpiarUploads(2);

echo "Generando imágenes de Brasa Negra…\n";
$img = array();
$img['logo1']  = logo(1, 'BN', '#D8B26E');
$img['cover1'] = ambiente(1, 'marca', 'portada', 'bn-cover-11', 'brasa', 1600, 1000, 1.15);

$catImg = array();
foreach ($categorias as $i => $c) {
    $catImg[$i] = ambiente(1, 'categorias', 'cat-' . ($i + 1), 'bn-cat-' . $i . '-5', $c[4][0], 1200, 800, 0.9);
}

$prodImg = array();
$n = 0;
foreach ($platillos as $ci => $lista) {
    foreach ($lista as $pi => $p) {
        $n++;
        $mezcla = $categorias[$ci][4];
        $prodImg[$ci . '-' . $pi] = ambiente(1, 'platillos', 'p-' . $n, 'bn-p-' . $n . '-13',
            $mezcla[$pi % count($mezcla)], 1600, 1067, 0.8 + (($pi % 3) * 0.22));
        echo "\r  platillos: $n/35   ";
    }
}
echo "\n";

echo "Generando imágenes de Café Central…\n";
$img['logo2']  = logo(2, 'CC', '#E0C08A');
$img['cover2'] = ambiente(2, 'marca', 'portada', 'cc-cover-4', 'marfil', 1600, 1000, 0.9);
$cafeImg = array();
$m = 0;
foreach ($cafe as $ci => $c) {
    foreach ($c[5] as $pi => $p) {
        $m++;
        $cafeImg[$ci . '-' . $pi] = ambiente(2, 'platillos', 'c-' . $m, 'cc-p-' . $m . '-7',
            $c[4][$pi % count($c[4])], 1400, 933, 0.85);
    }
}
echo "  productos Café Central: $m\n";

/* ---------------------------------------------------------------
   Construcción del SQL
   --------------------------------------------------------------- */

$sql = array();
$sql[] = "-- MenúGold · datos de demostración (Brasa Negra y Café Central).";
$sql[] = "-- Generado el " . date('Y-m-d H:i') . ". Reinstalable: borra y recrea los ids 1 y 2.";
$sql[] = "SET NAMES utf8mb4;";
$sql[] = "SET FOREIGN_KEY_CHECKS = 0;";
$sql[] = "DELETE FROM `restaurants` WHERE `id` IN (1,2);";
$sql[] = "DELETE FROM `users` WHERE `restaurant_id` IN (1,2) OR `username` = 'admin@plataforma.gt';";
$sql[] = "SET FOREIGN_KEY_CHECKS = 1;";
$sql[] = "";

/* Superadministrador de la demostración */
$sql[] = "INSERT INTO `users` (`restaurant_id`,`role`,`name`,`username`,`email`,`password_hash`,`is_active`,`created_at`) VALUES "
       . "(NULL,'superadmin','Administrador de la plataforma','admin@plataforma.gt','admin@plataforma.gt'," . q(hash_demo('Admin2026!')) . ",1,NOW());";
$sql[] = "";

/* --- Restaurante 1 --- */
$sql[] = "INSERT INTO `restaurants` (`id`,`slug`,`name`,`tagline`,`description`,`logo`,`cover`,`phone`,`whatsapp`,`email`,`address`,`city`,`map_url`,`review_url`,`currency`,`tax_rate`,`tax_included`,`tip_enabled`,`tip_options`,`service_modes`,`order_mode`,`theme`,`font_combo`,`primary_color`,`accent_color`,`lang_default`,`langs`,`timezone`,`bank_info`,`plan_id`,`plan_expires_at`,`status`,`created_at`) VALUES ("
    . "1,'brasa-negra','Brasa Negra','Parrilla de leña y cortes madurados',"
    . q('Cocina de fuego en el corazón de la zona 10. Carne madurada en casa, brasa de encino y una barra que no se apura.') . ","
    . q($img['logo1']) . "," . q($img['cover1']) . ",'+502 2360 4500','50223604500','hola@brasanegra.gt',"
    . "'6a avenida 12-45, Zona 10','Ciudad de Guatemala','https://maps.google.com/?q=Brasa+Negra','https://g.page/r/brasa-negra/review',"
    . "'Q',12.00,1,1,'10,15,20','dine_in,takeaway,delivery','order','brasa','editorial','#D8B26E','#C4502B','es','es,en','America/Guatemala',"
    . q("Banco Industrial\nCuenta monetaria 123-456789-0\nA nombre de Brasa Negra, S. A.\nNIT 1234567-8") . ","
    . "3," . q(date('Y-m-d', strtotime('+11 months'))) . ",'active',NOW());";

foreach (array(0, 1, 2, 3, 4, 5, 6) as $d) {
    $abre = ($d === 0) ? '12:00:00' : '12:00:00';
    $cierra = in_array($d, array(5, 6), true) ? '01:00:00' : '23:00:00';
    $sql[] = "INSERT INTO `restaurant_hours` (`restaurant_id`,`weekday`,`opens_at`,`closes_at`,`is_closed`) VALUES (1,$d," . q($abre) . "," . q($cierra) . ",0);";
}

$usuarios1 = array(
    array('owner',   'Marcela Villagrán', 'dueno@brasanegra.gt', 'dueno@brasanegra.gt', 'Brasa2026!', ''),
    array('manager', 'Diego Pineda',      'gerente1',            'gerente@brasanegra.gt', 'Gerente2026!', '2468'),
    array('kitchen', 'Chef Elder Ramos',  'cocina1',             'cocina@brasanegra.gt',  'Cocina2026!', '1357'),
    array('waiter',  'Sofía Marroquín',   'mesero1',             'mesero@brasanegra.gt',  'Mesero2026!', '2222'),
    array('waiter',  'Julio Cordón',      'mesero2',             '',                      'Mesero2026!', '3333'),
);
foreach ($usuarios1 as $u) {
    $sql[] = "INSERT INTO `users` (`restaurant_id`,`role`,`name`,`username`,`email`,`password_hash`,`pin`,`is_active`,`created_at`) VALUES "
        . "(1," . q($u[0]) . "," . q($u[1]) . "," . q($u[2]) . "," . q($u[3]) . "," . q(hash_demo($u[4])) . ","
        . ($u[5] !== '' ? q(hash_demo($u[5])) : "''") . ",1,NOW());";
}

foreach ($categorias as $i => $c) {
    $sql[] = "INSERT INTO `categories` (`id`,`restaurant_id`,`name`,`name_en`,`description`,`image`,`roman`,`sort`,`is_active`,`days_mask`) VALUES ("
        . ($i + 1) . ",1," . q($c[0]) . "," . q($c[1]) . "," . q($c[3]) . "," . q($catImg[$i]) . "," . q($c[2]) . "," . ($i + 1) . ",1,127);";
}

$gid = 0;
$gidMap = array();
foreach ($modificadores as $clave => $g) {
    $gid++;
    $gidMap[$clave] = $gid;
    $sql[] = "INSERT INTO `modifier_groups` (`id`,`restaurant_id`,`name`,`name_en`,`help`,`type`,`min_select`,`max_select`,`is_required`,`sort`) VALUES ("
        . $gid . ",1," . q($g[0]) . "," . q($g[1]) . ",''," . q($g[2]) . "," . (int)$g[3] . "," . (int)$g[4] . "," . (int)$g[5] . "," . $gid . ");";
    foreach ($g[6] as $oi => $o) {
        $sql[] = "INSERT INTO `modifier_options` (`group_id`,`name`,`price_delta`,`is_default`,`is_active`,`sort`) VALUES ("
            . $gid . "," . q($o[0]) . "," . number_format((float)$o[1], 2, '.', '') . "," . ($oi === 0 && $g[5] ? 1 : 0) . ",1," . $oi . ");";
    }
}

$pid = 0;
$nombreAId = array();
foreach ($platillos as $ci => $lista) {
    foreach ($lista as $pi => $p) {
        $pid++;
        $nombreAId[$p[0]] = $pid;
        $sql[] = "INSERT INTO `products` (`id`,`restaurant_id`,`category_id`,`name`,`name_en`,`description`,`description_en`,`price`,`image`,`prep_minutes`,`tags`,`is_active`,`is_featured`,`sort`,`created_at`) VALUES ("
            . $pid . ",1," . ($ci + 1) . "," . q($p[0]) . "," . q($p[1]) . "," . q($p[2]) . ",''," . number_format((float)$p[3], 2, '.', '') . ","
            . q($prodImg[$ci . '-' . $pi]) . "," . (int)$p[5] . "," . q($p[4]) . ",1," . (int)$p[6] . "," . $pid . ",NOW());";
        foreach ($p[7] as $clave) {
            if (!isset($gidMap[$clave])) { continue; }
            $sql[] = "INSERT INTO `product_modifier_groups` (`product_id`,`group_id`,`sort`) VALUES ($pid," . $gidMap[$clave] . ",0);";
        }
        if (isset($variantes[$p[0]])) {
            foreach ($variantes[$p[0]] as $vi => $v) {
                $sql[] = "INSERT INTO `variants` (`product_id`,`name`,`price_delta`,`is_default`,`sort`) VALUES ("
                    . $pid . "," . q($v[0]) . "," . number_format((float)$v[1], 2, '.', '') . "," . ($v[1] == 0 ? 1 : 0) . "," . $vi . ");";
            }
        }
    }
}
$maxProd1 = $pid;

$zonas = array(array('Salón', 6), array('Terraza', 4), array('Barra', 2));
$tid = 0;
foreach ($zonas as $z) {
    for ($i = 0; $i < $z[1]; $i++) {
        $tid++;
        $sql[] = "INSERT INTO `tables` (`id`,`restaurant_id`,`name`,`zone`,`seats`,`qr_token`,`status`,`is_active`,`sort`) VALUES ("
            . $tid . ",1,'Mesa " . $tid . "'," . q($z[0]) . "," . ($z[0] === 'Barra' ? 2 : ($tid % 3 === 0 ? 6 : 4)) . ","
            . q(rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=')) . ",'free',1," . $tid . ");";
    }
}
$maxTable1 = $tid;

foreach (array(
    array('Zona 10 y 9', 25, 150, 30), array('Zona 14 y 15', 35, 200, 40),
    array('Zona 1 y 4', 45, 250, 50), array('Carretera a El Salvador', 60, 350, 60),
) as $i => $z) {
    $sql[] = "INSERT INTO `delivery_zones` (`restaurant_id`,`name`,`fee`,`min_order`,`eta_minutes`,`is_active`,`sort`) VALUES "
        . "(1," . q($z[0]) . "," . number_format((float)$z[1], 2, '.', '') . "," . number_format((float)$z[2], 2, '.', '') . "," . (int)$z[3] . ",1," . $i . ");";
}

$sql[] = "INSERT INTO `promotions` (`restaurant_id`,`name`,`type`,`value`,`scope`,`scope_id`,`starts_at`,`ends_at`,`is_active`) VALUES "
    . "(1,'Martes de brasa','percent',15.00,'category',2," . q(date('Y-m-01')) . "," . q(date('Y-m-d', strtotime('+3 months'))) . ",1);";
$sql[] = "INSERT INTO `promotions` (`restaurant_id`,`name`,`type`,`value`,`scope`,`scope_id`,`starts_at`,`ends_at`,`is_active`) VALUES "
    . "(1,'Postre del mes','amount',15.00,'category',5,NULL," . q(date('Y-m-d', strtotime('+2 months'))) . ",1);";

$sql[] = "INSERT INTO `coupons` (`restaurant_id`,`code`,`type`,`value`,`min_total`,`max_uses`,`used`,`starts_at`,`ends_at`,`is_active`) VALUES "
    . "(1,'BIENVENIDA10','percent',10.00,0.00,300,42,NULL," . q(date('Y-m-d', strtotime('+6 months'))) . ",1),"
    . "(1,'ENVIOGRATIS','free_delivery',0.00,250.00,0,18,NULL,NULL,1),"
    . "(1,'BRASA50','amount',50.00,400.00,100,9," . q(date('Y-m-01')) . "," . q(date('Y-m-d', strtotime('+45 days'))) . ",1);";

$sql[] = "INSERT INTO `combos` (`restaurant_id`,`name`,`description`,`price`,`items`,`is_active`,`sort`) VALUES "
    . "(1,'Mesa para dos','Entrada a elegir, dos cortes de 300 g, guarnición y dos postres.',780.00,"
    . q(json_encode(array(
        array('id' => $nombreAId['Tuétano al carbón'], 'name' => 'Tuétano al carbón'),
        array('id' => $nombreAId['New York de la finca'], 'name' => 'New York de la finca'),
        array('id' => $nombreAId['Papas al rescoldo'], 'name' => 'Papas al rescoldo'),
        array('id' => $nombreAId['Tres leches de café'], 'name' => 'Tres leches de café'),
    ), JSON_UNESCAPED_UNICODE)) . ",1,1);";

/* --- Restaurante 2 --- */
$sql[] = "";
$sql[] = "INSERT INTO `restaurants` (`id`,`slug`,`name`,`tagline`,`description`,`logo`,`cover`,`phone`,`whatsapp`,`email`,`address`,`city`,`currency`,`tax_rate`,`tax_included`,`tip_enabled`,`tip_options`,`service_modes`,`order_mode`,`theme`,`font_combo`,`primary_color`,`accent_color`,`lang_default`,`langs`,`timezone`,`plan_id`,`plan_expires_at`,`status`,`created_at`) VALUES ("
    . "2,'cafe-central','Café Central','Tostaduría y panadería de barrio',"
    . q('Café de altura tostado cada semana y pan horneado en la madrugada. Abrimos temprano.') . ","
    . q($img['logo2']) . "," . q($img['cover2']) . ",'+502 2232 1180','50222321180','hola@cafecentral.gt',"
    . "'9a calle 5-20, Zona 1','Ciudad de Guatemala','Q',12.00,1,1,'10,15','dine_in,takeaway','order','marfil','editorial','#E0C08A','#8A6A3A','es','es','America/Guatemala',"
    . "2," . q(date('Y-m-d', strtotime('+7 months'))) . ",'active',NOW());";

for ($d = 0; $d <= 6; $d++) {
    $sql[] = "INSERT INTO `restaurant_hours` (`restaurant_id`,`weekday`,`opens_at`,`closes_at`,`is_closed`) VALUES (2,$d,'06:30:00','19:00:00'," . ($d === 0 ? 1 : 0) . ");";
}
$sql[] = "INSERT INTO `users` (`restaurant_id`,`role`,`name`,`username`,`email`,`password_hash`,`pin`,`is_active`,`created_at`) VALUES "
    . "(2,'owner','Rodrigo Estrada','dueno@cafecentral.gt','dueno@cafecentral.gt'," . q(hash_demo('Cafe2026!')) . ",'',1,NOW()),"
    . "(2,'waiter','Karla Sandoval','barista1',''," . q(hash_demo('Barista2026!')) . "," . q(hash_demo('4444')) . ",1,NOW());";

$cid = 100;
$pid2 = 100;
$ci2 = 0;
foreach ($cafe as $c) {
    $cid++;
    $ci2++;
    $sql[] = "INSERT INTO `categories` (`id`,`restaurant_id`,`name`,`name_en`,`description`,`roman`,`sort`,`is_active`,`days_mask`) VALUES ("
        . $cid . ",2," . q($c[0]) . "," . q($c[1]) . "," . q($c[3]) . "," . q($c[2]) . "," . $ci2 . ",1,127);";
    foreach ($c[5] as $pi => $p) {
        $pid2++;
        $key = ($ci2 - 1) . '-' . $pi;
        $sql[] = "INSERT INTO `products` (`id`,`restaurant_id`,`category_id`,`name`,`name_en`,`description`,`price`,`image`,`prep_minutes`,`tags`,`is_active`,`is_featured`,`sort`,`created_at`) VALUES ("
            . $pid2 . ",2," . $cid . "," . q($p[0]) . "," . q($p[1]) . "," . q($p[2]) . "," . number_format((float)$p[3], 2, '.', '') . ","
            . q(isset($cafeImg[$key]) ? $cafeImg[$key] : '') . "," . (int)$p[5] . "," . q($p[4]) . ",1," . (int)$p[6] . "," . $pid2 . ",NOW());";
    }
}
for ($i = 1; $i <= 6; $i++) {
    $sql[] = "INSERT INTO `tables` (`id`,`restaurant_id`,`name`,`zone`,`seats`,`qr_token`,`status`,`is_active`,`sort`) VALUES ("
        . (100 + $i) . ",2,'Mesa " . $i . "','Salón',2," . q(rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=')) . ",'free',1," . $i . ");";
}

file_put_contents(MG_ROOT . '/database/database_demo.sql.parte1', implode("\n", $sql) . "\n");
echo "Catálogo listo. Generando pedidos históricos…\n";

/* ---------------------------------------------------------------
   Pedidos históricos
   --------------------------------------------------------------- */

$precios = array();
$nombres = array();
$prep = array();
$idx = 0;
foreach ($platillos as $ci => $lista) {
    foreach ($lista as $p) {
        $idx++;
        $precios[$idx] = (float)$p[3];
        $nombres[$idx] = $p[0];
        $prep[$idx] = (int)$p[5];
    }
}

mt_srand(20260829);
$alfabeto = 'ACDEFGHJKLMNPQRTUVWXY3479';
$usados = array();
function codigo($alfabeto, &$usados)
{
    do {
        $c = '';
        for ($i = 0; $i < 6; $i++) { $c .= $alfabeto[mt_rand(0, strlen($alfabeto) - 1)]; }
    } while (isset($usados[$c]));
    $usados[$c] = true;
    return $c;
}

$clientes = array(
    array('Ana Lucía Prado', '50255120044', '12 calle 3-45, Zona 10'),
    array('Rodrigo Estrada', '50241239876', 'Avenida Reforma 8-60, Zona 9'),
    array('María José Alonzo', '50257788112', '5a avenida 14-22, Zona 14'),
    array('Luis Fernando Coy', '50230014477', 'Calzada Roosevelt km 12'),
    array('Gabriela Ixcot', '50259903311', '2a calle 7-18, Zona 1'),
    array('Héctor Sagastume', '50242216688', 'Boulevard Vista Hermosa 25-19'),
);
$sqlO = array();
$oid = 0;
$itemId = 0;
$custId = 0;
foreach ($clientes as $i => $c) {
    $custId++;
    $sqlO[] = "INSERT INTO `customers` (`id`,`restaurant_id`,`name`,`phone`,`address`,`orders_count`,`total_spent`,`last_order_at`,`created_at`) VALUES ("
        . $custId . ",1," . q($c[0]) . "," . q($c[1]) . "," . q($c[2]) . ",0,0.00,NOW(),"
        . q(date('Y-m-d H:i:s', strtotime('-' . (60 + $i * 9) . ' days'))) . ");";
}

$modOpciones = array(
    array('Término de la carne', 'Medio', 0.0),
    array('Extras de la brasa', 'Tuétano adicional', 45.0),
    array('Extras de la brasa', 'Huevo de campo', 18.0),
    array('Quitar ingredientes', 'Sin cilantro', 0.0),
    array('Extras de la brasa', 'Queso fundido', 32.0),
);

function agregarPedido(&$sqlO, &$oid, &$itemId, &$usados, $alfabeto, $precios, $nombres, $prep, $modOpciones,
                       $cuando, $estado, $modo, $tableId, $customerId, $clientes, $maxProd, $waiterId)
{
    $oid++;
    $code = codigo($alfabeto, $usados);
    $lineas = mt_rand(1, 5);
    $subtotal = 0.0;
    $items = array();
    for ($i = 0; $i < $lineas; $i++) {
        $p = mt_rand(1, $maxProd);
        $qty = mt_rand(1, 2);
        $unit = $precios[$p];
        $mods = array();
        if (mt_rand(0, 100) < 45) {
            $m = $modOpciones[mt_rand(0, count($modOpciones) - 1)];
            $mods[] = array('group' => $m[0], 'name' => $m[1], 'price' => $m[2]);
            $unit += $m[2];
        }
        $line = round($unit * $qty, 2);
        $subtotal += $line;
        $items[] = array($p, $nombres[$p], $qty, $unit, $mods, $line, mt_rand(0, 100) < 12 ? 'Sin sal, por favor' : '');
    }
    $descuento = mt_rand(0, 100) < 18 ? round($subtotal * 0.10, 2) : 0.0;
    $envio = $modo === 'delivery' ? (float)(array(25, 35, 45, 60)[mt_rand(0, 3)]) : 0.0;
    $propina = in_array($estado, array('paid'), true) && mt_rand(0, 100) < 70
        ? round(($subtotal - $descuento) * (array(0.10, 0.15, 0.20)[mt_rand(0, 2)]), 2) : 0.0;
    $total = round($subtotal - $descuento + $envio + $propina, 2);

    $t = strtotime($cuando);
    $accepted = date('Y-m-d H:i:s', $t + mt_rand(60, 300));
    $ready    = date('Y-m-d H:i:s', $t + mt_rand(600, 1500));
    $delivered= date('Y-m-d H:i:s', $t + mt_rand(1600, 2400));
    $paid     = date('Y-m-d H:i:s', $t + mt_rand(2500, 5400));

    $cliente = $customerId ? $clientes[$customerId - 1] : null;
    $sqlO[] = "INSERT INTO `orders` (`id`,`restaurant_id`,`code`,`table_id`,`mode`,`status`,`customer_id`,`customer_name`,`customer_phone`,`address`,`delivery_fee`,`subtotal`,`discount`,`tip`,`tax`,`total`,`coupon_code`,`payment_method`,`payment_status`,`notes`,`waiter_id`,`source`,`lang`,`track_token`,`placed_at`,`accepted_at`,`ready_at`,`delivered_at`,`paid_at`) VALUES ("
        . $oid . ",1," . q($code) . "," . ($tableId ? $tableId : 'NULL') . "," . q($modo) . "," . q($estado) . ","
        . ($customerId ? $customerId : 'NULL') . "," . q($cliente ? $cliente[0] : '') . "," . q($cliente ? $cliente[1] : '') . ","
        . q($modo === 'delivery' && $cliente ? $cliente[2] : '') . ","
        . number_format($envio, 2, '.', '') . "," . number_format($subtotal, 2, '.', '') . ","
        . number_format($descuento, 2, '.', '') . "," . number_format($propina, 2, '.', '') . ",0.00,"
        . number_format($total, 2, '.', '') . "," . q($descuento > 0 ? 'BIENVENIDA10' : '') . ","
        . q($estado === 'paid' ? (array('cash', 'card', 'transfer')[mt_rand(0, 2)]) : 'pending') . ","
        . q($estado === 'paid' ? 'paid' : 'pending') . ",'',"
        . ($waiterId ? $waiterId : 'NULL') . ",'qr','es'," . q(rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=')) . ","
        . q($cuando) . ","
        . q(in_array($estado, array('preparing','ready','delivered','paid'), true) ? $accepted : null) . ","
        . q(in_array($estado, array('ready','delivered','paid'), true) ? $ready : null) . ","
        . q(in_array($estado, array('delivered','paid'), true) ? $delivered : null) . ","
        . q($estado === 'paid' ? $paid : null) . ");";

    foreach ($items as $it) {
        $itemId++;
        $sqlO[] = "INSERT INTO `order_items` (`order_id`,`product_id`,`name_snapshot`,`qty`,`unit_price`,`modifiers`,`modifiers_total`,`line_total`,`notes`,`status`) VALUES ("
            . $oid . "," . $it[0] . "," . q($it[1]) . "," . $it[2] . "," . number_format($it[3], 2, '.', '') . ","
            . q(json_encode($it[4], JSON_UNESCAPED_UNICODE)) . ","
            . number_format(array_sum(array_column($it[4], 'price')), 2, '.', '') . ","
            . number_format($it[5], 2, '.', '') . "," . q($it[6]) . ","
            . q($estado === 'paid' ? 'served' : 'pending') . ");";
    }
    $sqlO[] = "INSERT INTO `order_events` (`order_id`,`from_status`,`to_status`,`note`,`created_at`) VALUES ("
        . $oid . ",'','new','Pedido recibido'," . q($cuando) . ");";
    if ($estado === 'paid') {
        $sqlO[] = "INSERT INTO `order_events` (`order_id`,`from_status`,`to_status`,`note`,`created_at`) VALUES ("
            . $oid . ",'delivered','paid','Cobrado'," . q($paid) . ");";
    }
    return $total;
}

$gastoCliente = array_fill(1, count($clientes), 0.0);
$pedidosCliente = array_fill(1, count($clientes), 0);

for ($i = 0; $i < 40; $i++) {
    $dias = mt_rand(1, 29);
    $hora = mt_rand(12, 22);
    $cuando = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days ' . ($hora - date('H')) . ' hours ' . mt_rand(0, 59) . ' minutes'));
    $modo = array('dine_in', 'dine_in', 'dine_in', 'takeaway', 'delivery')[mt_rand(0, 4)];
    $estado = mt_rand(0, 100) < 92 ? 'paid' : 'cancelled';
    $tableId = $modo === 'dine_in' ? mt_rand(1, 12) : null;
    $customerId = $modo === 'dine_in' ? null : mt_rand(1, count($clientes));
    $waiterId = $estado === 'paid' ? (mt_rand(0, 1) ? 4 : 5) : null;
    $total = agregarPedido($sqlO, $oid, $itemId, $usados, $alfabeto, $precios, $nombres, $prep, $modOpciones,
        $cuando, $estado, $modo, $tableId, $customerId, $clientes, $maxProd1, $waiterId);
    if ($customerId && $estado === 'paid') {
        $gastoCliente[$customerId] += $total;
        $pedidosCliente[$customerId]++;
    }
}

// Tres pedidos vivos para que la pantalla de cocina tenga qué mostrar.
foreach (array(array('new', 3), array('preparing', 14), array('ready', 26)) as $vivo) {
    $cuando = date('Y-m-d H:i:s', time() - $vivo[1] * 60);
    agregarPedido($sqlO, $oid, $itemId, $usados, $alfabeto, $precios, $nombres, $prep, $modOpciones,
        $cuando, $vivo[0], 'dine_in', mt_rand(1, 12), null, $clientes, $maxProd1, null);
}
$sqlO[] = "UPDATE `tables` SET `status`='occupied' WHERE `restaurant_id`=1 AND `id` IN (SELECT DISTINCT `table_id` FROM `orders` WHERE `restaurant_id`=1 AND `status` IN ('new','preparing','ready') AND `table_id` IS NOT NULL);";
$sqlO[] = "INSERT INTO `service_calls` (`restaurant_id`,`table_id`,`type`,`status`,`created_at`) VALUES (1,3,'waiter','open'," . q(date('Y-m-d H:i:s', time() - 240)) . ");";

foreach ($gastoCliente as $cid2 => $gasto) {
    $sqlO[] = "UPDATE `customers` SET `orders_count`=" . (int)$pedidosCliente[$cid2] . ", `total_spent`=" . number_format($gasto, 2, '.', '') . " WHERE `id`=" . $cid2 . ";";
}

// Pedidos del segundo restaurante (para comprobar el aislamiento de datos).
$oid2 = 1000;
for ($i = 0; $i < 6; $i++) {
    $oid2++;
    $code = codigo($alfabeto, $usados);
    $cuando = date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 20) . ' days ' . mt_rand(7, 17) . ' hours'));
    $sub = (float)mt_rand(40, 180);
    $sqlO[] = "INSERT INTO `orders` (`id`,`restaurant_id`,`code`,`table_id`,`mode`,`status`,`subtotal`,`total`,`payment_method`,`payment_status`,`source`,`track_token`,`placed_at`,`paid_at`) VALUES ("
        . $oid2 . ",2," . q($code) . "," . (100 + mt_rand(1, 6)) . ",'dine_in','paid'," . number_format($sub, 2, '.', '') . ","
        . number_format($sub, 2, '.', '') . ",'cash','paid','qr'," . q(rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=')) . ","
        . q($cuando) . "," . q($cuando) . ");";
    $sqlO[] = "INSERT INTO `order_items` (`order_id`,`product_id`,`name_snapshot`,`qty`,`unit_price`,`modifiers`,`line_total`,`status`) VALUES ("
        . $oid2 . ",101,'Espresso doble',1," . number_format($sub, 2, '.', '') . ",'[]'," . number_format($sub, 2, '.', '') . ",'served');";
}

$sqlO[] = "";
$sqlO[] = "UPDATE `landing_content` SET `cvalue`='brasa-negra' WHERE `ckey`='demo_slug';";
$sqlO[] = "INSERT INTO `landing_content` (`ckey`,`cvalue`) VALUES ('demo_slug','brasa-negra') ON DUPLICATE KEY UPDATE `cvalue`='brasa-negra';";

$parte1 = (string)file_get_contents(MG_ROOT . '/database/database_demo.sql.parte1');
@unlink(MG_ROOT . '/database/database_demo.sql.parte1');
file_put_contents(MG_ROOT . '/database/database_demo.sql', $parte1 . "\n" . implode("\n", $sqlO) . "\n");

echo "Listo:\n";
echo "  database/database_demo.sql (" . number_format(filesize(MG_ROOT . '/database/database_demo.sql') / 1024, 0) . " KB)\n";
echo "  " . $oid . " pedidos en Brasa Negra, 6 en Café Central\n";
