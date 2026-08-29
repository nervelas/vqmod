<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\App;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Model;

class Order extends Model
{
    protected string $table = 'orders';
    protected array $fillable = [
        'restaurant_id','codigo','table_id','mesa_nombre','modo','estado','customer_id','cliente_nombre',
        'cliente_telefono','cliente_direccion','cliente_referencia','delivery_zone_id','costo_envio','subtotal',
        'descuento','cupon_codigo','impuesto','propina','total','pagado_con','metodo_pago','notas',
        'motivo_anulacion','user_id','creado_por','session_token','ip','minutos_prep','calificacion','comentario',
        'actualizado','listo_en','entregado_en','pagado_en',
    ];

    public const ESTADOS = ['nuevo','preparando','listo','entregado','pagado','anulado'];
    public const ABIERTOS = ['nuevo','preparando','listo','entregado'];

    public const ETIQUETA_ESTADO = [
        'nuevo'      => 'Recibido',
        'preparando' => 'En preparación',
        'listo'      => 'Listo',
        'entregado'  => 'Entregado',
        'pagado'     => 'Pagado',
        'anulado'    => 'Anulado',
    ];

    // =====================================================================
    //  Calculo del pedido (SIEMPRE en el servidor)
    // =====================================================================

    /**
     * Recalcula un carrito enviado por el cliente. El cliente solo manda
     * IDs de producto, IDs de opciones y cantidades: los precios se leen
     * de la base de datos, nunca del navegador.
     *
     * @param array $lineas [['product_id'=>int,'cantidad'=>int,'opciones'=>[ids],'notas'=>string]]
     * @return array{lineas:array,subtotal:float,errores:array}
     */
    public function calcularLineas(array $lineas, int $rid): array
    {
        $out = [];
        $errores = [];
        $subtotal = 0.0;

        $ids = [];
        foreach ($lineas as $l) {
            $pid = (int)($l['product_id'] ?? 0);
            if ($pid > 0) $ids[$pid] = $pid;
        }
        if (!$ids) return ['lineas' => [], 'subtotal' => 0.0, 'errores' => ['El pedido está vacío.']];

        [$ph, $par] = DB::inList(array_values($ids), 'p');
        $par['r'] = $rid;
        $productos = [];
        foreach (DB::all("SELECT * FROM products WHERE id IN ({$ph}) AND restaurant_id = :r", $par) as $p) {
            $productos[(int)$p['id']] = $p;
        }

        foreach ($lineas as $l) {
            $pid = (int)($l['product_id'] ?? 0);
            $cant = max(1, min(50, (int)($l['cantidad'] ?? 1)));
            $p = $productos[$pid] ?? null;
            if (!$p) { $errores[] = 'Un platillo del pedido ya no está disponible.'; continue; }
            if ((int)$p['activo'] !== 1) { $errores[] = $p['nombre'] . ' ya no está en el menú.'; continue; }
            if ((int)$p['agotado'] === 1) { $errores[] = $p['nombre'] . ' está agotado por hoy.'; continue; }
            if (!Product::disponibleAhora($p)) { $errores[] = $p['nombre'] . ' no está disponible a esta hora.'; continue; }

            $precio = Product::precioVigente($p);
            [$mods, $extra, $errMod] = $this->validarModificadores($pid, (array)($l['opciones'] ?? []), $rid);
            if ($errMod !== '') { $errores[] = $p['nombre'] . ': ' . $errMod; continue; }

            $lineaSubtotal = round(($precio + $extra) * $cant, 2);
            $subtotal += $lineaSubtotal;

            $out[] = [
                'product_id'    => $pid,
                'nombre'        => (string)$p['nombre'],
                'precio_unit'   => $precio,
                'extra_unit'    => $extra,
                'cantidad'      => $cant,
                'modificadores' => $mods,
                'notas'         => mb_substr(trim((string)($l['notas'] ?? '')), 0, 255),
                'subtotal'      => $lineaSubtotal,
                'estacion'      => (string)$p['estacion'],
                'imagen'        => (string)$p['imagen'],
                'tiempo_prep'   => (int)$p['tiempo_prep'],
            ];
        }
        return ['lineas' => $out, 'subtotal' => round($subtotal, 2), 'errores' => $errores];
    }

