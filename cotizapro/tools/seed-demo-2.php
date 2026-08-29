<?php
declare(strict_types=1);
/** Continuación del seeder: clientes, cotizaciones y volcado a SQL. */

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
        'name' => $name, 'legal_name' => $legal, 'nit' => $nit, 'email' => $email,
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
    $ins('customer_contacts', ['customer_id' => $cid,
        'name' => ['Luis Morales', 'Claudia Herrera', 'José Alberto Gómez', 'Silvia Ruano', 'Óscar Batres'][$i % 5],
        'position' => ['Jefe de compras', 'Jefe de mantenimiento', 'Supervisor de planta', 'Analista de abastecimiento'][$i % 4],
        'email' => $email, 'phone' => $phone, 'is_primary' => 1]);
}

/* --------------------------------------------------------- cotizaciones */
$codes = array_keys($prodIds);
$prodRows = [];
foreach ($pdo->query('SELECT id, code, name, unit, price FROM products') as $r) {
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
            'number' => sprintf('COT-%d-%04d', (int) date('Y'), $seq++),
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
                'quote_id' => $qid, 'product_id' => (int) $p['id'],
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
            $ins('quote_events', ['quote_id' => $qid, 'user_id' => $seller,
                'actor' => $type === 'cliente' ? 'Cliente' : 'Sistema', 'type' => $type,
                'title' => $title, 'body' => $body !== '' ? $body : null, 'created_at' => $when]);
        }
    }
}
$pdo->exec('UPDATE company SET quote_next = ' . ($seq) . ' WHERE id = 1');
echo "Cotizaciones: {$quoteNo}\n";

/* ================================================================ VOLCADO */
$tables = ['company', 'users', 'brands', 'categories', 'attribute_defs', 'products', 'product_images',
    'product_attributes', 'product_documents', 'price_lists', 'product_prices', 'customers', 'customer_contacts',
    'quotes', 'quote_items', 'quote_events'];

$out = "-- =====================================================================\n"
     . "--  CotizaPro B2B · datos de demostración\n"
     . "--  Empresa: Industrial Pérez, S.A.\n"
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
    $out .= "DELETE FROM `{$t}`;\n";
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

$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(BASE_PATH . '/database/database_demo.sql', $out);
printf("database_demo.sql generado (%.1f KB)\n", strlen($out) / 1024);
