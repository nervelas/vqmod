<?php
declare(strict_types=1);
/**
 * Construye los datos de demostración y genera database/database_demo.sql.
 *
 *   php tools/seed-demo.php <base_de_datos_temporal>
 *
 * Crea dos empresas ("Industrial Pérez, S.A." y "Uniformes Roca") con
 * catálogo, clientes, vendedores y cotizaciones en todos los estados.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Security;

$dbName = $argv[1] ?? 'cotizapro_seed';
$host = getenv('SEED_HOST') ?: '127.0.0.1';
$user = getenv('SEED_USER') ?: 'cp';
$pass = getenv('SEED_PASS') ?: 'cp123';

$root = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
$root->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
foreach (preg_split('/;\s*[\r\n]/', (string) file_get_contents(BASE_PATH . '/database/database.sql')) as $s) {
    $s = trim(preg_replace('/^--.*$/m', '', $s) ?? '');
    if ($s !== '') {
        $pdo->exec($s);
    }
}

mt_srand(4210);
$ins = static function (string $table, array $data) use ($pdo): int {
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(',:', $cols) . ')';
    $st = $pdo->prepare($sql);
    $st->execute($data);
    return (int) $pdo->lastInsertId();
};
$d = static fn (int $daysAgo, int $h = 9, int $m = 15): string => date('Y-m-d H:i:s', strtotime("-{$daysAgo} days " . sprintf('%02d:%02d:00', $h, $m)));

/* =====================================================================
   EMPRESA 1 · Industrial Pérez, S.A.
   ===================================================================== */
$c1 = $ins('companies', [
    'slug' => 'industrial-perez', 'name' => 'Industrial Pérez', 'legal_name' => 'Industrial Pérez, Sociedad Anónima',
    'nit' => '2458961-4', 'plan_id' => null, 'status' => 'activa', 'expires_at' => date('Y-m-d', strtotime('+11 months')),
    'domain' => null, 'logo' => null, 'hero_image' => null, 'og_image' => null,
    'theme' => 'acero', 'color_accent' => '#E8590C', 'color_ink' => '#1C1F22', 'color_paper' => '#F5F6F4',
    'tagline' => 'Sellos, empaques y repuestos industriales con respaldo técnico',
    'about' => "Desde 1998 abastecemos a la industria guatemalteca de sellos mecánicos, empaques, teflón y hules técnicos. Trabajamos con inventario en Guatemala y con fabricación bajo plano cuando la pieza ya no existe en el mercado.\n\nNuestro equipo técnico levanta la medida en planta, propone el material correcto para su proceso y entrega con el respaldo de las marcas que distribuimos.",
    'years_experience' => 28,
    'email' => 'ventas@industrialperez.gt', 'phone' => '2245-8800', 'whatsapp' => '50255480011',
    'address' => '12 avenida 4-56, zona 12, Complejo Industrial El Cortijo', 'city' => 'Guatemala', 'country' => 'Guatemala',
    'currency_symbol' => 'Q', 'tax_rate' => 12.000, 'tax_label' => 'IVA', 'price_visibility' => 'oculto',
    'quote_prefix' => 'COT', 'quote_next' => 36, 'quote_year' => (int) date('Y'), 'quote_pad' => 4,
    'pdf_terms' => 'Los precios están expresados en quetzales e incluyen IVA. La entrega se cuenta a partir de la recepción de la orden de compra en firme. Materiales bajo pedido no admiten devolución.',
    'pdf_footer' => 'Industrial Pérez, S.A. · ventas@industrialperez.gt · 2245-8800 · Guatemala',
    'validity_days' => 15, 'delivery_terms' => '8 días hábiles', 'payment_terms' => '50% anticipo, 50% contra entrega',
    'reminder_days_seller' => 3, 'reminder_days_client' => 7, 'assign_mode' => 'rotativo',
    'seo_title' => 'Industrial Pérez — Sellos mecánicos, empaques y repuestos industriales en Guatemala',
    'seo_description' => 'Catálogo técnico de sellos mecánicos, empaques, teflón y hules industriales. Cotice en línea por código o medida y reciba su cotización el mismo día.',
    'created_at' => $d(720), 'updated_at' => $d(2),
]);

