<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Upload;
use App\Core\Validator;
use App\Services\GiftCardService;
use App\Services\PackageService;
use App\Services\PaymentService;
use App\Services\PricingService;

/** Pagos: lista y filtros, comprobantes por verificar, registro en citas, recibo y reembolsos. */
final class PaymentsController extends A3Controller
{
    private const STATUSES = ['pending' => 'Por verificar', 'verified' => 'Verificado', 'rejected' => 'Rechazado', 'refunded' => 'Reembolsado'];

    public function index(Request $req, array $p): Response
    {
        $status = $req->str('estado', 10);
        $method = $req->str('metodo', 12);
        $from = $this->dateParam($req, 'desde', '');
        $to = $this->dateParam($req, 'hasta', '');
        $q = $req->str('q', 80);
        $where = ['1=1'];
        $args = [];
        if (isset(self::STATUSES[$status])) {
            $where[] = 'p.status = ?';
            $args[] = $status;
        }
        if (in_array($method, ['cash', 'transfer', 'card_onsite', 'link', 'package', 'gift_card', 'other'], true)) {
            $where[] = 'p.method = ?';
            $args[] = $method;
        }
        if ($from !== '') {
            $where[] = 'p.created_at >= ?';
            $args[] = $this->rangeUtc($from, $from)[0];
        }
        if ($to !== '') {
            $where[] = 'p.created_at <= ?';
            $args[] = $this->rangeUtc($to, $to)[1];
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(b.guest_name LIKE ? OR c.name LIKE ? OR p.reference LIKE ?)';
            array_push($args, $like, $like, $like);
        }
        $sql = 'SELECT p.*, b.guest_name, b.starts_at, b.total AS booking_total, c.name AS client_name, e.name AS event_name,
                       f.token AS proof_token, f.mime AS proof_mime, f.original_name AS proof_name
                FROM payments p
                LEFT JOIN bookings b ON b.id = p.booking_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN event_types e ON e.id = b.event_type_id
                LEFT JOIN files f ON f.id = p.proof_file_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC LIMIT 200';
        $rows = Db::all($sql, $args);
        $pending = Db::all("SELECT p.*, b.guest_name, b.starts_at, b.total AS booking_total, e.name AS event_name, f.token AS proof_token, f.mime AS proof_mime, f.original_name AS proof_name
                FROM payments p LEFT JOIN bookings b ON b.id = p.booking_id LEFT JOIN event_types e ON e.id = b.event_type_id LEFT JOIN files f ON f.id = p.proof_file_id
                WHERE p.status = 'pending' ORDER BY p.id ASC LIMIT 50");
        $sum = static fn (string $st): float => (float) Db::val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = ?', [$st]);
        $totals = ['verified' => $sum('verified'), 'refunded' => $sum('refunded'), 'pending' => count($pending)];
        return $this->page('admin/payments/index', [
            'rows' => $rows, 'pending' => $pending, 'totals' => $totals, 'statuses' => self::STATUSES,
            'f' => ['estado' => $status, 'metodo' => $method, 'desde' => $from, 'hasta' => $to, 'q' => $q],
        ], '/admin/pagos', 'Pagos');
    }

    public function record(Request $req, array $p): Response
    {
        $bookingId = (int) $p['id'];
        $b = $this->bookingOr404($bookingId);
        $back = $this->backPath($req, '/admin/citas/' . $bookingId);
        if (in_array($b['status'], ['cancelled', 'rejected'], true)) {
            return $this->fail($req, 'La cita está cancelada o rechazada, así que ya no admite pagos.', $back);
        }
        $method = $req->str('method', 12);
        if (!in_array($method, ['cash', 'transfer', 'card_onsite', 'link', 'package', 'gift_card', 'other'], true)) {
            return $this->fail($req, 'Elige cómo se pagó.', $back);
        }
        $dueC = max(0, PricingService::cents($b['total']) - PricingService::cents($b['paid_amount']));
        $amountIn = $req->str('amount', 20);
        $amount = $amountIn === '' && in_array($method, ['package', 'gift_card'], true) ? number_format($dueC / 100, 2, '.', '') : Validator::money($amountIn);
        if ($amount === null || PricingService::cents($amount) <= 0) {
            return $this->fail($req, 'Escribe un monto válido, mayor que cero (por ejemplo 150.00).', $back);
        }
        $ref = $req->str('reference', 190);
        $note = $req->str('note', 255);
        $confirmed = $req->bool('confirmed');

        $fileId = null;
        $up = $req->files['proof'] ?? null;
        if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $f = Upload::store($up, ['kind' => 'payment_proof', 'owner_type' => 'booking', 'owner_id' => $bookingId, 'uploaded_by' => $this->userId()]);
                $fileId = (int) $f['id'];
            } catch (\RuntimeException $e) {
                return $this->fail($req, $e->getMessage(), $back);
            }
        }
        // Transferencias y enlaces quedan por verificar salvo que la persona confirme que ya llegó el dinero
        $status = in_array($method, ['transfer', 'link'], true) && !$confirmed ? 'pending' : 'verified';

