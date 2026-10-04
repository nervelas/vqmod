<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\ClientService;

/** GET /api/v1/clients, /clients/{id}; POST /clients. */
final class ClientsController extends ApiController
{
    private const COLUMNS = 'c.*, (SELECT COUNT(*) FROM bookings b WHERE b.client_id = c.id) AS bookings_count,
        (SELECT MAX(b.starts_at) FROM bookings b WHERE b.client_id = c.id) AS last_booking_at';

    protected function doIndex(Request $req, array $p): Response
    {
        [$limit, $offset] = $this->paging($req);
        $where = ['c.anonymized_at IS NULL'];
        $params = [];
        $q = $this->strParam($req, 'q', 100);
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $email = strtolower($this->strParam($req, 'email', 190));
        if ($email !== '') {
            $where[] = 'c.email = ?';
            $params[] = $email;
        }
        $phone = $this->strParam($req, 'phone', 40);
        if ($phone !== '') {
            $norm = Str::phone($phone, (string) Settings::get('phone_cc', '502'));
            $where[] = 'c.phone = ?';
            $params[] = $norm ?? '';
        }
        $w = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM clients c WHERE ' . $w, $params);
        $rows = Db::all('SELECT ' . self::COLUMNS . ' FROM clients c WHERE ' . $w . ' ORDER BY c.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
        return $this->paged(array_map(fn (array $c): array => $this->present($c), $rows), $total, $limit, $offset);
    }

    protected function doShow(Request $req, array $p): Response
    {
        return $this->ok($this->find((int) $p['id']));
    }

    protected function doCreate(Request $req, array $p): Response
    {
        $body = $this->jsonBody($req);
        $name = $this->bodyStr($body, 'name', 160);
        $email = strtolower($this->bodyStr($body, 'email', 190));
        $phoneRaw = $this->bodyStr($body, 'phone', 40);
        $tz = $this->bodyStr($body, 'timezone', 64);
        if (mb_strlen($name) < 2) {
            throw new ApiError('Escribe el nombre de la persona (mínimo 2 letras).', 'validation', 422);
        }
        if ($email === '' && $phoneRaw === '') {
            throw new ApiError('Indica al menos un correo o un teléfono.', 'validation', 422);
        }
        if ($email !== '' && !Validator::email($email)) {
            throw new ApiError('El correo electrónico no es válido.', 'validation', 422);
        }
        $phone = $phoneRaw !== '' ? Str::phone($phoneRaw, (string) Settings::get('phone_cc', '502')) : null;
        if ($phoneRaw !== '' && $phone === null) {
            throw new ApiError('El teléfono no es válido: usa 8 dígitos (+502) o un número internacional completo.', 'validation', 422);
        }
        if ($tz !== '' && !Tz::valid($tz)) {
            throw new ApiError('La zona horaria no es válida. Usa un nombre como America/Guatemala.', 'validation', 422);
        }
        $existed = ($email !== '' && Db::val('SELECT id FROM clients WHERE email = ? AND anonymized_at IS NULL', [$email]) !== null)
            || ($phone !== null && Db::val('SELECT id FROM clients WHERE phone = ? AND anonymized_at IS NULL', [$phone]) !== null);
        $id = ClientService::upsert([
            'name' => $name, 'email' => $email, 'phone' => $phone ?? '', 'nit' => $this->bodyStr($body, 'nit', 30),
            'timezone' => $tz, 'source' => $this->bodyStr($body, 'source', 190) ?: 'API',
        ]);
        return $this->ok($this->find($id), ['created' => !$existed], $existed ? 200 : 201);
    }

    private function find(int $id): array
    {
        $c = Db::one('SELECT ' . self::COLUMNS . ' FROM clients c WHERE c.id = ? AND c.anonymized_at IS NULL', [$id]);
        if ($c === null) {
            throw new ApiError('No encontramos a ese cliente.', 'not_found', 404);
        }
        return $this->present($c);
    }

    private function present(array $c): array
    {
        return [
            'id' => (int) $c['id'],
            'name' => $c['name'],
            'email' => $c['email'],
            'phone' => $c['phone'],
            'nit' => $c['nit'],
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $c['tags'])))),
            'source' => $c['source'],
            'timezone' => $c['timezone'],
            'no_show_count' => (int) $c['noshow_count'],
            'blocked' => self::bool($c['blocked']),
            'bookings_count' => (int) $c['bookings_count'],
            'last_booking_at' => self::iso($c['last_booking_at']),
            'created_at' => self::iso($c['created_at']),
        ];
    }
}
