<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Super;

use MenuGold\Core\App;
use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Mailer;
use MenuGold\Core\Request;
use MenuGold\Core\Security;
use MenuGold\Core\Session;
use MenuGold\Core\Validator;
use MenuGold\Models\Plan;
use MenuGold\Models\Restaurant;
use MenuGold\Models\User;

/**
 * Alta, edicion y control de los restaurantes de la plataforma.
 */
class Restaurantes extends Panel
{
    public function index(): void
    {
        $q = Request::str('q', '', 60);
        $estado = Request::enum('estado', ['activo', 'suspendido', 'prueba'], '');
        $w = '1=1';
        $p = [];
        if ($q !== '') { $w .= ' AND (r.nombre LIKE :q OR r.slug LIKE :q2 OR r.dominio LIKE :q3)';
            $p['q'] = "%{$q}%"; $p['q2'] = "%{$q}%"; $p['q3'] = "%{$q}%"; }
        if ($estado !== '') { $w .= ' AND r.estado = :e'; $p['e'] = $estado; }

        $this->super('super/restaurantes', [
            'restaurantes' => DB::all(
                "SELECT r.*, p.nombre AS plan,
                        (SELECT COUNT(*) FROM products x WHERE x.restaurant_id = r.id) AS productos,
                        (SELECT COUNT(*) FROM tables t WHERE t.restaurant_id = r.id) AS mesas,
                        (SELECT COUNT(*) FROM users u WHERE u.restaurant_id = r.id) AS usuarios
                 FROM restaurants r LEFT JOIN plans p ON p.id = r.plan_id
                 WHERE {$w} ORDER BY r.creado DESC", $p
            ),
            'q' => $q, 'estado' => $estado,
            'planes' => (new Plan())->all('orden ASC'),
        ]);
    }

    public function form(array $par = []): void
    {
        $id = (int)($par['id'] ?? 0);
        $r = null;
        $dueno = null;
        if ($id > 0) {
            $r = (new Restaurant())->find($id);
            if (!$r) throw HttpException::notFound('Restaurante no encontrado.');
            $dueno = DB::one("SELECT * FROM users WHERE restaurant_id=:r AND rol='dueno' ORDER BY id ASC LIMIT 1", ['r' => $id]);
        }
        $this->super('super/restaurante-form', [
            'rest'   => $r,
            'dueno'  => $dueno,
            'planes' => (new Plan())->all('orden ASC'),
        ]);
    }

