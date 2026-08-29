<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Customer
{
    public static function find(int $companyId, int $id): ?array
    {
        return DB::one('SELECT * FROM customers WHERE id = ? AND company_id = ? LIMIT 1', [$id, $companyId]);
    }

    public static function search(int $companyId, array $f = []): array
    {
        $where = ['c.company_id = ?'];
        $params = [$companyId];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
            $where[] = '(c.name LIKE ? OR c.legal_name LIKE ? OR c.nit LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($f['user_id'])) {
            $where[] = 'c.assigned_user_id = ?';
            $params[] = (int) $f['user_id'];
        }
        if (!empty($f['price_list_id'])) {
            $where[] = 'c.price_list_id = ?';
            $params[] = (int) $f['price_list_id'];
        }
        $w = implode(' AND ', $where);
        $total = (int) DB::value("SELECT COUNT(*) FROM customers c WHERE {$w}", $params, 0);
        $limit  = max(1, min(200, (int) ($f['limit'] ?? 25)));
        $offset = max(0, (int) ($f['offset'] ?? 0));
        $rows = DB::all(
            "SELECT c.*, u.name AS seller_name, pl.name AS price_list_name,
                    (SELECT COUNT(*) FROM quotes q WHERE q.customer_id = c.id AND q.company_id = c.company_id AND q.is_current = 1) AS quote_count,
                    (SELECT COALESCE(SUM(q.total),0) FROM quotes q WHERE q.customer_id = c.id AND q.company_id = c.company_id AND q.status = 'aprobada') AS won_total
             FROM customers c
             LEFT JOIN users u ON u.id = c.assigned_user_id
             LEFT JOIN price_lists pl ON pl.id = c.price_list_id AND pl.company_id = c.company_id
             WHERE {$w} ORDER BY c.name LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return [$rows, $total];
    }

    public static function contacts(int $companyId, int $customerId): array
    {
        return DB::all('SELECT * FROM customer_contacts WHERE customer_id = ? AND company_id = ? ORDER BY is_primary DESC, name', [$customerId, $companyId]);
    }

    public static function quotes(int $companyId, int $customerId): array
    {
        return DB::all(
            'SELECT q.*, u.name AS seller_name FROM quotes q
             LEFT JOIN users u ON u.id = q.user_id
             WHERE q.customer_id = ? AND q.company_id = ? AND q.is_current = 1
             ORDER BY q.created_at DESC LIMIT 100',
            [$customerId, $companyId]
        );
    }

    /** Busca por NIT o correo; si no existe lo crea. Usado por el cotizador público. */
    public static function findOrCreate(int $companyId, array $data, ?int $assignTo = null): int
    {
        $nit   = trim((string) ($data['nit'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $found = null;
        if ($nit !== '' && strtoupper($nit) !== 'C/F') {
            $found = DB::one('SELECT id FROM customers WHERE company_id = ? AND nit = ? LIMIT 1', [$companyId, $nit]);
        }
        if (!$found && $email !== '') {
            $found = DB::one('SELECT id FROM customers WHERE company_id = ? AND email = ? LIMIT 1', [$companyId, $email]);
        }
        if ($found) {
            return (int) $found['id'];
        }
        $default = DB::value('SELECT id FROM price_lists WHERE company_id = ? AND is_default = 1 LIMIT 1', [$companyId]);
        return DB::insert('customers', [
            'company_id'       => $companyId,
            'name'             => mb_substr(trim((string) ($data['company'] ?: $data['name'])), 0, 160),
            'legal_name'       => mb_substr(trim((string) ($data['company'] ?? '')), 0, 200) ?: null,
            'nit'              => $nit ?: null,
            'email'            => $email ?: null,
            'phone'            => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40) ?: null,
            'whatsapp'         => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 30) ?: null,
            'price_list_id'    => $default ? (int) $default : null,
            'assigned_user_id' => $assignTo,
            'created_at'       => nowSql(),
            'updated_at'       => nowSql(),
        ]);
    }
}
