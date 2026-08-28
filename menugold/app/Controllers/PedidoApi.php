<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\App;
use MenuGold\Core\Controller;
use MenuGold\Core\Csrf;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Lang;
use MenuGold\Core\RateLimit;
use MenuGold\Core\Request;
use MenuGold\Core\Sse;
use MenuGold\Models\Coupon;
use MenuGold\Models\Customer;
use MenuGold\Models\DeliveryZone;
use MenuGold\Models\Order;
use MenuGold\Models\Product;
use MenuGold\Models\Restaurant;
use MenuGold\Models\RestaurantTable;
use MenuGold\Models\WaiterCall;

/**
 * API publica del menu: ficha de producto, cupones, creacion y
 * seguimiento de pedidos, llamadas al mesero.
 *
 * Regla de oro: el cliente solo envia IDs y cantidades.
 * Todos los precios y totales se calculan aqui, en el servidor.
 */
class PedidoApi extends Controller
{
    private function rest(?string $slug): array
    {
        return Menu::resolver($slug);
    }

    // =====================================================================
    //  Ficha de producto con sus modificadores
    // =====================================================================
    public function producto(array $p = []): void
    {
        $r = $this->rest($p['slug'] ?? null);
        $rid = (int)$r['id'];
        $pm = (new Product())->forRestaurant($rid);
        $prod = $pm->find((int)($p['id'] ?? 0));
        if (!$prod || (int)$prod['activo'] !== 1) {
            $this->fail('Ese platillo ya no está disponible.', 404);
        }

        $etiquetas = [];
        foreach (Product::etiquetasArray($prod) as $t) {
            $etiquetas[] = ['clave' => $t, 'texto' => Product::ETIQUETAS[$t][0]];
        }

        $grupos = [];
        foreach ($pm->modificadores((int)$prod['id']) as $g) {
            $ops = [];
            foreach ($g['opciones'] as $o) {
                $ops[] = [
                    'id'            => (int)$o['id'],
                    'nombre'        => t($o, 'nombre'),
                    'precio_extra'  => round((float)$o['precio_extra'], 2),
                    'agotado'       => (int)$o['agotado'] === 1,
                    'predeterminado'=> (int)$o['predeterminado'] === 1,
                ];
            }
            $grupos[] = [
                'id'          => (int)$g['id'],
                'nombre'      => t($g, 'nombre'),
                'tipo'        => (string)$g['tipo'],
                'obligatorio' => (int)$g['obligatorio'] === 1,
                'min_sel'     => (int)$g['min_sel'],
                'max_sel'     => max(1, (int)$g['max_sel']),
                'opciones'    => $ops,
            ];
        }

        $this->ok([
            'producto' => [
                'id'           => (int)$prod['id'],
                'nombre'       => t($prod, 'nombre'),
                'descripcion'  => t($prod, 'descripcion'),
                'precio'       => Product::precioVigente($prod),
                'precio_antes' => Product::tieneDescuento($prod) ? round((float)$prod['precio'], 2) : null,
                'imagen'       => $prod['imagen'] ? uploaded((string)$prod['imagen']) : '',
                'agotado'      => (int)$prod['agotado'] === 1,
                'tiempo_prep'  => (int)$prod['tiempo_prep'],
                'calorias'     => $prod['calorias'] !== null ? (int)$prod['calorias'] : null,
                'etiquetas'    => $etiquetas,
                'alergenos'    => Product::alergenosArray($prod),
            ],
            'grupos' => $grupos,
        ]);
    }

    // =====================================================================
    //  Cupon
    // =====================================================================
    public function cupon(): void
    {
        Csrf::enforce();
        $r = $this->rest(Request::str('slug'));
        $rid = (int)$r['id'];
        $rl = RateLimit::hit('cupon:' . client_ip() . ':' . $rid, 20, 600);
        if (!$rl['permitido']) $this->fail('Demasiados intentos. Espera un momento.', 429);

        $subtotal = max(0, Request::float('subtotal'));
        [$c, $err] = (new Coupon())->forRestaurant($rid)->validar(Request::str('codigo', '', 40), $subtotal);
        if (!$c) $this->fail($err);

        $desc = Coupon::calcular($c, $subtotal, 0);
        $this->ok([
            'descuento' => $desc,
            'codigo'    => (string)$c['codigo'],
        ], $c['tipo'] === 'porcentaje'
            ? 'Cupón aplicado: ' . rtrim(rtrim(number_format((float)$c['valor'], 2, '.', ''), '0'), '.') . '% de descuento'
            : 'Cupón aplicado: ' . money($desc, (string)$r['simbolo']) . ' de descuento');
    }

