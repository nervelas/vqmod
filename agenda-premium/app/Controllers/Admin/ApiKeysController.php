<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ApiAuth;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Claves de API: la clave en claro se muestra una sola vez. */
final class ApiKeysController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $newKey = Session::get('a3_new_key');
        Session::forget('a3_new_key');
        $keys = Db::all('SELECT k.*, u.name AS user_name FROM api_keys k LEFT JOIN users u ON u.id = k.created_by ORDER BY (k.revoked_at IS NULL) DESC, k.id DESC');
        return $this->page('admin/api/index', ['keys' => $keys, 'newKey' => is_array($newKey) ? $newKey : null], '/admin/api', 'API y claves');
    }

    public function create(Request $req, array $p): Response
    {
        $name = $req->str('name', 120);
        $scope = $req->str('scope', 5) === 'write' ? 'write' : 'read';
        if ($name === '') {
            return $this->fail($req, 'Ponle un nombre a la clave, por ejemplo el nombre de la integración.', '/admin/api');
        }
        $issued = ApiAuth::issue($name, $scope, $this->userId());
        $this->audit('apikey.create', 'api_key', $issued['id'], $name . ' (' . $scope . ')');
        Session::set('a3_new_key', ['key' => $issued['key'], 'name' => $name, 'scope' => $scope]);
        return $this->redirect('/admin/api');
    }

    public function revoke(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM api_keys WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        ApiAuth::revoke($id);
        $this->audit('apikey.revoke', 'api_key', $id);
        $this->flash('success', 'La clave quedó revocada. Cualquier integración que la use dejará de funcionar.');
        return $this->redirect('/admin/api');
    }
}