$u1 = $ins('users', ['company_id' => $c1, 'name' => 'Roberto Pérez Molina', 'email' => 'admin@industrialperez.gt',
    'username' => 'rperez', 'password' => Security::hashPassword('Perez2026!'), 'role' => 'admin',
    'phone' => '2245-8800', 'whatsapp' => '50255480011', 'position' => 'Gerente general',
    'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(720), 'last_login_at' => $d(1, 8, 12), 'last_login_ip' => '181.174.10.22']);
$v1 = $ins('users', ['company_id' => $c1, 'name' => 'Ana Lucía Ramírez', 'email' => 'aramirez@industrialperez.gt',
    'username' => 'vendedor1', 'password' => Security::hashPassword('Venta2026!'), 'role' => 'vendedor',
    'phone' => '2245-8801', 'whatsapp' => '50255480012', 'position' => 'Asesora técnica · sellos y empaques',
    'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(640), 'last_login_at' => $d(0, 7, 55), 'last_login_ip' => '181.174.10.23']);
$v2 = $ins('users', ['company_id' => $c1, 'name' => 'Marco Tulio Say', 'email' => 'msay@industrialperez.gt',
    'username' => 'vendedor2', 'password' => Security::hashPassword('Venta2026!'), 'role' => 'vendedor',
    'phone' => '2245-8802', 'whatsapp' => '50255480013', 'position' => 'Asesor técnico · transmisión',
    'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(520), 'last_login_at' => $d(1, 16, 40), 'last_login_ip' => '181.174.10.24']);
$v3 = $ins('users', ['company_id' => $c1, 'name' => 'Karla Estrada', 'email' => 'kestrada@industrialperez.gt',
    'username' => 'vendedor3', 'password' => Security::hashPassword('Venta2026!'), 'role' => 'vendedor',
    'phone' => '2245-8803', 'whatsapp' => '50255480014', 'position' => 'Asesora técnica · hules y plásticos',
    'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(300), 'last_login_at' => $d(2, 11, 5), 'last_login_ip' => '181.174.10.25']);
$vis = $ins('users', ['company_id' => $c1, 'name' => 'Sofía Contreras', 'email' => 'scontreras@industrialperez.gt',
    'username' => 'visor1', 'password' => Security::hashPassword('Visor2026!'), 'role' => 'visor',
    'position' => 'Contabilidad', 'status' => 'activo', 'receives_leads' => 0, 'created_at' => $d(200)]);
$sellers = [$v1, $v2, $v3];

$pl1 = $ins('price_lists', ['company_id' => $c1, 'name' => 'Precio de lista', 'discount_pct' => 0, 'is_default' => 1]);
$pl2 = $ins('price_lists', ['company_id' => $c1, 'name' => 'Mayorista', 'discount_pct' => 12, 'is_default' => 0]);
$pl3 = $ins('price_lists', ['company_id' => $c1, 'name' => 'Distribuidor', 'discount_pct' => 22, 'is_default' => 0]);

$brands = [];
foreach ([['Chesterton', 1], ['Garlock', 2], ['John Crane', 3], ['SKF', 4], ['Parker', 5], ['Gates', 6], ['Teadit', 7]] as [$bn, $so]) {
    $brands[$bn] = $ins('brands', ['company_id' => $c1, 'name' => $bn, 'slug' => slugify($bn), 'sort' => $so, 'active' => 1]);
}

$cats = [];
$catData = [
    ['Sellos mecánicos', 'SM', 'Sellos de fuelle, de cartucho y retenes para bombas centrífugas, de proceso y sanitarias.'],
    ['Empaques y juntas', 'EM', 'Empaques de brida, espirometálicos y juntas cortadas a la medida en cualquier material.'],
    ['Teflón y plásticos técnicos', 'TF', 'Lámina, barra y tubo de PTFE virgen y cargado, nylon, UHMW y acetal.'],
    ['Hules y elastómeros', 'HU', 'O-rings, plancha de hule, perfiles y empaquetaduras en nitrilo, vitón, EPDM y silicón.'],
    ['Rodamientos y transmisión', 'RT', 'Rodamientos, bandas, acoples y chumaceras para líneas de producción.'],
    ['Válvulas y mangueras', 'VM', 'Válvulas de compuerta y bola, mangueras industriales, acoples y lubricación.'],
];
foreach ($catData as $i => [$cn, $code, $desc]) {
    $cats[$code] = $ins('categories', ['company_id' => $c1, 'parent_id' => null, 'name' => $cn, 'slug' => slugify($cn),
        'code' => $code, 'description' => $desc, 'image' => null, 'sort' => $i + 1, 'active' => 1,
        'seo_title' => $cn . ' — Industrial Pérez', 'seo_description' => $desc, 'created_at' => $d(700)]);
}
// Subcategorías
$sub = [];
foreach ([['SM', 'Tipo fuelle'], ['SM', 'De cartucho'], ['EM', 'Espirometálicos'], ['HU', 'O-rings']] as $i => [$parent, $name]) {
    $sub[$name] = $ins('categories', ['company_id' => $c1, 'parent_id' => $cats[$parent], 'name' => $name, 'slug' => slugify($name),
        'sort' => 10 + $i, 'active' => 1, 'created_at' => $d(690)]);
}

$attrs = [];
$attrData = [
    ['material', 'Material', 'texto', '', ['Carbón / Cerámica', 'Carburo de silicio', 'Tungsteno', 'Nitrilo (NBR)', 'Vitón (FKM)', 'EPDM', 'PTFE virgen', 'Grafito', 'Acero inoxidable 316'], 1],
    ['medida', 'Medida nominal', 'texto', 'mm', [], 2],
    ['norma', 'Norma / Serie', 'texto', '', ['ASME B16.5', 'DIN 24960', 'ISO 3069', 'SAE J517', 'ANSI 150#'], 3],
    ['temp_max', 'Temperatura máxima', 'numero', '°C', [], 4],
    ['presion_max', 'Presión máxima', 'numero', 'bar', [], 5],
    ['aplicacion', 'Aplicación', 'lista', '', ['Agua y efluentes', 'Químicos', 'Alimentos y bebidas', 'Vapor', 'Aceites y combustibles', 'Azúcar y melaza', 'Minería'], 6],
];
foreach ($attrData as [$code, $label, $type, $unit, $opts, $sort]) {
    $attrs[$code] = $ins('attribute_defs', ['company_id' => $c1, 'category_id' => null, 'code' => $code, 'label' => $label,
        'type' => $type, 'unit' => $unit ?: null, 'options' => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : null,
        'filterable' => 1, 'sort' => $sort]);
}

/* ------------------------------------------------------------ productos */
$P = [
    // [código, nombre, cat, subcat|null, marca, unidad, precio, lámina, descCorta, atributos]
    ['SM-T21-025', 'Sello mecánico tipo 21 · eje 25 mm', 'SM', 'Tipo fuelle', 'John Crane', 'unidad', 385.00, 'sello-mecanico', 'Sello de fuelle de hule con caras carbón/cerámica para bomba centrífuga.', ['material' => 'Carbón / Cerámica', 'medida' => '25', 'norma' => 'DIN 24960', 'temp_max' => '120', 'presion_max' => '10', 'aplicacion' => 'Agua y efluentes']],
    ['SM-T21-032', 'Sello mecánico tipo 21 · eje 32 mm', 'SM', 'Tipo fuelle', 'John Crane', 'unidad', 425.00, 'sello-mecanico', 'Sello de fuelle de hule, caras carbón/cerámica, resorte único.', ['material' => 'Carbón / Cerámica', 'medida' => '32', 'norma' => 'DIN 24960', 'temp_max' => '120', 'presion_max' => '10', 'aplicacion' => 'Agua y efluentes']],
    ['SM-T21-045', 'Sello mecánico tipo 21 · eje 45 mm', 'SM', 'Tipo fuelle', 'John Crane', 'unidad', 610.00, 'sello-mecanico', 'Sello de fuelle para bombas de mediano caudal.', ['material' => 'Carbón / Cerámica', 'medida' => '45', 'norma' => 'DIN 24960', 'temp_max' => '120', 'presion_max' => '10', 'aplicacion' => 'Agua y efluentes']],
    ['SM-SIC-038', 'Sello mecánico carburo de silicio · 38 mm', 'SM', 'Tipo fuelle', 'Chesterton', 'unidad', 1240.00, 'sello-mecanico', 'Caras de carburo de silicio para líquidos abrasivos y melaza.', ['material' => 'Carburo de silicio', 'medida' => '38', 'temp_max' => '200', 'presion_max' => '16', 'aplicacion' => 'Azúcar y melaza']],
    ['SM-CAR-050', 'Sello de cartucho simple · 50 mm', 'SM', 'De cartucho', 'Chesterton', 'unidad', 3480.00, 'sello-cartucho', 'Cartucho premontado, instalación sin ajustes en planta.', ['material' => 'Carburo de silicio', 'medida' => '50', 'norma' => 'ISO 3069', 'temp_max' => '180', 'presion_max' => '25', 'aplicacion' => 'Químicos']],
    ['SM-CAR-065', 'Sello de cartucho doble · 65 mm', 'SM', 'De cartucho', 'John Crane', 'unidad', 5760.00, 'sello-cartucho', 'Cartucho doble con plan de barrera para productos peligrosos.', ['material' => 'Tungsteno', 'medida' => '65', 'norma' => 'ISO 3069', 'temp_max' => '220', 'presion_max' => '40', 'aplicacion' => 'Químicos']],
    ['SM-SAN-040', 'Sello sanitario 3A · 40 mm', 'SM', 'De cartucho', 'Chesterton', 'unidad', 2890.00, 'sello-cartucho', 'Sello sanitario en acero 316L con elastómeros grado alimenticio.', ['material' => 'Acero inoxidable 316', 'medida' => '40', 'temp_max' => '140', 'presion_max' => '12', 'aplicacion' => 'Alimentos y bebidas']],
    ['RET-3555-10', 'Retén de aceite 35×55×10', 'SM', null, 'SKF', 'unidad', 68.00, 'reten-aceite', 'Retén con labio de nitrilo y resorte de acero inoxidable.', ['material' => 'Nitrilo (NBR)', 'medida' => '35', 'temp_max' => '100', 'aplicacion' => 'Aceites y combustibles']],
    ['RET-5075-10', 'Retén de aceite 50×75×10', 'SM', null, 'SKF', 'unidad', 92.00, 'reten-aceite', 'Retén estándar para eje de 50 mm.', ['material' => 'Nitrilo (NBR)', 'medida' => '50', 'temp_max' => '100', 'aplicacion' => 'Aceites y combustibles']],
    ['RET-VIT-6085', 'Retén Vitón 60×85×12', 'SM', null, 'Parker', 'unidad', 235.00, 'reten-aceite', 'Retén de fluorocarbono para altas temperaturas y aceites sintéticos.', ['material' => 'Vitón (FKM)', 'medida' => '60', 'temp_max' => '200', 'aplicacion' => 'Aceites y combustibles']],

    ['EMP-BR-2-150', 'Empaque de brida 2" clase 150', 'EM', null, 'Garlock', 'unidad', 48.00, 'empaque-brida', 'Empaque de fibra comprimida sin asbesto, cara realzada.', ['material' => 'Grafito', 'medida' => '50', 'norma' => 'ASME B16.5', 'temp_max' => '260', 'presion_max' => '20', 'aplicacion' => 'Vapor']],
    ['EMP-BR-3-150', 'Empaque de brida 3" clase 150', 'EM', null, 'Garlock', 'unidad', 62.00, 'empaque-brida', 'Empaque de fibra comprimida, 3 mm de espesor.', ['material' => 'Grafito', 'medida' => '80', 'norma' => 'ASME B16.5', 'temp_max' => '260', 'presion_max' => '20', 'aplicacion' => 'Vapor']],
    ['EMP-BR-4-300', 'Empaque de brida 4" clase 300', 'EM', null, 'Garlock', 'unidad', 118.00, 'empaque-brida', 'Para servicio de mayor presión en líneas de proceso.', ['material' => 'Grafito', 'medida' => '100', 'norma' => 'ASME B16.5', 'temp_max' => '300', 'presion_max' => '50', 'aplicacion' => 'Químicos']],
    ['EMP-SW-3-150', 'Empaque espirometálico 3" 150#', 'EM', 'Espirometálicos', 'Teadit', 'unidad', 285.00, 'empaque-espirometalico', 'Devanado en V de acero 316 con relleno de grafito flexible.', ['material' => 'Acero inoxidable 316', 'medida' => '80', 'norma' => 'ASME B16.5', 'temp_max' => '450', 'presion_max' => '50', 'aplicacion' => 'Vapor']],
    ['EMP-SW-6-300', 'Empaque espirometálico 6" 300#', 'EM', 'Espirometálicos', 'Teadit', 'unidad', 640.00, 'empaque-espirometalico', 'Con anillo guía externo e interno para alta presión.', ['material' => 'Acero inoxidable 316', 'medida' => '150', 'norma' => 'ASME B16.5', 'temp_max' => '450', 'presion_max' => '100', 'aplicacion' => 'Vapor']],
    ['EMP-COR-A', 'Empaque cortado a la medida (según plano)', 'EM', null, null, 'unidad', 0.00, 'empaque-brida', 'Cortamos su empaque en cualquier material a partir de la muestra o el plano.', ['aplicacion' => 'Químicos']],

    ['TF-LAM-1.5', 'Lámina de PTFE virgen 1.5 mm · 1×1 m', 'TF', null, null, 'lámina', 745.00, 'lamina-teflon', 'PTFE virgen 100%, blanco, apto para contacto con alimentos.', ['material' => 'PTFE virgen', 'medida' => '1.5', 'temp_max' => '260', 'aplicacion' => 'Alimentos y bebidas']],
    ['TF-LAM-3', 'Lámina de PTFE virgen 3 mm · 1×1 m', 'TF', null, null, 'lámina', 1420.00, 'lamina-teflon', 'Lámina de teflón virgen para juntas y placas de desgaste.', ['material' => 'PTFE virgen', 'medida' => '3', 'temp_max' => '260', 'aplicacion' => 'Químicos']],
    ['TF-LAM-6', 'Lámina de PTFE virgen 6 mm · 1×1 m', 'TF', null, null, 'lámina', 2780.00, 'lamina-teflon', 'Espesor grueso para asientos y guías.', ['material' => 'PTFE virgen', 'medida' => '6', 'temp_max' => '260', 'aplicacion' => 'Químicos']],
    ['TF-BAR-50', 'Barra de PTFE Ø50 mm × 1 m', 'TF', null, null, 'barra', 1180.00, 'lamina-teflon', 'Barra maciza para maquinado de bujes y anillos.', ['material' => 'PTFE virgen', 'medida' => '50', 'temp_max' => '260', 'aplicacion' => 'Químicos']],
    ['TF-NYL-40', 'Barra de nylon 6/6 Ø40 mm × 1 m', 'TF', null, null, 'barra', 385.00, 'lamina-teflon', 'Nylon extruido para engranes, bujes y poleas.', ['material' => 'PTFE virgen', 'medida' => '40', 'temp_max' => '95', 'aplicacion' => 'Minería']],
    ['TF-UHMW-20', 'Lámina UHMW 20 mm · 1×2 m', 'TF', null, null, 'lámina', 3260.00, 'lamina-teflon', 'Polietileno de ultra alto peso molecular para tolvas y guías.', ['material' => 'PTFE virgen', 'medida' => '20', 'temp_max' => '80', 'aplicacion' => 'Azúcar y melaza']],

    ['OR-NBR-214', 'O-ring nitrilo AS568-214', 'HU', 'O-rings', 'Parker', 'unidad', 6.50, 'oring', 'Anillo O-ring de nitrilo 70 shore, medida estándar.', ['material' => 'Nitrilo (NBR)', 'medida' => '24.99', 'temp_max' => '100', 'aplicacion' => 'Aceites y combustibles']],
    ['OR-VIT-222', 'O-ring Vitón AS568-222', 'HU', 'O-rings', 'Parker', 'unidad', 28.00, 'oring', 'O-ring de fluorocarbono para químicos agresivos.', ['material' => 'Vitón (FKM)', 'medida' => '37.47', 'temp_max' => '200', 'aplicacion' => 'Químicos']],
    ['OR-EPD-232', 'O-ring EPDM AS568-232', 'HU', 'O-rings', 'Parker', 'unidad', 14.50, 'oring', 'EPDM para vapor, agua caliente y limpieza CIP.', ['material' => 'EPDM', 'medida' => '69.44', 'temp_max' => '150', 'aplicacion' => 'Alimentos y bebidas']],
    ['OR-KIT-386', 'Kit de o-rings surtido · 386 piezas', 'HU', 'O-rings', 'Parker', 'juego', 985.00, 'oring', 'Estuche metálico con 30 medidas métricas en nitrilo.', ['material' => 'Nitrilo (NBR)', 'temp_max' => '100', 'aplicacion' => 'Agua y efluentes']],
    ['HU-PL-NBR-6', 'Plancha de hule nitrilo 6 mm · 1.4 m', 'HU', null, null, 'metro', 428.00, 'plancha-hule', 'Plancha de nitrilo resistente a aceites, rollo de 1.4 m de ancho.', ['material' => 'Nitrilo (NBR)', 'medida' => '6', 'temp_max' => '100', 'aplicacion' => 'Aceites y combustibles']],
    ['HU-PL-EPD-3', 'Plancha de hule EPDM 3 mm · 1.4 m', 'HU', null, null, 'metro', 296.00, 'plancha-hule', 'EPDM para intemperie, vapor y agua caliente.', ['material' => 'EPDM', 'medida' => '3', 'temp_max' => '150', 'aplicacion' => 'Vapor']],
    ['HU-PL-SIL-5', 'Plancha de silicón grado alimenticio 5 mm', 'HU', null, null, 'metro', 812.00, 'plancha-hule', 'Silicón blanco translúcido, certificado FDA.', ['material' => 'EPDM', 'medida' => '5', 'temp_max' => '230', 'aplicacion' => 'Alimentos y bebidas']],
    ['HU-EMPQ-12', 'Empaquetadura de grafito 12 mm · metro', 'HU', null, 'Garlock', 'metro', 268.00, 'plancha-hule', 'Trenza de grafito puro con esquineros para bombas y válvulas.', ['material' => 'Grafito', 'medida' => '12', 'temp_max' => '450', 'presion_max' => '25', 'aplicacion' => 'Vapor']],

    ['RB-6205-2RS', 'Rodamiento 6205-2RS', 'RT', null, 'SKF', 'unidad', 96.00, 'rodamiento', 'Rodamiento rígido de bolas sellado, 25×52×15 mm.', ['medida' => '25', 'temp_max' => '110', 'aplicacion' => 'Agua y efluentes']],
    ['RB-6308-2RS', 'Rodamiento 6308-2RS', 'RT', null, 'SKF', 'unidad', 268.00, 'rodamiento', 'Rodamiento sellado 40×90×23 mm para carga media.', ['medida' => '40', 'temp_max' => '110', 'aplicacion' => 'Minería']],
    ['RB-22212-E', 'Rodamiento de rodillos a rótula 22212 E', 'RT', null, 'SKF', 'unidad', 1685.00, 'rodamiento', 'Autoalineante para ejes con desalineación y carga alta.', ['medida' => '60', 'temp_max' => '120', 'aplicacion' => 'Azúcar y melaza']],
    ['BV-B75', 'Banda en V B75', 'RT', null, 'Gates', 'unidad', 118.00, 'banda-v', 'Banda industrial clásica de perfil B, 75 pulgadas.', ['medida' => '1905', 'temp_max' => '80', 'aplicacion' => 'Minería']],
    ['BV-SPB2500', 'Banda dentada SPB 2500', 'RT', null, 'Gates', 'unidad', 246.00, 'banda-v', 'Banda de perfil estrecho para transmisión de alta potencia.', ['medida' => '2500', 'temp_max' => '80', 'aplicacion' => 'Azúcar y melaza']],
    ['AC-FLEX-90', 'Acople flexible de quijadas L-090', 'RT', null, null, 'juego', 685.00, 'acople', 'Acople de quijadas con araña de uretano, incluye ambos cubos.', ['medida' => '90', 'temp_max' => '90', 'aplicacion' => 'Agua y efluentes']],
    ['AC-GRID-1060', 'Acople de rejilla 1060 T10', 'RT', null, null, 'juego', 2480.00, 'acople', 'Acople de rejilla de acero para servicio pesado.', ['medida' => '60', 'temp_max' => '110', 'aplicacion' => 'Minería']],

    ['VA-COM-3-150', 'Válvula de compuerta 3" clase 150', 'VM', null, null, 'unidad', 2840.00, 'valvula', 'Cuerpo de hierro dúctil, vástago ascendente, bridada.', ['material' => 'Acero inoxidable 316', 'medida' => '80', 'norma' => 'ANSI 150#', 'temp_max' => '180', 'presion_max' => '20', 'aplicacion' => 'Agua y efluentes']],
    ['VA-BOL-2-316', 'Válvula de bola 2" acero 316', 'VM', null, null, 'unidad', 1965.00, 'valvula', 'Válvula de bola de dos piezas, paso total, asientos de PTFE.', ['material' => 'Acero inoxidable 316', 'medida' => '50', 'norma' => 'ANSI 150#', 'temp_max' => '200', 'presion_max' => '40', 'aplicacion' => 'Químicos']],
    ['MG-VAP-1', 'Manguera de vapor 1" · metro', 'VM', null, 'Parker', 'metro', 385.00, 'manguera', 'Manguera de vapor saturado con refuerzo textil y alambre.', ['medida' => '25', 'norma' => 'SAE J517', 'temp_max' => '210', 'presion_max' => '18', 'aplicacion' => 'Vapor']],
    ['MG-HID-R2-12', 'Manguera hidráulica R2 1/2" · metro', 'VM', null, 'Parker', 'metro', 178.00, 'manguera', 'Doble malla de acero, para presión hidráulica alta.', ['medida' => '12.7', 'norma' => 'SAE J517', 'temp_max' => '100', 'presion_max' => '275', 'aplicacion' => 'Aceites y combustibles']],
    ['LU-GRA-EP2', 'Grasa industrial EP2 · cartucho 400 g', 'VM', null, 'Chesterton', 'unidad', 68.00, 'lubricante', 'Grasa de litio con aditivo de extrema presión.', ['temp_max' => '130', 'aplicacion' => 'Minería']],
    ['LU-GRA-ALT', 'Grasa de alta temperatura · cartucho 400 g', 'VM', null, 'Chesterton', 'unidad', 142.00, 'lubricante', 'Grasa de complejo de sulfonato de calcio hasta 200 °C.', ['temp_max' => '200', 'aplicacion' => 'Azúcar y melaza']],
];

$prodIds = [];
foreach ($P as $i => $row) {
    [$code, $name, $cat, $subName, $brand, $unit, $price, $plate, $short, $pattrs] = $row;
    $catId = $subName !== null ? $sub[$subName] : $cats[$cat];
    $pid = $ins('products', [
        'company_id' => $c1, 'category_id' => $catId, 'brand_id' => $brand ? $brands[$brand] : null,
        'code' => $code, 'name' => $name, 'slug' => slugify($name . '-' . $code),
        'short_desc' => $short,
        'description' => $short . " Contamos con existencia en Guatemala y equivalencias de las principales marcas. Si necesita una medida distinta, la fabricamos bajo plano o a partir de su muestra.",
        'application' => $pattrs['aplicacion'] ?? null,
        'unit' => $unit, 'price' => $price, 'cost' => round($price * 0.62, 2),
        'price_visibility' => 'heredar', 'min_qty' => in_array($unit, ['metro', 'lámina'], true) ? 1 : 1,
        'lead_time' => $price > 2000 ? '10 a 15 días' : 'Inmediato',
        'stock_note' => $price > 2500 ? 'Bajo pedido' : 'En existencia',
        'featured' => in_array($i, [0, 4, 13, 17, 22, 30, 37, 40], true) ? 1 : 0,
        'active' => 1, 'views' => mt_rand(12, 480), 'quote_count' => mt_rand(0, 26),
        'seo_title' => $name . ' · ' . $code . ' — Industrial Pérez',
        'seo_description' => $short,
        'created_at' => $d(mt_rand(120, 700)), 'updated_at' => $d(mt_rand(1, 90)),
    ]);
    $prodIds[$code] = $pid;
    $ins('product_images', ['company_id' => $c1, 'product_id' => $pid, 'path' => 'assets:img/plates/' . $plate . '.svg',
        'path_webp' => null, 'path_thumb' => 'assets:img/plates/' . $plate . '.svg', 'width' => 900, 'height' => 675,
        'alt' => $name, 'sort' => 1]);
    foreach ($pattrs as $ac => $av) {
        if (!isset($attrs[$ac])) {
            continue;
        }
        $ins('product_attributes', ['company_id' => $c1, 'product_id' => $pid, 'attribute_id' => $attrs[$ac], 'value' => (string) $av]);
    }
    if ($price > 0 && $i % 4 === 0) {
        $ins('product_prices', ['company_id' => $c1, 'product_id' => $pid, 'price_list_id' => $pl3, 'price' => round($price * 0.74, 2)]);
    }
}
$ins('product_documents', ['company_id' => $c1, 'product_id' => $prodIds['SM-CAR-050'], 'name' => 'Ficha técnica · sello de cartucho simple.pdf',
    'path' => 'assets:img/plates/sello-cartucho.svg', 'size' => 184320, 'created_at' => $d(120)]);

echo "Empresa 1: " . count($prodIds) . " productos\n";
require __DIR__ . '/seed-demo-2.php';