    // =====================================================================
    //  Recalcular el carrito (vista previa)
    // =====================================================================
    public function calcular(): void
    {
        Csrf::enforce();
        $r = $this->rest(Request::str('slug'));
        $rid = (int)$r['id'];
        $om = (new Order())->forRestaurant($rid);
        $res = $om->calcularLineas(Request::arr('lineas'), $rid);
        $tot = $om->calcularTotales($r, $res['subtotal'], [
            'propina_pct' => Request::float('propina_pct'),
        ]);
        $this->ok(['lineas' => $res['lineas'], 'totales' => $tot, 'avisos' => $res['errores']]);
    }

    // =====================================================================
    //  Crear pedido
    // =====================================================================
    public function crear(): void
    {
        Csrf::enforce();
        $r = $this->rest(Request::str('slug'));
        $rid = (int)$r['id'];

        if (!Restaurant::aceptaPedidos($r)) {
            $this->fail('Este menú es solo de consulta.');
        }
        $apertura = (new Restaurant())->estadoApertura($r);
        $modo = Request::enum('modo', ['mesa','llevar','delivery','whatsapp'], '');
        if ($modo === '' || !Restaurant::permiteModo($r, $modo)) {
            $this->fail('Esa forma de pedido no está habilitada.');
        }
        if (!$apertura['abierto'] && $modo !== 'whatsapp') {
            $this->fail('El restaurante está cerrado en este momento. ' . $apertura['proximo']);
        }

        // --- Anti-spam por IP y por mesa ---
        $ip = client_ip();
        $rl = RateLimit::hit('pedido:ip:' . $ip . ':' . $rid, 12, 900);
        if (!$rl['permitido']) {
            $this->fail('Recibimos varios pedidos desde este dispositivo. Espera unos minutos.', 429);
        }

        // --- Mesa ---
        $mesa = null;
        if ($modo === 'mesa') {
            $mesaId = Request::int('mesa_id');
            $guardada = $_SESSION['mesa_' . $rid] ?? null;
            if (!$guardada || (int)$guardada['id'] !== $mesaId) {
                $this->fail('Vuelve a escanear el código QR de tu mesa para pedir.');
            }
            $mesa = (new RestaurantTable())->forRestaurant($rid)->find($mesaId);
            if (!$mesa) $this->fail('Esa mesa ya no existe.');
            $rlm = RateLimit::hit('pedido:mesa:' . $rid . ':' . $mesaId, 8, 600);
            if (!$rlm['permitido']) {
                $this->fail('Ya hay varios pedidos en curso para esta mesa. Llama a tu mesero.', 429);
            }
        }

        // --- Lineas (precios recalculados en el servidor) ---
        $om = (new Order())->forRestaurant($rid);
        $calc = $om->calcularLineas(Request::arr('lineas'), $rid);
        if (!$calc['lineas']) {
            $this->fail($calc['errores'] ? implode(' ', $calc['errores']) : 'Tu pedido está vacío.');
        }
        if ($calc['errores']) {
            $this->fail(implode(' ', array_slice($calc['errores'], 0, 2)));
        }
        $subtotal = $calc['subtotal'];
        if ((float)$r['pedido_minimo'] > 0 && $subtotal < (float)$r['pedido_minimo']) {
            $this->fail('El pedido mínimo es ' . money($r['pedido_minimo'], (string)$r['simbolo']) . '.');
        }

        // --- Datos del cliente ---
        $cli = Request::arr('cliente');
        $nombre = mb_substr(trim((string)($cli['nombre'] ?? '')), 0, 80);
        $tel    = mb_substr(trim((string)($cli['telefono'] ?? '')), 0, 20);
        $dir    = mb_substr(trim((string)($cli['direccion'] ?? '')), 0, 200);
        $ref    = mb_substr(trim((string)($cli['referencia'] ?? '')), 0, 120);
        if (in_array($modo, ['llevar','delivery','whatsapp'], true)) {
            if (mb_strlen($nombre) < 2) $this->fail('Escribe tu nombre.');
            if (strlen(preg_replace('/\D/', '', $tel) ?? '') < 7) $this->fail('Escribe un teléfono válido.');
        }

        // --- Envio ---
        $envio = 0.0;
        $zonaId = null;
        if ($modo === 'delivery') {
            $zonas = (new DeliveryZone())->forRestaurant($rid)->activas();
            if ($zonas) {
                $zonaId = Request::int('zona_id');
                $z = null;
                foreach ($zonas as $x) if ((int)$x['id'] === $zonaId) $z = $x;
                if (!$z) $this->fail('Elige una zona de entrega válida.');
                $envio = (float)$z['costo'];
                if ((float)$z['minimo'] > 0 && $subtotal < (float)$z['minimo']) {
                    $this->fail('Para ' . $z['nombre'] . ' el mínimo es ' . money($z['minimo'], (string)$r['simbolo']) . '.');
                }
            }
            if (mb_strlen($dir) < 8) $this->fail('Escribe tu dirección completa.');
        }

        // --- Cupon ---
        $descuento = 0.0;
        $cuponCodigo = '';
        $cuponId = 0;
        $cod = Request::str('cupon', '', 40);
        if ($cod !== '') {
            $cm = (new Coupon())->forRestaurant($rid);
            [$c, $err] = $cm->validar($cod, $subtotal);
            if (!$c) $this->fail($err);
            $descuento = Coupon::calcular($c, $subtotal, $envio);
            $cuponCodigo = (string)$c['codigo'];
            $cuponId = (int)$c['id'];
            if ($c['tipo'] === 'envio_gratis') { $envio = max(0, $envio - $descuento); $descuento = 0; }
        }

        $totales = $om->calcularTotales($r, $subtotal, [
            'envio'       => $envio,
            'descuento'   => $descuento,
            'propina_pct' => Request::float('propina_pct'),
        ]);

        // --- Cliente guardado ---
        $customerId = null;
        if ($tel !== '') {
            $customerId = (new Customer())->forRestaurant($rid)->registrar([
                'nombre' => $nombre ?: 'Cliente', 'telefono' => $tel,
                'direccion' => $dir, 'referencia' => $ref, 'zone_id' => $zonaId,
            ]);
        }

        $token = Order::tokenCliente();
        $metodos = array_filter(array_map('trim', explode(',', (string)$r['metodos_pago'])));
        $metodo = Request::str('metodo_pago', '', 30);
        if (!in_array($metodo, $metodos, true)) $metodo = (string)($metodos[0] ?? '');

        $orderId = $om->crear([
            'restaurant_id'      => $rid,
            'table_id'           => $mesa ? (int)$mesa['id'] : null,
            'mesa_nombre'        => $mesa ? (string)$mesa['nombre'] : '',
            'modo'               => $modo,
            'estado'             => $modo === 'whatsapp' ? 'nuevo' : 'nuevo',
            'customer_id'        => $customerId,
            'cliente_nombre'     => $nombre,
            'cliente_telefono'   => $tel,
            'cliente_direccion'  => $dir,
            'cliente_referencia' => $ref,
            'delivery_zone_id'   => $zonaId,
            'costo_envio'        => $totales['envio'],
            'subtotal'           => $totales['subtotal'],
            'descuento'          => $totales['descuento'],
            'cupon_codigo'       => $cuponCodigo,
            'impuesto'           => $totales['impuesto'],
            'propina'            => $totales['propina'],
            'total'              => $totales['total'],
            'metodo_pago'        => $metodo,
            'notas'              => Request::str('notas', '', 300),
            'creado_por'         => 'cliente',
            'session_token'      => $token,
            'ip'                 => $ip,
        ], $calc['lineas']);

        if ($cuponId > 0) (new Coupon())->forRestaurant($rid)->registrarUso($cuponId);
        if ($mesa) (new RestaurantTable())->forRestaurant($rid)->abrir((int)$mesa['id']);

        $pedido = $om->find($orderId);
        $codigo = (string)$pedido['codigo'];

        // --- Modo WhatsApp: se arma el mensaje y se abre wa.me ---
        if ($modo === 'whatsapp') {
            $wa = preg_replace('/\D/', '', (string)$r['whatsapp']) ?? '';
            if ($wa === '') $this->fail('El restaurante no tiene WhatsApp configurado.');
            $this->ok([
                'codigo'   => $codigo,
                'whatsapp' => 'https://wa.me/' . $wa . '?text=' . rawurlencode($this->mensajeWhatsapp($r, $pedido, $calc['lineas'], $totales)),
                'redirect' => url('r/' . $r['slug'] . '/gracias/' . $codigo),
            ]);
        }

        $this->ok([
            'codigo'   => $codigo,
            'redirect' => url('r/' . $r['slug'] . '/gracias/' . $codigo),
        ], 'Tu pedido fue enviado');
    }

