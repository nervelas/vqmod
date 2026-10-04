<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Str;

/**
 * Textos legales genéricos (aviso de privacidad, términos de uso y aviso de cookies) y registro de consentimientos.
 * Son plantillas de partida: el negocio debe revisarlas con su asesor y adaptarlas a su actividad.
 * Formato: texto plano con **negrita** y párrafos separados por línea en blanco (se muestra con Str::richText).
 */
final class LegalService
{
    /** Documentos administrables: clave => clave en settings. */
    private const DOCS = ['privacy' => 'privacy_text', 'terms' => 'terms_text', 'cookies' => 'cookies_notice'];
    /** Documentos que la persona acepta al reservar. */
    private const CONSENT_DOCS = ['privacy', 'terms'];

    /** @return array{privacy:string,terms:string,cookies:string} */
    public static function defaults(): array
    {
        $biz = trim((string) Settings::get('business_name', '')) ?: 'el negocio';
        $contact = self::contactLine();
        $months = Settings::int('retention_months', 0);
        $keep = $months > 0
            ? 'Conservamos tus datos durante ' . $months . ($months === 1 ? ' mes' : ' meses') . ' después de tu última cita. Pasado ese plazo los eliminamos o los anonimizamos, salvo que debamos guardar algún dato por una obligación legal o contable.'
            : 'Conservamos tus datos mientras mantengas una relación con nosotros o hasta que nos pidas eliminarlos. Algunos datos de pagos pueden guardarse por más tiempo si una obligación legal o contable lo exige.';

        $privacy = <<<TXT
**Aviso de privacidad de {$biz}**

Este aviso explica qué datos personales recogemos cuando reservas una cita con {$biz}, para qué los usamos y cómo puedes ejercer tus derechos.

**1. Quién es responsable de tus datos**
{$biz} es quien decide cómo se usan los datos que nos das al reservar. {$contact}

**2. Qué datos recogemos**
Recogemos tu nombre, correo electrónico y teléfono; tu NIT, solo si decides darlo; las respuestas que escribes en el formulario de reserva; las notas que nos dejas; la zona horaria de tu dispositivo; y los datos de la cita (servicio, fecha, hora y profesional). Si pagas o dejas un anticipo, guardamos el monto, el método y la referencia del pago, pero nunca los datos completos de tu tarjeta. También guardamos la fecha de tu consentimiento y una versión abreviada de tu dirección IP.

**3. Para qué los usamos**
Para agendar y gestionar tu cita, enviarte confirmaciones y recordatorios por correo o WhatsApp, atenderte mejor, emitir comprobantes cuando corresponda, y entender de forma agregada cómo se usa nuestra agenda. Nuestra analítica es propia: no usa cookies ni servicios de terceros.

**4. Tu consentimiento**
Al marcar la casilla de aceptación en el formulario de reserva nos autorizas a tratar tus datos para estas finalidades. Guardamos constancia de la versión del texto que aceptaste.

**5. Con quién los compartimos**
No vendemos ni cedemos tus datos. Pueden acceder a ellos las personas de {$biz} que te atienden y los proveedores que nos prestan servicios de alojamiento, envío de correo y mensajería, quienes solo los tratan por encargo nuestro y para ese fin.

**6. Cuánto tiempo los conservamos**
{$keep}

**7. Tus derechos**
Puedes pedirnos acceso a tus datos, que los corrijamos, que los eliminemos o que dejemos de usarlos para ciertos fines. Escríbenos o llámanos con tu nombre y el correo o teléfono con el que reservaste, y te responderemos en un plazo razonable. {$contact}

**8. Seguridad**
Protegemos tu información con acceso restringido por usuario y contraseña, conexiones cifradas y copias de seguridad. Ningún sistema es infalible, pero trabajamos para reducir los riesgos.

**9. Cambios a este aviso**
Si actualizamos este aviso, publicaremos la nueva versión en esta página. Siempre indicaremos la versión vigente al momento de tu reserva.
TXT;

        $terms = <<<TXT
**Términos de uso del servicio de reservas de {$biz}**

Al reservar una cita en línea con {$biz} aceptas estos términos.

**1. Qué ofrece este servicio**
Esta página te permite ver horarios disponibles y reservar citas con {$biz}. La disponibilidad se muestra en tiempo real, pero puede cambiar mientras completas tu reserva; si el horario ya fue tomado, te lo indicaremos para que elijas otro.

**2. Tu reserva**
Debes darnos datos verdaderos y un medio de contacto válido. Algunas citas requieren aprobación; en ese caso la reserva queda pendiente hasta que {$biz} la confirme. Recibirás un correo con el resultado.

**3. Cambios y cancelaciones**
Puedes reprogramar o cancelar tu cita desde el enlace de tu correo dentro del plazo que indica la política de cada servicio. Fuera de ese plazo, {$biz} puede cobrar la cita o el anticipo, según la política publicada.

**4. Puntualidad**
Te pedimos llegar o conectarte a la hora acordada. Si llegas tarde, es posible que debamos acortar la cita para no afectar a quien sigue.

**5. Pagos y anticipos**
Si el servicio tiene precio o anticipo, verás el monto antes de confirmar. Los pagos se acreditan según el método que elijas y {$biz} te entregará el comprobante que corresponda.

**6. Inasistencias**
Si no asistes sin avisar, {$biz} puede pedir un anticipo en futuras reservas o limitar nuevas reservas en línea.

**7. Uso adecuado**
No puedes usar este servicio para reservar de forma masiva, automatizada o con datos de otras personas sin su permiso.

**8. Responsabilidad**
{$biz} se esfuerza por mantener el servicio disponible y exacto, pero no garantiza que funcione sin interrupciones. La atención profesional se rige por lo que acuerdes directamente con {$biz}.

**9. Datos personales**
El tratamiento de tus datos se explica en el aviso de privacidad vigente.

**10. Cambios y contacto**
Podemos actualizar estos términos; aplicarán a las reservas hechas después del cambio. {$contact}
TXT;

        $cookies = <<<TXT
**Aviso sobre cookies de {$biz}**

Este sitio solo usa cookies y almacenamiento local estrictamente necesarios para funcionar. Por eso no te mostramos un banner de aceptación.

**Qué usamos**
Una cookie de sesión y un código de seguridad, que protegen formularios y mantienen abierta la sesión del equipo cuando entra al panel. Una preferencia de tema (oscuro o claro) guardada en tu navegador. Un código de seguridad de la reserva, que evita envíos automáticos.

**Qué no usamos**
No usamos cookies de publicidad ni de seguimiento, ni servicios de analítica de terceros. Medimos las visitas con una analítica propia que no guarda cookies ni identifica a las personas.

**Cómo controlarlas**
Puedes borrar o bloquear las cookies desde la configuración de tu navegador. Si bloqueas las necesarias, es posible que algunas funciones, como el panel del equipo, no funcionen.
TXT;

        return ['privacy' => $privacy, 'terms' => $terms, 'cookies' => $cookies];
    }