        try {
            Db::tx(function () use ($bookingId, $b, $method, $amount, $ref, $note, $fileId, $status, $req): void {
                if ($method === 'package') {
                    $cpId = $req->int('client_package_id');
                    $ok = false;
                    if ($b['client_id'] !== null) {
                        foreach (PackageService::balance((int) $b['client_id']) as $cp) {
                            if ((int) $cp['id'] === $cpId && $cp['state'] === 'usable' && ($cp['event_type_id'] === null || (int) $cp['event_type_id'] === (int) $b['event_type_id'])) {
                                $ok = true;
                            }
                        }
                    }
                    if (!$ok) {
                        throw new \InvalidArgumentException('Elige un paquete del cliente que esté pagado, vigente y con sesiones disponibles.');
                    }
                    PricingService::consume(['client_package_id' => $cpId], $bookingId);
                } elseif ($method === 'gift_card') {
                    $card = GiftCardService::lookup($req->str('gift_code', 40));
                    $why = $card ? GiftCardService::unusableReason($card) : 'No encontramos ese certificado de regalo. Revisa el código.';
                    if ($why !== null) {
                        throw new \InvalidArgumentException($why);
                    }
                    if (PricingService::cents($amount) > PricingService::cents($card['balance'])) {
                        throw new \InvalidArgumentException('El certificado solo tiene ' . money($card['balance']) . ' de saldo.');
                    }
                    PricingService::consume(['gift_card_id' => (int) $card['id'], 'gift_applied' => (float) $amount], $bookingId);
                    $ref = $ref !== '' ? $ref : (string) $card['code'];
                }
                PaymentService::record($bookingId, [
                    'amount' => $amount, 'method' => $method, 'status' => $status, 'reference' => $ref, 'note' => $note, 'proof_file_id' => $fileId,
                ], $this->userId());
            });
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->fail($req, $e->getMessage(), $back);
        }
        $this->audit('payment.record', 'booking', $bookingId, $method . ' Q' . $amount);
        $this->flash('success', $status === 'pending' ? 'Pago registrado. Queda por verificar hasta que confirmes que llegó.' : 'Pago registrado.');
        return Response::redirect(url($back));
    }

    public function verify(Request $req, array $p): Response
    {
        return $this->review($req, (int) $p['id'], true);
    }

    public function reject(Request $req, array $p): Response
    {
        return $this->review($req, (int) $p['id'], false);
    }

    private function review(Request $req, int $paymentId, bool $ok): Response
    {
        $pay = Db::one('SELECT id, booking_id FROM payments WHERE id = ?', [$paymentId]);
        if (!$pay || $pay['booking_id'] === null) {
            throw new HttpException(404);
        }
        $this->bookingOr404((int) $pay['booking_id']);
        $back = $this->backPath($req, '/admin/pagos');
        try {
            PaymentService::verify($paymentId, $ok, $this->userId());
        } catch (\InvalidArgumentException $e) {
            return $this->fail($req, $e->getMessage(), $back);
        }
        $this->audit($ok ? 'payment.verify' : 'payment.reject', 'payment', $paymentId);
        $this->flash('success', $ok ? 'Pago verificado. Ya cuenta en el saldo de la cita.' : 'Pago rechazado.');
        return Response::redirect(url($back));
    }

    public function refund(Request $req, array $p): Response
    {
        $paymentId = (int) $p['id'];
        $pay = Db::one('SELECT id, booking_id FROM payments WHERE id = ?', [$paymentId]);
        if (!$pay || $pay['booking_id'] === null) {
            throw new HttpException(404);
        }
        $this->bookingOr404((int) $pay['booking_id']);
        $back = $this->backPath($req, '/admin/pagos');
        try {
            PaymentService::refund($paymentId, $req->str('amount', 20) ?: null, $req->str('note', 255) ?: null, $this->userId());
        } catch (\InvalidArgumentException $e) {
            return $this->fail($req, $e->getMessage(), $back);
        }
        $this->audit('payment.refund', 'payment', $paymentId);
        $this->flash('success', 'Reembolso registrado.');
        return Response::redirect(url($back));
    }

    /** Recibo imprimible de una cita ({id} = id de la cita). */
    public function receipt(Request $req, array $p): Response
    {
        $this->bookingOr404((int) $p['id']);
        try {
            $r = PaymentService::receiptData((int) $p['id']);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(404);
        }
        return $this->view('admin/payments/receipt', ['r' => $r, 'title' => 'Recibo ' . $r['receipt_number']]);
    }

    /** Ruta de regreso segura (solo dentro del panel). */
    private function backPath(Request $req, string $default): string
    {
        $v = (string) $req->input('volver', '');
        return preg_match('#^/admin/[A-Za-z0-9/_\-]*$#', $v) ? $v : $default;
    }
}