    private function mensajeWhatsapp(array $r, array $pedido, array $lineas, array $t): string
    {
        $s = (string)$r['simbolo'];
        $m = "*Nuevo pedido · " . $r['nombre'] . "*\n";
        $m .= "Código: " . $pedido['codigo'] . "\n";
        if (!empty($pedido['mesa_nombre'])) $m .= "Mesa: " . $pedido['mesa_nombre'] . "\n";
        $m .= "Tipo: " . Order::etiquetaModo((string)$pedido['modo']) . "\n";
        if (!empty($pedido['cliente_nombre'])) $m .= "Cliente: " . $pedido['cliente_nombre'] . "\n";
        if (!empty($pedido['cliente_telefono'])) $m .= "Teléfono: " . $pedido['cliente_telefono'] . "\n";
        if (!empty($pedido['cliente_direccion'])) $m .= "Dirección: " . $pedido['cliente_direccion'] . "\n";
        if (!empty($pedido['cliente_referencia'])) $m .= "Referencia: " . $pedido['cliente_referencia'] . "\n";
        $m .= "\n*Pedido:*\n";
        foreach ($lineas as $l) {
            $m .= "• " . $l['cantidad'] . " × " . $l['nombre'] . "  " . $s . number_format($l['subtotal'], 2) . "\n";
            foreach ($l['modificadores'] as $mod) {
                $m .= "    - " . $mod['opcion'] . ($mod['precio'] > 0 ? ' (+' . $s . number_format($mod['precio'], 2) . ')' : '') . "\n";
            }
            if (!empty($l['notas'])) $m .= "    📝 " . $l['notas'] . "\n";
        }
        $m .= "\nSubtotal: " . $s . number_format($t['subtotal'], 2) . "\n";
        if ($t['descuento'] > 0) $m .= "Descuento: -" . $s . number_format($t['descuento'], 2) . "\n";
        if ($t['envio'] > 0)     $m .= "Envío: " . $s . number_format($t['envio'], 2) . "\n";
        if ($t['propina'] > 0)   $m .= "Propina: " . $s . number_format($t['propina'], 2) . "\n";
        $m .= "*TOTAL: " . $s . number_format($t['total'], 2) . "*\n";
        if (!empty($pedido['notas'])) $m .= "\nNotas: " . $pedido['notas'] . "\n";
        if (!empty($pedido['metodo_pago'])) $m .= "Pago: " . $pedido['metodo_pago'] . "\n";
        return $m;
    }

