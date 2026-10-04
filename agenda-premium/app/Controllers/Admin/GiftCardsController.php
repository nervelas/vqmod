<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Core\Validator;

/** Certificados de regalo: creación, consulta de saldo e impresión. */
final class GiftCardsController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $cards = Db::all('SELECT * FROM gift_cards ORDER BY id DESC LIMIT 300');
        $lookup = null;
        $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $req->str('consultar', 40)) ?? '');
        $lookupDone = $code !== '';
        if ($lookupDone) {
            $lookup = $this->svc('GiftCardService') ? \App\Services\GiftCardService::lookup($code) : Db::one('SELECT * FROM gift_cards WHERE code = ?', [$code]);
        }
        return $this->page('admin/giftcards/index', ['cards' => $cards, 'lookup' => $lookup, 'lookupDone' => $lookupDone, 'code' => $code, 'now' => Clock::utc(), 'justCreated' => $req->int('impreso')], '/admin/certificados', 'Certificados de regalo');
    }

    public function create(Request $req, array $p): Response
    {
        $amount = $this->money($req, 'amount');
        $exp = $req->str('expires', 10);
        if ($amount === null || (float) $amount <= 0) {
            return $this->fail($req, 'Escribe un monto válido, mayor que cero.', '/admin/certificados');
        }
        if ($exp !== '' && !Validator::date($exp)) {
            return $this->fail($req, 'La fecha de vencimiento no es válida.', '/admin/certificados');
        }
        if (!$this->svc('GiftCardService')) {
            return $this->fail($req, 'El servicio de certificados aún no está disponible.', '/admin/certificados', 503);
        }
        $data = [
            'amount' => $amount,
            'buyer_name' => $req->str('buyer_name', 160) ?: null,
            'recipient_name' => $req->str('recipient_name', 160) ?: null,
            'message' => $req->str('message', 255) ?: null,
            'expires_at' => $exp !== '' ? Tz::localToUtc($exp . ' 23:59:59', $this->bizTz()) : null,
        ];
        try {
            $id = \App\Services\GiftCardService::create($data);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Crear certificado', $e);
            return $this->fail($req, $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No pudimos crear el certificado. Inténtalo de nuevo.', '/admin/certificados');
        }
        $this->audit('giftcard.create', 'gift_card', $id, 'Q' . $amount);
        $this->flash('success', 'Certificado creado. Ya puedes imprimirlo.');
        return $this->redirect('/admin/certificados', ['impreso' => $id]);
    }

    public function toggle(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, active FROM gift_cards WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::update('gift_cards', ['active' => (int) $row['active'] ? 0 : 1], 'id = ?', [$row['id']]);
        $this->audit('giftcard.toggle', 'gift_card', $row['id']);
        $this->flash('success', (int) $row['active'] ? 'El certificado se desactivó: ya no se puede canjear.' : 'El certificado se activó de nuevo.');
        return $this->redirect('/admin/certificados');
    }

    public function print(Request $req, array $p): Response
    {
        $card = Db::one('SELECT * FROM gift_cards WHERE id = ?', [(int) $p['id']]);
        if (!$card) {
            throw new HttpException(404);
        }
        return $this->view('admin/giftcards/print', ['card' => $card, 'title' => 'Certificado ' . $card['code']]);
    }
}
