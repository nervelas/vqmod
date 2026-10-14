<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Http;
use S5\Core\Log;
use S5\Core\Mailer;
use S5\Core\Sanitize;
use S5\Core\Settings;

/** Avisos del sistema: TODOS van al dueño (WhatsApp; correo solo como respaldo). Nunca se escribe al cliente. Todo texto en español. */
final class Notifier
{
    private static function h($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Número del dueño para los avisos: 8 dígitos de Guatemala o internacional sin «+». */
    private static function waNumber(): string
    {
        $n = preg_replace('/\D/', '', (string) Settings::get('wa_notify_number', '50232040756')) ?? '';
        return strlen($n) === 8 ? '502' . $n : $n;
    }

    /** Envía un aviso por WhatsApp al dueño. Devuelve true si el proveedor lo aceptó. */
    public static function whatsapp(string $text): bool
    {
        $prov = (string) Settings::get('wa_notify_provider', 'callmebot');
        $key = (string) Settings::get('wa_notify_key', '');
        $num = self::waNumber();
        if ($prov === 'none' || $key === '' || $num === '') {
            return false;
        }
        $text = mb_substr($text, 0, 1500, 'UTF-8');
        $base = (string) getenv('S5_WA_URL');
        try {
            if ($prov === 'cloud') {
                $pid = preg_replace('/\D/', '', (string) Settings::get('wa_notify_phone_id', '')) ?? '';
                if ($pid === '') {
                    return false;
                }
                $r = Http::request('POST', ($base !== '' ? $base : 'https://graph.facebook.com') . '/v20.0/' . $pid . '/messages', [
                    'timeout' => 20,
                    'headers' => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
                    'body' => json_encode(['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'text', 'text' => ['body' => $text]], JSON_UNESCAPED_UNICODE),
                ]);
                return $r['status'] >= 200 && $r['status'] < 300;
            }
            $r = Http::request('GET', ($base !== '' ? $base : 'https://api.callmebot.com') . '/whatsapp.php?phone=' . $num . '&text=' . rawurlencode($text) . '&apikey=' . rawurlencode($key), ['timeout' => 20]);
            return $r['status'] >= 200 && $r['status'] < 300 && stripos($r['body'], 'error') === false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp de avisos: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * TODO aviso del sistema (vista previa lista, publicada, pago, fallos…) va al dueño por WhatsApp.
     * Nunca se escribe al cliente. Si el WhatsApp no está configurado o falla, se usa el correo del dueño como respaldo.
     */
    public static function owner(string $subject, string $text, ?int $orderId = null): void
    {
        $msg = '*[Servicom] ' . $subject . "*\n" . $text;
        if ($orderId) {
            $msg .= "\n" . Settings::portalUrl() . '/admin/pedido/' . $orderId;
        }
        if (self::whatsapp($msg)) {
            return;
        }
        Log::audit('wa_aviso_no_enviado', $subject, $orderId);
        $to = (string) Settings::get('owner_email', '');
        if ($to === '') {
            return;
        }
        $body = '<p>' . nl2br(self::h($text)) . '</p>';
        if ($orderId) {
            $body .= '<p><a href="' . self::h(Settings::portalUrl() . '/admin/pedido/' . $orderId) . '">Abrir el pedido #' . (int) $orderId . '</a></p>';
        }
        Mailer::send($to, '[Servicom] ' . $subject, Mailer::wrap($subject, $body));
    }

    private static function cliente(array $o): string
    {
        $b = [];
        try { $b = Orders::data($o); } catch (\Throwable $e) { $b = []; }
        $n = (string) ($o['business_name'] ?: ($b['negocio']['nombre'] ?? '') ?: '#' . $o['id']);
        $tel = (string) ($b['contacto']['whatsapp'] ?? $b['contacto']['telefono'] ?? '');
        $mail = (string) ($o['client_email'] ?? '');
        return $n . ($tel !== '' ? ' · Tel/WhatsApp: ' . $tel : '') . ($mail !== '' ? ' · Correo: ' . $mail : '');
    }

    public static function previewReady(array $o): void
    {
        $url = Settings::portalUrl() . '/continuar/' . $o['token'];
        self::owner('Vista previa lista', 'Cliente: ' . self::cliente($o) . "\nEnlace privado de la vista previa (para enviárselo usted): " . $url, (int) $o['id']);
    }

    public static function paymentReceived(array $o): void
    {
        self::owner('Comprobante de pago por revisar', 'El cliente ' . ($o['business_name'] ?: '#' . $o['id']) . ' subió un comprobante por ' . money_q(Orders::total($o)) . '. Revise y apruebe o rechace en el panel.', (int) $o['id']);
    }

    public static function paymentRejected(array $o, string $reason): void
    {
        $url = Settings::portalUrl() . '/pago/' . $o['token'];
        self::owner('Pago no confirmado', 'Cliente: ' . self::cliente($o) . "\nMotivo: " . $reason . "\nEnlace para que suba otro comprobante (envíeselo usted): " . $url, (int) $o['id']);
    }

    public static function published(array $o, string $siteUrl, string $resetUrl, string $adminUrl): void
    {
        self::owner('Web publicada', 'Cliente: ' . self::cliente($o) . "\nWeb: " . $siteUrl . "\nPanel: " . $adminUrl . "\nEnlace para que el cliente defina su contraseña (envíeselo usted): " . $resetUrl . "\nCorreos y dominio .com: se configuran manualmente (máx. 2 días hábiles).", (int) $o['id']);
    }

    public static function ownerPublished(array $o, array $b): void
    {
        $lines = ['Web publicada: ' . ($o['fqdn'] ?? ''), 'Cliente: ' . ($b['negocio']['nombre'] ?? ''), 'Correo de contacto: ' . ($b['correo_contacto'] ?? ''), 'WhatsApp: ' . ($b['contacto']['whatsapp'] ?? '')];
        $dom = !empty($b['dominio']['tiene']) ? 'Ya tiene dominio: ' . $b['dominio']['dominio'] : 'Dominio nuevo deseado: ' . ($b['dominio']['deseado'] ?: '(no indicó)');
        $lines[] = $dom;
        $mails = $b['correos'] ?: ['info'];
        $dd = !empty($b['dominio']['tiene']) ? $b['dominio']['dominio'] : ($b['dominio']['deseado'] ?: '(dominio por definir)');
        $lines[] = 'Correos solicitados: ' . implode(', ', array_map(fn($m) => $m . '@' . $dd, $mails));
        if (!empty($o['card_extra'])) {
            $lines[] = 'EXTRA TARJETA solicitado (activar Visanet/Epay manualmente).';
        }
        self::owner('Web publicada: dominio y correos por crear', implode("\n", $lines), (int) $o['id']);
    }

    public static function renewalSoon(array $o): void
    {
        self::owner('Renovación próxima', ($o['business_name'] ?: $o['fqdn']) . ' vence el ' . $o['renewal_at'] . '.', (int) $o['id']);
    }
}
