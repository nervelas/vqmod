<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Log;
use S5\Core\Settings;
use S5\Provision\HostDriver;
use S5\Provision\ProvisionException;

/** Pago, publicación, suspensión, renovación, dominio propio y borrado seguro. */
final class Lifecycle
{
    private static function stageMini(HostDriver $d, array $o, string $purpose): string
    {
        $job = substr(bin2hex(random_bytes(8)), 0, 12);
        $d->stageJob((string) $o['site_path'], $job, json_encode(['version' => 1, 'purpose' => $purpose], JSON_UNESCAPED_UNICODE), [], bin2hex(random_bytes(24)));
        return $job;
    }

    private static function runMini(array $o, string $step, array $args): array
    {
        $d = Hosts::driver((int) $o['host_id']);
        $job = self::stageMini($d, $o, $step);
        try {
            $r = $d->provision((string) $o['site_path'], $job, $step, $args);
        } finally {
            try {
                $d->removeJob((string) $o['site_path'], $job);
                $d->removeProvisionScript((string) $o['site_path']);
            } catch (\Throwable $e) {
                Log::error('limpieza ' . $step . ': ' . $e->getMessage());
            }
        }
        if (empty($r['ok'])) {
            throw new ProvisionException((string) ($r['error'] ?? 'Falló el paso ' . $step), !empty($r['retry']));
        }
        return $r['data'] ?? [];
    }

    // ------------------------------------------------------------ pago
    public static function submitPayment(array $o, string $nameNit, int $proofFileId): void
    {
        Orders::set((int) $o['id'], ['status' => Orders::ST_PAGO, 'pay_name' => mb_substr($nameNit, 0, 190), 'pay_file_id' => $proofFileId, 'pay_reject_reason' => null]);
        Log::audit('comprobante_subido', '', (int) $o['id']);
        Notifier::paymentReceived(Orders::byId((int) $o['id']));
    }

    public static function reject(int $id, string $reason): void
    {
        $o = Orders::byId($id);
        if (!$o || $o['status'] !== Orders::ST_PAGO) {
            throw new \RuntimeException('El pedido no está pendiente de revisión de pago.');
        }
        $reason = mb_substr(trim($reason), 0, 400) ?: 'El comprobante no pudo validarse.';
        Orders::set($id, ['status' => Orders::ST_LISTA, 'pay_reject_reason' => $reason]);
        Log::audit('pago_rechazado', $reason, $id);
        Notifier::paymentRejected($o, $reason);
    }

    /** Aprueba el pago y publica. Devuelve datos útiles para el dueño. */
    public static function approve(int $id): array
    {
        $o = Orders::byId($id);
        if (!$o || $o['status'] !== Orders::ST_PAGO) {
            throw new \RuntimeException('El pedido no está pendiente de revisión de pago.');
        }
        $b = Orders::data($o);
        $email = $b['correo_contacto'] ?: '';
        $data = self::runMini($o, 'publish', ['email' => $email !== '' ? $email : ('cliente-' . $id . '@' . Settings::baseDomain()), 'name' => (string) $b['negocio']['nombre']]);
        $site = Orders::previewUrl($o, false);
        $resetUrl = (string) ($data['reset_url'] ?? '');
        $adminUrl = (string) ($data['admin_url'] ?? rtrim($site, '/') . '/wp-admin/');
        Orders::set($id, [
            'status' => Orders::ST_PUBLICADA, 'paid_at' => Db::now(), 'published_at' => Db::now(),
            'renewal_at' => gmdate('Y-m-d', time() + 365 * 86400), 'renewal_notified' => 0, 'expires_at' => null,
            'emails_requested' => json_encode($b['correos'] ?: ['info'], JSON_UNESCAPED_UNICODE),
            'domain_requested' => $b['dominio']['tiene'] ? $b['dominio']['dominio'] : $b['dominio']['deseado'],
        ]);
        // La presentación original y todo lo ya importado a WordPress se eliminan; se conserva solo el comprobante.
        foreach (['presentacion', 'presentacion_img', 'foto', 'logo'] as $k) {
            Files::purgeKind($id, $k);
        }
        $work = Files::root() . '/work/' . $id;
        if (is_dir($work) && Fs::inside($work, Files::root() . '/work')) {
            Fs::rmTreeSafe($work, [Files::root() . '/work']);
        }
        Orders::set($id, ['analysis' => null]);
        Log::audit('publicada', $site, $id);
        $o = Orders::byId($id);
        if ($email !== '') {
            Notifier::published($o, $site, $resetUrl, $adminUrl);
        }
        Notifier::ownerPublished($o, $b);
        return ['reset_url' => $resetUrl, 'admin_url' => $adminUrl, 'site_url' => $site, 'email_sent' => $email !== ''];
    }

