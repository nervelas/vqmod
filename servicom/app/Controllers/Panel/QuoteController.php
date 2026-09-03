<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Pdf;
use App\Core\Request;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Quote;

final class QuoteController extends Controller
{
    /** Carga la cotización verificando empresa y propiedad del vendedor. */
    private function load(int $id, array $u, array $c): array
    {
        $q = Quote::find($id);
        if (!$q) {
            ErrorHandler::render(404);
        }
        if (Auth::isSeller() && (int) $q['user_id'] !== (int) $u['id']) {
            ErrorHandler::render(403);
        }
        if (Auth::isViewer() && !Request::isPost()) {
            return $q;
        }
        return $q;
    }

    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$page, $per, $offset] = Request::page(25);
        $status = Request::str('estado');
        $filters = [
            'q'        => Request::str('q'),
            'status'   => $status !== '' ? [$status] : [],
            'user_id'  => Auth::ownerFilter() ?: Request::int('vendedor'),
            'from'     => Request::str('desde'),
            'to'       => Request::str('hasta'),
            'sort'     => Request::str('orden'),
            'limit'    => $per,
            'offset'   => $offset,
        ];
        [$rows, $total] = Quote::search($filters);
        $this->view('panel/quotes', [
            'title'   => 'Cotizaciones',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) ceil($total / $per),
            'filters' => $filters,
            'status'  => $status,
            'sellers' => DB::all('SELECT id, name FROM users WHERE role IN ("admin","vendedor") ORDER BY name'),
        ], 'layout/panel');
    }

    public function create(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        if (!\App\Core\Request::isPost()) {
            $this->view('panel/quote-new', [
                'title'     => 'Nueva cotización',
                'customers' => DB::all('SELECT id, name, nit, email, phone, price_list_id FROM customers ORDER BY name LIMIT 500'),
            ], 'layout/panel');
            return;
        }
        Csrf::verify();
        $customerId = Request::int('customer_id');
        $cust = $customerId ? Customer::find($customerId) : null;
        $name = mb_substr(Request::str('contact_name'), 0, 140) ?: (string) ($cust['name'] ?? '');
        if ($name === '') {
            Flash::error('Indique el nombre de contacto o elija un cliente.');
            redirect('/panel/cotizaciones/nueva');
        }
        $num = Quote::nextNumber();
        $id = DB::insert('quotes', [
            'number'          => $num['number'],
            'folio_seq'       => $num['seq'],
            'folio_year'      => $num['year'],
            'version'         => 1,
            'is_current'      => 1,
            'customer_id'     => $cust ? (int) $cust['id'] : null,
            'user_id'         => (int) $u['id'],
            'contact_name'    => $name,
            'contact_company' => mb_substr(Request::str('contact_company'), 0, 180) ?: ($cust['legal_name'] ?? $cust['name'] ?? null),
            'contact_nit'     => mb_substr(Request::str('contact_nit'), 0, 30) ?: ($cust['nit'] ?? null),
            'contact_phone'   => mb_substr(Request::str('contact_phone'), 0, 40) ?: ($cust['phone'] ?? null),
            'contact_email'   => Request::email('contact_email') ?: ($cust['email'] ?? null),
            'status'          => 'elaboracion',
            'source'          => 'panel',
            'currency_symbol' => (string) $c['currency_symbol'],
            'tax_rate'        => (float) $c['tax_rate'],
            'validity_days'   => (int) $c['validity_days'],
            'valid_until'     => date('Y-m-d', strtotime('+' . (int) $c['validity_days'] . ' days')),
            'delivery_time'   => (string) ($c['delivery_terms'] ?? '') ?: null,
            'payment_terms'   => (string) ($c['payment_terms'] ?? '') ?: null,
            'track_token'     => Quote::newToken(),
            'last_contact_at' => nowSql(),
            'created_at'      => nowSql(),
            'updated_at'      => nowSql(),
        ]);
        Quote::event($id, 'sistema', 'Cotización creada desde el panel');
        Audit::log('cotizacion.crear', 'quote', $id, ['numero' => $num['number']]);
        Flash::ok('Cotización ' . $num['number'] . ' creada. Agregue los productos.');
        redirect('/panel/cotizaciones/' . $id);
    }

    public function edit(array $params): void
    {
        [$u, $c] = $this->panel();
        $q = $this->load((int) $params['id'], $u, $c);
        $this->view('panel/quote-edit', [
            'title'     => $q['number'],
            'q'         => $q,
            'items'     => Quote::items((int) $q['id']),
            'events'    => Quote::events((int) $q['id']),
            'versions'  => Quote::versions($q),
            'sellers'   => DB::all('SELECT id, name FROM users WHERE role IN ("admin","vendedor") AND status = "activo" ORDER BY name'),
            'customers' => DB::all('SELECT id, name, nit FROM customers ORDER BY name LIMIT 500'),
            'lostReasons' => Company::LOST_REASONS,
            'readonly'  => Auth::isViewer(),
        ], 'layout/panel');
    }

    public function save(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);

        $discountType = Request::str('discount_type');
        if (!in_array($discountType, ['ninguno', 'porcentaje', 'monto'], true)) {
            $discountType = 'ninguno';
        }
        $validity = max(1, min(365, Request::int('validity_days', (int) $c['validity_days'])));
        $data = [
            'contact_name'    => mb_substr(Request::str('contact_name'), 0, 140) ?: $q['contact_name'],
            'contact_company' => mb_substr(Request::str('contact_company'), 0, 180) ?: null,
            'contact_nit'     => mb_substr(Request::str('contact_nit'), 0, 30) ?: null,
            'contact_phone'   => mb_substr(Request::str('contact_phone'), 0, 40) ?: null,
            'contact_email'   => Request::email('contact_email') ?: null,
            'customer_id'     => Request::int('customer_id') ?: null,
            'discount_type'   => $discountType,
            'discount_value'  => max(0, Request::float('discount_value')),
            'tax_rate'        => max(0, min(100, Request::float('tax_rate', (float) $c['tax_rate']))),
            'validity_days'   => $validity,
            'valid_until'     => date('Y-m-d', strtotime((string) ($q['created_at'] ?? 'now')) + $validity * 86400),
            'delivery_time'   => mb_substr(Request::str('delivery_time'), 0, 160) ?: null,
            'payment_terms'   => mb_substr(Request::str('payment_terms'), 0, 190) ?: null,
            'notes'           => mb_substr(Request::str('notes'), 0, 4000) ?: null,
            'internal_notes'  => mb_substr(Request::str('internal_notes'), 0, 4000) ?: null,
            'next_followup_at' => Request::str('next_followup_at') ?: null,
            'updated_at'      => nowSql(),
        ];
        if ($data['customer_id'] && !Customer::find((int) $data['customer_id'])) {
            $data['customer_id'] = null;
        }
        if ($data['next_followup_at'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['next_followup_at'])) {
            $data['next_followup_at'] = null;
        }
        $changedDiscount = (float) $q['discount_value'] !== (float) $data['discount_value'] || $q['discount_type'] !== $discountType;
        DB::update('quotes', $data, 'id = :id', ['id' => (int) $q['id']]);
        Quote::recalc((int) $q['id']);
        if ($changedDiscount) {
            Audit::log('cotizacion.descuento', 'quote', (int) $q['id'], ['tipo' => $discountType, 'valor' => $data['discount_value']]);
        }
        Audit::log('cotizacion.editar', 'quote', (int) $q['id'], ['numero' => $q['number']]);
        Flash::ok('Cambios guardados.');
        redirect('/panel/cotizaciones/' . $q['id']);
    }

    /** Alta, edición y baja de líneas (AJAX). */
    public function item(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        Csrf::verify();
        $q = $this->load((int) $params['id'], $u, $c);
        $op = Request::str('op');

        if ($op === 'add') {
            $pid = Request::int('product_id');
            $p = $pid ? Product::find($pid) : null;
            $priceList = $q['customer_id'] ? (int) DB::value('SELECT price_list_id FROM customers WHERE id = ?', [(int) $q['customer_id']], 0) : 0;
            if ($p) {
                $specs = [];
                foreach (Product::attributes((int) $p['id']) as $a) {
                    $specs[] = $a['label'] . ': ' . $a['value'] . ($a['unit'] ? ' ' . $a['unit'] : '');
                }
                DB::insert('quote_items', [
                    'quote_id'   => (int) $q['id'],
                    'product_id' => (int) $p['id'],
                    'code'       => (string) $p['code'],
                    'name'       => (string) $p['name'],
                    'specs'      => mb_substr(implode(' · ', $specs), 0, 500) ?: null,
                    'qty'        => max(0.01, Request::float('qty', 1)),
                    'unit'       => (string) $p['unit'],
                    'unit_price' => Product::priceFor((int) $p['id'], $priceList ?: null),
                    'sort'       => (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM quote_items WHERE quote_id = ?', [(int) $q['id']], 0),
                ]);
            } else {
                // Línea libre (servicio, flete, ítem sin catálogo).
                $name = mb_substr(Request::str('name'), 0, 220);
                if ($name === '') {
                    jsonOut(['ok' => false, 'error' => 'Escriba la descripción de la línea.'], 400);
                }
                DB::insert('quote_items', [
                    'quote_id'   => (int) $q['id'],
                    'product_id' => null,
                    'code'       => mb_substr(Request::str('code'), 0, 60) ?: null,
                    'name'       => $name,
                    'qty'        => max(0.01, Request::float('qty', 1)),
                    'unit'       => mb_substr(Request::str('unit'), 0, 20) ?: 'unidad',
                    'unit_price' => max(0, Request::float('unit_price')),
                    'sort'       => (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM quote_items WHERE quote_id = ?', [(int) $q['id']], 0),
                ]);
            }
        } elseif ($op === 'update') {
            $iid = Request::int('item_id');
            $it = DB::one('SELECT * FROM quote_items WHERE id = ? AND quote_id = ? LIMIT 1', [$iid, (int) $q['id']]);
            if (!$it) {
                jsonOut(['ok' => false, 'error' => 'Línea no encontrada'], 404);
            }
            $newPrice = max(0, Request::float('unit_price', (float) $it['unit_price']));
            if (abs($newPrice - (float) $it['unit_price']) > 0.001) {
                Audit::log('cotizacion.precio_linea', 'quote_item', $iid, ['de' => (float) $it['unit_price'], 'a' => $newPrice, 'cot' => $q['number']]);
            }
            DB::update('quote_items', [
                'qty'          => max(0.01, Request::float('qty', (float) $it['qty'])),
                'unit_price'   => $newPrice,
                'discount_pct' => max(0, min(100, Request::float('discount_pct', (float) $it['discount_pct']))),
                'name'         => mb_substr(Request::str('name', (string) $it['name']), 0, 220) ?: (string) $it['name'],
                'notes'        => mb_substr(Request::str('notes'), 0, 300) ?: null,
            ], 'id = :id', ['id' => $iid]);
        } elseif ($op === 'delete') {
            DB::delete('quote_items', 'id = :id AND quote_id = :q', [
                'id' => Request::int('item_id'), 'q' => (int) $q['id'],
            ]);
        } else {
            jsonOut(['ok' => false, 'error' => 'Operación no válida'], 400);
        }

        $t = Quote::recalc((int) $q['id']);
        $sym = (string) $q['currency_symbol'];
        jsonOut([
            'ok'    => true,
            'items' => Quote::items((int) $q['id']),
            'totals' => [
                'subtotal' => money($t['subtotal'], $sym),
                'discount' => money($t['discountAmount'], $sym),
                'tax'      => money($t['tax'], $sym),
                'total'    => money($t['total'], $sym),
                'raw'      => $t,
            ],
        ]);
    }

    public function status(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);
        $to = Request::str('status');
        if (!isset(Quote::STATUSES[$to])) {
            Flash::error('Estado no válido.');
            $this->back('/panel/cotizaciones/' . $q['id']);
        }
        if ($to === 'perdida' && Request::str('lost_reason') === '') {
            Flash::error('Indique el motivo de pérdida.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        Quote::setStatus((int) $q['id'], $to, [
            'lost_reason' => Request::str('lost_reason'),
            'lost_detail' => Request::str('lost_detail'),
            'note'        => Request::str('note'),
        ]);
        Flash::ok('Estado actualizado a ' . Quote::STATUSES[$to]['label'] . '.');
        redirect('/panel/cotizaciones/' . $q['id']);
    }

    public function note(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);
        $type = Request::str('type');
        if (!in_array($type, ['nota', 'llamada', 'correo', 'whatsapp'], true)) {
            $type = 'nota';
        }
        $body = mb_substr(Request::str('body'), 0, 2000);
        if (trim($body) === '') {
            Flash::error('Escriba el contenido de la bitácora.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        $titles = ['nota' => 'Nota interna', 'llamada' => 'Llamada registrada', 'correo' => 'Correo registrado', 'whatsapp' => 'WhatsApp registrado'];
        Quote::event((int) $q['id'], $type, $titles[$type], $body);
        DB::update('quotes', ['last_contact_at' => nowSql(), 'updated_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
        Flash::ok('Registro agregado a la bitácora.');
        redirect('/panel/cotizaciones/' . $q['id']);
    }

    public function assign(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $q = Quote::find((int) $params['id']);
        if (!$q) {
            ErrorHandler::render(404);
        }
        $to = Request::int('user_id');
        $seller = DB::one('SELECT id, name, email FROM users WHERE id = ? AND status = "activo" LIMIT 1', [$to]);
        if (!$seller) {
            Flash::error('Vendedor no válido.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        DB::update('quotes', ['user_id' => (int) $seller['id'], 'updated_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
        Quote::event((int) $q['id'], 'sistema', 'Asignada a ' . $seller['name']);
        Notification::push((int) $seller['id'], 'Se le asignó ' . $q['number'], (string) ($q['contact_company'] ?: $q['contact_name']), '/panel/cotizaciones/' . $q['id'], 'cotizacion');
        Audit::log('cotizacion.asignar', 'quote', (int) $q['id'], ['a' => $seller['name']]);
        Flash::ok('Cotización asignada a ' . $seller['name'] . '.');
        redirect('/panel/cotizaciones/' . $q['id']);
    }

    public function duplicate(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);
        $new = Quote::duplicate((int) $q['id'], (int) $u['id']);
        if (!$new) {
            Flash::error('No se pudo duplicar.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        Flash::ok('Cotización duplicada.');
        redirect('/panel/cotizaciones/' . $new);
    }

    public function version(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);
        $new = Quote::newVersion((int) $q['id']);
        if (!$new) {
            Flash::error('No se pudo crear la versión.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        Flash::ok('Nueva versión creada. La anterior queda en el historial.');
        redirect('/panel/cotizaciones/' . $new);
    }

    public function destroy(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $q = Quote::find((int) $params['id']);
        if (!$q) {
            ErrorHandler::render(404);
        }
        DB::delete('quote_items', 'quote_id = :q', ['q' => (int) $q['id']]);
        DB::delete('quote_events', 'quote_id = :q', ['q' => (int) $q['id']]);
        DB::delete('quotes', 'id = :id', ['id' => (int) $q['id']]);
        Audit::log('cotizacion.eliminar', 'quote', (int) $q['id'], ['numero' => $q['number']]);
        Flash::ok('Cotización eliminada.');
        redirect('/panel/cotizaciones');
    }

    public function pdf(array $params): void
    {
        [$u, $c] = $this->panel();
        $q = $this->load((int) $params['id'], $u, $c);
        $items = Quote::items((int) $q['id']);
        if (!$items) {
            Flash::error('Agregue al menos un producto antes de generar el PDF.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        Quote::recalc((int) $q['id']);
        $q = Quote::find((int) $q['id']);
        $file = Pdf::quote($c, $q, $items);
        $rel  = str_replace(STORAGE_PATH . '/uploads/', '', $file);
        DB::update('quotes', ['pdf_path' => $rel], 'id = :id', ['id' => (int) $q['id']]);
        Quote::event((int) $q['id'], 'pdf', 'PDF generado');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', (string) $q['number'])) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function orderPdf(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $q = $this->load((int) $params['id'], $u, $c);
        if ($q['status'] !== 'aprobada') {
            Flash::error('La orden de trabajo se genera sobre cotizaciones aprobadas.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        $file = Pdf::quote($c, $q, Quote::items((int) $q['id']), ['order' => true]);
        Audit::log('cotizacion.orden', 'quote', (int) $q['id'], []);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="orden-' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', (string) $q['number'])) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /** Envío en un clic: PDF + correo + enlace de seguimiento. */
    public function send(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $q = $this->load((int) $params['id'], $u, $c);
        $items = Quote::items((int) $q['id']);
        if (!$items) {
            Flash::error('No puede enviar una cotización sin productos.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        $to = Request::email('to') ?: (string) $q['contact_email'];
        if ($to === '') {
            Flash::error('El cliente no tiene correo. Agregue uno antes de enviar.');
            redirect('/panel/cotizaciones/' . $q['id']);
        }
        Quote::recalc((int) $q['id']);
        $q = Quote::find((int) $q['id']);
        $file = Pdf::quote($c, $q, $items);
        DB::update('quotes', ['pdf_path' => str_replace(STORAGE_PATH . '/uploads/', '', $file)], 'id = :id', ['id' => (int) $q['id']]);

        $msg = mb_substr(Request::str('message'), 0, 2000);
        $body = '<p>Estimado(a) ' . e($q['contact_name']) . ',</p>'
              . '<p>Adjuntamos la cotización <strong>' . e($q['number']) . '</strong> por un total de <strong>'
              . e(money((float) $q['total'], (string) $q['currency_symbol'])) . '</strong>'
              . ($q['valid_until'] ? ', válida hasta el ' . e(fechaLarga((string) $q['valid_until'])) : '') . '.</p>'
              . ($msg !== '' ? '<p>' . nl2br(e($msg)) . '</p>' : '')
              . '<p>Desde el enlace de abajo puede consultar el estado, descargar el PDF, aprobarla o solicitar cambios.</p>';
        $ok = Mailer::send(
            $to,
            'Cotización ' . $q['number'] . ' — ' . $c['name'],
            Mailer::template('Su cotización ' . $q['number'], $body, $c, 'Ver y aprobar la cotización', Quote::trackUrl($q)),
            $c,
            [[$file, str_replace(' ', '-', (string) $q['number']) . '.pdf']],
            (string) ($q['seller_email'] ?? ''),
            (string) ($q['seller_name'] ?? '')
        );

        Quote::setStatus((int) $q['id'], 'enviada', ['note' => 'Enviada por correo a ' . $to]);
        Quote::event((int) $q['id'], 'correo', $ok ? 'Cotización enviada a ' . $to : 'Fallo el envío a ' . $to, $msg);
        Audit::log('cotizacion.enviar', 'quote', (int) $q['id'], ['to' => $to, 'ok' => $ok]);

        if ($ok) {
            Flash::ok('Cotización enviada a ' . $to . '. El enlace de seguimiento ya está activo.');
        } else {
            Flash::warn('No se pudo enviar el correo (revise el SMTP en Ajustes). La cotización quedó marcada como enviada y el enlace ya funciona.');
        }
        redirect('/panel/cotizaciones/' . $q['id']);
    }

    /** Buscador de productos del editor de cotización (por código o nombre). */
    public function productSearch(array $params = []): void
    {
        [$u, $c] = $this->panel();
        $q = Request::str('q');
        if (mb_strlen($q) < 1) {
            jsonOut(['ok' => true, 'items' => []]);
        }
        [$rows] = Product::search(['q' => $q, 'limit' => 12, 'only_active' => 1]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'    => (int) $r['id'],
                'code'  => (string) $r['code'],
                'name'  => (string) $r['name'],
                'unit'  => (string) $r['unit'],
                'price' => money((float) $r['price'], (string) $c['currency_symbol']),
                'cat'   => (string) ($r['category_name'] ?? ''),
            ];
        }
        jsonOut(['ok' => true, 'items' => $out]);
    }
}
