<?php
declare(strict_types=1);
/** Continuación del seeder: clientes, cotizaciones, segunda empresa y volcado. */

use App\Core\Security;

/** @var PDO $pdo */
/** @var callable $ins */
/** @var callable $d */
/** @var int $c1 */
/** @var array $prodIds */
/** @var array $sellers */
/** @var int $pl1 */
/** @var int $pl2 */
/** @var int $pl3 */

/* ------------------------------------------------------------- clientes */
$customerData = [
    ['Ingenio San Rafael', 'Ingenio San Rafael, S.A.', '1024578-9', 'compras@sanrafael.gt', '7887-2200', 'Escuintla', 'Azucarero'],
    ['Cementos del Sur', 'Cementos del Sur, S.A.', '2048871-3', 'abastecimiento@cemsur.gt', '2410-6600', 'Guatemala', 'Cemento'],
    ['Embotelladora Central', 'Embotelladora Central, S.A.', '3157902-1', 'compras@embocentral.gt', '2380-4400', 'Guatemala', 'Bebidas'],
    ['Alimentos del Valle', 'Alimentos del Valle, S.A.', '4471203-7', 'jcompras@alimentosdelvalle.gt', '6640-1122', 'Quetzaltenango', 'Alimentos'],
    ['Textiles Xelajú', 'Textiles Xelajú, S.A.', '5580211-5', 'mantenimiento@texxela.gt', '7761-8800', 'Quetzaltenango', 'Textil'],
    ['Papelera Nacional', 'Papelera Nacional, S.A.', '6690345-2', 'compras@papelnac.gt', '2225-9900', 'Guatemala', 'Papel'],
    ['Minera Los Cerros', 'Minera Los Cerros, S.A.', '7712008-8', 'suministros@loscerros.gt', '7930-4455', 'Izabal', 'Minería'],
    ['Lácteos La Pradera', 'Lácteos La Pradera, S.A.', '8823117-4', 'compras@lapradera.gt', '7832-2200', 'Chimaltenango', 'Alimentos'],
    ['Química Industrial GT', 'Química Industrial de Guatemala, S.A.', '9934226-0', 'compras@quimicagt.com', '2472-3300', 'Guatemala', 'Químico'],
    ['Municipalidad de Mixco', 'Municipalidad de Mixco', '1045335-6', 'compras@munimixco.gob.gt', '2432-1100', 'Mixco', 'Sector público'],
    ['Agroindustrias del Norte', 'Agroindustrias del Norte, S.A.', '1156444-2', 'compras@agronorte.gt', '7951-6677', 'Cobán', 'Agroindustria'],
    ['Plásticos Modernos', 'Plásticos Modernos, S.A.', '1267553-8', 'jvasquez@plastimod.gt', '2385-1200', 'Villa Nueva', 'Plásticos'],
    ['Beneficio Café Antigua', 'Beneficio Café Antigua, S.A.', '1378662-4', 'compras@cafeantigua.gt', '7832-9911', 'Sacatepéquez', 'Café'],
    ['Hidroeléctrica Río Azul', 'Hidroeléctrica Río Azul, S.A.', '1489771-0', 'mantenimiento@rioazul.gt', '7720-3344', 'Alta Verapaz', 'Energía'],
    ['Farmacéutica Centroamericana', 'Farmacéutica Centroamericana, S.A.', '1590880-7', 'compras@farmacen.gt', '2445-7788', 'Guatemala', 'Farmacéutico'],
    ['Astilleros del Pacífico', 'Astilleros del Pacífico, S.A.', '1601999-3', 'compras@astipac.gt', '7881-5566', 'San José', 'Naval'],
    ['Procesadora Avícola El Roble', 'Procesadora Avícola El Roble, S.A.', '1713008-9', 'compras@elroble.gt', '6620-4433', 'Escuintla', 'Alimentos'],
    ['Refinería Petén', 'Refinería Petén, S.A.', '1824117-5', 'suministros@refpeten.gt', '7926-1100', 'Petén', 'Petróleo'],
    ['Talleres Industriales Monzón', 'Talleres Industriales Monzón', '1935226-1', 'monzon.taller@gmail.com', '5544-8899', 'Guatemala', 'Metalmecánica'],
    ['Constructora Bethel', 'Constructora Bethel, S.A.', '2046335-8', 'compras@bethel.gt', '2260-7788', 'Guatemala', 'Construcción'],
];
$customers = [];
foreach ($customerData as $i => [$name, $legal, $nit, $email, $phone, $city, $sector]) {
    $cid = $ins('customers', [
        'company_id' => $c1, 'name' => $name, 'legal_name' => $legal, 'nit' => $nit, 'email' => $email,
        'phone' => $phone, 'whatsapp' => '502' . preg_replace('/\D/', '', $phone),
        'address' => 'Km ' . mt_rand(8, 92) . ' carretera al ' . ['Pacífico', 'Atlántico', 'Salvador', 'Occidente'][$i % 4],
        'city' => $city, 'sector' => $sector,
        'price_list_id' => [$pl1, $pl2, $pl3][$i % 3],
        'assigned_user_id' => $sellers[$i % 3],
        'notes' => $i % 4 === 0 ? 'Compra por orden de compra. Requiere factura con orden adjunta.' : null,
        'next_followup' => $i % 5 === 0 ? date('Y-m-d', strtotime('+' . mt_rand(2, 20) . ' days')) : null,
        'created_at' => $d(mt_rand(90, 640)), 'updated_at' => $d(mt_rand(1, 60)),
    ]);
    $customers[] = ['id' => $cid, 'name' => $name, 'legal' => $legal, 'nit' => $nit, 'email' => $email, 'phone' => $phone];
    $ins('customer_contacts', ['company_id' => $c1, 'customer_id' => $cid,
        'name' => ['Luis Morales', 'Claudia Herrera', 'José Alberto Gómez', 'Silvia Ruano', 'Óscar Batres'][$i % 5],
        'position' => ['Jefe de compras', 'Jefe de mantenimiento', 'Supervisor de planta', 'Analista de abastecimiento'][$i % 4],
        'email' => $email, 'phone' => $phone, 'is_primary' => 1]);
}

