<?php
declare(strict_types=1);

namespace S5\Services;

use S5\Core\Db;
use S5\Core\Log;
use S5\Core\Settings;

/**
 * Control de gasto de IA. Suma la tabla `ai_usage` (contrato §2) contra un
 * tope diario (Setting `ai_cap_day`, 1.00 USD) y uno total (`ai_cap_total`,
 * 10.00 USD). El "día" se calcula en America/Guatemala; `created_at` se
 * guarda y compara en hora de Guatemala (UTC-6, sin horario de verano).
 *
 * Precios en USD por millón de tokens: Settings `ai_price_in_<modelo>` y
 * `ai_price_out_<modelo>`; si no existen se usan los DEFAULTS de abajo.
 */
class AiBudget
{
    /** [entrada, salida, umbral_tokens, entrada_alta, salida_alta] (USD/MTok). */
    private const PRECIOS = [
        'claude-sonnet-5-5' => [2.00, 10.00, 0, 0.0, 0.0],
        'claude-sonnet-5'   => [2.00, 10.00, 0, 0.0, 0.0],
        'claude-haiku-5-5'  => [0.10, 0.50, 100000, 0.50, 2.50],
        'claude-haiku-4-5'  => [1.00, 5.00, 0, 0.0, 0.0],
        'claude-opus-5-5'   => [4.00, 20.00, 0, 0.0, 0.0],
    ];
    /** Para modelos desconocidos: precio conservador (el más alto de la tabla). */
    private const PRECIO_DESCONOCIDO = [5.00, 25.00, 0, 0.0, 0.0];

    /** Reloj inyectable para pruebas: callable(): \DateTimeImmutable. */
    public static $reloj = null;

    /** Estimación de tokens sin tokenizador (conservadora): caracteres / 3.2. */
    public static function estimarTokens(string $texto): int
    {
        return (int)ceil(mb_strlen($texto, 'UTF-8') / 3.2);
    }

    public static function ahora(): \DateTimeImmutable
    {
        if (is_callable(self::$reloj)) {
            return (self::$reloj)();
        }
        return new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala'));
    }

    /** Costo en USD de una llamada. */
    public static function costo(string $model, int $in, int $out): float
    {
        $def = self::PRECIOS[$model] ?? self::PRECIO_DESCONOCIDO;
        $pi = Settings::get('ai_price_in_' . $model);
        $po = Settings::get('ai_price_out_' . $model);
        if ($pi !== null && $pi !== '' && is_numeric($pi) && $po !== null && $po !== '' && is_numeric($po)) {
            $rin = (float)$pi;
            $rout = (float)$po;
        } else {
            $alto = $def[2] > 0 && $in > $def[2];
            $rin = $alto ? $def[3] : $def[0];
            $rout = $alto ? $def[4] : $def[1];
            if ($pi !== null && $pi !== '' && is_numeric($pi)) {
                $rin = (float)$pi;
            }
            if ($po !== null && $po !== '' && is_numeric($po)) {
                $rout = (float)$po;
            }
        }
        return round((max(0, $in) * $rin + max(0, $out) * $rout) / 1000000, 6);
    }

    /** Costo estimado de la PRÓXIMA llamada de este tipo (margen de seguridad). */
    public static function margen(string $kind): float
    {
        $v = Settings::get('ai_margin_' . $kind);
        if ($v !== null && $v !== '' && is_numeric($v)) {
            return max(0.0, (float)$v);
        }
        if ($kind === 'analisis') {
            $m = (string)(Settings::get('ai_model_extract', 'claude-haiku-5-5') ?: 'claude-haiku-5-5');
            return self::costo($m, 60000, 6000);
        }
        $m = (string)(Settings::get('ai_model_main', 'claude-sonnet-5-5') ?: 'claude-sonnet-5-5');
        return self::costo($m, 4000, 4000);
    }

