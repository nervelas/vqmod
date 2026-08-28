<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_log';
    protected array $jsonFields = ['antes','despues'];

    public function buscar(array $f, int $limite = 100, int $offset = 0): array
    {
        $w = '1=1';
        $p = [];
        if (!empty($f['q'])) {
            $w .= ' AND (usuario LIKE :q OR accion LIKE :q2 OR entidad LIKE :q3)';
            $p['q'] = "%{$f['q']}%"; $p['q2'] = "%{$f['q']}%"; $p['q3'] = "%{$f['q']}%";
        }
        if (!empty($f['accion'])) { $w .= ' AND accion = :a'; $p['a'] = $f['accion']; }
        if (!empty($f['desde']))  { $w .= ' AND creado >= :d'; $p['d'] = $f['desde'] . ' 00:00:00'; }
        if (!empty($f['hasta']))  { $w .= ' AND creado <= :h'; $p['h'] = $f['hasta'] . ' 23:59:59'; }
        return $this->where($w, $p, 'id DESC', $limite, $offset);
    }

    public static function etiqueta(string $accion): string
    {
        $m = [
            'ingreso' => 'Inicio de sesión', 'salida' => 'Cierre de sesión',
            'precio' => 'Cambio de precio', 'producto.crear' => 'Producto creado',
            'producto.editar' => 'Producto editado', 'producto.borrar' => 'Producto eliminado',
            'producto.agotado' => 'Disponibilidad', 'pedido.anular' => 'Pedido anulado',
            'pedido.cobrar' => 'Pedido cobrado', 'mesa.cerrar' => 'Mesa cerrada',
            'config' => 'Configuración', 'usuario.crear' => 'Usuario creado',
            'usuario.editar' => 'Usuario editado', 'usuario.borrar' => 'Usuario eliminado',
            'respaldo' => 'Respaldo de base de datos', 'descuento' => 'Descuento aplicado',
            'importacion' => 'Importación masiva',
        ];
        return $m[$accion] ?? ucfirst(str_replace('.', ' ', $accion));
    }
}
