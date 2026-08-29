<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Captcha;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Models\AttributeDef;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Quote;

final class SiteController extends Controller
{
    public function home(array $params): void
    {
        $c   = $this->tenant($params);
        $cid = (int) $c['id'];

        [$featured] = Product::search($cid, ['only_active' => 1, 'featured' => 1, 'limit' => 8]);
        if (count($featured) < 4) {
            [$featured] = Product::search($cid, ['only_active' => 1, 'sort' => 'cotizados', 'limit' => 8]);
        }
        [$mostQuoted] = Product::search($cid, ['only_active' => 1, 'sort' => 'cotizados', 'limit' => 6]);

        $cats = array_values(array_filter(Category::tree($cid, true), static fn ($x) => true));
        $this->view('site/home', [
            'title'       => $c['seo_title'] ?: ($c['name'] . ' — Catálogo técnico y cotizador en línea'),
            'description' => $c['seo_description'] ?: str_limit((string) ($c['tagline'] ?: $c['about']), 155),
            'categories'  => array_slice($cats, 0, 6),
            'featured'    => $featured,
            'mostQuoted'  => $mostQuoted,
            'brands'      => Brand::all($cid, true),
            'productTotal' => (int) DB::value('SELECT COUNT(*) FROM products WHERE company_id = ? AND active = 1', [$cid], 0),
            'quoteTotal'  => (int) DB::value('SELECT COUNT(*) FROM quotes WHERE company_id = ?', [$cid], 0),
            'cartCount'   => Cart::count($cid),
        ], 'layout/site');
    }

    public function catalog(array $params): void
    {
        $c   = $this->tenant($params);
        $cid = (int) $c['id'];

        $cat = null;
        if (!empty($params['cat'])) {
            $cat = Category::bySlug($cid, (string) $params['cat']);
            if (!$cat || !$cat['active']) {
                ErrorHandler::render(404);
            }
        }
        $attrFilters = [];
        foreach (Request::arr('a') as $k => $v) {
            if (is_string($k) && is_string($v) && $v !== '') {
                $attrFilters[preg_replace('/[^a-z0-9_\-]/i', '', $k)] = mb_substr($v, 0, 90);
            }
        }
        [$page, $per, $offset] = Request::page(24);
        $filters = [
            'only_active' => 1,
            'q'           => Request::str('q'),
            'category_id' => $cat ? (int) $cat['id'] : 0,
            'brand_id'    => Request::int('marca'),
            'attr'        => $attrFilters,
            'sort'        => Request::str('orden'),
            'limit'       => $per,
            'offset'      => $offset,
        ];
        [$rows, $total] = Product::search($cid, $filters);

        $this->view('site/catalog', [
            'title'       => ($cat ? $cat['name'] . ' — ' : 'Catálogo técnico — ') . $c['name'],
            'description' => $cat ? str_limit((string) ($cat['seo_description'] ?: $cat['description']), 155) : 'Catálogo completo de ' . $c['name'] . '. Busque por código, nombre o medida y solicite su cotización en línea.',
            'category'    => $cat,
            'crumbs'      => $cat ? Category::breadcrumb($cid, (int) $cat['id']) : [],
            'tree'        => Category::tree($cid, true),
            'products'    => $rows,
            'total'       => $total,
            'page'        => $page,
            'pages'       => (int) ceil($total / $per),
            'facets'      => AttributeDef::facets($cid, $cat ? (int) $cat['id'] : null),
            'brands'      => Brand::all($cid, true),
            'view'        => Request::str('vista') === 'lista' ? 'lista' : 'cuadricula',
            'filters'     => $filters,
            'attrFilters' => $attrFilters,
            'cartCount'   => Cart::count($cid),
        ], 'layout/site');
    }

    public function product(array $params): void
    {
        $c   = $this->tenant($params);
        $cid = (int) $c['id'];
        $p = Product::bySlug($cid, (string) $params['prod']);
        if (!$p || !$p['active']) {
            ErrorHandler::render(404);
        }
        DB::run('UPDATE products SET views = views + 1 WHERE id = ? AND company_id = ?', [(int) $p['id'], $cid]);

        $this->view('site/product', [
            'title'       => $p['seo_title'] ?: ($p['name'] . ' · ' . $p['code'] . ' — ' . $c['name']),
            'description' => $p['seo_description'] ?: str_limit((string) ($p['short_desc'] ?: $p['description']), 155),
            'p'           => $p,
            'images'      => Product::images($cid, (int) $p['id']),
            'attributes'  => Product::attributes($cid, (int) $p['id']),
            'documents'   => Product::documents($cid, (int) $p['id']),
            'related'     => Product::related($cid, $p, 4),
            'crumbs'      => $p['category_id'] ? Category::breadcrumb($cid, (int) $p['category_id']) : [],
            'showPrice'   => Product::priceVisible($c, $p),
            'cartCount'   => Cart::count($cid),
        ], 'layout/site');
    }