    // ------------------------------------------------------------ renovación / suspensión
    public static function renewed(int $id): void
    {
        $o = Orders::byId($id);
        if (!$o) { return; }
        $base = $o['renewal_at'] ? strtotime((string) $o['renewal_at'] . ' UTC') : time();
        $new = gmdate('Y-m-d', strtotime('+1 year', max($base, time() - 86400 * 365)));
        $st = $o['status'] === Orders::ST_VENCIDA ? Orders::ST_PUBLICADA : $o['status'];
        Orders::set($id, ['renewal_at' => $new, 'renewal_notified' => 0, 'status' => $st]);
        Log::audit('renovada', $new, $id);
    }

    public static function suspend(int $id): void
    {
        $o = Orders::byId($id);
        if (!$o || !$o['site_path']) { throw new \RuntimeException('El pedido no tiene sitio.'); }
        $d = Hosts::driver((int) $o['host_id']);
        $https = Settings::scheme() === 'https';
        $page = '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sitio suspendido</title><body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#111;color:#f3ead2;text-align:center;padding:24px"><div><h1 style="font-weight:600">Sitio temporalmente suspendido</h1><p>Para reactivarlo, comuníquese con Servicom.</p></div></body></html>';
        $d->writeFile((string) $o['site_path'], 'sc-suspendido.html', $page, 0644);
        $d->writeFile((string) $o['site_path'], 'sc-suspendido.flag', '1', 0644);
        $d->writeFile((string) $o['site_path'], '.htaccess', WpFiles::htaccess($https, true), 0644);
        Orders::setStatus($id, Orders::ST_SUSPENDIDA);
        Log::audit('suspendida', '', $id);
    }

    public static function reactivate(int $id): void
    {
        $o = Orders::byId($id);
        if (!$o || !$o['site_path']) { throw new \RuntimeException('El pedido no tiene sitio.'); }
        $d = Hosts::driver((int) $o['host_id']);
        $d->writeFile((string) $o['site_path'], '.htaccess', WpFiles::htaccess(Settings::scheme() === 'https', false), 0644);
        $d->deleteFile((string) $o['site_path'], 'sc-suspendido.html');
        $d->deleteFile((string) $o['site_path'], 'sc-suspendido.flag');
        Orders::setStatus($id, Orders::ST_PUBLICADA);
        Log::audit('reactivada', '', $id);
    }

