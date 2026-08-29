<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Quote;

/**
 * Página pública de seguimiento /c/{token}.
 * El token es HMAC largo, revocable y no contiene datos del cliente.
 */
final class TrackController extends Controller
{
    private function load(array $params): array
    {
        $q = Quote::byToken((string) ($params['token'] ?? ''));
        if (!$q) {
            ErrorHandler::render(404);
        }
        $c = Company::get();
        if (!$c) {
            ErrorHandler::render(404);
        }
        $this->company = $c;
        \App\Core\View::share('company', $c);
        \App\Core\View::share('theme', Company::theme($c));
        return [$q, $c];
    }

    public function show(array $params): void
    {
        [$q, $c] = $this->load($params);
        if (empty($q['viewed_at']) && in_array($q['status'], ['enviada', 'negociacion'], true)) {
            DB::update('quotes', ['viewed_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
            Quote::event((int) $q['id'], 'cliente', 'El cliente abrió el enlace de seguimiento', '', (string) $q['contact_name']);
            $q['viewed_at'] = nowSql();
        }
        $this->view('site/track', [
            'title'     => 'Cotización ' . $q['number'],
            'q'         => $q,
            'items'     => Quote::items((int) $q['id']),
            'events'    => DB::all(
                'SELECT * FROM quote_events WHERE quote_id = ? AND type IN ("estado","cliente","correo","pdf")
                 ORDER BY created_at DESC, id DESC LIMIT 30',
                [(int) $q['id']]
            ),
            'hasPdf'    => $q['pdf_path'] && is_file(STORAGE_PATH . '/uploads/' . $q['pdf_path']),
            'noindex'   => true,
            'cartCount' => 0,
        ], 'layout/track');
    }

    public function pdf(array $params): void
    {
        [$q, $c] = $this->load($params);
        $rel = (string) ($q['pdf_path'] ?? '');
        $abs = $rel !== '' ? STORAGE_PATH . '/uploads/' . $rel : '';
        if ($rel === '' || !is_file($abs)) {
            ErrorHandler::render(404);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', (string) $q['number'])) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    public function approve(array $params): void
    {
        [$q, $c] = $this->load($params);
        $this->guardPost();
        if (!RateLimit::hit('track_action', (string) $q['id'] . App::ip(), 10, 3600)) {
            Flash::error('Demasiadas acciones seguidas. Intente más tarde.');
            redirect('/c/' . $q['track_token']);
        }
        if (!in_array($q['status'], ['enviada', 'negociacion'], true)) {
            Flash::warn('Esta cotización ya no admite aprobación en línea. Comuníquese con su asesor.');
            redirect('/c/' . $q['track_token']);
        }
        $who = mb_substr(Request::str('name'), 0, 120) ?: (string) $q['contact_name'];
        Quote::setStatus((int) $q['id'], 'aprobada', ['note' => 'Aprobada por el cliente desde el enlace de seguimiento.'], $who);
        Quote::event((int) $q['id'], 'cliente', 'El cliente APROBÓ la cotización', 'Aprobada por: ' . $who . ' · IP ' . App::ip(), $who);
        Audit::log('cotizacion.aprobada_cliente', 'quote', (int) $q['id'], ['por' => $who]);
        $this->notifyCompany($c, $q, 'Cotización ' . $q['number'] . ' APROBADA por el cliente',
            '<p><strong>' . e($who) . '</strong> aprobó la cotización <strong>' . e($q['number']) . '</strong> por ' . e(money((float) $q['total'], (string) $q['currency_symbol'])) . '.</p>');
        Flash::ok('¡Gracias! Registramos su aprobación y su asesor fue notificado.');
        redirect('/c/' . $q['track_token']);
    }

    public function changes(array $params): void
    {
        [$q, $c] = $this->load($params);
        $this->guardPost();
        if (!RateLimit::hit('track_action', (string) $q['id'] . App::ip(), 10, 3600)) {
            Flash::error('Demasiadas acciones seguidas. Intente más tarde.');
            redirect('/c/' . $q['track_token']);
        }
        $comment = mb_substr(Request::str('comment'), 0, 1500);
        if (mb_strlen(trim($comment)) < 5) {
            Flash::error('Escriba brevemente qué cambio necesita.');
            redirect('/c/' . $q['track_token']);
        }
        $who = mb_substr(Request::str('name'), 0, 120) ?: (string) $q['contact_name'];
        if (in_array($q['status'], ['enviada'], true)) {
            Quote::setStatus((int) $q['id'], 'negociacion', ['note' => 'El cliente solicitó cambios.'], $who);
        }
        Quote::event((int) $q['id'], 'cliente', 'El cliente solicitó cambios', $comment, $who);
        DB::update('quotes', ['last_contact_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
        Audit::log('cotizacion.cambios_cliente', 'quote', (int) $q['id'], []);
        $this->notifyCompany($c, $q, 'El cliente pide cambios en ' . $q['number'],
            '<p><strong>' . e($who) . '</strong> solicitó cambios en <strong>' . e($q['number']) . '</strong>:</p><blockquote style="margin:12px 0;padding:10px 14px;border-left:3px solid #E8590C;background:#F5F6F4">' . nl2br(e($comment)) . '</blockquote>');
        Flash::ok('Enviamos su comentario al asesor. Le responderemos a la brevedad.');
        redirect('/c/' . $q['track_token']);
    }

    private function notifyCompany(array $c, array $q, string $subject, string $bodyHtml): void
    {
        $link = absUrl('/panel/cotizaciones/' . $q['id']);
        $html = Mailer::template($subject, $bodyHtml, $c, 'Abrir la cotización', $link);
        $to = (string) ($q['seller_email'] ?: $c['email'] ?: $c['smtp_from']);
        if ($to !== '') {
            Mailer::send($to, $subject, $html, $c);
        }
        if ($c['email'] && $c['email'] !== $to) {
            Mailer::send((string) $c['email'], $subject, $html, $c);
        }
        $targets = $q['user_id']
            ? [['id' => (int) $q['user_id']]]
            : DB::all('SELECT id FROM users WHERE role = "admin" AND status = "activo"');
        foreach ($targets as $t) {
            Notification::push((int) $t['id'], $subject, (string) ($q['contact_company'] ?: $q['contact_name']), '/panel/cotizaciones/' . $q['id'], 'cliente');
        }
    }
}
