<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Casa;
use App\Models\Cuota;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Models\Visita;

final class ApiControlador extends Controlador
{
    public function tablero(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $this->json(['ok' => true, 'kpi' => Reporte::tablero()]);
    }

    public function notificaciones(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false, 'error' => 'Sesión no iniciada.'], 401);
        }
        $items = [];
        foreach (Usuario::notificaciones(Auth::id(), 15) as $n) {
            $items[] = [
                'id'     => (int) $n['id'],
                'titulo' => (string) $n['titulo'],
                'cuerpo' => (string) ($n['cuerpo'] ?? ''),
                'url'    => !empty($n['url']) ? url((string) $n['url']) : '',
                'hace'   => hace((string) $n['creado_en']),
                'leido'  => $n['leido_en'] !== null,
            ];
        }
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function marcarLeidas(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false], 401);
        }
        $this->verificarCsrf();
        Usuario::marcarNotificacionesLeidas(Auth::id());
        $this->json(['ok' => true]);
    }

    // ----------------------------------------------------------------- PUSH

    public function clavePush(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false], 401);
        }
        $clave = Ajustes::get('vapid_publica', '');
        $this->json(['ok' => $clave !== '', 'clave' => $clave]);
    }

    public function suscribirPush(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false, 'error' => 'Sesión no iniciada.'], 401);
        }
        $this->verificarCsrf();
        $d = Peticion::json();
        $endpoint = (string) ($d['endpoint'] ?? '');
        $p256dh   = (string) ($d['p256dh'] ?? '');
        $auth     = (string) ($d['auth'] ?? '');
        if (!filter_var($endpoint, FILTER_VALIDATE_URL) || $p256dh === '' || $auth === '') {
            $this->json(['ok' => false, 'error' => 'Datos de suscripción incompletos.'], 400);
        }
        DB::q(
            'INSERT INTO push_subs (usuario_id, endpoint, p256dh, auth_key) VALUES (:u, :e, :p, :a)
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), p256dh = VALUES(p256dh), auth_key = VALUES(auth_key)',
            ['u' => Auth::id(), 'e' => mb_substr($endpoint, 0, 500), 'p' => mb_substr($p256dh, 0, 190), 'a' => mb_substr($auth, 0, 120)]
        );
        $this->json(['ok' => true]);
    }

    public function cancelarPush(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false], 401);
        }
        $this->verificarCsrf();
        $d = Peticion::json();
        DB::eliminar('push_subs', 'usuario_id = :u AND endpoint = :e',
            ['u' => Auth::id(), 'e' => (string) ($d['endpoint'] ?? '')]);
        $this->json(['ok' => true]);
    }

    // --------------------------------------------------------------- GARITA

    public function validarCodigo(): void
    {
        $this->exigirRol('garita', 'admin');
        $this->verificarCsrf();
        $d = Peticion::json();
        $codigo = trim((string) ($d['codigo'] ?? Peticion::texto('codigo')));
        if ($codigo === '') {
            $this->json(['ok' => false, 'mensaje' => 'No se recibió ningún código.'], 400);
        }
        $r = Visita::validar($codigo);
        $salida = [
            'ok'      => $r['ok'],
            'mensaje' => $r['mensaje'],
        ];
        if ($r['prereg'] !== null) {
            $p = $r['prereg'];
            $salida['prereg'] = [
                'id'        => (int) $p['id'],
                'visitante' => (string) $p['visitante'],
                'placa'     => (string) ($p['placa'] ?? ''),
                'motivo'    => (string) ($p['motivo'] ?? ''),
                'vigencia'  => fechahora((string) $p['valido_hasta']),
                'recurrente' => (int) $p['recurrente'] === 1,
            ];
        }
        if ($r['casa'] !== null) {
            $casa = $r['casa'];
            $salida['casa'] = [
                'id'          => (int) $casa['id'],
                'codigo'      => (string) $casa['codigo'],
                'fase'        => (string) ($casa['fase'] ?? ''),
                'restringida' => (int) $casa['restringida'] === 1,
                'residente'   => (string) (Casa::propietario((int) $casa['id'])['nombre'] ?? ''),
            ];
            if ((int) $casa['restringida'] === 1 && Ajustes::esVerdadero('mostrar_restriccion_garita', true)) {
                $salida['aviso'] = 'Esta vivienda tiene restricción de servicios por mora. Consulte con administración.';
            }
        }
        $this->json($salida);
    }

    public function buscarPlaca(): void
    {
        $this->exigirRol('garita', 'admin', 'junta');
        $placa = Peticion::texto('placa');
        $v = Visita::buscarPlaca($placa);
        if ($v === null) {
            $historial = Visita::historialVisitante(Peticion::texto('dpi'));
            $this->json(['ok' => $historial !== null, 'datos' => $historial]);
        }
        $this->json(['ok' => true, 'datos' => [
            'casa_id' => (int) $v['casa_id'],
            'casa'    => (string) $v['casa'],
            'marca'   => (string) ($v['marca'] ?? ''),
            'linea'   => (string) ($v['linea'] ?? ''),
            'color'   => (string) ($v['color'] ?? ''),
        ]]);
    }

    /** Recibe los ingresos registrados sin conexión. */
    public function sincronizarGarita(): void
    {
        $this->exigirRol('garita', 'admin');
        $this->verificarCsrf();
        $d = Peticion::json();
        $registros = $d['registros'] ?? [];
        if (!is_array($registros)) {
            $this->json(['ok' => false, 'error' => 'Formato no válido.'], 400);
        }
        $guardados = 0;
        foreach (array_slice($registros, 0, 200) as $r) {
            if (!is_array($r) || empty($r['visitante'])) {
                continue;
            }
            $local = (string) ($r['_local'] ?? '');
            if ($local !== '') {
                $ya = DB::valor('SELECT id FROM visitas WHERE notas = :n LIMIT 1', ['n' => 'sync:' . $local]);
                if ($ya) {
                    continue;
                }
            }
            try {
                DB::insertar('visitas', [
                    'casa_id'    => !empty($r['casa_id']) ? (int) $r['casa_id'] : null,
                    'tipo'       => in_array((string) ($r['tipo'] ?? 'visita'),
                                    ['visita', 'proveedor', 'delivery', 'servicio', 'empleado', 'mudanza'], true)
                                    ? (string) $r['tipo'] : 'visita',
                    'visitante'  => mb_substr((string) $r['visitante'], 0, 140),
                    'dpi'        => !empty($r['dpi']) ? mb_substr((string) $r['dpi'], 0, 30) : null,
                    'placa'      => !empty($r['placa']) ? mb_strtoupper(mb_substr((string) $r['placa'], 0, 20)) : null,
                    'vehiculo'   => !empty($r['vehiculo']) ? mb_substr((string) $r['vehiculo'], 0, 90) : null,
                    'personas'   => max(1, (int) ($r['personas'] ?? 1)),
                    'motivo'     => !empty($r['motivo']) ? mb_substr((string) $r['motivo'], 0, 190) : null,
                    'entrada'    => !empty($r['entrada']) && strtotime((string) $r['entrada'])
                                    ? date('Y-m-d H:i:s', (int) strtotime((string) $r['entrada']))
                                    : date('Y-m-d H:i:s'),
                    'guardia_in' => Auth::id() ?: null,
                    'autorizado' => 1,
                    'uuid'       => bin2hex(random_bytes(16)),
                    'notas'      => $local !== '' ? 'sync:' . $local : null,
                ]);
                $guardados++;
            } catch (\Throwable $e) {
                \App\Core\Log::error('Sincronizar garita: ' . $e->getMessage());
            }
        }
        if ($guardados > 0) {
            Auditoria::registrar('sincronizar_garita', 'visitas', null, $guardados . ' registro(s)');
        }
        $this->json(['ok' => true, 'guardados' => $guardados]);
    }

    // ----------------------------------------------------------------- VARIOS

    public function casas(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad', 'garita');
        $q = Peticion::texto('q');
        $filas = DB::todos(
            'SELECT c.id, c.codigo, f.nombre AS fase,
                    (SELECT r.nombre FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                     ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS residente
             FROM casas c LEFT JOIN fases f ON f.id = c.fase_id
             WHERE c.codigo LIKE :q
             ORDER BY LENGTH(c.codigo), c.codigo LIMIT 30',
            ['q' => '%' . $q . '%']
        );
        $this->json(['ok' => true, 'casas' => $filas]);
    }

    public function cargosCasa(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        Cuota::recalcularMora($id);
        $cargos = [];
        foreach (Cuota::cargos($id, 'pendientes') as $c) {
            $cargos[] = [
                'id'          => (int) $c['id'],
                'descripcion' => (string) $c['descripcion'],
                'vence'       => fecha((string) $c['fecha_vence']),
                'saldo'       => Cuota::saldoCargo($c),
            ];
        }
        $this->json(['ok' => true, 'cargos' => $cargos, 'saldo' => Casa::saldo($id)]);
    }

    /** Guarda la preferencia de tema o modo oscuro del usuario. */
    public function tema(): void
    {
        if (Auth::invitado()) {
            $this->json(['ok' => false], 401);
        }
        $this->verificarCsrf();
        $d = Peticion::json();
        $datos = [];
        if (isset($d['tema'])) {
            $temas = ['verde-oro', 'negro-oro', 'azul-marino', 'grafito', 'borgona', 'azul-real', 'terracota', 'purpura'];
            if (in_array((string) $d['tema'], $temas, true)) {
                $datos['tema'] = (string) $d['tema'];
                $_SESSION['usuario']['tema'] = $datos['tema'];
            }
        }
        if (isset($d['modo_oscuro'])) {
            $datos['modo_oscuro'] = (int) $d['modo_oscuro'] === 1 ? 1 : 0;
            $_SESSION['usuario']['modo_oscuro'] = $datos['modo_oscuro'];
        }
        if ($datos !== []) {
            DB::actualizar('usuarios', $datos, 'id = :id', ['id' => Auth::id()]);
        }
        $this->json(['ok' => true]);
    }
}
