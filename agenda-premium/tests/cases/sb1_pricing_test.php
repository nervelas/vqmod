<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/Sb1.php';
T::boot('sb1_pricing', ['profession' => 'medico', 'demo' => true]);

use App\Core\Clock;
use App\Core\Db;
use App\Services\ClientService;
use App\Services\CouponService;
use App\Services\EventRepository;
use App\Services\GiftCardService;
use App\Services\PackageService;
use App\Services\PricingService;

$base = EventRepository::find(1);
$ev = static fn (float $price, string $dt = 'none', float $dv = 0, array $x = []): array => array_merge($base, ['price' => number_format($price, 2, '.', ''), 'deposit_type' => $dt, 'deposit_value' => number_format($dv, 2, '.', '')], $x);
$coupon = static function (string $code, array $o = []): int {
    return Db::insert('coupons', array_merge(['code' => $code, 'type' => 'percent', 'value' => '10.00', 'max_uses' => null, 'used' => 0, 'valid_from' => null, 'valid_to' => null, 'event_type_id' => null, 'active' => 1], $o));
};
$q = static fn (array $e, int $seats = 1, ?string $c = null, ?string $g = null, ?int $cl = null, ?array $pol = null): array => PricingService::quote($e, 30, $seats, $c, $g, $cl, $pol);

T::section('Cotización básica y redondeo');
$r = $q($ev(300));
T::ok($r['ok'] && $r['price'] === 300.0 && $r['total'] === 300.0 && $r['discount'] === 0.0 && $r['deposit_due'] === 0.0 && $r['needs_payment'] === false, 'precio simple sin extras');
T::eq(99.99, $q($ev(33.33), 3)['total'], '33.33 x 3 asientos = 99.99');
T::eq(0.0, $q($ev(0))['total'], 'evento gratis');

T::section('Cupones');
$coupon('DIEZ');
$r = $q($ev(300), 1, ' diez ');
T::ok($r['ok'] && $r['discount'] === 30.0 && $r['total'] === 270.0 && $r['coupon_id'] > 0, 'porcentaje 10 % (código sin importar mayúsculas ni espacios)');
$coupon('FIJO50', ['type' => 'fixed', 'value' => '50.00']);
T::eq(250.0, $q($ev(300), 1, 'FIJO50')['total'], 'monto fijo 50');
$coupon('GRANDE', ['type' => 'fixed', 'value' => '999.00']);
$r = $q($ev(300), 1, 'GRANDE');
T::ok($r['discount'] === 300.0 && $r['total'] === 0.0, 'el descuento fijo no excede el precio');
$coupon('P6667', ['value' => '66.67']);
$r = $q($ev(100), 1, 'P6667');
T::ok($r['discount'] === 66.67 && $r['total'] === 33.33, 'redondeo 66.67 % de 100 → descuento 66.67 / total 33.33');
$coupon('P3333', ['value' => '33.33']);
$r = $q($ev(100), 1, 'P3333');
T::ok($r['discount'] === 33.33 && $r['total'] === 66.67, 'redondeo 33.33 % de 100 → descuento 33.33 / total 66.67');
$r = $q($ev(0.10), 1, 'P3333');
T::ok($r['ok'] && $r['total'] >= 0.0 && $r['discount'] <= 0.10, 'precios de centavos no producen negativos');
$coupon('VENCIDO', ['valid_to' => gmdate('Y-m-d H:i:s', Clock::now() - 3600)]);
$r = $q($ev(300), 1, 'VENCIDO');
T::ok(!$r['ok'] && stripos((string) $r['error'], 'venci') !== false && $r['total'] === 0.0, 'cupón vencido');
$coupon('FUTURO', ['valid_from' => gmdate('Y-m-d H:i:s', Clock::now() + 86400)]);
T::ok(!$q($ev(300), 1, 'FUTURO')['ok'], 'cupón aún no vigente');
$coupon('AGOTADO', ['max_uses' => 2, 'used' => 2]);
$r = $q($ev(300), 1, 'AGOTADO');
T::ok(!$r['ok'] && stripos((string) $r['error'], 'límite') !== false, 'cupón agotado');
$coupon('SOLOEV2', ['event_type_id' => 2]);
T::ok(!$q($ev(300), 1, 'SOLOEV2')['ok'], 'cupón de otro evento no aplica');
T::ok($q($ev(300, 'none', 0, ['id' => 2]), 1, 'SOLOEV2')['ok'], 'cupón del evento correcto sí aplica');
$coupon('MIN500', ['min_amount' => '500.00']);
T::ok(!$q($ev(300), 1, 'MIN500')['ok'] && $q($ev(300), 2, 'MIN500')['ok'], 'monto mínimo del cupón (600 con 2 asientos sí)');
$coupon('APAGADO', ['active' => 0]);
T::ok(!$q($ev(300), 1, 'APAGADO')['ok'], 'cupón inactivo');
T::ok(!$q($ev(300), 1, 'NOEXISTE')['ok'], 'cupón inexistente');
T::ok(!$q($ev(300, 'none', 0, ['allow_coupon' => 0]), 1, 'DIEZ')['ok'], 'evento que no acepta cupones');
T::ok(!$q($ev(300), 1, "' OR 1=1 --")['ok'], 'cupón con intento de inyección SQL se rechaza como inexistente');