    /**
     * Valida las opciones elegidas contra los grupos del producto.
     * @return array{0:array,1:float,2:string}
     */
    private function validarModificadores(int $productId, array $opcionIds, int $rid): array
    {
        $opcionIds = array_values(array_unique(array_filter(array_map('intval', $opcionIds))));
        $grupos = DB::all(
            'SELECT g.* FROM modifier_groups g
             INNER JOIN product_modifiers pm ON pm.group_id = g.id
             WHERE pm.product_id = :p AND g.restaurant_id = :r AND g.activo = 1
             ORDER BY pm.orden ASC',
            ['p' => $productId, 'r' => $rid]
        );
        if (!$grupos) return [[], 0.0, $opcionIds ? '' : ''];

        $porGrupo = [];
        $opciones = [];
        if ($opcionIds) {
            [$ph, $par] = DB::inList($opcionIds, 'o');
            $par['r'] = $rid;
            foreach (DB::all("SELECT * FROM modifier_options WHERE id IN ({$ph}) AND restaurant_id = :r AND activo = 1", $par) as $o) {
                $opciones[(int)$o['id']] = $o;
                $porGrupo[(int)$o['group_id']][] = $o;
            }
        }

        $extra = 0.0;
        $elegidas = [];
        foreach ($grupos as $g) {
            $gid = (int)$g['id'];
            $sel = $porGrupo[$gid] ?? [];
            $n = count($sel);
            $max = max(1, (int)$g['max_sel']);
            $min = (int)$g['obligatorio'] === 1 ? max(1, (int)$g['min_sel']) : (int)$g['min_sel'];
            if ($g['tipo'] === 'unico') $max = 1;

            if ($n < $min) {
                return [[], 0.0, 'falta elegir «' . $g['nombre'] . '».'];
            }
            if ($n > $max) {
                return [[], 0.0, 'en «' . $g['nombre'] . '» puedes elegir hasta ' . $max . '.'];
            }
            foreach ($sel as $o) {
                if ((int)$o['agotado'] === 1) {
                    return [[], 0.0, '"' . $o['nombre'] . '" está agotado.'];
                }
                $extra += (float)$o['precio_extra'];
                $elegidas[] = [
                    'grupo'  => (string)$g['nombre'],
                    'opcion' => (string)$o['nombre'],
                    'precio' => round((float)$o['precio_extra'], 2),
                    'id'     => (int)$o['id'],
                ];
            }
        }
        // Opciones enviadas que no pertenecen a ningun grupo del producto
        $validas = [];
        foreach ($elegidas as $e) $validas[] = $e['id'];
        foreach ($opcionIds as $oid) {
            if (!in_array($oid, $validas, true)) {
                return [[], 0.0, 'una de las opciones elegidas no es válida.'];
            }
        }
        return [$elegidas, round($extra, 2), ''];
    }

    /**
     * Calcula los totales finales del pedido.
     * @return array<string,float|string>
     */
    public function calcularTotales(array $r, float $subtotal, array $opciones = []): array
    {
        $envio     = round((float)($opciones['envio'] ?? 0), 2);
        $descuento = round((float)($opciones['descuento'] ?? 0), 2);
        $descuento = min($descuento, $subtotal);
        $base      = max(0, $subtotal - $descuento);

        $pctImp = (float)($r['impuesto_pct'] ?? 0);
        $incluido = (int)($r['impuesto_incluido'] ?? 1) === 1;
        $impuesto = 0.0;
        if ($pctImp > 0) {
            $impuesto = $incluido
                ? round($base - ($base / (1 + $pctImp / 100)), 2)   // ya viene dentro del precio
                : round($base * $pctImp / 100, 2);
        }

        $totalSinPropina = $incluido ? $base + $envio : $base + $impuesto + $envio;
        $pctPropina = max(0, min(100, (float)($opciones['propina_pct'] ?? 0)));
        $propina = round($base * $pctPropina / 100, 2);
        if (isset($opciones['propina_monto'])) {
            $propina = round(max(0, (float)$opciones['propina_monto']), 2);
        }

        return [
            'subtotal'  => round($subtotal, 2),
            'descuento' => $descuento,
            'envio'     => $envio,
            'impuesto'  => $impuesto,
            'propina'   => $propina,
            'total'     => round($totalSinPropina + $propina, 2),
        ];
    }

