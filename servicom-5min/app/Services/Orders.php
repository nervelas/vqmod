<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Http;
use S5\Core\Settings;

/** Pedidos / borradores y su máquina de estados. */
final class Orders
{
    public const ST_BORRADOR = 'borrador';
    public const ST_ANALIZANDO = 'analizando';
    public const ST_CONSTRUYENDO = 'construyendo';
    public const ST_PREPARANDO = 'preparando';      // fallo QA: no se muestra nada roto
    public const ST_LISTA = 'vista_lista';
    public const ST_PAGO = 'pago_revisar';
    public const ST_PUBLICADA = 'publicada';
    public const ST_VENCIDA = 'vencida';
    public const ST_SUSPENDIDA = 'suspendida';
    public const ST_ELIMINADA = 'eliminada';

    public const LABELS = [
        'borrador' => 'Borrador', 'analizando' => 'Analizando presentación', 'construyendo' => 'Creando vista previa',
        'preparando' => 'Preparando (requiere revisión)', 'vista_lista' => 'Vista previa lista', 'pago_revisar' => 'Pago por revisar',
        'publicada' => 'Publicada', 'vencida' => 'Vencida', 'suspendida' => 'Suspendida', 'eliminada' => 'Eliminada',
    ];

    public static function create(string $plan, string $ip = '', string $ua = ''): array
    {
        $token = bin2hex(random_bytes(32));
        $data = Brief::blank($plan);
        $id = Db::insert('orders', [
            'token' => $token,
            'preview_key' => bin2hex(random_bytes(16)),
            'status' => self::ST_BORRADOR,
            'plan' => $data['plan'],
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $ip ?: Http::ip(),
            'user_agent' => mb_substr($ua, 0, 250),
            'started_at' => Db::now(),
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + Settings::int('preview_days', 15) * 86400),
        ]);
        return self::byId($id);
    }

    public static function byId(int $id): ?array
    {
        return Db::one('SELECT * FROM ' . Db::t('orders') . ' WHERE id=?', [$id]);
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $o = Db::one('SELECT * FROM ' . Db::t('orders') . ' WHERE token=? AND status<>?', [$token, self::ST_ELIMINADA]);
        return $o;
    }

    public static function byPreviewKey(string $key): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            return null;
        }
        return Db::one('SELECT * FROM ' . Db::t('orders') . ' WHERE preview_key=? AND status<>?', [$key, self::ST_ELIMINADA]);
    }

    public static function data(array $o): array
    {
        $d = json_decode((string) $o['data'], true);
        return is_array($d) ? $d + Brief::blank((string) $o['plan']) : Brief::blank((string) $o['plan']);
    }

    public static function saveData(int $id, array $data): void
    {
        $n = (string) ($data['negocio']['nombre'] ?? '');
        Db::update('orders', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'plan' => $data['plan'],
            'business_name' => mb_substr($n, 0, 190),
            'client_email' => (string) ($data['correo_contacto'] ?? ''),
            'client_phone' => (string) ($data['contacto']['whatsapp'] ?? ''),
            'card_extra' => !empty($data['tarjeta_extra']) ? 1 : 0,
            'updated_at' => Db::now(),
        ], 'id=?', [$id]);
    }

    public static function set(int $id, array $fields): void
    {
        $fields['updated_at'] = Db::now();
        Db::update('orders', $fields, 'id=?', [$id]);
    }

    public static function setStatus(int $id, string $status, ?string $msg = null): void
    {
        $f = ['status' => $status];
        if ($msg !== null) {
            $f['build_msg'] = mb_substr($msg, 0, 500);
        }
        self::set($id, $f);
    }

    /** Un cliente solo puede editar datos mientras el pedido no se publicó. */
    public static function editable(array $o): bool
    {
        return in_array($o['status'], [self::ST_BORRADOR, self::ST_ANALIZANDO, self::ST_LISTA, self::ST_PREPARANDO, self::ST_PAGO], true);
    }

    public static function total(array $o): float
    {
        $p = $o['plan'] === 'tienda' ? Settings::float('precio_tienda', 1750) : Settings::float('precio_info', 1250);
        if (!empty($o['card_extra'])) {
            $p += Settings::float('precio_tarjeta', 750);
        }
        return $p;
    }

    public static function previewUrl(array $o, bool $withKey = true): string
    {
        $fq = (string) ($o['fqdn'] ?? '');
        if ($fq === '') {
            return '';
        }
        $scheme = Settings::scheme();
        $u = $scheme . '://' . $fq . self::portSuffix();
        return $withKey && $o['status'] !== self::ST_PUBLICADA ? $u . '/?scpk=' . $o['preview_key'] : $u;
    }

    /** Puerto para entornos de prueba (S5_SITE_PORT), vacío en producción. */
    public static function portSuffix(): string
    {
        $p = getenv('S5_SITE_PORT');
        return $p ? ':' . (int) $p : '';
    }

    public static function alert(string $level, string $message, ?int $orderId = null): void
    {
        Db::insert('alerts', ['level' => $level, 'message' => mb_substr($message, 0, 500), 'order_id' => $orderId, 'created_at' => Db::now()]);
    }
}
