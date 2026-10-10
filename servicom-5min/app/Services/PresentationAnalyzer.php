<?php
declare(strict_types=1);

namespace S5\Services;

use S5\Core\Db;
use S5\Core\Log;

/**
 * Orquesta el análisis de una presentación: parser local + IA, y entrega un
 * resultado normalizado al esquema §5.2 (nunca inventa). Máx. 3 análisis por
 * pedido (`orders.analysis_count`).
 */
class PresentationAnalyzer
{
    public const MAX_POR_PEDIDO = 3;
    public const MSG_LIMITE = 'Ya usó los 3 análisis de presentación disponibles para este pedido; puede llenar los datos manualmente.';

    /**
     * @return array resultado §5.2 (+ `imagenes` con archivo relativo al workdir)
     * @throws AiUnavailable
     */
    public static function analizar(int $orderId, string $path, string $tipo, string $workdir): array
    {
        $tipo = strtolower($tipo);
        if (!in_array($tipo, ['pdf', 'pptx', 'docx'], true)) {
            throw new AiUnavailable(AiClient::MSG_NO_DISPONIBLE);
        }
        if (!AiBudget::puedeGastar('analisis')) {
            throw new AiUnavailable(AiClient::MSG_NO_DISPONIBLE);
        }
        self::reservarIntento($orderId);

        $imagenes = [];
        try {
            if ($tipo === 'pdf') {
                $datos = AiClient::extraerPresentacion('pdf', $path, $orderId)['datos'];
                // Imágenes y logo del PDF: extracción local (nunca rompe el análisis)
                try {
                    $imagenes = PresentationParser::extract($path, 'pdf', $workdir)['imagenes'] ?? [];
                } catch (\Throwable $e) {
                    self::log('Imágenes del PDF: ' . $e->getMessage());
                    $imagenes = [];
                }
            } else {
                try {
                    $ext = PresentationParser::extract($path, $tipo, $workdir);
                } catch (ParserError $e) {
                    self::limpiarWorkdir($workdir, [], $path);
                    throw new AiUnavailable($e->getMessage());
                }
                $imagenes = $ext['imagenes'];
                $util = trim((string)preg_replace('/\[Diapositiva \d+\]|Título:|Notas:|\[Encabezado\/Pie\]/u', '', $ext['texto']));
                if (mb_strlen($util, 'UTF-8') < 30) {
                    $datos = TextSchema::validarExtraccion([]);
                } else {
                    $datos = AiClient::extraerPresentacion($tipo, $ext['texto'], $orderId)['datos'];
                }
            }
        } catch (AiUnavailable $e) {
            self::limpiarWorkdir($workdir, [], $path);
            throw $e;
        } catch (\Throwable $e) {
            self::limpiarWorkdir($workdir, [], $path);
            self::log('Analizador: ' . $e->getMessage());
            throw new AiUnavailable(AiClient::MSG_NO_DISPONIBLE);
        }

        // Imágenes: ids y rutas relativas al workdir.
        $base = rtrim($workdir, '/') . '/';
        $img = [];
        $guardar = [];
        foreach ($imagenes as $i => $im) {
            $rel = ltrim(str_starts_with($im['archivo'], $base) ? substr($im['archivo'], strlen($base)) : basename($im['archivo']), '/');
            $img[] = ['id' => 'img' . ($i + 1), 'w' => (int)$im['w'], 'h' => (int)$im['h'], 'archivo' => $rel, 'hash' => $im['hash'],
                'logo' => !empty($im['logo_cand']), 'pagina' => isset($im['pagina']) ? (int)$im['pagina'] : null];
            $guardar[] = $im['archivo'];
        }
        $datos['imagenes'] = $img;
        $datos['conflictos'] = self::conflictos($orderId, $datos);
        self::limpiarWorkdir($workdir, $guardar, $path);
        return $datos;
    }

