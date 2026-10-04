<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Secret;
use Aurea\Core\Settings;

/**
 * Módulo OPCIONAL: WhatsApp Business Cloud API (Meta). Requiere cuenta de Meta del cliente,
 * token permanente, ID del número y plantillas aprobadas por Meta. Desactivado por defecto.
 * Envía mensajes de plantilla (type=template) con el texto completo como único parámetro {{1}}.
 */
final class WhatsAppCloud
{
    private static function base(): string
    {
        // Configurable solo por el archivo config.php (pruebas); por defecto el dominio oficial de Meta
        return rtrim((string)config('wa_api_base', 'https://graph.facebook.com'), '/');
    }

    /** @return array{0:bool,1:string} */
    public static function send(array $q): array
    {
        $token = Secret::decrypt((string)Settings::get('wa_token', ''));
        $phoneId = preg_replace('/\D+/', '', (string)Settings::get('wa_phone_id', '')) ?? '';
        if ($token === '' || $phoneId === '') { return [false, 'WhatsApp API sin token o ID de número']; }
        $tpl = Db::one('SELECT wa_template_name FROM message_templates WHERE code=?', [$q['type']]);
        $name = trim((string)($tpl['wa_template_name'] ?? ''));
        if ($name === '') { return [false, 'La plantilla "' . $q['type'] . '" no tiene nombre de plantilla aprobada de Meta']; }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', (string)$q['recipient']),
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => (string)Settings::get('wa_template_lang', 'es')],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr(str_replace(["\n", "\t"], ' ', (string)$q['body']), 0, 1000)]]]],
            ],
        ];
        $url = self::base() . '/' . rawurlencode((string)Settings::get('wa_api_version', 'v21.0')) . '/' . $phoneId . '/messages';
        return self::post($url, $token, $payload);
    }

    private static function post(string $url, string $token, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
            'header' => "Authorization: Bearer " . $token . "\r\nContent-Type: application/json\r\n",
            'content' => $json,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) { return [false, 'No se pudo contactar la API de WhatsApp']; }
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; }
        }
        if ($code >= 200 && $code < 300) { return [true, '']; }
        $j = json_decode((string)$res, true);
        $msg = is_array($j) ? (string)($j['error']['message'] ?? '') : '';
        return [false, 'API WhatsApp HTTP ' . $code . ($msg !== '' ? ': ' . $msg : '')];
    }
}