    // =====================================================================
    //  Llamar al mesero / pedir la cuenta
    // =====================================================================
    public function llamar(): void
    {
        Csrf::enforce();
        $r = $this->rest(Request::str('slug'));
        $rid = (int)$r['id'];
        if (!Restaurant::permiteModo($r, 'mesa')) $this->fail('Función no disponible.');

        $mesaId = Request::int('mesa_id');
        $guardada = $_SESSION['mesa_' . $rid] ?? null;
        if (!$guardada || (int)$guardada['id'] !== $mesaId) {
            $this->fail('Escanea de nuevo el QR de tu mesa.');
        }
        $mesa = (new RestaurantTable())->forRestaurant($rid)->find($mesaId);
        if (!$mesa) $this->fail('Esa mesa ya no existe.');

        $tipo = Request::enum('tipo', ['mesero', 'cuenta'], 'mesero');
        $wc = (new WaiterCall())->forRestaurant($rid);
        if ($wc->reciente($mesaId, $tipo)) {
            $this->ok([], $tipo === 'cuenta' ? 'Ya avisamos: tu cuenta viene en camino.' : 'Ya avisamos al mesero.');
        }
        $rl = RateLimit::hit('llamar:' . $rid . ':' . $mesaId, 10, 900);
        if (!$rl['permitido']) $this->fail('Espera un momento antes de volver a llamar.', 429);

        $wc->create([
            'table_id'    => $mesaId,
            'mesa_nombre' => (string)$mesa['nombre'],
            'tipo'        => $tipo,
            'estado'      => 'pendiente',
        ]);
        (new RestaurantTable())->forRestaurant($rid)->updateById($mesaId, [
            'estado' => $tipo === 'cuenta' ? 'cuenta' : 'llamada',
        ]);

        $this->ok([], $tipo === 'cuenta' ? 'Tu cuenta viene en camino.' : 'Un mesero viene en camino.');
    }