    public function about(array $params): void
    {
        $c = $this->tenant($params);
        $this->view('site/about', [
            'title'       => 'Quiénes somos — ' . $c['name'],
            'description' => str_limit((string) ($c['about'] ?: $c['tagline']), 155),
            'brands'      => Brand::all((int) $c['id'], true),
            'cartCount'   => Cart::count((int) $c['id']),
        ], 'layout/site');
    }

    public function contact(array $params): void
    {
        $c = $this->tenant($params);
        $this->view('site/contact', [
            'title'       => 'Contacto — ' . $c['name'],
            'description' => 'Escríbanos o solicite su cotización. ' . $c['name'] . '.',
            'cartCount'   => Cart::count((int) $c['id']),
        ], 'layout/site');
    }

    // ------------------------------------------------------- carrito de cotización
    public function cart(array $params): void
    {
        $c   = $this->tenant($params);
        $cid = (int) $c['id'];
        $lines = Cart::lines($cid);
        $this->view('site/cart', [
            'title'       => 'Su solicitud de cotización — ' . $c['name'],
            'description' => 'Revise su lista y envíe la solicitud de cotización.',
            'lines'       => $lines,
            'captcha'     => Captcha::make(),
            'cartCount'   => count($lines),
            'noindex'     => true,
        ], 'layout/site');
    }

    /** API del carrito (AJAX, con token CSRF). */
    public function cartApi(array $params): void
    {
        $c = $this->tenant($params);
        $cid = (int) $c['id'];
        Csrf::verify();
        $action = Request::str('action');
        $pid    = Request::int('id');
        $ok = true;
        switch ($action) {
            case 'add':
                $ok = Cart::add($cid, $pid, max(0.01, Request::float('qty', 1)), Request::str('note'));
                if ($ok) {
                    DB::run('UPDATE products SET quote_count = quote_count + 1 WHERE id = ? AND company_id = ?', [$pid, $cid]);
                }
                break;
            case 'qty':
                Cart::setQty($cid, $pid, Request::float('qty', 1));
                break;
            case 'note':
                Cart::setNote($cid, $pid, Request::str('note'));
                break;
            case 'remove':
                Cart::remove($cid, $pid);
                break;
            case 'clear':
                Cart::clear($cid);
                break;
            default:
                jsonOut(['ok' => false, 'error' => 'Acción no reconocida'], 400);
        }
        jsonOut(['ok' => $ok, 'count' => Cart::count($cid)]);
    }