/* --------------------------------------------------------- cotizaciones */
$codes = array_keys($prodIds);
$prodRows = [];
foreach ($pdo->query('SELECT id, code, name, unit, price FROM products WHERE company_id = ' . (int) $c1) as $r) {
    $prodRows[$r['code']] = $r;
}
$specsByProduct = [];
foreach ($pdo->query('SELECT pa.product_id, a.label, pa.value, a.unit FROM product_attributes pa JOIN attribute_defs a ON a.id = pa.attribute_id') as $r) {
    $specsByProduct[(int) $r['product_id']][] = $r['label'] . ': ' . $r['value'] . ($r['unit'] ? ' ' . $r['unit'] : '');
}

$statusPlan = [
    ['nueva', 5], ['elaboracion', 5], ['enviada', 7], ['negociacion', 5], ['aprobada', 8], ['perdida', 5],
];
$lostReasons = ['precio', 'tiempo de entrega', 'competencia', 'sin presupuesto', 'sin respuesta'];
$seq = 1;
$quoteNo = 0;
$deliveries = ['Inmediata sobre existencia', '8 días hábiles', '10 a 15 días hábiles', '3 semanas (importación)'];
$payments = ['50% anticipo, 50% contra entrega', 'Crédito 30 días previa aprobación', 'Contado contra entrega', '30% anticipo, saldo a 30 días'];

