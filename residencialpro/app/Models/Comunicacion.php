<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Notificar;

/** Avisos, eventos, incidencias, votaciones y mensajería. */
final class Comunicacion
{
    // ---------------------------------------------------------------- AVISOS

    public static function avisos(array $filtros = [], int $limite = 50): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['vigentes'])) {
            $where[] = 'a.publicar_en <= NOW() AND (a.vence_en IS NULL OR a.vence_en >= NOW())';
        }
        if (!empty($filtros['casa'])) {
            $casa = Casa::porId((int) $filtros['casa']);
            if ($casa !== null) {
                $where[] = '(a.alcance = "todos"
                          OR (a.alcance = "fase"  AND a.destino_id = :fase)
                          OR (a.alcance = "calle" AND a.destino_id = :calle)
                          OR (a.alcance = "casa"  AND a.destino_id = :casa))';
                $params['fase']  = (int) $casa['fase_id'];
                $params['calle'] = (int) ($casa['calle_id'] ?? 0);
                $params['casa']  = (int) $casa['id'];
            }
        }
        return DB::todos(
            'SELECT a.*, u.nombre AS autor,
                    (SELECT COUNT(*) FROM avisos_lecturas l WHERE l.aviso_id = a.id) AS lecturas
             FROM avisos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(a.prioridad,"urgente","importante","normal"), a.publicar_en DESC
             LIMIT ' . (int) $limite,
            $params
        );
    }

    public static function aviso(int $id): ?array
    {
        return DB::uno(
            'SELECT a.*, u.nombre AS autor FROM avisos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function guardarAviso(array $d, int $id = 0): int
    {
        $datos = [
            'titulo'      => mb_substr((string) $d['titulo'], 0, 190),
            'cuerpo'      => (string) $d['cuerpo'],
            'alcance'     => (string) $d['alcance'],
            'destino_id'  => !empty($d['destino_id']) ? (int) $d['destino_id'] : null,
            'prioridad'   => (string) ($d['prioridad'] ?? 'normal'),
            'publicar_en' => (string) ($d['publicar_en'] ?? date('Y-m-d H:i:s')),
            'vence_en'    => !empty($d['vence_en']) ? (string) $d['vence_en'] : null,
            'confirmar'   => !empty($d['confirmar']) ? 1 : 0,
        ];
        if (!empty($d['imagen']))  { $datos['imagen']  = $d['imagen']; }
        if (!empty($d['archivo'])) { $datos['archivo'] = $d['archivo']; }

        if ($id > 0) {
            DB::actualizar('avisos', $datos, 'id = :id', ['id' => $id]);
            Auditoria::registrar('editar_aviso', 'avisos', $id, $datos['titulo']);
            return $id;
        }
        $datos['usuario_id'] = Auth::id() ?: null;
        $nuevo = DB::insertar('avisos', $datos);
        Auditoria::registrar('crear_aviso', 'avisos', $nuevo, $datos['titulo']);
        self::notificarAviso($nuevo);
        return $nuevo;
    }

    private static function notificarAviso(int $avisoId): void
    {
        $a = self::aviso($avisoId);
        if ($a === null || strtotime((string) $a['publicar_en']) > time()) {
            return;
        }
        $sql = 'SELECT DISTINCT r.usuario_id FROM residentes r
                INNER JOIN casas c ON c.id = r.casa_id
                WHERE r.activo = 1 AND r.usuario_id IS NOT NULL';
        $params = [];
        if ($a['alcance'] === 'fase') {
            $sql .= ' AND c.fase_id = :d';
            $params['d'] = (int) $a['destino_id'];
        } elseif ($a['alcance'] === 'calle') {
            $sql .= ' AND c.calle_id = :d';
            $params['d'] = (int) $a['destino_id'];
        } elseif ($a['alcance'] === 'casa') {
            $sql .= ' AND c.id = :d';
            $params['d'] = (int) $a['destino_id'];
        }
        foreach (DB::todos($sql, $params) as $f) {
            Notificar::usuario((int) $f['usuario_id'], $a['titulo'], recortar((string) $a['cuerpo'], 110), '/portal/avisos/' . $avisoId, 'megafono');
        }
    }

    public static function marcarLeido(int $avisoId, int $usuarioId, ?int $casaId = null): void
    {
        try {
            DB::q(
                'INSERT IGNORE INTO avisos_lecturas (aviso_id, usuario_id, casa_id) VALUES (:a, :u, :c)',
                ['a' => $avisoId, 'u' => $usuarioId, 'c' => $casaId]
            );
        } catch (\Throwable) {
        }
    }

    public static function avisosNoLeidos(int $usuarioId, int $casaId): int
    {
        $avisos = self::avisos(['vigentes' => true, 'casa' => $casaId], 100);
        $leidos = DB::todos('SELECT aviso_id FROM avisos_lecturas WHERE usuario_id = :u', ['u' => $usuarioId]);
        $ids = array_map(static fn($f) => (int) $f['aviso_id'], $leidos);
        $n = 0;
        foreach ($avisos as $a) {
            if (!in_array((int) $a['id'], $ids, true)) {
                $n++;
            }
        }
        return $n;
    }

    // --------------------------------------------------------------- EVENTOS

    public static function eventos(bool $soloFuturos = true, int $limite = 40): array
    {
        return DB::todos(
            'SELECT * FROM eventos ' . ($soloFuturos ? 'WHERE inicio >= DATE_SUB(NOW(), INTERVAL 1 DAY) ' : '')
            . 'ORDER BY inicio ASC LIMIT ' . (int) $limite
        );
    }

    // ----------------------------------------------------------- INCIDENCIAS

    public static function incidencias(array $filtros = [], int $limite = 100): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['estado'])) {
            $where[] = 'i.estado = :e';
            $params['e'] = (string) $filtros['estado'];
        }
        if (!empty($filtros['casa'])) {
            $where[] = 'i.casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        if (!empty($filtros['abiertas'])) {
            $where[] = 'i.estado IN ("recibida","proceso")';
        }
        return DB::todos(
            'SELECT i.*, c.codigo AS casa, u.nombre AS reporta
             FROM incidencias i
             LEFT JOIN casas c ON c.id = i.casa_id
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(i.estado,"recibida","proceso","resuelta","cerrada"),
                      FIELD(i.prioridad,"alta","media","baja"), i.creado_en DESC
             LIMIT ' . (int) $limite,
            $params
        );
    }

    public static function incidencia(int $id): ?array
    {
        return DB::uno(
            'SELECT i.*, c.codigo AS casa, u.nombre AS reporta FROM incidencias i
             LEFT JOIN casas c ON c.id = i.casa_id
             LEFT JOIN usuarios u ON u.id = i.usuario_id WHERE i.id = :id',
            ['id' => $id]
        );
    }

    public static function abiertas(): int
    {
        return (int) DB::valor('SELECT COUNT(*) FROM incidencias WHERE estado IN ("recibida","proceso")', [], 0);
    }

    public static function crearIncidencia(array $d): int
    {
        $id = DB::insertar('incidencias', [
            'casa_id'     => !empty($d['casa_id']) ? (int) $d['casa_id'] : null,
            'usuario_id'  => Auth::id() ?: null,
            'categoria'   => (string) ($d['categoria'] ?? 'general'),
            'titulo'      => mb_substr((string) $d['titulo'], 0, 190),
            'descripcion' => (string) $d['descripcion'],
            'ubicacion'   => $d['ubicacion'] ?? null,
            'foto'        => $d['foto'] ?? null,
            'prioridad'   => (string) ($d['prioridad'] ?? 'media'),
        ]);
        Auditoria::registrar('crear_incidencia', 'incidencias', $id, (string) $d['titulo']);
        Notificar::rol(['admin'], 'Nueva incidencia reportada', (string) $d['titulo'], '/admin/incidencias/' . $id, 'alerta');
        return $id;
    }

    public static function actualizarIncidencia(int $id, string $estado, string $comentario): bool
    {
        $i = self::incidencia($id);
        if ($i === null) {
            return false;
        }
        $datos = ['estado' => $estado];
        if (in_array($estado, ['resuelta', 'cerrada'], true)) {
            $datos['resuelto_en'] = date('Y-m-d H:i:s');
            $datos['resolucion']  = $comentario;
        }
        DB::actualizar('incidencias', $datos, 'id = :id', ['id' => $id]);
        DB::insertar('incidencia_seguimiento', [
            'incidencia_id' => $id,
            'usuario_id'    => Auth::id() ?: null,
            'texto'         => $comentario !== '' ? $comentario : 'Actualización de estado.',
            'estado'        => $estado,
        ]);
        if (!empty($i['casa_id'])) {
            Notificar::casa((int) $i['casa_id'], 'Su reporte fue actualizado', $i['titulo'] . ' — ' . $estado, '/portal/incidencias');
        }
        Auditoria::registrar('actualizar_incidencia', 'incidencias', $id, $estado);
        return true;
    }

    public static function seguimiento(int $incidenciaId): array
    {
        return DB::todos(
            'SELECT s.*, u.nombre AS autor FROM incidencia_seguimiento s
             LEFT JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.incidencia_id = :i ORDER BY s.id ASC',
            ['i' => $incidenciaId]
        );
    }

    // ------------------------------------------------------------ VOTACIONES

    public static function votaciones(array $filtros = []): array
    {
        $where = '1=1';
        if (!empty($filtros['abiertas'])) {
            $where = 'estado = "abierta" AND inicio <= NOW() AND fin >= NOW()';
        }
        return DB::todos('SELECT * FROM votaciones WHERE ' . $where . ' ORDER BY inicio DESC LIMIT 60');
    }

    public static function votacion(int $id): ?array
    {
        return DB::uno('SELECT * FROM votaciones WHERE id = :id', ['id' => $id]);
    }

    public static function opciones(int $votacionId): array
    {
        return DB::todos('SELECT * FROM votacion_opciones WHERE votacion_id = :v ORDER BY orden, id', ['v' => $votacionId]);
    }

    /** Resultados con peso por casa o por coeficiente. */
    public static function resultados(int $votacionId): array
    {
        $v = self::votacion($votacionId);
        if ($v === null) {
            return ['opciones' => [], 'total' => 0.0, 'votos' => 0, 'quorum' => 0.0, 'universo' => 0.0];
        }
        $opciones = self::opciones($votacionId);
        $filas = DB::todos(
            'SELECT opcion_id, COUNT(*) AS n, COALESCE(SUM(peso),0) AS peso
             FROM votos WHERE votacion_id = :v GROUP BY opcion_id',
            ['v' => $votacionId]
        );
        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['opcion_id']] = ['n' => (int) $f['n'], 'peso' => (float) $f['peso']];
        }
        $universo = $v['modo'] === 'coeficiente'
            ? (float) DB::valor('SELECT COALESCE(SUM(coeficiente),0) FROM casas', [], 0)
            : (float) DB::valor('SELECT COUNT(*) FROM casas', [], 0);
        $total = 0.0;
        $votos = 0;
        $res = [];
        foreach ($opciones as $o) {
            $d = $mapa[(int) $o['id']] ?? ['n' => 0, 'peso' => 0.0];
            $total += $d['peso'];
            $votos += $d['n'];
            $res[] = ['id' => (int) $o['id'], 'texto' => $o['texto'], 'votos' => $d['n'], 'peso' => round($d['peso'], 4)];
        }
        foreach ($res as &$r) {
            $r['porcentaje'] = $total > 0 ? round($r['peso'] * 100 / $total, 2) : 0.0;
        }
        return [
            'opciones' => $res,
            'total'    => round($total, 4),
            'votos'    => $votos,
            'universo' => round($universo, 4),
            'quorum'   => $universo > 0 ? round($total * 100 / $universo, 2) : 0.0,
        ];
    }

    public static function yaVoto(int $votacionId, int $casaId): bool
    {
        return DB::valor('SELECT id FROM votos WHERE votacion_id = :v AND casa_id = :c', ['v' => $votacionId, 'c' => $casaId]) !== null;
    }

    public static function votar(int $votacionId, int $opcionId, int $casaId): array
    {
        $v = self::votacion($votacionId);
        if ($v === null || $v['estado'] !== 'abierta') {
            return ['ok' => false, 'mensaje' => 'La votación no está abierta.'];
        }
        if (strtotime((string) $v['inicio']) > time() || strtotime((string) $v['fin']) < time()) {
            return ['ok' => false, 'mensaje' => 'La votación está fuera del período habilitado.'];
        }
        if (self::yaVoto($votacionId, $casaId)) {
            return ['ok' => false, 'mensaje' => 'Esta vivienda ya emitió su voto.'];
        }
        $op = DB::uno('SELECT * FROM votacion_opciones WHERE id = :o AND votacion_id = :v', ['o' => $opcionId, 'v' => $votacionId]);
        if ($op === null) {
            return ['ok' => false, 'mensaje' => 'La opción seleccionada no es válida.'];
        }
        $casa = Casa::porId($casaId);
        $peso = $v['modo'] === 'coeficiente' ? (float) ($casa['coeficiente'] ?? 1) : 1.0;
        DB::insertar('votos', [
            'votacion_id' => $votacionId,
            'opcion_id'   => $opcionId,
            'casa_id'     => $casaId,
            'usuario_id'  => Auth::id() ?: null,
            'peso'        => $peso,
        ]);
        Auditoria::registrar('votar', 'votaciones', $votacionId, 'Casa ' . $casaId);
        return ['ok' => true, 'mensaje' => 'Su voto quedó registrado. Gracias por participar.'];
    }

    // ------------------------------------------------------------- MENSAJES

    public static function mensajes(int $usuarioId, string $rol, int $limite = 60): array
    {
        if (in_array($rol, ['admin', 'junta'], true)) {
            return DB::todos(
                'SELECT m.*, u.nombre AS remitente, c.codigo AS casa FROM mensajes m
                 LEFT JOIN usuarios u ON u.id = m.de_usuario
                 LEFT JOIN casas c ON c.id = m.casa_id
                 ORDER BY m.id DESC LIMIT ' . (int) $limite
            );
        }
        return DB::todos(
            'SELECT m.*, u.nombre AS remitente, c.codigo AS casa FROM mensajes m
             LEFT JOIN usuarios u ON u.id = m.de_usuario
             LEFT JOIN casas c ON c.id = m.casa_id
             WHERE m.de_usuario = :u OR m.para_usuario = :u
             ORDER BY m.id DESC LIMIT ' . (int) $limite,
            ['u' => $usuarioId]
        );
    }

    public static function enviarMensaje(array $d): int
    {
        $id = DB::insertar('mensajes', [
            'hilo_id'      => !empty($d['hilo_id']) ? (int) $d['hilo_id'] : null,
            'casa_id'      => !empty($d['casa_id']) ? (int) $d['casa_id'] : null,
            'de_usuario'   => Auth::id(),
            'para_rol'     => $d['para_rol'] ?? null,
            'para_usuario' => !empty($d['para_usuario']) ? (int) $d['para_usuario'] : null,
            'asunto'       => mb_substr((string) ($d['asunto'] ?? ''), 0, 190),
            'cuerpo'       => (string) $d['cuerpo'],
        ]);
        if (!empty($d['para_usuario'])) {
            Notificar::usuario((int) $d['para_usuario'], 'Nuevo mensaje de la administración', recortar((string) $d['cuerpo'], 100), '/portal/mensajes', 'chat');
        } else {
            Notificar::rol(['admin'], 'Mensaje de un residente', recortar((string) $d['cuerpo'], 100), '/admin/mensajes', 'chat');
        }
        return $id;
    }

    public static function contactosEmergencia(): array
    {
        return DB::todos('SELECT * FROM contactos_emergencia ORDER BY orden, nombre');
    }
}