T::section('Paquetes de sesiones');
$cid = ClientService::upsert(['name' => 'Ana Paquete', 'email' => 'ana.paquete@example.test', 'phone' => '55550001']);
$pkgId = Db::insert('packages', ['name' => 'Bono 3 sesiones', 'sessions' => 3, 'price' => '750.00', 'validity_days' => 30, 'event_type_id' => null, 'active' => 1]);
$unpaid = PackageService::sell($cid, $pkgId, 1, false);
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM payments WHERE client_package_id = ?', [$unpaid]), 'paquete sin pagar no genera ingreso');
T::eq(null, $q($ev(300), 1, null, null, $cid)['client_package_id'], 'paquete sin pagar no se aplica');
PackageService::markPaid($unpaid, 'cash', 1);
PackageService::markPaid($unpaid, 'cash', 1);
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM payments WHERE client_package_id = ?', [$unpaid]), 'marcar pagado dos veces registra un solo ingreso');
$r = $q($ev(300), 1, null, null, $cid);
T::ok($r['client_package_id'] === $unpaid && $r['total'] === 0.0 && $r['needs_payment'] === false, 'paquete pagado: total 0 y client_package_id');
T::eq(null, $q($ev(300), 2, null, null, $cid)['client_package_id'], 'varios asientos no usan paquete');
$pkgOther = Db::insert('packages', ['name' => 'Solo evento 2', 'sessions' => 1, 'price' => '0.00', 'validity_days' => 30, 'event_type_id' => 2, 'active' => 1]);
$cid2 = ClientService::upsert(['name' => 'Beto', 'email' => 'beto@example.test', 'phone' => '55550002']);
PackageService::sell($cid2, $pkgOther, 1, true);
T::eq(null, $q($ev(300), 1, null, null, $cid2)['client_package_id'], 'paquete de otro tipo de cita no aplica');
T::ok($q($ev(300, 'none', 0, ['id' => 2]), 1, null, null, $cid2)['client_package_id'] > 0, 'paquete del tipo de cita correcto aplica');
$bal = PackageService::balance($cid);
T::ok($bal[0]['remaining'] === 3 && $bal[0]['state'] === 'usable', 'saldo de paquetes del cliente');
Clock::set(Clock::now() + 31 * 86400);
T::eq(null, $q($ev(300), 1, null, null, $cid)['client_package_id'], 'paquete vencido no aplica');
T::eq('expired', PackageService::balance($cid)[0]['state'], 'balance marca vencido');
Clock::set(null);

