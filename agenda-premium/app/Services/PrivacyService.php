<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Upload;

/** Derechos de las personas: exportar, eliminar (anonimizar) y aplicar el plazo de conservación. */
final class PrivacyService
{
    public const ERASED_NAME = 'Persona eliminada';

    /** Todo lo asociado a una persona, listo para entregar (JSON). Sin tokens ni rutas internas. */
    public static function exportPerson(int $clientId): array
    {
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            throw new \InvalidArgumentException('No encontramos a esa persona.');
        }
        $ids = self::bookingIds($client);
        $in = self::marks($ids);

        $bookings = $ids ? Db::all('SELECT * FROM bookings WHERE id IN (' . $in . ') ORDER BY starts_at', $ids) : [];
        foreach ($bookings as &$b) {
            unset($b['token'], $b['series_token'], $b['created_by']);
        }
        unset($b);

        $fileIds = self::fileIds($clientId, $ids);
        $files = $fileIds ? Db::all('SELECT original_name, mime, size, kind, created_at FROM files WHERE id IN (' . self::marks($fileIds) . ')', $fileIds) : [];
        $email = (string) ($client['email'] ?? '');
        $phone = (string) ($client['phone'] ?? '');

        return [
            'generado_utc' => Clock::utc(),
            'cliente' => $client,
            'citas' => $bookings,
            'respuestas' => $ids ? Db::all('SELECT booking_id, label, value FROM booking_answers WHERE booking_id IN (' . $in . ')', $ids) : [],
            'invitados' => $ids ? Db::all('SELECT booking_id, name, email, phone FROM booking_attendees WHERE booking_id IN (' . $in . ')', $ids) : [],
            'notas' => Db::all('SELECT body, created_at FROM client_notes WHERE client_id = ? ORDER BY created_at', [$clientId]),
            'pagos' => self::payments($clientId, $ids),
            'paquetes' => Db::all('SELECT package_id, remaining, expires_at, paid, purchased_at FROM client_packages WHERE client_id = ?', [$clientId]),
            'consentimientos' => Db::all('SELECT document, version, text_hash, ip_trunc, created_at FROM consents WHERE client_id = ?' . ($ids ? ' OR booking_id IN (' . $in . ')' : '') . ($email !== '' ? ' OR email = ?' : ''), array_merge([$clientId], $ids, $email !== '' ? [$email] : [])),
            'archivos' => $files,
            'lista_de_espera' => self::waitlistRows($email, $phone),
            'resenas' => $ids ? Db::all('SELECT client_name, rating, comment, status, submitted_at FROM reviews WHERE booking_id IN (' . $in . ')', $ids) : [],
        ];
    }

    /** Anonimiza a la persona y borra sus datos personales; conserva montos y estadísticas sin identidad. */
    public static function erasePerson(int $clientId): void
    {
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            throw new \InvalidArgumentException('No encontramos a esa persona.');
        }
        $files = Db::tx(static function () use ($client, $clientId): array {
            $ids = self::bookingIds($client);
            $files = self::fileIds($clientId, $ids);
            $paths = self::pathsOf($files);
            self::anonymizeBookings($ids);
            $email = (string) ($client['email'] ?? '');
            $phone = (string) ($client['phone'] ?? '');

            Db::delete('client_notes', 'client_id = ?', [$clientId]);
            Db::exec('UPDATE payments SET proof_file_id = NULL, reference = NULL, note = NULL WHERE client_id = ?' . ($ids ? ' OR booking_id IN (' . self::marks($ids) . ')' : ''), array_merge([$clientId], $ids));
            Db::delete('consents', 'client_id = ?' . ($ids ? ' OR booking_id IN (' . self::marks($ids) . ')' : '') . ($email !== '' ? ' OR email = ?' : ''), array_merge([$clientId], $ids, $email !== '' ? [$email] : []));
            Db::delete('message_queue', 'client_id = ?' . ($ids ? ' OR booking_id IN (' . self::marks($ids) . ')' : '') . ($phone !== '' ? ' OR phone = ?' : ''), array_merge([$clientId], $ids, $phone !== '' ? [$phone] : []));
            if ($email !== '') {
                Db::delete('email_queue', 'to_email = ?', [$email]);
                Db::delete('poll_votes', 'voter_email = ?', [$email]);
            }
            [$wWhere, $wParams] = self::contactMatch($email, $phone);
            if ($wWhere !== '') {
                Db::delete('waitlist', $wWhere, $wParams);
            }
            if ($files) {
                Db::delete('files', 'id IN (' . self::marks($files) . ')', $files);
            }
            Db::update('clients', [
                'name' => self::ERASED_NAME, 'email' => null, 'phone' => null, 'nit' => null, 'tags' => null,
                'source' => null, 'timezone' => null, 'anonymized_at' => Clock::utc(), 'updated_at' => Clock::utc(),
            ], 'id = ?', [$clientId]);
            Auth::audit('privacy.erase', 'client', $clientId, 'Datos personales eliminados y cliente anonimizado');
            return $paths;
        });
        self::unlinkFiles($files);
    }

    /**
     * Aplica el plazo de conservación (settings.retention_months; 0 = nunca).
     * @return int registros procesados (clientes, citas, archivos, consentimientos y colas)
     */
    public static function applyRetention(): int
    {
        $months = Settings::int('retention_months', 0);
        if ($months <= 0) {
            return 0;
        }
        $cutoff = (new \DateTimeImmutable('@' . Clock::now()))->modify('-' . $months . ' months')->format('Y-m-d H:i:s');
        $done = 0;

        // 1. Clientes sin actividad desde la fecha de corte
        $stale = Db::col(
            "SELECT c.id FROM clients c WHERE c.anonymized_at IS NULL AND c.created_at < ?
             AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.client_id = c.id AND (b.ends_at >= ? OR b.status IN ('pending','confirmed')))",
            [$cutoff, $cutoff]
        );
        foreach ($stale as $id) {
            self::erasePerson((int) $id);
            $done++;
        }

        // 2. Citas antiguas (finalizadas) de personas que siguen activas o sin ficha
        $old = Db::col(
            "SELECT id FROM bookings WHERE ends_at < ? AND status NOT IN ('pending','confirmed')
             AND (guest_name <> ? OR guest_email IS NOT NULL OR guest_phone IS NOT NULL OR notes IS NOT NULL OR internal_note IS NOT NULL)",
            [$cutoff, self::ERASED_NAME]
        );
        if ($old) {
            $files = Db::tx(static function () use ($old): array {
                $ids = array_map('intval', $old);
                $files = $ids ? Db::col('SELECT id FROM files WHERE owner_type = ? AND owner_id IN (' . self::marks($ids) . ')', array_merge(['booking'], $ids)) : [];
                $files = array_values(array_unique(array_merge($files, Db::col('SELECT file_id FROM booking_answers WHERE file_id IS NOT NULL AND booking_id IN (' . self::marks($ids) . ')', $ids))));
                $paths = self::pathsOf($files);
                self::anonymizeBookings($ids);
                if ($files) {
                    Db::delete('files', 'id IN (' . self::marks($files) . ')', $files);
                }
                return $paths;
            });
            self::unlinkFiles($files);
            $done += count($old);
        }

        // 3. Consentimientos, colas de mensajes y listas de espera antiguas
        $done += Db::delete('consents', 'created_at < ?', [$cutoff]);
        $done += Db::delete('email_queue', "status <> 'pending' AND created_at < ?", [$cutoff]);
        $done += Db::delete('message_queue', "status <> 'pending' AND created_at < ?", [$cutoff]);
        $done += Db::delete('waitlist', "status IN ('booked','expired','cancelled') AND created_at < ?", [$cutoff]);
        return $done;
    }

    // ------------------------------------------------------------------ internos

    /** Citas de la persona: por ficha, correo o teléfono. */
    private static function bookingIds(array $client): array
    {
        $where = ['client_id = ?'];
        $params = [(int) $client['id']];
        if (!empty($client['email'])) {
            $where[] = 'guest_email = ?';
            $params[] = $client['email'];
        }
        if (!empty($client['phone'])) {
            $where[] = 'guest_phone = ?';
            $params[] = $client['phone'];
        }
        return array_map('intval', Db::col('SELECT id FROM bookings WHERE ' . implode(' OR ', $where), $params));
    }

    /** Archivos de la persona: adjuntos de sus citas, respuestas con archivo, comprobantes de pago y archivos de la ficha. */
    private static function fileIds(int $clientId, array $bookingIds): array
    {
        $ids = Db::col("SELECT id FROM files WHERE owner_type = 'client' AND owner_id = ?", [$clientId]);
        if ($bookingIds) {
            $m = self::marks($bookingIds);
            $ids = array_merge(
                $ids,
                Db::col("SELECT id FROM files WHERE owner_type = 'booking' AND owner_id IN ($m)", $bookingIds),
                Db::col("SELECT file_id FROM booking_answers WHERE file_id IS NOT NULL AND booking_id IN ($m)", $bookingIds),
                Db::col("SELECT proof_file_id FROM payments WHERE proof_file_id IS NOT NULL AND (client_id = ? OR booking_id IN ($m))", array_merge([$clientId], $bookingIds))
            );
        } else {
            $ids = array_merge($ids, Db::col('SELECT proof_file_id FROM payments WHERE proof_file_id IS NOT NULL AND client_id = ?', [$clientId]));
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private static function payments(int $clientId, array $bookingIds): array
    {
        $sql = 'SELECT booking_id, amount, method, status, reference, note, created_at FROM payments WHERE client_id = ?';
        if ($bookingIds) {
            $sql .= ' OR booking_id IN (' . self::marks($bookingIds) . ')';
        }
        return Db::all($sql, array_merge([$clientId], $bookingIds));
    }

    /** @return array{0:string,1:array} condición SQL por correo y/o teléfono (vacía si no hay ninguno) */
    private static function contactMatch(string $email, string $phone): array
    {
        $where = [];
        $params = [];
        if ($email !== '') {
            $where[] = 'email = ?';
            $params[] = $email;
        }
        if ($phone !== '') {
            $where[] = 'phone = ?';
            $params[] = $phone;
        }
        return [implode(' OR ', $where), $params];
    }

    private static function waitlistRows(string $email, string $phone): array
    {
        [$where, $params] = self::contactMatch($email, $phone);
        return $where === '' ? [] : Db::all('SELECT event_type_id, name, email, phone, duration, want_date, status, created_at FROM waitlist WHERE ' . $where, $params);
    }

    /** Rutas físicas de archivos (se leen antes de borrar las filas). */
    private static function pathsOf(array $fileIds): array
    {
        if (!$fileIds) {
            return [];
        }
        return array_map(static fn (array $r): string => Upload::path($r), Db::all('SELECT stored_name FROM files WHERE id IN (' . self::marks($fileIds) . ')', $fileIds));
    }

    /** Quita los datos personales de las citas y borra respuestas, invitados y reseñas con nombre. Conserva importes y estados. */
    private static function anonymizeBookings(array $ids): void
    {
        if (!$ids) {
            return;
        }
        $m = self::marks($ids);
        Db::exec(
            "UPDATE bookings SET guest_name = ?, guest_email = NULL, guest_phone = NULL, notes = NULL, internal_note = NULL, cancel_reason = NULL, video_url = NULL, updated_at = ? WHERE id IN ($m)",
            array_merge([self::ERASED_NAME, Clock::utc()], $ids)
        );
        Db::delete('booking_answers', "booking_id IN ($m)", $ids);
        Db::delete('booking_attendees', "booking_id IN ($m)", $ids);
        Db::exec("UPDATE reviews SET client_name = ?, comment = NULL WHERE booking_id IN ($m)", array_merge([self::ERASED_NAME], $ids));
        Db::exec("UPDATE booking_history SET detail = NULL WHERE booking_id IN ($m)", $ids);
    }

    /** Borra los archivos físicos una vez confirmada la transacción. */
    private static function unlinkFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path) && !@unlink($path)) {
                Logger::error('No se pudo borrar un archivo al eliminar datos personales');
            }
        }
    }

    private static function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