    // =====================================================================
    //  Reseña interna
    // =====================================================================
    public function resena(): void
    {
        Csrf::enforce();
        $r = $this->rest(Request::str('slug'));
        $rid = (int)$r['id'];
        $om = (new Order())->forRestaurant($rid);
        $pedido = $om->first('codigo = :c', ['c' => Request::str('codigo', '', 20)]);
        if (!$pedido) $this->fail('Pedido no encontrado.', 404);
        if (!hash_equals((string)$pedido['session_token'], Order::tokenCliente())) {
            $this->fail('No podemos registrar esa reseña.', 403);
        }
        $cal = max(1, min(5, Request::int('calificacion')));
        $om->updateById((int)$pedido['id'], [
            'calificacion' => $cal,
            'comentario'   => Request::str('comentario', '', 500),
        ]);
        $this->ok(['google' => (string)$r['google_reviews']], '¡Gracias por tu opinión!');
    }

    // =====================================================================
    //  Seguimiento del pedido
    // =====================================================================
    public function estado(array $p = []): void
    {
        $r = $this->rest($p['slug'] ?? null);
        $rid = (int)$r['id'];
        $om = (new Order())->forRestaurant($rid);
        $pedido = $om->first('codigo = :c', ['c' => (string)($p['codigo'] ?? '')]);
        if (!$pedido) $this->fail('Pedido no encontrado.', 404);
        $this->ok(['pedido' => $this->resumen($pedido, $om)]);
    }

    public function sse(array $p = []): void
    {
        $r = $this->rest($p['slug'] ?? null);
        $rid = (int)$r['id'];
        $codigo = (string)($p['codigo'] ?? '');
        $om = (new Order())->forRestaurant($rid);
        // Cerramos la sesion para no bloquear otras peticiones del navegador
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

        Sse::loop(function () use ($om, $codigo) {
            $pedido = $om->first('codigo = :c', ['c' => $codigo]);
            if (!$pedido) return ['error' => 'no encontrado'];
            return $this->resumen($pedido, $om);
        }, 'estado');
        exit;
    }

    private function resumen(array $pedido, Order $om): array
    {
        return [
            'codigo'     => (string)$pedido['codigo'],
            'estado'     => (string)$pedido['estado'],
            'etiqueta'   => Order::ETIQUETA_ESTADO[$pedido['estado']] ?? '',
            'total'      => (float)$pedido['total'],
            'creado'     => dt((string)$pedido['creado'], 'H:i'),
            'listo_en'   => $pedido['listo_en'] ? dt((string)$pedido['listo_en'], 'H:i') : null,
            'entregado_en' => $pedido['entregado_en'] ? dt((string)$pedido['entregado_en'], 'H:i') : null,
            'pagado_en'  => $pedido['pagado_en'] ? dt((string)$pedido['pagado_en'], 'H:i') : null,
            'minutos'    => (int)floor((time() - strtotime((string)$pedido['creado'])) / 60),
        ];
    }
}
