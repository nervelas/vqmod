<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Services\BookingException;

/**
 * Base de la API v1. Cada acción se escribe como doNombre(Request, array): Response|array y el router la invoca como nombre():
 * __call la envuelve para que TODO error salga como JSON {error, code} con el código HTTP correcto y sin detalles internos.
 * Éxito: {data, meta}. Error: {error, code}.
 */
abstract class ApiController extends Controller
{
    protected const DEFAULT_LIMIT = 25;
    protected const MAX_LIMIT = 100;

    private const BOOKING_ERRORS = [
        'slot_unavailable' => 409, 'closed' => 409, 'policy' => 409, 'validation' => 422,
        'blocked' => 403, 'rate_limit' => 429, 'payment' => 402,
    ];

    public function __call(string $name, array $args): Response
    {
        $action = 'do' . ucfirst($name);
        if (!method_exists($this, $action)) {
            throw new \BadMethodCallException('Acción de API inexistente: ' . $name);
        }
        try {
            return $this->$action(...$args);
        } catch (ApiError $e) {
            return self::error($e->getMessage(), $e->apiCode, $e->httpStatus);
        } catch (BookingException $e) {
            return self::error($e->getMessage(), $e->errorCode, self::BOOKING_ERRORS[$e->errorCode] ?? 400);
        } catch (\Throwable $e) {
            Logger::error('Error en la API ' . ($GLOBALS['__request']->method ?? '') . ' ' . ($GLOBALS['__request']->path ?? ''), $e);
            return self::error('Algo salió mal de nuestro lado. Ya quedó registrado; inténtalo de nuevo en unos minutos.', 'server_error', 500);
        }
    }

    public static function error(string $message, string $code, int $status): Response
    {
        return Response::json(['error' => $message, 'code' => $code], $status);
    }

    protected function ok($data, array $meta = [], int $status = 200): Response
    {
        return Response::json(['data' => $data, 'meta' => ['request_id' => Str::token(6)] + $meta], $status);
    }

    protected function paged(array $items, int $total, int $limit, int $offset): Response
    {
        return $this->ok($items, ['total' => $total, 'count' => count($items), 'limit' => $limit, 'offset' => $offset, 'has_more' => $offset + count($items) < $total]);
    }

    /** @return array{0:int,1:int} limit y offset validados */
    protected function paging(Request $req): array
    {
        return [
            $this->intParam($req, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT),
            $this->intParam($req, 'offset', 0, 0, 1000000),
        ];
    }

    protected function intParam(Request $req, string $name, int $default, int $min, int $max): int
    {
        $v = $req->get($name);
        if ($v === null || $v === '') {
            return $default;
        }
        if (!is_string($v) || !preg_match('/^-?\d{1,9}$/', $v) || (int) $v < $min || (int) $v > $max) {
            throw new ApiError('El parámetro «' . $name . '» debe ser un número entero entre ' . $min . ' y ' . $max . '.', 'bad_request', 400);
        }
        return (int) $v;
    }

    /** Parámetro de texto simple (no arreglos), recortado. */
    protected function strParam(Request $req, string $name, int $max = 120): string
    {
        $v = $req->get($name);
        if ($v === null) {
            return '';
        }
        if (!is_string($v)) {
            throw new ApiError('El parámetro «' . $name . '» debe ser un texto.', 'bad_request', 400);
        }
        return Str::clean($v, $max);
    }

    protected function timezoneParam(Request $req): string
    {
        $tz = $this->strParam($req, 'tz', 64);
        if ($tz === '') {
            return Settings::tz();
        }
        if (!Tz::valid($tz)) {
            throw new ApiError('La zona horaria «tz» no es válida. Usa un nombre como America/Guatemala.', 'bad_request', 400);
        }
        return $tz;
    }

    /**
     * Fecha (Y-m-d) u hora ISO 8601 -> UTC "Y-m-d H:i:s". Una fecha sola se toma a las 00:00 de la zona $tz;
     * una hora sin zona se interpreta en $tz.
     */
    protected function instant(string $value, string $tz, string $param): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            throw new ApiError('El parámetro «' . $param . '» debe ser una fecha (2026-10-05) o una hora ISO 8601 (2026-10-05T14:30:00-06:00).', 'bad_request', 400);
        }
        try {
            $d = new \DateTimeImmutable(strlen($value) === 10 ? $value . ' 00:00:00' : $value, new \DateTimeZone($tz));
        } catch (\Throwable $e) {
            throw new ApiError('El parámetro «' . $param . '» no es una fecha válida.', 'bad_request', 400);
        }
        return gmdate('Y-m-d H:i:s', $d->getTimestamp());
    }

    /** Cuerpo JSON de la petición (objeto). */
    protected function jsonBody(Request $req): array
    {
        if (stripos((string) $req->header('Content-Type'), 'application/json') === false) {
            throw new ApiError('Envía el cuerpo como JSON y con la cabecera Content-Type: application/json.', 'unsupported_media_type', 415);
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > 262144) {
            throw new ApiError('El cuerpo de la petición está vacío o es demasiado grande.', 'bad_request', 400);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data !== [] && array_keys($data) === range(0, count($data) - 1))) {
            throw new ApiError('El cuerpo no es un objeto JSON válido.', 'invalid_json', 400);
        }
        return $data;
    }

    /** Texto escalar de un campo del cuerpo ('' si no viene). */
    protected function bodyStr(array $body, string $key, int $max): string
    {
        $v = $body[$key] ?? null;
        if ($v === null) {
            return '';
        }
        if (!is_scalar($v) || is_bool($v)) {
            throw new ApiError('El campo «' . $key . '» debe ser un texto.', 'validation', 422);
        }
        return Str::clean((string) $v, $max);
    }

    protected function bodyInt(array $body, string $key): ?int
    {
        $v = $body[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_int($v) && !(is_string($v) && preg_match('/^-?\d{1,9}$/', $v))) {
            throw new ApiError('El campo «' . $key . '» debe ser un número entero.', 'validation', 422);
        }
        return (int) $v;
    }

    protected static function iso(?string $utc): ?string
    {
        return $utc === null ? null : Tz::iso($utc);
    }

    protected static function bool($v): bool
    {
        return (int) $v === 1;
    }
}