    /**
     * ¿Se puede hacer otra llamada? Considera el gasto actual más un margen
     * (o el costo estimado indicado) frente a los topes diario y total.
     * Ante cualquier error de base de datos responde false (lado seguro).
     */
    public static function puedeGastar(string $kind = 'redaccion', ?float $costoEstimado = null): bool
    {
        try {
            $capDia = self::capDia();
            $capTot = self::capTotal();
            $m = $costoEstimado ?? self::margen($kind);
            $r = self::resumen();
            if ($r['dia']['gastado'] + $m > $capDia + 1e-9) {
                return false;
            }
            if ($r['total']['gastado'] + $m > $capTot + 1e-9) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            self::log('AiBudget::puedeGastar falló: ' . $e->getMessage());
            return false;
        }
    }

    public static function capDia(): float
    {
        $v = Settings::get('ai_cap_day', 1.00);
        return ($v === null || $v === '' || !is_numeric($v)) ? 1.00 : (float)$v;
    }

    public static function capTotal(): float
    {
        $v = Settings::get('ai_cap_total', 10.00);
        return ($v === null || $v === '' || !is_numeric($v)) ? 10.00 : (float)$v;
    }

    /**
     * Registra una llamada (también las fallidas, con ok=false).
     * @return float costo registrado en USD
     */
    public static function registrar(string $kind, string $model, int $tokensIn, int $tokensOut, ?int $orderId = null, bool $ok = true): float
    {
        if ($kind !== 'redaccion' && $kind !== 'analisis') {
            $kind = 'redaccion';
        }
        $costo = self::costo($model, $tokensIn, $tokensOut);
        try {
            Db::q(
                'INSERT INTO ' . Db::t('ai_usage') . ' (kind, model, tokens_in, tokens_out, cost_usd, order_id, ok, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [$kind, mb_substr($model, 0, 80, 'UTF-8'), max(0, $tokensIn), max(0, $tokensOut), number_format($costo, 6, '.', ''), $orderId, $ok ? 1 : 0, self::ahora()->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            self::log('AiBudget::registrar falló: ' . $e->getMessage());
        }
        return $costo;
    }

    /**
     * Resumen de gasto.
     * @return array{dia:array,total:array}
     */
    public static function resumen(): array
    {
        $ahora = self::ahora();
        $ini = $ahora->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $fin = $ahora->setTime(0, 0, 0)->modify('+1 day')->format('Y-m-d H:i:s');
        $t = Db::t('ai_usage');

        $porKindDia = self::agrupar(Db::all(
            'SELECT kind, COALESCE(SUM(cost_usd),0) AS c, COUNT(*) AS n FROM ' . $t . ' WHERE created_at >= ? AND created_at < ? GROUP BY kind',
            [$ini, $fin]
        ));
        $porKindTot = self::agrupar(Db::all(
            'SELECT kind, COALESCE(SUM(cost_usd),0) AS c, COUNT(*) AS n FROM ' . $t . ' GROUP BY kind',
            []
        ));
        $gDia = $porKindDia['redaccion']['costo'] + $porKindDia['analisis']['costo'];
        $gTot = $porKindTot['redaccion']['costo'] + $porKindTot['analisis']['costo'];
        $capDia = self::capDia();
        $capTot = self::capTotal();
        return [
            'dia' => [
                'fecha' => $ahora->format('Y-m-d'),
                'gastado' => round($gDia, 6),
                'tope' => $capDia,
                'restante' => round(max(0.0, $capDia - $gDia), 6),
                'por_kind' => $porKindDia,
            ],
            'total' => [
                'gastado' => round($gTot, 6),
                'tope' => $capTot,
                'restante' => round(max(0.0, $capTot - $gTot), 6),
                'por_kind' => $porKindTot,
            ],
        ];
    }

    /** @return array<string,array{costo:float,llamadas:int}> */
    private static function agrupar(array $rows): array
    {
        $o = ['redaccion' => ['costo' => 0.0, 'llamadas' => 0], 'analisis' => ['costo' => 0.0, 'llamadas' => 0]];
        foreach ($rows as $r) {
            $k = (string)($r['kind'] ?? '');
            if (isset($o[$k])) {
                $o[$k] = ['costo' => (float)$r['c'], 'llamadas' => (int)$r['n']];
            }
        }
        return $o;
    }

    private static function log(string $m): void
    {
        try {
            Log::error($m);
        } catch (\Throwable $e) {
            // sin registro disponible
        }
    }
}
