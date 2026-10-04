<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/Sb1.php';
T::boot('sb1_payments', ['profession' => 'medico', 'demo' => true]);

use App\Core\Db;
use App\Services\BookingService;
use App\Services\GiftCardService;
use App\Services\PaymentService;
use App\Services\PricingService;

$b = Sb1::booking(1); // consulta de 300
$id = (int) $b['id'];
$st = static fn (): array => Db::one('SELECT paid_amount, payment_status, total FROM bookings WHERE id = ?', [$id]);
T::eq('300.00', $st()['total'], 'la cita real tiene total 300');

T::section('Registro de pagos');
T::throws(fn () => PaymentService::record($id, ['amount' => '0', 'method' => 'cash'], 1), 'monto cero rechazado', \InvalidArgumentException::class, 'monto');
T::throws(fn () => PaymentService::record($id, ['amount' => 'abc', 'method' => 'cash'], 1), 'monto no numérico rechazado', \InvalidArgumentException::class);
T::throws(fn () => PaymentService::record($id, ['amount' => '10', 'method' => 'bitcoin'], 1), 'método inválido', \InvalidArgumentException::class, 'método');
T::throws(fn () => PaymentService::record(999999, ['amount' => '10', 'method' => 'cash'], 1), 'cita inexistente', \InvalidArgumentException::class);
$p1 = PaymentService::record($id, ['amount' => '100.00', 'method' => 'cash', 'reference' => '=HYPERLINK("x")'], 1);
T::eq(['100.00', 'partial'], [$st()['paid_amount'], $st()['payment_status']], 'pago parcial → partial');
T::throws(fn () => PaymentService::record($id, ['amount' => '250', 'method' => 'cash'], 1), 'no se puede pagar más que el saldo', \InvalidArgumentException::class, 'saldo');
$p2 = PaymentService::record($id, ['amount' => '200', 'method' => 'card_onsite'], 1);
T::eq(['300.00', 'paid'], [$st()['paid_amount'], $st()['payment_status']], 'saldo cubierto → paid');

T::section('Reembolsos');
T::throws(fn () => PaymentService::refund($p1, '150'), 'reembolso mayor al pago', \InvalidArgumentException::class);
$r1 = PaymentService::refund($p1, '40.00', 'Ajuste', 1);
T::eq(['260.00', 'partial'], [$st()['paid_amount'], $st()['payment_status']], 'reembolso parcial → partial');
T::eq('refunded', Db::val('SELECT status FROM payments WHERE id = ?', [$r1]), 'el reembolso es una fila refunded');
T::throws(fn () => PaymentService::refund($p1, '70'), 'no se reembolsa más de lo que queda del pago', \InvalidArgumentException::class);
PaymentService::refund($p1);
PaymentService::refund($p2);
T::eq(['0.00', 'refunded'], [$st()['paid_amount'], $st()['payment_status']], 'todo reembolsado → refunded');
T::throws(fn () => PaymentService::refund($p1), 'ya reembolsado por completo', \InvalidArgumentException::class, 'reembols');
T::throws(fn () => PaymentService::refund($r1), 'no se reembolsa un reembolso', \InvalidArgumentException::class);
PaymentService::record($id, ['amount' => '300', 'method' => 'transfer'], 1);
T::eq('paid', $st()['payment_status'], 'un nuevo pago tras reembolso vuelve a paid');

T::section('Comprobante y verificación');
$b2 = Sb1::booking(1, 3);
$id2 = (int) $b2['id'];
$fid = Db::insert('files', ['token' => bin2hex(random_bytes(16)), 'original_name' => 'comprobante.jpg', 'stored_name' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 10, 'kind' => 'proof', 'created_at' => gmdate('Y-m-d H:i:s')]);
Db::update('bookings', ['deposit_due' => '100.00'], 'id = ?', [$id2]);
$pp = PaymentService::proof($id2, $fid);
$row = Db::one('SELECT * FROM payments WHERE id = ?', [$pp]);
T::ok($row['status'] === 'pending' && $row['amount'] === '100.00' && (int) $row['proof_file_id'] === $fid && $row['method'] === 'transfer', 'comprobante → pago pending por el depósito');
T::eq('pending', Db::val('SELECT payment_status FROM bookings WHERE id = ?', [$id2]), 'la cita queda con pago pending');
T::eq($pp, PaymentService::proof($id2, $fid), 'subir otro comprobante reemplaza al pendiente');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM payments WHERE booking_id = ?', [$id2]), 'sin filas duplicadas');
T::throws(fn () => PaymentService::proof($id2, 99999), 'archivo inexistente', \InvalidArgumentException::class);
PaymentService::verify($pp, false, 1);
T::eq(['none', '0.00'], [Db::val('SELECT payment_status FROM bookings WHERE id = ?', [$id2]), Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$id2])], 'rechazado → none');
T::throws(fn () => PaymentService::verify($pp, true, 1), 'un pago ya revisado no se vuelve a revisar', \InvalidArgumentException::class, 'revisado');
$pp = PaymentService::proof($id2, $fid);
T::ok($pp > 0, 'nuevo comprobante después del rechazo');
PaymentService::verify($pp, true, 1);
T::eq(['partial', '100.00'], [Db::val('SELECT payment_status FROM bookings WHERE id = ?', [$id2]), Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$id2])], 'verificado el depósito → partial');
PaymentService::record($id2, ['amount' => '200', 'method' => 'cash'], 1);
T::throws(fn () => PaymentService::proof($id2, $fid), 'sin saldo no se acepta comprobante', \InvalidArgumentException::class, 'saldo');
T::eq('paid', Db::val('SELECT payment_status FROM bookings WHERE id = ?', [$id2]), 'depósito + resto → paid');

T::section('Recibo');
$rc = PaymentService::receiptData($id2);
T::eq('R-' . str_pad((string) $id2, 6, '0', STR_PAD_LEFT), $rc['receipt_number'], 'número de recibo');
T::ok($rc['total'] === 300.0 && $rc['paid'] === 300.0 && $rc['balance'] === 0.0 && count($rc['payments']) === 2, 'totales y líneas de pago (solo verificados)');
T::ok($rc['business']['name'] === 'Negocio de Prueba' && $rc['booking']['event'] !== '' && $rc['client']['name'] !== '', 'negocio, cita y cliente');
T::throws(fn () => PaymentService::receiptData(999999), 'recibo de cita inexistente', \InvalidArgumentException::class);

T::section('Certificado en recibo y total');
$g = GiftCardService::create(['amount' => '120']);
$b3 = Sb1::booking(1, 4, ['gift_code' => (string) Db::val('SELECT code FROM gift_cards WHERE id = ?', [$g])]);
T::eq('180.00', Db::val('SELECT total FROM bookings WHERE id = ?', [(int) $b3['id']]), 'la cita con certificado debe 180');
T::eq(120.0, PaymentService::receiptData((int) $b3['id'])['gift_applied'], 'el recibo muestra el certificado aplicado');
if (class_exists(\App\Services\WorkflowService::class)) {
    BookingService::cancel((int) $b3['id'], 'prueba', ['type' => 'system', 'label' => 'Prueba']);
    T::eq('120.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$g]), 'cancelar la cita devuelve el saldo del certificado (integración con BookingService)');
} else {
    PricingService::release($b3);
    T::eq('120.00', (string) Db::val('SELECT balance FROM gift_cards WHERE id = ?', [$g]), 'release devuelve el saldo del certificado (WorkflowService aún no existe: cancelación completa no probada)');
}

T::done();