    // =====================================================================
    //  Creacion
    // =====================================================================

    public function generarCodigo(int $rid): string
    {
        $prefijo = date('md');
        for ($i = 0; $i < 12; $i++) {
            $codigo = $prefijo . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $existe = DB::int('SELECT COUNT(*) FROM orders WHERE restaurant_id=:r AND codigo=:c',
                ['r' => $rid, 'c' => $codigo]);
            if (!$existe) return $codigo;
        }
        return $prefijo . '-' . substr((string)microtime(true), -5);
    }

    /**
     * Crea el pedido con sus lineas dentro de una transaccion.
     * @return int id del pedido
     */
    public function crear(array $cabecera, array $lineas): int
    {
        $rid = (int)($cabecera['restaurant_id'] ?? $this->rid());
        return DB::transaction(function () use ($cabecera, $lineas, $rid) {
            $cabecera['restaurant_id'] = $rid;
            $cabecera['codigo'] = $cabecera['codigo'] ?? $this->generarCodigo($rid);
            $cabecera['creado'] = date('Y-m-d H:i:s');
            $orderId = $this->forRestaurant($rid)->create($cabecera);

            foreach ($lineas as $l) {
                DB::insert('order_items', [
                    'order_id'      => $orderId,
                    'restaurant_id' => $rid,
                    'product_id'    => (int)$l['product_id'] ?: null,
                    'nombre'        => mb_substr((string)$l['nombre'], 0, 180),
                    'precio_unit'   => (float)$l['precio_unit'],
                    'extra_unit'    => (float)($l['extra_unit'] ?? 0),
                    'cantidad'      => (int)$l['cantidad'],
                    'modificadores' => json_encode($l['modificadores'] ?? [], JSON_UNESCAPED_UNICODE),
                    'notas'         => mb_substr((string)($l['notas'] ?? ''), 0, 255),
                    'subtotal'      => (float)$l['subtotal'],
                    'estacion'      => in_array(($l['estacion'] ?? 'cocina'), ['cocina','bar','postres'], true) ? $l['estacion'] : 'cocina',
                    'estado'        => 'pendiente',
                    'creado'        => date('Y-m-d H:i:s'),
                ]);
                if (!empty($l['product_id'])) {
                    DB::ejecutar('UPDATE products SET vendidos = vendidos + :c WHERE id = :p AND restaurant_id = :r',
                        ['c' => (int)$l['cantidad'], 'p' => (int)$l['product_id'], 'r' => $rid]);
                }
            }
            $this->evento($orderId, (string)($cabecera['estado'] ?? 'nuevo'), 'Pedido creado');
            return $orderId;
        });
    }

    public function evento(int $orderId, string $estado, string $nota = ''): void
    {
        DB::insert('order_events', [
            'order_id' => $orderId,
            'estado'   => mb_substr($estado, 0, 20),
            'user_id'  => Auth::id() ?: null,
            'usuario'  => Auth::nombre() ?: 'cliente',
            'nota'     => mb_substr($nota, 0, 255),
            'creado'   => date('Y-m-d H:i:s'),
        ]);
    }

    // =====================================================================
    //  Consultas
    // =====================================================================

    public function conLineas(int $id): ?array
    {
        $o = $this->find($id);
        if (!$o) return null;
        $o['items'] = $this->lineas($id);
        return $o;
    }

    public function lineas(int $orderId): array
    {
        $rows = DB::all(
            'SELECT * FROM order_items WHERE order_id = :o AND restaurant_id = :r ORDER BY id ASC',
            ['o' => $orderId, 'r' => $this->rid()]
        );
        foreach ($rows as &$r) $r['modificadores'] = jdec($r['modificadores']);
        unset($r);
        return $rows;
    }

