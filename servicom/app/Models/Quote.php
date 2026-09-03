<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Security;

final class Quote
{
    /** Columnas del tablero Kanban, en orden. */
    public const STATUSES = [
        'nueva'       => ['label' => 'Nuevas',         'short' => 'Nueva',        'hint' => 'Solicitudes recibidas sin trabajar'],
        'elaboracion' => ['label' => 'En elaboración', 'short' => 'Elaboración',  'hint' => 'Se está armando la oferta'],
        'enviada'     => ['label' => 'Enviadas',       'short' => 'Enviada',      'hint' => 'Ya está en manos del cliente'],
        'negociacion' => ['label' => 'En negociación', 'short' => 'Negociación',  'hint' => 'Ajustes, contraofertas, dudas'],
        'aprobada'    => ['label' => 'Aprobadas',      'short' => 'Aprobada',     'hint' => 'Cerrada y ganada'],
        'perdida'     => ['label' => 'Perdidas',       'short' => 'Perdida',      'hint' => 'No se concretó'],
    ];

    /** Estados visibles para el cliente en /c/{token}. */
    public const CLIENT_STEPS = [
        'nueva'       => ['n' => 1, 'label' => 'Recibida'],
        'elaboracion' => ['n' => 2, 'label' => 'En elaboración'],
        'enviada'     => ['n' => 3, 'label' => 'Enviada'],
        'negociacion' => ['n' => 3, 'label' => 'Enviada'],
        'aprobada'    => ['n' => 4, 'label' => 'Aprobada'],
        'perdida'     => ['n' => 4, 'label' => 'Cerrada'],
    ];

    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT q.*, u.name AS seller_name, u.email AS seller_email, u.phone AS seller_phone,
                    u.position AS seller_position, u.whatsapp AS seller_whatsapp,
                    cu.name AS customer_name, cu.nit AS customer_nit, cu.price_list_id
             FROM quotes q
             LEFT JOIN users u ON u.id = q.user_id
             LEFT JOIN customers cu ON cu.id = q.customer_id
             WHERE q.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{40,90}$/', $token)) {
            return null;
        }
        return DB::one(
            'SELECT q.*, u.name AS seller_name, u.email AS seller_email, u.phone AS seller_phone, u.whatsapp AS seller_whatsapp
             FROM quotes q LEFT JOIN users u ON u.id = q.user_id
             WHERE q.track_token = ? AND q.token_revoked = 0 LIMIT 1',
            [$token]
        );
    }

    public static function items(int $quoteId): array
    {
        return DB::all('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort, id', [$quoteId]);
    }

    public static function events(int $quoteId): array
    {
        return DB::all('SELECT * FROM quote_events WHERE quote_id = ? ORDER BY created_at DESC, id DESC', [$quoteId]);
    }

    /** Numeración correlativa por año: COT-2026-0001 */
    public static function nextNumber(): array
    {
        DB::begin();
        try {
            $c = DB::one('SELECT quote_prefix, quote_next, quote_year, quote_pad FROM company WHERE id = ? FOR UPDATE', [Company::ID]);
            if (!$c) {
                throw new \RuntimeException('La empresa no está configurada');
            }
            $year = (int) date('Y');
            $seq  = (int) $c['quote_next'];
            if ((int) $c['quote_year'] !== $year) {
                $seq = 1;
            }
            $pad    = max(3, (int) $c['quote_pad']);
            $number = sprintf('%s-%d-%s', $c['quote_prefix'], $year, str_pad((string) $seq, $pad, '0', STR_PAD_LEFT));
            DB::update('company', ['quote_next' => $seq + 1, 'quote_year' => $year], 'id = :id', ['id' => Company::ID]);
            Company::forget();
            DB::commit();
            return ['number' => $number, 'seq' => $seq, 'year' => $year];
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    public static function newToken(): string
    {
        do {
            $t = Security::signedToken('quote');
        } while (DB::one('SELECT id FROM quotes WHERE track_token = ? LIMIT 1', [$t]));
        return $t;
    }

    /**
     * Recalcula SIEMPRE en servidor: líneas, descuentos, impuesto y total.
     * No confía en ningún importe enviado por el navegador.
     */
    public static function recalc(int $quoteId): array
    {
        $q = DB::one('SELECT * FROM quotes WHERE id = ? LIMIT 1', [$quoteId]);
        if (!$q) {
            throw new \RuntimeException('Cotización inexistente');
        }
        $items = self::items($quoteId);
        $subtotal = 0.0;
        foreach ($items as $it) {
            $qty  = max(0.0, (float) $it['qty']);
            $unit = max(0.0, (float) $it['unit_price']);
            $dpct = min(100.0, max(0.0, (float) $it['discount_pct']));
            $line = round($qty * $unit * (1 - $dpct / 100), 2);
            if (abs($line - (float) $it['line_total']) > 0.001) {
                DB::update('quote_items', ['line_total' => $line], 'id = :id', ['id' => (int) $it['id']]);
            }
            $subtotal += $line;
        }
        $subtotal = round($subtotal, 2);

        $discountAmount = 0.0;
        if ($q['discount_type'] === 'porcentaje') {
            $discountAmount = round($subtotal * min(100.0, max(0.0, (float) $q['discount_value'])) / 100, 2);
        } elseif ($q['discount_type'] === 'monto') {
            $discountAmount = round(min($subtotal, max(0.0, (float) $q['discount_value'])), 2);
        }
        $base = round(max(0, $subtotal - $discountAmount), 2);
        $rate = max(0.0, (float) $q['tax_rate']);
        $tax  = round($base * $rate / 100, 2);
        $total = round($base + $tax, 2);

        DB::update('quotes', [
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_base'    => $base,
            'tax_amount'      => $tax,
            'total'           => $total,
            'updated_at'      => nowSql(),
        ], 'id = :id', ['id' => $quoteId]);

        return compact('subtotal', 'discountAmount', 'base', 'tax', 'total');
    }

    public static function event(int $quoteId, string $type, string $title, string $body = '', ?string $actor = null): int
    {
        return DB::insert('quote_events', [
            'quote_id'   => $quoteId,
            'user_id'    => Auth::id() ?: null,
            'actor'      => $actor ?? (Auth::user()['name'] ?? 'Sistema'),
            'type'       => $type,
            'title'      => mb_substr($title, 0, 190),
            'body'       => $body !== '' ? $body : null,
            'created_at' => nowSql(),
        ]);
    }

    /** Cambia de estado registrando bitácora y marcas de tiempo. */
    public static function setStatus(int $quoteId, string $status, array $extra = [], ?string $actor = null): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        $q = DB::one('SELECT status, total, number, sent_at FROM quotes WHERE id = ? LIMIT 1', [$quoteId]);
        if (!$q) {
            return false;
        }
        $old = (string) $q['status'];
        if ($old === $status && !$extra) {
            return true;
        }
        $data = ['status' => $status, 'updated_at' => nowSql(), 'last_contact_at' => nowSql()];
        if ($status === 'aprobada') {
            $data['approved_at'] = nowSql();
            $data['won_amount']  = (float) $q['total'];
        }
        if ($status === 'perdida') {
            $data['lost_at']     = nowSql();
            $data['won_amount']  = 0;
            $data['lost_reason'] = mb_substr((string) ($extra['lost_reason'] ?? 'otro'), 0, 60);
            $data['lost_detail'] = mb_substr((string) ($extra['lost_detail'] ?? ''), 0, 255) ?: null;
        }
        if ($status === 'enviada' && empty($q['sent_at'])) {
            $data['sent_at'] = nowSql();
        }
        DB::update('quotes', $data, 'id = :id', ['id' => $quoteId]);

        $labels = self::STATUSES;
        self::event(
            $quoteId,
            'estado',
            'Estado: ' . $labels[$old]['short'] . ' → ' . $labels[$status]['short'],
            (string) ($extra['note'] ?? ''),
            $actor
        );
        Audit::log('cotizacion.estado', 'quote', $quoteId, ['de' => $old, 'a' => $status, 'numero' => $q['number']]);
        return true;
    }

    /**
     * Listado con filtros para tabla y Kanban.
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    public static function search(array $f = []): array
    {
        $where  = ['q.is_current = 1'];
        $params = [];

        if (!empty($f['status'])) {
            $st = (array) $f['status'];
            $st = array_values(array_intersect($st, array_keys(self::STATUSES)));
            if ($st) {
                $where[] = 'q.status IN (' . implode(',', array_fill(0, count($st), '?')) . ')';
                foreach ($st as $s) {
                    $params[] = $s;
                }
            }
        }
        if (!empty($f['user_id'])) {
            $where[] = 'q.user_id = ?';
            $params[] = (int) $f['user_id'];
        }
        if (!empty($f['customer_id'])) {
            $where[] = 'q.customer_id = ?';
            $params[] = (int) $f['customer_id'];
        }
        if (!empty($f['from'])) {
            $where[] = 'q.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $where[] = 'q.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
            $where[] = '(q.number LIKE ? OR q.contact_name LIKE ? OR q.contact_company LIKE ? OR q.contact_email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) DB::value("SELECT COUNT(*) FROM quotes q WHERE {$w}", $params, 0);

        $order = match ((string) ($f['sort'] ?? '')) {
            'monto'  => 'q.total DESC',
            'antigua' => 'q.created_at ASC',
            'seguimiento' => 'COALESCE(q.last_contact_at, q.created_at) ASC',
            default  => 'q.created_at DESC',
        };
        $limit  = max(1, min(500, (int) ($f['limit'] ?? 30)));
        $offset = max(0, (int) ($f['offset'] ?? 0));
        $rows = DB::all(
            "SELECT q.*, u.name AS seller_name,
                    (SELECT COUNT(*) FROM quote_items qi WHERE qi.quote_id = q.id) AS item_count
             FROM quotes q LEFT JOIN users u ON u.id = q.user_id
             WHERE {$w} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return [$rows, $total];
    }

    /** Semáforo de seguimiento: verde <3 días, amarillo 3–7, rojo >7. */
    public static function trafficLight(array $q): string
    {
        if (in_array($q['status'], ['aprobada', 'perdida'], true)) {
            return 'cerrada';
        }
        $d = daysSince((string) ($q['last_contact_at'] ?: $q['created_at']));
        if ($d < 3) {
            return 'verde';
        }
        return $d <= 7 ? 'ambar' : 'rojo';
    }

    /** Crea la versión siguiente (v2, v3…) copiando líneas y condiciones. */
    public static function newVersion(int $quoteId): ?int
    {
        $q = self::find($quoteId);
        if (!$q) {
            return null;
        }
        $rootId = (int) ($q['parent_id'] ?: $q['id']);
        $maxV = (int) DB::value('SELECT MAX(version) FROM quotes WHERE id = ? OR parent_id = ?', [$rootId, $rootId], 1);

        DB::begin();
        try {
            DB::run('UPDATE quotes SET is_current = 0 WHERE id = ? OR parent_id = ?', [$rootId, $rootId]);
            $new = $q;
            unset($new['id'], $new['seller_name'], $new['seller_email'], $new['seller_phone'], $new['seller_position'], $new['seller_whatsapp'], $new['customer_name'], $new['customer_nit'], $new['price_list_id']);
            $new['parent_id']   = $rootId;
            $new['version']     = $maxV + 1;
            $new['is_current']  = 1;
            $new['status']      = 'elaboracion';
            $new['track_token'] = self::newToken();
            $new['pdf_path']    = null;
            $new['sent_at']     = null;
            $new['viewed_at']   = null;
            $new['approved_at'] = null;
            $new['lost_at']     = null;
            $new['created_at']  = nowSql();
            $new['updated_at']  = nowSql();
            $new['last_contact_at'] = nowSql();
            $new['number']      = preg_replace('/ v\d+$/', '', (string) $q['number']) . ' v' . ($maxV + 1);
            $newId = DB::insert('quotes', $new);

            foreach (self::items($quoteId) as $it) {
                unset($it['id']);
                $it['quote_id'] = $newId;
                DB::insert('quote_items', $it);
            }
            self::event($newId, 'sistema', 'Versión ' . ($maxV + 1) . ' creada a partir de ' . $q['number']);
            DB::commit();
            Audit::log('cotizacion.version', 'quote', $newId, ['origen' => $quoteId]);
            return $newId;
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    /** Copia completa como cotización nueva e independiente. */
    public static function duplicate(int $quoteId, ?int $userId = null): ?int
    {
        $q = self::find($quoteId);
        if (!$q) {
            return null;
        }
        $num = self::nextNumber();
        DB::begin();
        try {
            $new = $q;
            unset($new['id'], $new['seller_name'], $new['seller_email'], $new['seller_phone'], $new['seller_position'], $new['seller_whatsapp'], $new['customer_name'], $new['customer_nit'], $new['price_list_id']);
            $new['number']      = $num['number'];
            $new['folio_seq']   = $num['seq'];
            $new['folio_year']  = $num['year'];
            $new['version']     = 1;
            $new['parent_id']   = null;
            $new['is_current']  = 1;
            $new['status']      = 'elaboracion';
            $new['source']      = 'panel';
            $new['track_token'] = self::newToken();
            $new['pdf_path']    = null;
            $new['sent_at']     = null;
            $new['viewed_at']   = null;
            $new['approved_at'] = null;
            $new['lost_at']     = null;
            $new['lost_reason'] = null;
            $new['lost_detail'] = null;
            $new['won_amount']  = 0;
            $new['user_id']     = $userId ?: $q['user_id'];
            $new['created_at']  = nowSql();
            $new['updated_at']  = nowSql();
            $new['last_contact_at'] = nowSql();
            $newId = DB::insert('quotes', $new);
            foreach (self::items($quoteId) as $it) {
                unset($it['id']);
                $it['quote_id'] = $newId;
                DB::insert('quote_items', $it);
            }
            self::event($newId, 'sistema', 'Duplicada desde ' . $q['number']);
            DB::commit();
            Audit::log('cotizacion.duplicar', 'quote', $newId, ['origen' => $quoteId]);
            return $newId;
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    public static function versions(array $q): array
    {
        $rootId = (int) ($q['parent_id'] ?: $q['id']);
        return DB::all(
            'SELECT id, number, version, status, total, created_at, is_current FROM quotes
             WHERE id = ? OR parent_id = ? ORDER BY version',
            [$rootId, $rootId]
        );
    }

    public static function trackUrl(array $q): string
    {
        return absUrl('/c/' . $q['track_token']);
    }

    /** Mensaje de WhatsApp prellenado para el vendedor. */
    public static function whatsappLink(array $company, array $q): string
    {
        $phone = preg_replace('/\D+/', '', (string) ($q['contact_phone'] ?? '')) ?: '';
        $text  = "Estimado(a) {$q['contact_name']}, le comparto la cotización {$q['number']} de "
               . $company['name'] . ".\n\nTotal: " . money((float) $q['total'], (string) $q['currency_symbol'])
               . "\nSeguimiento y PDF: " . self::trackUrl($q)
               . "\n\nQuedo atento a sus comentarios.";
        $base = $phone !== '' ? "https://wa.me/{$phone}" : 'https://wa.me/';
        return $base . '?text=' . rawurlencode($text);
    }
}