T::section('Certificados de regalo');
$g1 = GiftCardService::create(['amount' => '100.00', 'buyer_name' => 'Comprador']);
$card = Db::one('SELECT * FROM gift_cards WHERE id = ?', [$g1]);
T::ok((bool) preg_match('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $card['code']), 'código legible sin caracteres ambiguos: ' . $card['code']);
T::eq($g1, (int) GiftCardService::lookup(strtolower(str_replace('-', ' ', $card['code'])))['id'], 'lookup tolera minúsculas y espacios');
T::eq(null, GiftCardService::lookup('XXXX-XXXX'), 'lookup de código corto → null');
$codes = [];
for ($i = 0; $i < 30; $i++) {
    $codes[Db::val('SELECT code FROM gift_cards WHERE id = ?', [GiftCardService::create(['amount' => '5'])])] = 1;
}
T::eq(30, count($codes), 'códigos únicos');
T::throws(fn () => GiftCardService::create(['amount' => '-5']), 'monto negativo rechazado', \InvalidArgumentException::class);
$r = $q($ev(300), 1, null, $card['code']);
T::ok($r['gift_applied'] === 100.0 && $r['total'] === 200.0 && $r['gift_card_id'] === $g1, 'certificado parcial: aplica todo el saldo');
$r = $q($ev(60), 1, null, $card['code']);
T::ok($r['gift_applied'] === 60.0 && $r['total'] === 0.0 && $r['deposit_due'] === 0.0, 'certificado total: cubre la cita');
$r = $q($ev(300), 1, 'DIEZ', $card['code']);
T::ok($r['discount'] === 30.0 && $r['gift_applied'] === 100.0 && $r['total'] === 170.0, 'cupón + certificado');
T::ok(!$q($ev(300), 1, null, 'ZZZZ-ZZZZ-ZZZZ')['ok'], 'certificado inexistente');
$gOld = GiftCardService::create(['amount' => '50']);
Db::update('gift_cards', ['expires_at' => gmdate('Y-m-d H:i:s', Clock::now() - 60)], 'id = ?', [$gOld]);
T::ok(!$q($ev(300), 1, null, (string) Db::val('SELECT code FROM gift_cards WHERE id = ?', [$gOld]))['ok'], 'certificado vencido');

T::section('Depósitos');
T::eq(100.0, $q($ev(300, 'fixed', 100))['deposit_due'], 'depósito fijo');
T::ok($q($ev(300, 'fixed', 100))['needs_payment'], 'needs_payment con depósito');
T::eq(90.0, $q($ev(300, 'percent', 30))['deposit_due'], 'depósito 30 %');
T::eq(33.33, $q($ev(100, 'percent', 33.33))['deposit_due'], 'depósito 33.33 % de 100');
T::eq(300.0, $q($ev(300, 'fixed', 500))['deposit_due'], 'el depósito no excede el total');
T::eq(135.0, $q($ev(300, 'percent', 50), 1, 'DIEZ')['deposit_due'], 'depósito sobre el total ya descontado');
$r = $q($ev(300), 1, null, null, null, ['require_deposit' => true]);
T::ok($r['deposit_due'] === 150.0 && $r['needs_payment'], 'depósito obligatorio por inasistencias (noshow_deposit_percent 50 %)');
\App\Core\Settings::set('noshow_deposit_percent', '33.33');
T::eq(99.99, $q($ev(300), 1, null, null, null, ['require_deposit' => true])['deposit_due'], 'porcentaje de no-show con decimales (33.33 % de 300 = 99.99)');
\App\Core\Settings::set('noshow_deposit_percent', '50');
T::eq(200.0, $q($ev(300, 'fixed', 200), 1, null, null, null, ['require_deposit' => true])['deposit_due'], 'gana el mayor entre depósito del evento y el de no-show');
T::eq(0.0, $q($ev(0, 'fixed', 100), 1, null, null, null, ['require_deposit' => true])['deposit_due'], 'evento gratis nunca pide depósito');

T::section('Consumo y devolución');
$cp = $coupon('UNO', ['max_uses' => 1]);
$quote = $q($ev(300), 1, 'UNO');
PricingService::consume($quote, 9001);
T::eq(1, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$cp]), 'consume incrementa el cupón');
PricingService::consume($quote, 9001);
T::eq(1, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$cp]), 'consume es idempotente para la misma cita');
T::throws(fn () => PricingService::consume($quote, 9002), 'segundo uso del cupón de un solo uso falla', \RuntimeException::class, 'cupón');
T::eq(1, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$cp]), 'el fallo no incrementó');
PricingService::release(['id' => 9001]);
PricingService::release(['id' => 9001]);
T::eq(0, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$cp]), 'release devuelve el cupón una sola vez');
PricingService::consume($quote, 9002);
T::eq(1, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$cp]), 'tras devolver, otra cita puede usarlo');