foreach ($statusPlan as [$status, $count]) {
    for ($k = 0; $k < $count; $k++) {
        $quoteNo++;
        $cust = $customers[($quoteNo * 7) % count($customers)];
        $seller = $sellers[$quoteNo % 3];
        $ageMap = ['nueva' => [0, 4], 'elaboracion' => [2, 9], 'enviada' => [4, 26], 'negociacion' => [8, 40], 'aprobada' => [12, 150], 'perdida' => [20, 170]];
        $age = mt_rand($ageMap[$status][0], $ageMap[$status][1]);
        $created = $d($age, mt_rand(8, 17), mt_rand(0, 59));
        $token = bin2hex(random_bytes(28)) . substr(hash('sha256', 'demo' . $quoteNo), 0, 24);
        $source = $quoteNo % 3 === 0 ? 'web' : 'panel';
        $validity = 15;

        $qid = $ins('quotes', [
            'company_id' => $c1, 'number' => sprintf('COT-%d-%04d', (int) date('Y'), $seq++),
            'folio_seq' => $seq - 1, 'folio_year' => (int) date('Y'), 'version' => 1, 'is_current' => 1,
            'customer_id' => $cust['id'], 'user_id' => $seller,
            'contact_name' => ['Luis Morales', 'Claudia Herrera', 'José Alberto Gómez', 'Silvia Ruano', 'Óscar Batres'][$quoteNo % 5],
            'contact_company' => $cust['legal'], 'contact_nit' => $cust['nit'],
            'contact_phone' => $cust['phone'], 'contact_email' => $cust['email'],
            'status' => $status, 'source' => $source, 'currency_symbol' => 'Q',
            'tax_rate' => 12.000,
            'discount_type' => $quoteNo % 6 === 0 ? 'porcentaje' : 'ninguno',
            'discount_value' => $quoteNo % 6 === 0 ? 5 : 0,
            'validity_days' => $validity,
            'valid_until' => date('Y-m-d', strtotime($created . ' +' . $validity . ' days')),
            'delivery_time' => $deliveries[$quoteNo % 4],
            'payment_terms' => $payments[$quoteNo % 4],
            'notes' => $quoteNo % 5 === 0 ? 'Precios sujetos a existencia al momento de la orden. No incluye instalación en planta.' : null,
            'internal_notes' => $quoteNo % 7 === 0 ? 'Cliente compara con la competencia. Margen mínimo autorizado: 18%.' : null,
            'client_message' => $source === 'web' ? 'Necesitamos estos repuestos para el paro programado del próximo mes. Favor indicar tiempo de entrega.' : null,
            'track_token' => $token,
            'created_at' => $created, 'updated_at' => $created,
            'last_contact_at' => $d(max(0, $age - mt_rand(0, min(9, $age)))),
            'sent_at' => in_array($status, ['enviada', 'negociacion', 'aprobada', 'perdida'], true) ? $d(max(0, $age - 1), 11, 20) : null,
            'viewed_at' => in_array($status, ['negociacion', 'aprobada'], true) ? $d(max(0, $age - 2), 15, 5) : null,
            'approved_at' => $status === 'aprobada' ? $d(max(0, $age - 4), 10, 30) : null,
            'lost_at' => $status === 'perdida' ? $d(max(0, $age - 6), 16, 0) : null,
            'lost_reason' => $status === 'perdida' ? $lostReasons[$quoteNo % 5] : null,
            'lost_detail' => $status === 'perdida' ? ['El competidor ofreció 9% menos.', 'Requerían entrega en 3 días.', 'Compraron con el fabricante directo.', 'Presupuesto congelado hasta el siguiente trimestre.', 'No hubo respuesta tras tres seguimientos.'][$quoteNo % 5] : null,
        ]);

        // Líneas
        $n = mt_rand(2, 6);
        $subtotal = 0.0;
        $picked = [];
        for ($li = 0; $li < $n; $li++) {
            $code = $codes[($quoteNo * 13 + $li * 7) % count($codes)];
            if (isset($picked[$code])) {
                continue;
            }
            $picked[$code] = true;
            $p = $prodRows[$code];
            $qty = (float) mt_rand(1, 12);
            $unitPrice = (float) $p['price'];
            if ($unitPrice <= 0) {
                $unitPrice = (float) mt_rand(150, 900);
            }
            $disc = ($li === 0 && $quoteNo % 4 === 0) ? 8.0 : 0.0;
            $line = round($qty * $unitPrice * (1 - $disc / 100), 2);
            $subtotal += $line;
            $ins('quote_items', [
                'company_id' => $c1, 'quote_id' => $qid, 'product_id' => (int) $p['id'],
                'code' => $p['code'], 'name' => $p['name'],
                'specs' => mb_substr(implode(' · ', $specsByProduct[(int) $p['id']] ?? []), 0, 500) ?: null,
                'notes' => $li === 1 && $quoteNo % 3 === 0 ? 'Confirmar medida contra la pieza en planta.' : null,
                'qty' => $qty, 'unit' => $p['unit'], 'unit_price' => $unitPrice,
                'discount_pct' => $disc, 'line_total' => $line, 'sort' => $li,
            ]);
        }
        $subtotal = round($subtotal, 2);
        $discAmount = $quoteNo % 6 === 0 ? round($subtotal * 0.05, 2) : 0.0;
        $base = round($subtotal - $discAmount, 2);
        $tax = round($base * 0.12, 2);
        $total = round($base + $tax, 2);
        $pdo->prepare('UPDATE quotes SET subtotal=?, discount_amount=?, taxable_base=?, tax_amount=?, total=?, won_amount=? WHERE id=?')
            ->execute([$subtotal, $discAmount, $base, $tax, $total, $status === 'aprobada' ? $total : 0, $qid]);

        // Bitácora
        $events = [['sistema', $source === 'web' ? 'Solicitud recibida desde el sitio web' : 'Cotización creada desde el panel', '', $created]];
        if (in_array($status, ['elaboracion', 'enviada', 'negociacion', 'aprobada', 'perdida'], true)) {
            $events[] = ['estado', 'Estado: Nueva → Elaboración', '', $d(max(0, $age - 1), 9, 40)];
        }
        if (in_array($status, ['enviada', 'negociacion', 'aprobada', 'perdida'], true)) {
            $events[] = ['correo', 'Cotización enviada a ' . $cust['email'], 'Se adjuntó el PDF y el enlace de seguimiento.', $d(max(0, $age - 1), 11, 20)];
            $events[] = ['estado', 'Estado: Elaboración → Enviada', '', $d(max(0, $age - 1), 11, 21)];
        }
        if (in_array($status, ['negociacion', 'aprobada'], true)) {
            $events[] = ['cliente', 'El cliente abrió el enlace de seguimiento', '', $d(max(0, $age - 2), 15, 5)];
        }
        if ($status === 'negociacion') {
            $events[] = ['cliente', 'El cliente solicitó cambios', 'Solicitan mejorar el tiempo de entrega y revisar el precio de las dos primeras líneas.', $d(max(0, $age - 3), 9, 5)];
            $events[] = ['llamada', 'Llamada registrada', 'Se ofreció entrega parcial en 5 días para las líneas en existencia.', $d(max(0, $age - 4), 14, 30)];
        }
        if ($status === 'aprobada') {
            $events[] = ['cliente', 'El cliente APROBÓ la cotización', 'Aprobada desde el enlace de seguimiento.', $d(max(0, $age - 4), 10, 30)];
            $events[] = ['estado', 'Estado: Enviada → Aprobada', '', $d(max(0, $age - 4), 10, 31)];
        }
        if ($status === 'perdida') {
            $events[] = ['nota', 'Nota interna', (string) ($lostReasons[$quoteNo % 5] === 'precio' ? 'El cliente compartió la oferta de la competencia.' : 'Sin retroalimentación adicional del cliente.'), $d(max(0, $age - 5), 12, 0)];
            $events[] = ['estado', 'Estado: Enviada → Perdida', '', $d(max(0, $age - 6), 16, 0)];
        }
        foreach ($events as [$type, $title, $body, $when]) {
            $ins('quote_events', ['company_id' => $c1, 'quote_id' => $qid, 'user_id' => $seller,
                'actor' => $type === 'cliente' ? 'Cliente' : 'Sistema', 'type' => $type,
                'title' => $title, 'body' => $body !== '' ? $body : null, 'created_at' => $when]);
        }
    }
}
$pdo->exec('UPDATE companies SET quote_next = ' . ($seq) . ' WHERE id = ' . (int) $c1);
echo "Empresa 1: {$quoteNo} cotizaciones\n";

