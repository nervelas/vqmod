<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Mailer;
use S5\Core\Sanitize;
use S5\Core\Settings;

/** Correos del sistema (dueño y cliente). Todo texto en español. */
final class Notifier
{
    private static function h($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    public static function owner(string $subject, string $text, ?int $orderId = null): void
    {
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

    public static function previewReady(array $o): void
    {
        $to = Sanitize::email((string) $o['client_email']);
        if ($to === '') {
            return;
        }
        $url = Settings::portalUrl() . '/continuar/' . $o['token'];
        $body = '<p>¡Su vista previa está lista!</p><p><a href="' . self::h($url) . '" style="display:inline-block;background:#b8923a;color:#111;padding:12px 20px;border-radius:8px;text-decoration:none">Ver mi vista previa</a></p>'
            . '<p>Este enlace es personal: guárdelo para retomar o aprobar su web. Su vista previa se conserva ' . Settings::int('preview_days', 15) . ' días.</p>';
        Mailer::send($to, 'Su vista previa está lista', Mailer::wrap('Su vista previa está lista', $body));
    }

    public static function paymentReceived(array $o): void
    {
        self::owner('Comprobante de pago por revisar', 'El cliente ' . ($o['business_name'] ?: '#' . $o['id']) . ' subió un comprobante por ' . money_q(Orders::total($o)) . '. Revise y apruebe o rechace en el panel.', (int) $o['id']);
    }

    public static function paymentRejected(array $o, string $reason): void
    {
        $to = Sanitize::email((string) $o['client_email']);
        if ($to === '') {
            return;
        }
        $url = Settings::portalUrl() . '/pago/' . $o['token'];
        $body = '<p>No pudimos confirmar su pago.</p><p><strong>Motivo:</strong> ' . self::h($reason) . '</p><p><a href="' . self::h($url) . '">Subir un nuevo comprobante</a></p>';
        Mailer::send($to, 'No pudimos confirmar su pago', Mailer::wrap('Pago no confirmado', $body));
    }

    public static function published(array $o, string $siteUrl, string $resetUrl, string $adminUrl): void
    {
        $to = Sanitize::email((string) $o['client_email']);
        if ($to === '') {
            return;
        }
        $body = '<p>¡Su web ya está publicada!</p><p><a href="' . self::h($siteUrl) . '">' . self::h($siteUrl) . '</a></p>'
            . '<p>Para administrarla, defina su propia contraseña aquí (el enlace vence en unos días):</p>'
            . '<p><a href="' . self::h($resetUrl) . '" style="display:inline-block;background:#b8923a;color:#111;padding:12px 20px;border-radius:8px;text-decoration:none">Definir mi contraseña</a></p>'
            . '<p>Luego entre a <a href="' . self::h($adminUrl) . '">' . self::h($adminUrl) . '</a>. En el panel verá una sección llamada <strong>INSTRUCCIONES</strong> que le explica cómo cambiar cada cosa.</p>'
            . '<p><strong>Sus correos corporativos y su dominio .com</strong> los configuramos manualmente: estarán listos en un máximo de <strong>2 días hábiles</strong>.</p>';
        Mailer::send($to, 'Su web está publicada', Mailer::wrap('¡Su web está publicada!', $body));
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
        if (Settings::bool('notify_client_renewal')) {
            $to = Sanitize::email((string) $o['client_email']);
            if ($to !== '') {
                Mailer::send($to, 'Su web vence pronto', Mailer::wrap('Renovación', '<p>Su servicio vence el ' . self::h($o['renewal_at']) . '. Escríbanos por WhatsApp para renovar.</p>'));
            }
        }
    }
}