$g = GiftCardService::create(['amount' => '100']);
$gcode = (string) Db::val('SELECT code FROM gift_cards WHERE id = ?', [$g]);
$qg = $q($ev(70), 1, null, $gcode);
PricingService::consume($qg, 9010);
T::eq('30.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$g]), 'consume descuenta saldo del certificado');
$qg2 = $q($ev(70), 1, null, $gcode);
Db::exec('UPDATE gift_cards SET balance = 10 WHERE id = ?', [$g]);
T::throws(fn () => PricingService::consume($qg2, 9011), 'saldo insuficiente al reservar', \RuntimeException::class, 'certificado');
T::eq('10.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$g]), 'sin sobregiro');
Db::exec('UPDATE gift_cards SET balance = 30 WHERE id = ?', [$g]);
PricingService::release(['id' => 9010]);
T::eq('100.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$g]), 'release devuelve el saldo (sin pasar del monto inicial)');

$qp = $q($ev(300), 1, null, null, $cid);
PricingService::consume($qp, 9020);
T::eq(2, (int) Db::val('SELECT remaining FROM client_packages WHERE id = ?', [$unpaid]), 'consume descuenta una sesión');
PricingService::release(['id' => 9020]);
T::eq(3, (int) Db::val('SELECT remaining FROM client_packages WHERE id = ?', [$unpaid]), 'release devuelve la sesión');
Db::exec('UPDATE client_packages SET remaining = 0 WHERE id = ?', [$unpaid]);
T::throws(fn () => PricingService::consume($qp, 9021), 'paquete agotado entre cotizar y reservar', \RuntimeException::class, 'paquete');
Db::exec('UPDATE client_packages SET remaining = 3 WHERE id = ?', [$unpaid]);

T::section('Carreras (procesos PHP simultáneos)');
$c1 = $coupon('CARRERA', ['max_uses' => 1]);
$quote = $q($ev(300), 1, 'CARRERA');
$params = [];
for ($i = 0; $i < 10; $i++) {
    $params[] = ['quote' => $quote, 'booking_id' => 7000 + $i];
}
$res = Sb1::race('sb1_pricing', 'consume', $params);
$okc = count(array_filter($res, fn ($x) => $x === 'OK'));
T::eq(1, $okc, 'cupón max_uses=1 con 10 procesos: exactamente 1 éxito (' . implode('|', array_map(fn ($x) => substr($x, 0, 12), $res)) . ')');
T::eq(1, (int) Db::val('SELECT used FROM coupons WHERE id = ?', [$c1]), 'el cupón quedó con 1 uso');
T::eq(9, count(array_filter($res, fn ($x) => str_starts_with($x, 'ERR') && stripos($x, 'cupón') !== false)), 'los otros 9 recibieron mensaje amable');

$gr = GiftCardService::create(['amount' => '100']);
$grc = (string) Db::val('SELECT code FROM gift_cards WHERE id = ?', [$gr]);
$qgr = $q($ev(30), 1, null, $grc);
$params = [];
for ($i = 0; $i < 10; $i++) {
    $params[] = ['quote' => $qgr, 'booking_id' => 7100 + $i];
}
$res = Sb1::race('sb1_pricing', 'consume', $params);
T::eq(3, count(array_filter($res, fn ($x) => $x === 'OK')), 'certificado de 100 con consumos de 30: 3 éxitos');
T::eq('10.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$gr]), 'saldo final exacto, sin sobregiro');
$gx = GiftCardService::create(['amount' => '90']);
$qgx = $q($ev(30), 1, null, (string) Db::val('SELECT code FROM gift_cards WHERE id = ?', [$gx]));
$params = [];
for ($i = 0; $i < 6; $i++) {
    $params[] = ['quote' => $qgx, 'booking_id' => 7150 + $i];
}
$res = Sb1::race('sb1_pricing', 'consume', $params);
T::ok(count(array_filter($res, fn ($x) => $x === 'OK')) === 3 && (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$gx]) === '0.00', 'certificado con saldo exacto: 3 de 6 y saldo 0.00');

$cidr = ClientService::upsert(['name' => 'Carlos Carrera', 'email' => 'carlos.carrera@example.test', 'phone' => '55550003']);
$pk = PackageService::sell($cidr, Db::insert('packages', ['name' => 'Bono 2', 'sessions' => 2, 'price' => '0.00', 'validity_days' => 30, 'event_type_id' => null, 'active' => 1]), 1, true);
$qpr = $q($ev(300), 1, null, null, $cidr);
$params = [];
for ($i = 0; $i < 10; $i++) {
    $params[] = ['quote' => $qpr, 'booking_id' => 7200 + $i];
}
$res = Sb1::race('sb1_pricing', 'consume', $params);
T::eq(2, count(array_filter($res, fn ($x) => $x === 'OK')), 'paquete de 2 sesiones con 10 procesos: 2 éxitos');
T::eq(0, (int) Db::val('SELECT remaining FROM client_packages WHERE id = ?', [$pk]), 'sesiones restantes = 0 (nunca negativas)');

T::done();
