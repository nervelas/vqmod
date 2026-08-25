<?php
declare(strict_types=1);

namespace Fel\Certificador;

use Fel\Core\Config;
use Fel\Core\Logger;

/**
 * Cliente HTTP minimo sobre cURL, con reintentos para fallos de red.
 * No reintenta errores de negocio (4xx): esos son rechazos del certificador.
 */
final class HttpClient
{
    /**
     * @param array<string,string> $cabeceras
     * @return array{codigo:int,cuerpo:string,error:string}
     */
    public static function post(string $url, string $cuerpo, array $cabeceras = []): array
    {
        $intentos    = max(1, (int) Config::get('http.reintentos', 3));
        $esperaBase  = max(1, (int) Config::get('http.espera_reintento', 2));
        $ultimo      = ['codigo' => 0, 'cuerpo' => '', 'error' => 'sin intentos'];

        for ($intento = 1; $intento <= $intentos; $intento++) {
            $ultimo = self::ejecutar($url, $cuerpo, $cabeceras);

            $fallaRed = $ultimo['codigo'] === 0 || $ultimo['codigo'] >= 500;

            if (!$fallaRed) {
                return $ultimo;
            }

            Logger::error('Fallo de comunicacion con el certificador, reintentando', [
                'url'     => $url,
                'intento' => $intento,
                'codigo'  => $ultimo['codigo'],
                'error'   => $ultimo['error'],
            ]);

            if ($intento < $intentos) {
                sleep($esperaBase ** $intento);
            }
        }

        return $ultimo;
    }

    /**
     * @param array<string,string> $cabeceras
     * @return array{codigo:int,cuerpo:string,error:string}
     */
    private static function ejecutar(string $url, string $cuerpo, array $cabeceras): array
    {
        if (!function_exists('curl_init')) {
            return ['codigo' => 0, 'cuerpo' => '', 'error' => 'La extension cURL de PHP no esta habilitada.'];
        }

        $lineas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $lineas[] = $nombre . ': ' . $valor;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => $lineas,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) Config::get('http.timeout', 60),
            CURLOPT_CONNECTTIMEOUT => (int) Config::get('http.timeout_conexion', 15),
            // Nunca deshabilite la verificacion TLS en produccion: la conexion
            // con el certificador transporta datos fiscales del contribuyente.
            CURLOPT_SSL_VERIFYPEER => (bool) Config::get('http.verificar_tls', true),
            CURLOPT_SSL_VERIFYHOST => Config::get('http.verificar_tls', true) ? 2 : 0,
            CURLOPT_ENCODING       => '',
        ]);

        $respuesta = curl_exec($ch);
        $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        return [
            'codigo' => $codigo,
            'cuerpo' => $respuesta === false ? '' : (string) $respuesta,
            'error'  => $error,
        ];
    }
}