/* =====================================================================
   EMPRESA 2 · Uniformes Roca (verifica el aislamiento de datos)
   ===================================================================== */
$c2 = $ins('companies', [
    'slug' => 'uniformes-roca', 'name' => 'Uniformes Roca', 'legal_name' => 'Confecciones Roca, S.A.',
    'nit' => '3392015-7', 'plan_id' => null, 'status' => 'activa', 'expires_at' => date('Y-m-d', strtotime('+7 months')),
    'theme' => 'cobalto', 'color_accent' => '#1F5FBF', 'color_ink' => '#141A22', 'color_paper' => '#F3F5F8',
    'tagline' => 'Uniformes industriales y equipo de protección para su planta',
    'about' => "Confeccionamos uniformes industriales, overoles y ropa de trabajo con bordado y reflectivo. Atendemos pedidos por volumen con tallas por persona y reposición programada.",
    'years_experience' => 14,
    'email' => 'ventas@uniformesroca.gt', 'phone' => '2298-4400', 'whatsapp' => '50255990022',
    'address' => '5a calle 2-18, zona 3', 'city' => 'Mixco', 'country' => 'Guatemala',
    'currency_symbol' => 'Q', 'tax_rate' => 12.000, 'tax_label' => 'IVA', 'price_visibility' => 'publico',
    'quote_prefix' => 'UR', 'quote_next' => 5, 'quote_year' => (int) date('Y'), 'quote_pad' => 4,
    'pdf_terms' => 'Los precios incluyen bordado de una posición. Cambios de talla se reciben dentro de los 8 días posteriores a la entrega.',
    'validity_days' => 20, 'delivery_terms' => '12 días hábiles', 'payment_terms' => '60% anticipo, 40% contra entrega',
    'reminder_days_seller' => 4, 'reminder_days_client' => 0, 'assign_mode' => 'manual',
    'seo_title' => 'Uniformes Roca — Uniformes industriales y equipo de protección en Guatemala',
    'seo_description' => 'Camisas, overoles, chalecos reflectivos y equipo de protección personal. Cotice en línea por tallas y cantidades.',
    'created_at' => $d(400), 'updated_at' => $d(5),
]);
$u2 = $ins('users', ['company_id' => $c2, 'name' => 'Mónica Roca', 'email' => 'admin@uniformesroca.gt',
    'username' => 'mroca', 'password' => Security::hashPassword('Roca2026!'), 'role' => 'admin',
    'phone' => '2298-4400', 'whatsapp' => '50255990022', 'position' => 'Directora comercial',
    'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(400), 'last_login_at' => $d(3, 10, 0)]);
$v21 = $ins('users', ['company_id' => $c2, 'name' => 'Diego Alvarado', 'email' => 'dalvarado@uniformesroca.gt',
    'username' => 'dalvarado', 'password' => Security::hashPassword('Venta2026!'), 'role' => 'vendedor',
    'position' => 'Ejecutivo de cuenta', 'status' => 'activo', 'receives_leads' => 1, 'created_at' => $d(320)]);
$pl21 = $ins('price_lists', ['company_id' => $c2, 'name' => 'Precio de lista', 'discount_pct' => 0, 'is_default' => 1]);
$pl22 = $ins('price_lists', ['company_id' => $c2, 'name' => 'Volumen (100+)', 'discount_pct' => 15, 'is_default' => 0]);

$cat21 = $ins('categories', ['company_id' => $c2, 'name' => 'Uniformes y ropa de trabajo', 'slug' => 'uniformes-ropa-trabajo',
    'code' => 'UN', 'description' => 'Camisas, pantalones y overoles industriales con bordado incluido.', 'sort' => 1, 'active' => 1, 'created_at' => $d(390)]);
$cat22 = $ins('categories', ['company_id' => $c2, 'name' => 'Equipo de protección personal', 'slug' => 'equipo-proteccion-personal',
    'code' => 'EP', 'description' => 'Cascos, chalecos, guantes y calzado de seguridad certificado.', 'sort' => 2, 'active' => 1, 'created_at' => $d(390)]);
$b21 = $ins('brands', ['company_id' => $c2, 'name' => 'Roca Workwear', 'slug' => 'roca-workwear', 'sort' => 1, 'active' => 1]);
$a21 = $ins('attribute_defs', ['company_id' => $c2, 'code' => 'talla', 'label' => 'Tallas disponibles', 'type' => 'texto', 'filterable' => 1, 'sort' => 1]);
$a22 = $ins('attribute_defs', ['company_id' => $c2, 'code' => 'tela', 'label' => 'Tela', 'type' => 'lista',
    'options' => json_encode(['Gabardina 3/1', 'Oxford', 'Dril 12 oz', 'Poliéster reflectivo'], JSON_UNESCAPED_UNICODE), 'filterable' => 1, 'sort' => 2]);

$P2 = [
    ['UN-CAM-IND', 'Camisa industrial manga larga', $cat21, 'unidad', 168.00, 'uniforme-camisa', 'Gabardina 3/1 con bordado de logo en pecho.', ['talla' => 'S a 3XL', 'tela' => 'Gabardina 3/1']],
    ['UN-CAM-REF', 'Camisa con cinta reflectiva', $cat21, 'unidad', 215.00, 'uniforme-camisa', 'Camisa con cinta reflectiva de 2" en torso y mangas.', ['talla' => 'S a 3XL', 'tela' => 'Poliéster reflectivo']],
    ['UN-PAN-DRL', 'Pantalón de dril 12 oz', $cat21, 'unidad', 195.00, 'generico', 'Pantalón reforzado con bolsa portaherramientas.', ['talla' => '28 a 44', 'tela' => 'Dril 12 oz']],
    ['UN-OVE-IND', 'Overol industrial entero', $cat21, 'unidad', 385.00, 'generico', 'Overol de una pieza con cierre frontal y elástico en cintura.', ['talla' => 'S a 3XL', 'tela' => 'Gabardina 3/1']],
    ['EP-CAS-BLA', 'Casco de seguridad tipo I', $cat22, 'unidad', 96.00, 'casco', 'Casco ANSI Z89.1 con suspensión de 4 puntos y ratchet.', ['talla' => 'Universal']],
    ['EP-CHA-REF', 'Chaleco reflectivo clase 2', $cat22, 'unidad', 78.00, 'generico', 'Chaleco de malla con cintas reflectivas y cierre frontal.', ['talla' => 'M a 2XL', 'tela' => 'Poliéster reflectivo']],
    ['EP-GUA-NIT', 'Guante de nitrilo palma recubierta', $cat22, 'par', 32.00, 'generico', 'Guante de soporte de nylon con recubrimiento de nitrilo.', ['talla' => '8 a 11']],
    ['EP-BOT-DIE', 'Bota dieléctrica punta composite', $cat22, 'par', 545.00, 'generico', 'Calzado de seguridad dieléctrico con punta no metálica.', ['talla' => '37 a 45']],
];
foreach ($P2 as $i => [$code, $name, $cat, $unit, $price, $plate, $short, $pa]) {
    $pid = $ins('products', ['company_id' => $c2, 'category_id' => $cat, 'brand_id' => $b21, 'code' => $code,
        'name' => $name, 'slug' => slugify($name . '-' . $code), 'short_desc' => $short,
        'description' => $short . ' El precio incluye bordado de una posición; consulte por cantidades mayores a 100 unidades.',
        'unit' => $unit, 'price' => $price, 'cost' => round($price * 0.58, 2), 'price_visibility' => 'heredar',
        'min_qty' => 1, 'lead_time' => '12 días hábiles', 'stock_note' => 'Bajo pedido',
        'featured' => $i < 3 ? 1 : 0, 'active' => 1, 'views' => mt_rand(10, 180), 'quote_count' => mt_rand(0, 14),
        'created_at' => $d(mt_rand(60, 380)), 'updated_at' => $d(mt_rand(1, 40))]);
    $ins('product_images', ['company_id' => $c2, 'product_id' => $pid, 'path' => 'assets:img/plates/' . $plate . '.svg',
        'path_thumb' => 'assets:img/plates/' . $plate . '.svg', 'width' => 900, 'height' => 675, 'alt' => $name, 'sort' => 1]);
    foreach ($pa as $ac => $av) {
        $aid = $ac === 'talla' ? $a21 : $a22;
        $ins('product_attributes', ['company_id' => $c2, 'product_id' => $pid, 'attribute_id' => $aid, 'value' => (string) $av]);
    }
}

$cust2 = [];
foreach ([['Ingenio San Rafael', '1024578-9', 'rrhh@sanrafael.gt', '7887-2200'],
          ['Cementos del Sur', '2048871-3', 'seguridad@cemsur.gt', '2410-6600'],
          ['Constructora Bethel', '2046335-8', 'bodega@bethel.gt', '2260-7788']] as $i => [$n, $nit, $em, $ph]) {
    $cust2[] = $ins('customers', ['company_id' => $c2, 'name' => $n, 'legal_name' => $n . ', S.A.', 'nit' => $nit,
        'email' => $em, 'phone' => $ph, 'city' => 'Guatemala', 'sector' => 'Industrial',
        'price_list_id' => $i === 0 ? $pl22 : $pl21, 'assigned_user_id' => $v21,
        'created_at' => $d(mt_rand(40, 300)), 'updated_at' => $d(mt_rand(1, 30))]);
}

$prod2 = [];
foreach ($pdo->query('SELECT id, code, name, unit, price FROM products WHERE company_id = ' . (int) $c2) as $r) {
    $prod2[] = $r;
}
$st2 = ['nueva', 'enviada', 'aprobada', 'perdida'];
foreach ($st2 as $i => $status) {
    $age = [1, 9, 28, 46][$i];
    $created = $d($age, 10, 30);
    $qid = $ins('quotes', [
        'company_id' => $c2, 'number' => sprintf('UR-%d-%04d', (int) date('Y'), $i + 1),
        'folio_seq' => $i + 1, 'folio_year' => (int) date('Y'), 'version' => 1, 'is_current' => 1,
        'customer_id' => $cust2[$i % 3], 'user_id' => $i % 2 === 0 ? $v21 : $u2,
        'contact_name' => ['Ana Sical', 'Rodrigo Paz', 'Elena Marroquín'][$i % 3],
        'contact_company' => ['Ingenio San Rafael, S.A.', 'Cementos del Sur, S.A.', 'Constructora Bethel, S.A.'][$i % 3],
        'contact_nit' => ['1024578-9', '2048871-3', '2046335-8'][$i % 3],
        'contact_phone' => '5555-' . (1000 + $i), 'contact_email' => ['rrhh@sanrafael.gt', 'seguridad@cemsur.gt', 'bodega@bethel.gt'][$i % 3],
        'status' => $status, 'source' => $i === 0 ? 'web' : 'panel', 'currency_symbol' => 'Q', 'tax_rate' => 12.000,
        'validity_days' => 20, 'valid_until' => date('Y-m-d', strtotime($created . ' +20 days')),
        'delivery_time' => '12 días hábiles', 'payment_terms' => '60% anticipo, 40% contra entrega',
        'track_token' => bin2hex(random_bytes(28)) . substr(hash('sha256', 'roca' . $i), 0, 24),
        'created_at' => $created, 'updated_at' => $created, 'last_contact_at' => $d(max(0, $age - 2)),
        'sent_at' => $i > 0 ? $d(max(0, $age - 1), 12, 0) : null,
        'approved_at' => $status === 'aprobada' ? $d(max(0, $age - 5), 9, 0) : null,
        'lost_at' => $status === 'perdida' ? $d(max(0, $age - 8), 15, 0) : null,
        'lost_reason' => $status === 'perdida' ? 'precio' : null,
        'lost_detail' => $status === 'perdida' ? 'Otro proveedor ofreció Q12 menos por camisa.' : null,
    ]);
    $sub = 0.0;
    foreach (array_slice($prod2, $i, 3) as $li => $p) {
        $qty = (float) [120, 60, 25][$li % 3];
        $line = round($qty * (float) $p['price'], 2);
        $sub += $line;
        $ins('quote_items', ['company_id' => $c2, 'quote_id' => $qid, 'product_id' => (int) $p['id'],
            'code' => $p['code'], 'name' => $p['name'], 'qty' => $qty, 'unit' => $p['unit'],
            'unit_price' => (float) $p['price'], 'discount_pct' => 0, 'line_total' => $line, 'sort' => $li,
            'notes' => $li === 0 ? 'Bordado de logo a color en pecho izquierdo.' : null]);
    }
    $sub = round($sub, 2);
    $tax = round($sub * 0.12, 2);
    $tot = round($sub + $tax, 2);
    $pdo->prepare('UPDATE quotes SET subtotal=?, taxable_base=?, tax_amount=?, total=?, won_amount=? WHERE id=?')
        ->execute([$sub, $sub, $tax, $tot, $status === 'aprobada' ? $tot : 0, $qid]);
    $ins('quote_events', ['company_id' => $c2, 'quote_id' => $qid, 'user_id' => $v21, 'actor' => 'Sistema',
        'type' => 'sistema', 'title' => 'Cotización creada', 'created_at' => $created]);
}
echo "Empresa 2: " . count($P2) . " productos, " . count($st2) . " cotizaciones\n";

/* --------------------------------------------- bloques de la landing */
$blocks = [
    ['problema', 1, 'Se pierde el hilo', null, 'La cotización queda enterrada en un chat. Nadie sabe si el cliente la aprobó, la rechazó o simplemente la olvidó.'],
    ['problema', 2, 'Precios distintos', null, 'Cada vendedor arma su cotización en Excel con su propio formato y su propio criterio de descuento.'],
    ['problema', 3, 'Sin catálogo', null, 'El cliente no puede ver qué vende usted: tiene que preguntar por cada código, uno por uno.'],
    ['problema', 4, 'Cero medición', null, 'No sabe cuánto cotizó este mes, cuánto ganó, ni por qué perdió las que perdió.'],
    ['paso', 1, 'Suba su catálogo', null, 'Importe el CSV que ya exportó de WooCommerce o use la plantilla de Excel: categorías, códigos, medidas y fotos.'],
    ['paso', 2, 'El cliente cotiza solo', null, 'Busca por código o medida, arma su lista y envía la solicitud con sus datos. Sin registro, sin fricción.'],
    ['paso', 3, 'Usted cierra la venta', null, 'Recibe la solicitud en su tablero, ajusta precios, genera el PDF y lo envía por correo y WhatsApp en un clic.'],
    ['beneficio', 1, 'Catálogo con atributos técnicos', null, 'Defina material, medida, norma o aplicación. El cliente filtra como en un catálogo de fabricante.'],
    ['beneficio', 2, 'Precios que usted controla', null, 'Ocúltelos, muéstrelos a todos o solo a clientes. Listas de precio por tipo de cliente.'],
    ['beneficio', 3, 'PDF de lujo con su marca', null, 'Su logo, sus colores, su firma y un QR que lleva al seguimiento en línea.'],
    ['beneficio', 4, 'Kanban con semáforo', null, 'Verde, amarillo y rojo según los días sin contactar al cliente. Nada se enfría.'],
    ['beneficio', 5, 'Recordatorios automáticos', null, 'El sistema le avisa cuando una cotización lleva días sin respuesta.'],
    ['beneficio', 6, 'Reportes que sí usa', null, 'Monto cotizado, ganado, conversión, ranking de vendedores y motivos de pérdida.'],
    ['testimonio', 1, 'Roberto Pérez', 'Gerente general · Industrial Pérez', 'Antes cotizábamos por WhatsApp y perdíamos el rastro. Hoy el cliente entra, arma su lista y nosotros solo confirmamos precios. Bajamos el tiempo de respuesta de dos días a veinte minutos.'],
    ['testimonio', 2, 'Mónica Roca', 'Directora comercial · Uniformes Roca', 'El catálogo con tallas y el PDF con nuestro logo nos hicieron ver más grandes de lo que somos. Los clientes corporativos nos toman más en serio.'],
    ['testimonio', 3, 'Ana Lucía Ramírez', 'Asesora técnica · Industrial Pérez', 'El tablero con semáforo es lo que más uso. Si una cotización se pone en rojo ya sé que tengo que llamar hoy mismo.'],
];
foreach ($blocks as [$section, $sort, $title, $subtitle, $body]) {
    $ins('landing_blocks', ['section' => $section, 'sort' => $sort, 'title' => $title, 'subtitle' => $subtitle,
        'body' => $body, 'active' => 1]);
}

/* ================================================================ VOLCADO */
$tables = ['companies', 'users', 'brands', 'categories', 'attribute_defs', 'products', 'product_images',
    'product_attributes', 'product_documents', 'price_lists', 'product_prices', 'customers', 'customer_contacts',
    'quotes', 'quote_items', 'quote_events', 'landing_blocks'];

$out = "-- =====================================================================\n"
     . "--  CotizaPro B2B · datos de demostración\n"
     . "--  Empresas: Industrial Pérez, S.A. y Uniformes Roca\n"
     . "--  Se importa DESPUÉS de database.sql (el instalador lo hace solo).\n"
     . "-- =====================================================================\n"
     . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

foreach ($tables as $t) {
    $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll();
    if (!$rows) {
        continue;
    }
    $cols = array_keys($rows[0]);
    $out .= "-- {$t} (" . count($rows) . " filas)\n";
    // Nunca se borra el superadministrador creado por el instalador.
    $out .= $t === 'users' ? "DELETE FROM `users` WHERE `role` <> 'superadmin';\n" : "DELETE FROM `{$t}`;\n";
    $chunks = array_chunk($rows, 60);
    foreach ($chunks as $chunk) {
        $vals = [];
        foreach ($chunk as $r) {
            $v = [];
            foreach ($cols as $c) {
                $x = $r[$c];
                $v[] = $x === null ? 'NULL' : (is_int($x) || is_float($x) ? (string) $x : $pdo->quote((string) $x));
            }
            $vals[] = '(' . implode(',', $v) . ')';
        }
        $out .= "INSERT INTO `{$t}` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $vals) . ";\n";
    }
    $out .= "\n";
}

$out .= "-- Asigna los planes por código (los ids dependen de la instalación).\n"
      . "UPDATE `companies` SET `plan_id` = (SELECT id FROM plans WHERE code = 'pro' LIMIT 1) WHERE slug = 'industrial-perez';\n"
      . "UPDATE `companies` SET `plan_id` = (SELECT id FROM plans WHERE code = 'basico' LIMIT 1) WHERE slug = 'uniformes-roca';\n\n"
      . "-- Empresa que se muestra como demostración en la landing.\n"
      . "INSERT INTO `settings` (`key`,`value`) VALUES ('demo_slug','industrial-perez')\n"
      . "  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);\n"
      . "INSERT INTO `settings` (`key`,`value`) VALUES ('contact_email','ventas@cotizapro.gt')\n"
      . "  ON DUPLICATE KEY UPDATE `value` = `value`;\n"
      . "INSERT INTO `settings` (`key`,`value`) VALUES ('whatsapp','50255551234')\n"
      . "  ON DUPLICATE KEY UPDATE `value` = `value`;\n\n"
      . "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(BASE_PATH . '/database/database_demo.sql', $out);
printf("database_demo.sql generado (%.1f KB)\n", strlen($out) / 1024);