    /** Pedidos abiertos para la pantalla de cocina. */
    public function paraCocina(string $estacion = ''): array
    {
        $rid = $this->rid();
        $p = ['r' => $rid];
        $filtroEst = '';
        if ($estacion !== '' && in_array($estacion, ['cocina','bar','postres'], true)) {
            $filtroEst = ' AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.estacion = :e)';
            $p['e'] = $estacion;
        }
        $ordenes = DB::all(
            "SELECT o.*, t.nombre AS mesa
             FROM orders o LEFT JOIN tables t ON t.id = o.table_id
             WHERE o.restaurant_id = :r AND o.estado IN ('nuevo','preparando','listo')
             {$filtroEst}
             ORDER BY o.creado ASC LIMIT 120",
            $p
        );
        if (!$ordenes) return [];
        [$ph, $par] = DB::inList(array_column($ordenes, 'id'), 'o');
        $par['r2'] = $rid;
        $sqlItems = "SELECT * FROM order_items WHERE order_id IN ({$ph}) AND restaurant_id = :r2";
        if ($estacion !== '' && in_array($estacion, ['cocina','bar','postres'], true)) {
            $sqlItems .= ' AND estacion = :e2';
            $par['e2'] = $estacion;
        }
        $items = DB::all($sqlItems . ' ORDER BY id ASC', $par);
        $map = [];
        foreach ($items as $i) {
            $i['modificadores'] = jdec($i['modificadores']);
            $map[(int)$i['order_id']][] = $i;
        }
        foreach ($ordenes as &$o) {
            $o['items'] = $map[(int)$o['id']] ?? [];
            $o['minutos'] = (int)floor((time() - strtotime((string)$o['creado'])) / 60);
        }
        unset($o);
        return array_values(array_filter($ordenes, static fn($o) => !empty($o['items'])));
    }

    /** Pedidos abiertos de una mesa. */
    public function deMesa(int $tableId): array
    {
        $lista = $this->where(
            "table_id = :t AND estado IN ('nuevo','preparando','listo','entregado')",
            ['t' => $tableId], 'creado ASC'
        );
        foreach ($lista as &$o) $o['items'] = $this->lineas((int)$o['id']);
        unset($o);
        return $lista;
    }

    /** Historial con filtros para el panel. */
    public function historial(array $f, int $limite = 100, int $offset = 0): array
    {
        $w = '1=1';
        $p = [];
        if (!empty($f['q'])) {
            $w .= ' AND (codigo LIKE :q OR cliente_nombre LIKE :q2 OR cliente_telefono LIKE :q3 OR mesa_nombre LIKE :q4)';
            $p['q'] = "%{$f['q']}%"; $p['q2'] = "%{$f['q']}%"; $p['q3'] = "%{$f['q']}%"; $p['q4'] = "%{$f['q']}%";
        }
        if (!empty($f['estado']) && in_array($f['estado'], self::ESTADOS, true)) {
            $w .= ' AND estado = :es'; $p['es'] = $f['estado'];
        }
        if (!empty($f['modo'])) { $w .= ' AND modo = :mo'; $p['mo'] = $f['modo']; }
        if (!empty($f['desde'])) { $w .= ' AND creado >= :d'; $p['d'] = $f['desde'] . ' 00:00:00'; }
        if (!empty($f['hasta'])) { $w .= ' AND creado <= :h'; $p['h'] = $f['hasta'] . ' 23:59:59'; }
        if (!empty($f['user_id'])) { $w .= ' AND user_id = :u'; $p['u'] = (int)$f['user_id']; }
        return $this->where($w, $p, 'creado DESC', $limite, $offset);
    }

    public function contarHistorial(array $f): int
    {
        $w = '1=1';
        $p = [];
        if (!empty($f['q'])) {
            $w .= ' AND (codigo LIKE :q OR cliente_nombre LIKE :q2 OR cliente_telefono LIKE :q3 OR mesa_nombre LIKE :q4)';
            $p['q'] = "%{$f['q']}%"; $p['q2'] = "%{$f['q']}%"; $p['q3'] = "%{$f['q']}%"; $p['q4'] = "%{$f['q']}%";
        }
        if (!empty($f['estado']) && in_array($f['estado'], self::ESTADOS, true)) { $w .= ' AND estado = :es'; $p['es'] = $f['estado']; }
        if (!empty($f['modo']))  { $w .= ' AND modo = :mo'; $p['mo'] = $f['modo']; }
        if (!empty($f['desde'])) { $w .= ' AND creado >= :d'; $p['d'] = $f['desde'] . ' 00:00:00'; }
        if (!empty($f['hasta'])) { $w .= ' AND creado <= :h'; $p['h'] = $f['hasta'] . ' 23:59:59'; }
        if (!empty($f['user_id'])) { $w .= ' AND user_id = :u'; $p['u'] = (int)$f['user_id']; }
        return $this->count($w, $p);
    }