    /** Incrementa de forma atómica el contador de análisis; falla si ya hay 3. */
    private static function reservarIntento(int $orderId): void
    {
        $t = Db::t('orders');
        $st = Db::q('UPDATE ' . $t . ' SET analysis_count = analysis_count + 1 WHERE id = ? AND analysis_count < ?', [$orderId, self::MAX_POR_PEDIDO]);
        if ($st->rowCount() < 1) {
            throw new AiUnavailable(self::MSG_LIMITE);
        }
    }

    /**
     * Compara lo extraído con lo escrito por el cliente en el formulario.
     *
     * @return array<int,array{campo:string,form:string,pres:string}>
     */
    private static function conflictos(int $orderId, array $d): array
    {
        try {
            $raw = Db::val('SELECT data FROM ' . Db::t('orders') . ' WHERE id = ?', [$orderId]);
            $brief = is_string($raw) ? json_decode($raw, true) : null;
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($brief)) {
            return [];
        }
        $c = $brief['contacto'] ?? [];
        $pares = [
            ['nombre', $brief['negocio']['nombre'] ?? '', $d['nombre']['v'], false],
            ['telefono', $c['telefono'] ?? '', $d['contacto']['telefono']['v'], true],
            ['whatsapp', $c['whatsapp'] ?? '', $d['contacto']['whatsapp']['v'], true],
            ['correo', $brief['correo_contacto'] ?? '', $d['contacto']['correo']['v'], false],
            ['direccion', $c['direccion'] ?? '', $d['contacto']['direccion']['v'], false],
            ['horario', $c['horario'] ?? '', $d['contacto']['horario']['v'], false],
        ];
        $o = [];
        foreach ($pares as [$campo, $form, $pres, $tel]) {
            $form = TextClean::limpiar(is_scalar($form) ? (string)$form : '', 200);
            $pres = (string)$pres;
            if ($form === '' || $pres === '') {
                continue;
            }
            $a = $tel ? (preg_replace('/\D/', '', $form) ?? '') : mb_strtolower($form, 'UTF-8');
            $b = $tel ? (preg_replace('/\D/', '', $pres) ?? '') : mb_strtolower($pres, 'UTF-8');
            if ($tel) {
                // Ignora código de país (502) al comparar.
                $a = strlen($a) > 8 ? substr($a, -8) : $a;
                $b = strlen($b) > 8 ? substr($b, -8) : $b;
            }
            if ($a !== $b) {
                $o[] = ['campo' => $campo, 'form' => $form, 'pres' => $pres];
            }
        }
        return $o;
    }

    /** Borra todo de $workdir salvo las imágenes conservadas (y el archivo origen si está dentro). */
    private static function limpiarWorkdir(string $workdir, array $conservar, string $origen): void
    {
        $root = realpath($workdir);
        if ($root === false || !is_dir($root) || strlen($root) < 5) {
            return;
        }
        $keep = [];
        foreach ($conservar as $f) {
            $r = realpath($f);
            if ($r !== false) {
                $keep[$r] = true;
            }
        }
        $o = realpath($origen);
        if ($o !== false) {
            $keep[$o] = true;
        }
        self::barrer($root, $root, $keep);
    }

    private static function barrer(string $root, string $dir, array $keep): bool
    {
        $vacio = true;
        $it = @scandir($dir) ?: [];
        foreach ($it as $n) {
            if ($n === '.' || $n === '..') {
                continue;
            }
            $p = $dir . '/' . $n;
            if (is_link($p)) {
                @unlink($p);
                continue;
            }
            if (is_dir($p)) {
                if (!self::barrer($root, $p, $keep)) {
                    $vacio = false;
                } elseif ($p !== $root) {
                    @rmdir($p);
                }
                continue;
            }
            if (isset($keep[realpath($p) ?: $p])) {
                $vacio = false;
                continue;
            }
            @unlink($p);
        }
        return $vacio;
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
