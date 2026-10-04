<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use Throwable;

/** WhatsApp: enlace de un toque (wa.me) y envío opcional por la API Cloud de WhatsApp Business. */
final class WhatsAppService
{
    private const DEFAULT_BASE = 'https://graph.facebook.com/v20.0';

    /** https://wa.me/<dígitos>?text=<texto> */
    public static function link(string $phone, string $text): string
    {
        $digits = Str::phone($phone) ?? (string) preg_replace('/\D+/', '', $phone);
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }

    /**
     * Envía un mensaje por la API Cloud. Con plantilla configurada (wa_api_template) el texto viaja como la
     * variable {{1}} de la plantilla; sin ella se envía como texto libre (solo admitido por WhatsApp dentro de las
     * 24 h posteriores al último mensaje del cliente).
     * @return array{ok:bool,error:?string,id:?string}
     */
    public static function send(string $phone, string $text): array
    {
        try {
            $token = self::token();
            $phoneId = trim((string) Settings::get('wa_api_phone_id', ''));
            $to = Str::phone($phone);
            if ($token === '' || $phoneId === '' || !preg_match('/^\d{1,20}$/', $phoneId)) {
                return self::fail('Falta configurar la API de WhatsApp (token e identificador del número) en Ajustes.');
            }
            if ($to === null) {
                return self::fail('El número de teléfono no es válido.');
            }
            $template = trim((string) Settings::get('wa_api_template', ''));
            if ($template !== '') {
                $payload = [
                    'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => trim((string) Settings::get('wa_api_lang', 'es')) ?: 'es'],
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => self::singleLine($text)]]]],
                    ],
                ];
            } else {
                $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => true, 'body' => $text]];
            }
            $base = rtrim((string) Config::get('wa_api_base', self::DEFAULT_BASE), '/');
            $r = SafeHttp::post(
                $base . '/' . $phoneId . '/messages',
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
                ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
                ['timeout' => 10]
            );
            $json = json_decode($r['body'], true);
            if ($r['ok']) {
                return ['ok' => true, 'error' => null, 'id' => is_array($json) ? ($json['messages'][0]['id'] ?? null) : null];
            }
            $apiMsg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
            $code = is_array($json) ? (int) ($json['error']['code'] ?? 0) : 0;
            Logger::error('WhatsApp API: respuesta ' . $r['status'] . ' código ' . $code . ' ' . mb_substr($apiMsg, 0, 200));
            if ($r['status'] === 401 || $code === 190) {
                return self::fail('WhatsApp rechazó el token: no es válido o ya venció. Genera uno nuevo en Meta y guárdalo en Ajustes.');
            }
            if ($code === 131047 || $code === 131026) {
                return self::fail('WhatsApp no permitió el mensaje: pasaron más de 24 h desde que la persona escribió. Usa una plantilla aprobada.');
            }
            if ($r['status'] === 0) {
                return self::fail((string) $r['error']);
            }
            return self::fail('WhatsApp no aceptó el mensaje' . ($apiMsg !== '' ? ': ' . mb_substr($apiMsg, 0, 200) : ' (error ' . $r['status'] . ').'));
        } catch (Throwable $e) {
            Logger::error('WhatsApp API: error inesperado', $e);
            return self::fail('No se pudo enviar el mensaje de WhatsApp por un problema interno.');
        }
    }

    private static function fail(string $msg): array
    {
        return ['ok' => false, 'error' => $msg, 'id' => null];
    }

    /** Las plantillas no admiten saltos de línea ni muchos espacios seguidos en una variable. */
    private static function singleLine(string $t): string
    {
        return trim((string) preg_replace('/\s{2,}/', ' ', preg_replace('/\s*\R+\s*/', ' · ', $t) ?? $t));
    }

    private static function token(): string
    {
        $t = (string) Settings::get('wa_api_token', '');
        if (strncmp($t, 'v1:', 3) === 0) {
            return Crypto::decrypt($t) ?? '';
        }
        return trim($t);
    }
}