    public function guardar(): void
    {
        $id = Request::int('id');
        $m = new Restaurant();

        $datos = [
            'nombre'   => Request::str('nombre', '', 120),
            'plan_id'  => Request::int('plan_id') ?: null,
            'estado'   => Request::enum('estado', ['activo', 'suspendido', 'prueba'], 'prueba'),
            'vence_el' => Request::date('vence_el') ?: null,
            'dominio'  => $this->dominio(Request::str('dominio', '', 190)),
            'email'    => Request::email('email'),
            'telefono' => Request::str('telefono', '', 30),
            'whatsapp' => preg_replace('/\D/', '', Request::str('whatsapp', '', 30)) ?? '',
            'demo'     => Request::bool('demo') ? 1 : 0,
            'actualizado' => date('Y-m-d H:i:s'),
        ];
        $slug = Request::str('slug', '', 60);

        $v = Validator::make($datos + ['slug' => $slug])
            ->requerido('nombre', 'El nombre del restaurante')
            ->min('nombre', 3, 'El nombre')
            ->slug('slug');
        if ($v->falla()) {
            flash('error', $v->primerError());
            redirect($id > 0 ? 'super/restaurantes/' . $id : 'super/restaurantes/nuevo');
        }
        if ($datos['dominio'] !== null && DB::int('SELECT COUNT(*) FROM restaurants WHERE dominio=:d AND id<>:i',
            ['d' => $datos['dominio'], 'i' => $id]) > 0) {
            flash('error', 'Ese dominio ya está asignado a otro restaurante.');
            redirect($id > 0 ? 'super/restaurantes/' . $id : 'super/restaurantes/nuevo');
        }

        if ($id > 0) {
            $antes = $m->find($id);
            if (!$antes) throw HttpException::notFound();
            $datos['slug'] = $slug !== '' ? $m->slugUnico($slug, $id) : (string)$antes['slug'];
            $m->updateById($id, $datos);
            Audit::diff('plataforma.restaurante', 'restaurants', $id, $antes, $datos, $id);
            flash('exito', 'Restaurante actualizado.');
            redirect('super/restaurantes/' . $id);
        }

        // --- Alta completa ---
        $datos['slug'] = $m->slugUnico($slug !== '' ? $slug : $datos['nombre']);
        $datos['modos_pedido'] = 'consulta,mesa';
        $datos['metodos_pago'] = 'efectivo,tarjeta';
        $datos['creado'] = date('Y-m-d H:i:s');
        if ($datos['vence_el'] === null) $datos['vence_el'] = date('Y-m-d', strtotime('+30 days'));
        $rid = $m->create($datos);
        $m->horarioPorDefecto($rid);

        // Usuario dueño
        $nombreDueno = Request::str('dueno_nombre', '', 120) ?: 'Dueño';
        $emailDueno  = Request::email('dueno_email');
        $claveDueno  = (string)Request::input('dueno_password', '');
        if ($claveDueno === '') $claveDueno = 'Menu' . random_int(1000, 9999) . '!';

        $um = new User();
        $usuario = $um->usuarioUnico($nombreDueno);
        if ($emailDueno !== '' && !$um->disponible('email', $emailDueno)) {
            flash('aviso', 'Ese correo ya existía: el restaurante se creó sin usuario dueño nuevo.');
            $emailDueno = '';
        }
        if ($emailDueno !== '' || $usuario !== '') {
            DB::insert('users', [
                'restaurant_id' => $rid,
                'nombre'        => $nombreDueno,
                'email'         => $emailDueno ?: null,
                'usuario'       => $usuario,
                'password_hash' => Security::hashPassword($claveDueno),
                'rol'           => 'dueno',
                'activo'        => 1,
                'onboarding'    => 0,
                'creado'        => date('Y-m-d H:i:s'),
            ]);
        }

        // Categoría inicial para que el panel no se vea vacío
        DB::insert('categories', [
            'restaurant_id' => $rid, 'nombre' => 'Nuestra carta',
            'orden' => 0, 'activo' => 1, 'creado' => date('Y-m-d H:i:s'),
        ]);

        Audit::log('plataforma.alta', 'restaurants', $rid, null, ['nombre' => $datos['nombre'], 'slug' => $datos['slug']], $rid);

        if ($emailDueno !== '') {
            $cuerpo = '<p>¡Bienvenido a bordo!</p>'
                . '<p>Ya está lista la cuenta de <strong>' . e($datos['nombre']) . '</strong>.</p>'
                . '<ul style="line-height:1.9">'
                . '<li>Panel: ' . e(App::url('ingresar')) . '</li>'
                . '<li>Usuario: <strong>' . e($emailDueno) . '</strong></li>'
                . '<li>Contraseña: <strong>' . e($claveDueno) . '</strong></li>'
                . '<li>Tu menú: ' . e(App::url('r/' . $datos['slug'])) . '</li>'
                . '</ul>'
                . Mailer::boton('Entrar a mi panel', App::url('ingresar'))
                . '<p style="font-size:13px;color:#8a8578">Cambia tu contraseña la primera vez que entres.</p>';
            Mailer::send($emailDueno, 'Tu menú digital ya está listo', $cuerpo, null, $nombreDueno);
        }

        flash('exito', 'Restaurante creado. Contraseña del dueño: ' . $claveDueno);
        redirect('super/restaurantes/' . $rid);
    }

    public function estado(): void
    {
        $id = Request::int('id');
        $estado = Request::enum('estado', ['activo', 'suspendido', 'prueba'], 'activo');
        $m = new Restaurant();
        $r = $m->find($id);
        if (!$r) $this->fail('Restaurante no encontrado.', 404);
        $m->updateById($id, ['estado' => $estado, 'actualizado' => date('Y-m-d H:i:s')]);
        Audit::log('plataforma.estado', 'restaurants', $id, ['estado' => $r['estado']], ['estado' => $estado], $id);
        $this->ok(['estado' => $estado], 'Estado actualizado a "' . $estado . '"');
    }

    public function borrar(): void
    {
        $id = Request::int('id');
        $confirmar = Request::str('confirmar', '', 60);
        $m = new Restaurant();
        $r = $m->find($id);
        if (!$r) $this->fail('Restaurante no encontrado.', 404);
        if ($confirmar !== (string)$r['slug']) {
            $this->fail('Para confirmar, escribe exactamente: ' . $r['slug']);
        }
        $m->deleteById($id);   // en cascada borra su menú, pedidos y usuarios
        Audit::log('plataforma.baja', 'restaurants', $id, ['nombre' => $r['nombre'], 'slug' => $r['slug']]);
        $this->ok([], 'Restaurante eliminado con todos sus datos');
    }

    /** Entra al panel de un restaurante como superadmin. */
    public function entrar(array $par = []): void
    {
        $id = (int)($par['id'] ?? 0);
        $r = (new Restaurant())->find($id);
        if (!$r) throw HttpException::notFound('Restaurante no encontrado.');
        Session::set('super_rest', $id);
        Audit::log('plataforma.entrar', 'restaurants', $id, null, null, $id);
        flash('info', 'Estás viendo el panel de ' . $r['nombre'] . ' como administrador de plataforma.');
        redirect('panel');
    }

    public function salirRestaurante(): void
    {
        Session::forget('super_rest');
        redirect('super/restaurantes');
    }

    private function dominio(string $v): ?string
    {
        $v = mb_strtolower(trim($v));
        $v = preg_replace('~^https?://~', '', $v) ?? '';
        $v = rtrim(explode('/', $v)[0], '.');
        if ($v === '') return null;
        return preg_match('/^[a-z0-9]([a-z0-9.-]{1,180}[a-z0-9])?$/', $v) ? $v : null;
    }
}