    // ------------------------------------------------------------ dominio propio
    public static function assignDomain(int $id, string $domain): array
    {
        $o = Orders::byId($id);
        if (!$o || !in_array($o['status'], [Orders::ST_PUBLICADA, Orders::ST_VENCIDA], true)) {
            throw new \RuntimeException('Solo se puede asignar dominio a webs publicadas.');
        }
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = preg_replace('#^www\.#', '', explode('/', $domain)[0]) ?? '';
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain)) {
            throw new \RuntimeException('El dominio no es válido.');
        }
        $base = Settings::baseDomain();
        $protected = array_filter(array_map('trim', array_merge([$base], explode(',', strtolower((string) Settings::get('dominios_protegidos', ''))))));
        $isProtected = false;
        foreach ($protected as $pd) {
            if ($domain === $pd || str_ends_with($domain, '.' . $pd)) {
                $isProtected = true;
            }
        }
        if ($isProtected) {
            throw new \RuntimeException('Ese dominio pertenece a Servicom; no se puede asignar a un cliente.');
        }
        if ((int) Db::val('SELECT COUNT(*) FROM ' . Db::t('orders') . ' WHERE domain_assigned=? AND id<>? AND status<>?', [$domain, $id, Orders::ST_ELIMINADA]) > 0) {
            throw new \RuntimeException('Ese dominio ya está asignado a otra web.');
        }
        $d = Hosts::driver((int) $o['host_id']);
        $docroot = (string) $o['site_path'];
        // 1) comprobar que el DNS ya apunta a este hosting (evita dejar la web inalcanzable por la redirección canónica)
        $tok = bin2hex(random_bytes(8));
        $rel = '.well-known/sc-verify-' . $tok . '.txt';
        $d->writeFile($docroot, $rel, $tok, 0644);
        $verified = false;
        try {
            if (!getenv('S5_SKIP_DNS_CHECK')) {
                $u = $domain . '/' . $rel;
                foreach (['https://', 'http://'] as $sch) {
                    $r = \S5\Core\Http::request('GET', $sch . $u, ['timeout' => 12, 'follow' => true, 'verify' => $sch === 'https://']);
                    if ($r['status'] === 200 && trim($r['body']) === $tok) { $verified = true; break; }
                }
            } else {
                $verified = true;
            }
        } finally {
            $d->deleteFile($docroot, $rel);
        }
        if (!$verified) {
            throw new \RuntimeException('El dominio todavía no apunta a este hosting (DNS). Pida al cliente apuntar el dominio al hosting y vuelva a intentarlo.');
        }
        // 2) conectar el dominio en cPanel
        $d->attachDomain($domain, (string) $o['slug'], $docroot);
        // 3) reemplazar URLs en WordPress
        $from = Orders::previewUrl($o, false);
        $to = Settings::scheme() . '://' . $domain . Orders::portSuffix();
        $res = self::runMini($o, 'replace_domain', ['from' => $from, 'to' => $to]);
        Orders::set($id, ['domain_assigned' => $domain]);
        Log::audit('dominio_asignado', $domain, $id);
        return $res;
    }

    // ------------------------------------------------------------ borrado seguro
    /** ¿Se puede borrar sin riesgo? Doble verificación. */
    public static function deletable(array $o): ?string
    {
        if (!$o) { return 'no existe'; }
        if (!empty($o['is_demo'])) { return 'es una demo'; }
        if (in_array($o['status'], [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, Orders::ST_SUSPENDIDA, Orders::ST_ELIMINADA], true)) { return 'está publicada o ya eliminada'; }
        if (!empty($o['published_at']) || !empty($o['domain_assigned'])) { return 'tiene publicación o dominio'; }
        return null;
    }

    public static function deletePreview(int $id, string $why = 'manual'): bool
    {
        $o = Orders::byId($id);
        if (!$o) { return false; }
        $reason = self::deletable($o);
        if ($reason !== null) {
            Log::audit('borrado_rechazado', $reason, $id);
            return false;
        }
        // segunda verificación: ningún otro pedido publicado comparte slug o ruta
        if ($o['slug'] && (int) Db::val('SELECT COUNT(*) FROM ' . Db::t('orders') . ' WHERE id<>? AND slug=? AND status IN (?,?,?,?)', [$id, $o['slug'], Orders::ST_PUBLICADA, Orders::ST_VENCIDA, Orders::ST_SUSPENDIDA, Orders::ST_PAGO]) > 0) {
            Log::audit('borrado_rechazado', 'slug compartido', $id);
            return false;
        }
        $errs = Pipeline::rollback($o);
        Files::purgeAll($id);
        $scrub = ['status' => Orders::ST_ELIMINADA, 'data' => '{}', 'analysis' => null, 'texts' => null, 'client_email' => null, 'client_phone' => null, 'pay_file_id' => null, 'build_msg' => '', 'host_id' => $o['host_id']];
        Orders::set($id, $scrub);
        Db::delete('build_steps', 'order_id=?', [$id]);
        Log::audit('vista_previa_eliminada', ($errs ? 'con avisos: ' . implode('; ', $errs) : 'ok') . ' | motivo: ' . $why, $id);
        if ($errs) {
            Orders::alert('warn', 'Borrado con limpieza incompleta del pedido #' . $id . ': ' . implode('; ', $errs), $id);
        }
        return true;
    }
}