    /**
     * Guarda los textos (claves privacy, terms, cookies; las que falten se conservan).
     * Incrementa legal_version cuando algún texto cambia, y guarda el SHA-256 de cada documento.
     */
    public static function save(array $docs): void
    {
        Db::tx(static function () use ($docs): void {
            $changed = false;
            $first = true;
            $values = [];
            foreach (self::DOCS as $doc => $setting) {
                $old = (string) Settings::get($setting, '');
                $first = $first && $old === '';
                $new = isset($docs[$doc]) && is_string($docs[$doc]) ? trim(str_replace("\r\n", "\n", $docs[$doc])) : $old;
                if (mb_strlen($new) > 100000) {
                    throw new \InvalidArgumentException('El texto legal es demasiado largo (máximo 100,000 caracteres).');
                }
                $changed = $changed || $new !== $old;
                $values[$setting] = $new;
                $values['legal_hash_' . $doc] = hash('sha256', $new);
            }
            $version = max(1, Settings::int('legal_version', 1));
            if ($changed && !$first) {
                $version++;
            }
            $values['legal_version'] = (string) $version;
            Settings::setMany($values);
        });
    }

    /**
     * Texto vigente que la persona acepta al reservar.
     * @return array{version:int,label:string,hashes:array<string,string>,texts:array<string,string>}
     */
    public static function consentText(): array
    {
        $texts = [];
        $hashes = [];
        foreach (self::DOCS as $doc => $setting) {
            $texts[$doc] = (string) Settings::get($setting, '');
            $hashes[$doc] = hash('sha256', $texts[$doc]);
        }
        return [
            'version' => max(1, Settings::int('legal_version', 1)),
            'label' => 'Acepto el aviso de privacidad y los términos de uso.',
            'hashes' => $hashes,
            'texts' => $texts,
        ];
    }

    /** Registra el consentimiento: fecha UTC, IP truncada, versión y hash vigentes. No guarda nada más. */
    public static function recordConsent(?int $clientId, ?int $bookingId, ?string $email, string $ip): void
    {
        $c = self::consentText();
        $email = $email !== null && trim($email) !== '' ? Str::clean(strtolower(trim($email)), 190) : null;
        $ipTrunc = Str::ipTrunc($ip);
        $now = Clock::utc();
        Db::tx(static function () use ($c, $clientId, $bookingId, $email, $ipTrunc, $now): void {
            foreach (self::CONSENT_DOCS as $doc) {
                Db::insert('consents', [
                    'client_id' => $clientId,
                    'booking_id' => $bookingId,
                    'email' => $email,
                    'document' => $doc,
                    'version' => $c['version'],
                    'text_hash' => $c['hashes'][$doc],
                    'ip_trunc' => $ipTrunc !== '' ? $ipTrunc : null,
                    'created_at' => $now,
                ]);
            }
        });
    }

    private static function contactLine(): string
    {
        $parts = [];
        $email = trim((string) Settings::get('email', ''));
        $phone = trim((string) Settings::get('phone', ''));
        $wa = trim((string) Settings::get('whatsapp', ''));
        $addr = trim((string) Settings::get('address', ''));
        if ($email !== '') {
            $parts[] = 'correo ' . $email;
        }
        if ($phone !== '') {
            $parts[] = 'teléfono ' . Str::phoneDisplay($phone);
        }
        if ($wa !== '' && $wa !== $phone) {
            $parts[] = 'WhatsApp ' . Str::phoneDisplay($wa);
        }
        if ($addr !== '') {
            $parts[] = 'dirección ' . $addr;
        }
        return $parts ? 'Puedes contactarnos por ' . self::joinList($parts) . '.' : 'Puedes contactarnos directamente en el local o por los medios publicados en esta página.';
    }

    private static function joinList(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items) . ' o ' . $last : (string) $last;
    }
}