    /** Sugerencias del buscador (código, nombre o medida). */
    public function suggest(array $params): void
    {
        $c = $this->tenant($params);
        $q = Request::str('q');
        if (mb_strlen($q) < 2) {
            jsonOut(['ok' => true, 'items' => []]);
        }
        [$rows] = Product::search((int) $c['id'], ['only_active' => 1, 'q' => $q, 'limit' => 8]);
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'code' => $r['code'],
                'name' => $r['name'],
                'cat'  => $r['category_name'],
                'url'  => url('/e/' . $c['slug'] . '/producto/' . $r['slug']),
            ];
        }
        jsonOut(['ok' => true, 'items' => $items]);
    }

    /** Recibe la solicitud pública y crea la cotización en estado "nueva". */
    public function submit(array $params): void
    {
        $c   = $this->tenant($params);
        $cid = (int) $c['id'];
        $this->guardPost();

        if (!RateLimit::hit('quote_ip', App::ip(), 6, 3600)) {
            Flash::error('Recibimos varias solicitudes desde su conexión. Intente de nuevo en una hora o llámenos.');
            redirect('/e/' . $c['slug'] . '/cotizacion');
        }
        if (!Captcha::check(Request::str('captcha'), Request::str('captcha_stamp'))) {
            Flash::error('La respuesta de verificación no es correcta.');
            Flash::keep($_POST);
            redirect('/e/' . $c['slug'] . '/cotizacion');
        }
        // Trampa anti-robot: campo oculto que un humano nunca llena.
        if (Request::str('website') !== '') {
            redirect('/e/' . $c['slug'] . '/cotizacion');
        }

        $lines = Cart::lines($cid);
        if (!$lines) {
            Flash::error('Su lista está vacía. Agregue al menos un producto.');
            redirect('/e/' . $c['slug'] . '/catalogo');
        }

        $name  = mb_substr(Request::str('name'), 0, 140);
        $email = Request::email('email');
        $phone = mb_substr(Request::str('phone'), 0, 40);
        $co    = mb_substr(Request::str('company'), 0, 180);
        $nit   = mb_substr(Request::str('nit'), 0, 30);
        $msg   = mb_substr(Request::str('message'), 0, 2000);

        $v = new \App\Core\Validator();
        $v->required('name', $name, 'Su nombre')
          ->required('email', $email, 'Su correo')
          ->email('email', $email)
          ->required('phone', $phone, 'Su teléfono')
          ->phone('phone', $phone);
        if ($v->fails()) {
            Flash::error($v->first());
            Flash::keep($_POST);
            redirect('/e/' . $c['slug'] . '/cotizacion');
        }

        $seller = Company::nextSeller($cid);
        $num    = Quote::nextNumber($cid);
        $token  = Quote::newToken();

        DB::begin();
        try {
            $customerId = Customer::findOrCreate($cid, [
                'name' => $name, 'company' => $co, 'nit' => $nit, 'email' => $email, 'phone' => $phone,
            ], $seller);

            $quoteId = DB::insert('quotes', [
                'company_id'      => $cid,
                'number'          => $num['number'],
                'folio_seq'       => $num['seq'],
                'folio_year'      => $num['year'],
                'version'         => 1,
                'is_current'      => 1,
                'customer_id'     => $customerId,
                'user_id'         => $seller,
                'contact_name'    => $name,
                'contact_company' => $co ?: null,
                'contact_nit'     => $nit ?: null,
                'contact_phone'   => $phone,
                'contact_email'   => $email,
                'status'          => 'nueva',
                'source'          => 'web',
                'currency_symbol' => (string) $c['currency_symbol'],
                'tax_rate'        => (float) $c['tax_rate'],
                'validity_days'   => (int) $c['validity_days'],
                'valid_until'     => date('Y-m-d', strtotime('+' . (int) $c['validity_days'] . ' days')),
                'delivery_time'   => (string) ($c['delivery_terms'] ?? '') ?: null,
                'payment_terms'   => (string) ($c['payment_terms'] ?? '') ?: null,
                'client_message'  => $msg ?: null,
                'track_token'     => $token,
                'last_contact_at' => nowSql(),
                'created_at'      => nowSql(),
                'updated_at'      => nowSql(),
            ]);

            $sort = 0;
            foreach ($lines as $l) {
                $specs = [];
                foreach (Product::attributes($cid, (int) $l['id']) as $a) {
                    $specs[] = $a['label'] . ': ' . $a['value'] . ($a['unit'] ? ' ' . $a['unit'] : '');
                }
                DB::insert('quote_items', [
                    'company_id' => $cid,
                    'quote_id'   => $quoteId,
                    'product_id' => (int) $l['id'],
                    'code'       => (string) $l['code'],
                    'name'       => (string) $l['name'],
                    'specs'      => mb_substr(implode(' · ', $specs), 0, 500) ?: null,
                    'notes'      => mb_substr((string) $l['cart_note'], 0, 300) ?: null,
                    'qty'        => (float) $l['cart_qty'],
                    'unit'       => (string) $l['unit'],
                    'unit_price' => (float) $l['price'],
                    'sort'       => $sort++,
                ]);
                DB::run('UPDATE products SET quote_count = quote_count + 1 WHERE id = ? AND company_id = ?', [(int) $l['id'], $cid]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        Quote::recalc($cid, $quoteId);
        Quote::event($cid, $quoteId, 'cliente', 'Solicitud recibida desde el sitio web', $msg, $name);
        Audit::log('cotizacion.solicitud_web', 'quote', $quoteId, ['numero' => $num['number']], $cid);
        Cart::clear($cid);

        $q = Quote::find($cid, $quoteId);
        $items = Quote::items($cid, $quoteId);
        $this->notifyNewRequest($c, $q, $items, $seller);

        redirect('/e/' . $c['slug'] . '/recibida/' . $token);
    }

    private function notifyNewRequest(array $c, array $q, array $items, ?int $sellerId): void
    {
        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr><td style="padding:7px 0;border-bottom:1px solid #E7E9E4;font:600 12px Helvetica,Arial,sans-serif">' . e($it['code']) . '</td>'
                   . '<td style="padding:7px 8px;border-bottom:1px solid #E7E9E4;font:400 13px Helvetica,Arial,sans-serif">' . e($it['name']) . '</td>'
                   . '<td style="padding:7px 0;border-bottom:1px solid #E7E9E4;text-align:right;font:600 13px Helvetica,Arial,sans-serif">' . e(qty((float) $it['qty'])) . ' ' . e($it['unit']) . '</td></tr>';
        }
        $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0">' . $rows . '</table>';

        // 1) Acuse al cliente con su enlace de seguimiento.
        $bodyClient = '<p>Hola ' . e($q['contact_name']) . ', recibimos su solicitud <strong>' . e($q['number']) . '</strong>.</p>'
            . '<p>Nuestro equipo la está revisando y le enviaremos la cotización formal a la brevedad. '
            . 'Puede seguir el avance en cualquier momento desde el enlace de abajo.</p>' . $table;
        Mailer::send(
            (string) $q['contact_email'],
            'Recibimos su solicitud ' . $q['number'] . ' — ' . $c['name'],
            Mailer::template('Solicitud recibida', $bodyClient, $c, 'Ver el estado de mi cotización', Quote::trackUrl($q)),
            $c
        );

        // 2) Aviso interno a la empresa y al vendedor asignado.
        $internal = '<p>Nueva solicitud desde el sitio web.</p>'
            . '<p><strong>' . e($q['contact_company'] ?: $q['contact_name']) . '</strong><br>'
            . e($q['contact_name']) . ' · ' . e($q['contact_phone']) . ' · ' . e($q['contact_email'])
            . ($q['contact_nit'] ? '<br>NIT: ' . e($q['contact_nit']) : '') . '</p>'
            . ($q['client_message'] ? '<p><em>' . nl2br(e($q['client_message'])) . '</em></p>' : '')
            . $table;
        $to = (string) ($c['email'] ?: $c['smtp_from']);
        if ($to !== '') {
            Mailer::send($to, 'Nueva solicitud ' . $q['number'] . ' de ' . ($q['contact_company'] ?: $q['contact_name']), Mailer::template('Nueva solicitud de cotización', $internal, $c, 'Abrir en el panel', absUrl('/panel/cotizaciones/' . $q['id'])), $c, [], (string) $q['contact_email'], (string) $q['contact_name']);
        }
        if ($sellerId) {
            $seller = DB::one('SELECT email, name FROM users WHERE id = ? LIMIT 1', [$sellerId]);
            if ($seller && $seller['email'] && $seller['email'] !== $to) {
                Mailer::send((string) $seller['email'], 'Se le asignó la solicitud ' . $q['number'], Mailer::template('Solicitud asignada', $internal, $c, 'Abrir en el panel', absUrl('/panel/cotizaciones/' . $q['id'])), $c);
            }
            Notification::push($sellerId, 'Nueva solicitud ' . $q['number'], (string) ($q['contact_company'] ?: $q['contact_name']), '/panel/cotizaciones/' . $q['id'], 'cotizacion', (int) $c['id']);
        } else {
            foreach (DB::all('SELECT id FROM users WHERE company_id = ? AND role = "admin" AND status = "activo"', [(int) $c['id']]) as $a) {
                Notification::push((int) $a['id'], 'Nueva solicitud ' . $q['number'], (string) ($q['contact_company'] ?: $q['contact_name']), '/panel/cotizaciones/' . $q['id'], 'cotizacion', (int) $c['id']);
            }
        }
    }

    public function received(array $params): void
    {
        $c = $this->tenant($params);
        $q = Quote::byToken((string) $params['token']);
        if (!$q || (int) $q['company_id'] !== (int) $c['id']) {
            ErrorHandler::render(404);
        }
        $this->view('site/received', [
            'title'     => 'Solicitud ' . $q['number'] . ' recibida',
            'q'         => $q,
            'items'     => Quote::items((int) $c['id'], (int) $q['id']),
            'cartCount' => 0,
            'noindex'   => true,
        ], 'layout/site');
    }

    // ------------------------------------------------------------------ SEO
    public function sitemap(array $params): void
    {
        $c = $this->tenant($params);
        $cid = (int) $c['id'];
        header('Content-Type: application/xml; charset=utf-8');
        $base = '/e/' . $c['slug'];
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $out .= LandingController::urlNode(absUrl($base), date('Y-m-d'), '1.0');
        $out .= LandingController::urlNode(absUrl($base . '/catalogo'), date('Y-m-d'), '0.9');
        $out .= LandingController::urlNode(absUrl($base . '/nosotros'), date('Y-m-d'), '0.5');
        $out .= LandingController::urlNode(absUrl($base . '/contacto'), date('Y-m-d'), '0.5');
        foreach (DB::all('SELECT slug FROM categories WHERE company_id = ? AND active = 1', [$cid]) as $r) {
            $out .= LandingController::urlNode(absUrl($base . '/categoria/' . $r['slug']), date('Y-m-d'), '0.7');
        }
        foreach (DB::all('SELECT slug, updated_at FROM products WHERE company_id = ? AND active = 1 LIMIT 5000', [$cid]) as $r) {
            $out .= LandingController::urlNode(absUrl($base . '/producto/' . $r['slug']), substr((string) ($r['updated_at'] ?: nowSql()), 0, 10), '0.6');
        }
        echo $out . '</urlset>';
        exit;
    }
}
