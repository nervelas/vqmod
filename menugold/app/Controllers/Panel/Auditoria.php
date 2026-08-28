<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\Backup;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Request;
use MenuGold\Models\AuditLog;

/**
 * Bitacora de cambios y respaldos de la base de datos.
 */
class Auditoria extends Base
{
    public function index(): void
    {
        $this->exigir('auditoria');
        $f = [
            'q'      => Request::str('q', '', 60),
            'accion' => Request::str('accion', '', 60),
            'desde'  => Request::date('desde'),
            'hasta'  => Request::date('hasta'),
        ];
        $m = (new AuditLog())->forRestaurant($this->rid);
        $total = $m->count(
            $f['accion'] !== '' ? 'accion = :a' : '1=1',
            $f['accion'] !== '' ? ['a' => $f['accion']] : []
        );
        $pag = $this->paginar($total, 60);

        $this->panel('panel/auditoria', [
            'registros' => $m->buscar($f, $pag['por'], $pag['offset']),
            'filtros'   => $f,
            'pag'       => $pag,
            'acciones'  => DB::column(
                'SELECT DISTINCT accion FROM audit_log WHERE restaurant_id = :r ORDER BY accion', ['r' => $this->rid]
            ),
        ]);
    }

    // ---------------------------------------------------------------- respaldos
    public function respaldos(): void
    {
        $this->exigir('respaldo');
        if (!Auth::isOwner() && !Auth::isSuper()) throw HttpException::forbidden();
        $this->panel('panel/respaldos', [
            'archivos' => Backup::listar(),
            'espacio'  => Backup::espacio(),
        ]);
    }

    public function respaldoCrear(): void
    {
        $this->exigir('respaldo');
        if (!Auth::isOwner() && !Auth::isSuper()) $this->fail('Solo el dueño puede crear respaldos.', 403);
        try {
            $archivo = Backup::crear('manual-' . str_slug((string)$this->r['nombre']));
            Audit::log('respaldo', 'backup', 0, null, ['archivo' => basename($archivo)]);
            $this->ok([
                'archivo' => basename($archivo),
                'peso'    => Backup::formatoPeso(filesize($archivo) ?: 0),
                'url'     => url('panel/respaldo/bajar/' . basename($archivo)),
            ], 'Respaldo creado correctamente');
        } catch (\Throwable $e) {
            $this->fail('No se pudo crear el respaldo: ' . $e->getMessage(), 500);
        }
    }

    public function respaldoBajar(array $p = []): void
    {
        $this->exigir('respaldo');
        if (!Auth::isOwner() && !Auth::isSuper()) throw HttpException::forbidden();
        $archivo = Backup::ruta((string)($p['archivo'] ?? ''));
        if (!$archivo) throw HttpException::notFound('Respaldo no encontrado.');
        Audit::log('respaldo.descargar', 'backup', 0, null, ['archivo' => basename($archivo)]);
        $this->download((string)file_get_contents($archivo), basename($archivo), 'application/sql');
    }

    public function respaldoBorrar(): void
    {
        $this->exigir('respaldo');
        if (!Auth::isOwner() && !Auth::isSuper()) $this->fail('Sin permiso.', 403);
        $ok = Backup::borrar(Request::str('archivo', '', 190));
        if (!$ok) $this->fail('No se encontró ese respaldo.');
        Audit::log('respaldo.borrar', 'backup');
        $this->ok([], 'Respaldo eliminado');
    }
}