    // =====================================================================
    //  Transiciones de estado
    // =====================================================================

    public function cambiarEstado(int $id, string $nuevo, string $nota = ''): array
    {
        if (!in_array($nuevo, self::ESTADOS, true)) {
            throw HttpException::badRequest('Estado de pedido no válido.');
        }
        $o = $this->findOrFail($id);
        if ($o['estado'] === $nuevo) return $o;
        if ($o['estado'] === 'anulado') {
            throw HttpException::badRequest('Este pedido fue anulado y ya no puede cambiar.');
        }

        $datos = ['estado' => $nuevo, 'actualizado' => date('Y-m-d H:i:s')];
        if ($nuevo === 'listo' && empty($o['listo_en'])) {
            $datos['listo_en'] = date('Y-m-d H:i:s');
            $datos['minutos_prep'] = (int)max(0, floor((time() - strtotime((string)$o['creado'])) / 60));
        }
        if ($nuevo === 'entregado' && empty($o['entregado_en'])) $datos['entregado_en'] = date('Y-m-d H:i:s');
        if ($nuevo === 'pagado' && empty($o['pagado_en']))       $datos['pagado_en'] = date('Y-m-d H:i:s');

        $this->updateById($id, $datos);
        DB::ejecutar('UPDATE order_items SET estado = :e WHERE order_id = :o AND estado <> :a',
            ['e' => $this->estadoLinea($nuevo), 'o' => $id, 'a' => 'anulado']);
        $this->evento($id, $nuevo, $nota);
        return array_merge($o, $datos);
    }

    private function estadoLinea(string $estadoPedido): string
    {
        switch ($estadoPedido) {
            case 'nuevo':      return 'pendiente';
            case 'preparando': return 'preparando';
            case 'listo':      return 'listo';
            case 'entregado':
            case 'pagado':     return 'entregado';
            case 'anulado':    return 'anulado';
        }
        return 'pendiente';
    }

    public function siguienteEstado(string $actual): string
    {
        switch ($actual) {
            case 'nuevo':      return 'preparando';
            case 'preparando': return 'listo';
            case 'listo':      return 'entregado';
            case 'entregado':  return 'pagado';
        }
        return $actual;
    }

    public function anular(int $id, string $motivo): array
    {
        $o = $this->findOrFail($id);
        if ($o['estado'] === 'anulado') return $o;
        $this->updateById($id, [
            'estado' => 'anulado',
            'motivo_anulacion' => mb_substr($motivo, 0, 255),
            'actualizado' => date('Y-m-d H:i:s'),
        ]);
        DB::ejecutar('UPDATE order_items SET estado = :e WHERE order_id = :o', ['e' => 'anulado', 'o' => $id]);
        // Devolver el conteo de vendidos
        foreach ($this->lineas($id) as $l) {
            if (!empty($l['product_id'])) {
                DB::ejecutar('UPDATE products SET vendidos = GREATEST(0, vendidos - :c) WHERE id = :p AND restaurant_id = :r',
                    ['c' => (int)$l['cantidad'], 'p' => (int)$l['product_id'], 'r' => $this->rid()]);
            }
        }
        $this->evento($id, 'anulado', $motivo);
        return $this->findOrFail($id);
    }

    /** Marca la sesion del cliente para que pueda seguir su pedido. */
    public static function tokenCliente(): string
    {
        if (empty($_SESSION['pedido_token'])) {
            $_SESSION['pedido_token'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['pedido_token'];
    }

    public function porToken(string $token, int $rid): array
    {
        if ($token === '') return [];
        return DB::all(
            'SELECT * FROM orders WHERE restaurant_id = :r AND session_token = :t ORDER BY creado DESC LIMIT 10',
            ['r' => $rid, 't' => $token]
        );
    }

    public function timeline(int $orderId): array
    {
        return DB::all('SELECT * FROM order_events WHERE order_id = :o ORDER BY id ASC', ['o' => $orderId]);
    }

    public static function etiquetaModo(string $modo): string
    {
        $m = ['mesa' => 'En mesa', 'llevar' => 'Para llevar', 'delivery' => 'A domicilio', 'whatsapp' => 'WhatsApp'];
        return $m[$modo] ?? $modo;
    }
}
