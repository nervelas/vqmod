<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Privacidad y términos: textos legales, retención de datos y registro de consentimientos. */
final class LegalController extends A4Controller
{
    private const DOCS = [
        'privacy' => ['key' => 'privacy_text', 'label' => 'Aviso de privacidad'],
        'terms' => ['key' => 'terms_text', 'label' => 'Términos y condiciones'],
        'cookies' => ['key' => 'cookies_notice', 'label' => 'Aviso de cookies'],
    ];
    private const DOC_LABELS = ['privacy' => 'Privacidad', 'terms' => 'Términos', 'cookies' => 'Cookies'];
    private const MAX_TEXT = 40000;

    public function index(Request $req, array $p, array $old = [], array $errors = [], int $status = 200): Response
    {
        $defaults = $this->defaults();
        $texts = [];
        $isDefault = [];
        foreach (self::DOCS as $doc => $d) {
            $saved = (string) Settings::get($d['key'], '');
            $isDefault[$doc] = trim($saved) === '';
            $texts[$doc] = $old[$d['key']] ?? ($saved !== '' ? $saved : (string) ($defaults[$doc] ?? ''));
        }
        $q = $req->str('q', 120);
        $page = max(1, $req->int('pagina', 1));
        $per = 20;
        $where = '1=1';
        $args = [];
        if ($q !== '') {
            $where = 'email LIKE ?';
            $args[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $total = (int) Db::val('SELECT COUNT(*) FROM consents WHERE ' . $where, $args);
        $rows = Db::all('SELECT * FROM consents WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $args);

        $months = (int) Settings::get('retention_months', 0);
        $res = $this->page('admin/legal/index', [
            'title' => 'Privacidad y términos',
            'docs' => self::DOCS,
            'docLabels' => self::DOC_LABELS,
            'texts' => $texts,
            'isDefault' => $isDefault,
            'defaults' => $defaults,
            'version' => (int) Settings::get('legal_version', 1),
            'months' => $months,
            'monthsOld' => $old['retention_months'] ?? (string) $months,
            'affected' => $this->affected($months),
            'consents' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
            'q' => $q,
            'errors' => $errors,
        ], ['js/admin-settings.js'], '/admin/legal');
        $res->status = $status;
        return $res;
    }

    public function save(Request $req, array $p): Response
    {
        $errors = [];
        $new = [];
        foreach (self::DOCS as $doc => $d) {
            $v = str_replace(["\r\n", "\r"], "\n", (string) ($req->post[$d['key']] ?? ''));
            $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
            $v = trim($v);
            if ($v === '') {
                $errors[$d['key']] = 'Escribe el texto o usa “Restaurar plantilla”.';
            } elseif (mb_strlen($v) > self::MAX_TEXT) {
                $errors[$d['key']] = 'El texto es demasiado largo (máximo ' . number_format(self::MAX_TEXT) . ' caracteres).';
            }
            $new[$d['key']] = $v;
        }
        if ($errors) {
            return $this->index($req, [], $req->post, $errors, 422);
        }
        $changed = $this->persist($new);
        $this->audit('legal.save', $changed ? 'Textos actualizados: ' . implode(', ', $changed) . '; versión ' . (int) Settings::get('legal_version', 1) : 'Sin cambios en los textos', 'legal');
        $this->flash('success', $changed ? 'Guardamos los textos legales. Registramos la versión nueva para el consentimiento de tus clientes.' : 'No hubo cambios en los textos.');
        return $this->redirect('/admin/legal');
    }

    public function restore(Request $req, array $p): Response
    {
        $doc = $req->str('doc', 12);
        $defaults = $this->defaults();
        $targets = $doc === 'all' ? array_keys(self::DOCS) : (isset(self::DOCS[$doc]) ? [$doc] : []);
        if (!$targets || !$defaults) {
            return $this->fail($req, 'No pudimos cargar la plantilla.', '/admin/legal');
        }
        $new = [];
        foreach ($targets as $t) {
            $new[self::DOCS[$t]['key']] = (string) ($defaults[$t] ?? '');
        }
        $this->persist($new);
        $this->audit('legal.restore', 'Plantilla restaurada: ' . implode(', ', $targets), 'legal');
        $this->flash('success', 'Restauramos la plantilla genérica. Recuerda pedir a un abogado que la revise.');
        return $this->redirect('/admin/legal');
    }

    public function retention(Request $req, array $p): Response
    {
        $n = Validator::intRange($req->str('retention_months', 5), 0, 120);
        if ($n === null) {
            return $this->index($req, [], ['retention_months' => $req->str('retention_months', 5)], ['retention_months' => 'Indica un número de meses entre 0 y 120 (0 = no borrar nunca).'], 422);
        }
        $before = (int) Settings::get('retention_months', 0);
        Settings::set('retention_months', (string) $n);
        $this->audit('legal.retention', 'Retención de ' . $before . ' a ' . $n . ' meses', 'legal');
        $this->flash('success', $n === 0 ? 'Listo: no se borrarán datos automáticamente.' : 'Guardamos la política: se borrarán los datos inactivos con más de ' . $n . ' meses.');
        return $this->redirect('/admin/legal');
    }

    public function consentsCsv(Request $req, array $p): Response
    {
        $q = $req->str('q', 120);
        $where = '1=1';
        $args = [];
        if ($q !== '') {
            $where = 'email LIKE ?';
            $args[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $rows = Db::all('SELECT * FROM consents WHERE ' . $where . ' ORDER BY id DESC LIMIT 20000', $args);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [self::local($r['created_at'], 'Y-m-d H:i:s'), (string) $r['email'], self::DOC_LABELS[$r['document']] ?? (string) $r['document'], (string) $r['version'], (string) $r['ip_trunc'], (string) $r['text_hash']];
        }
        $this->audit('legal.consents_export', count($out) . ' registros', 'legal');
        return $this->csv('consentimientos-' . $this->stamp() . '.csv', ['Fecha (' . $this->bizTz() . ')', 'Correo', 'Documento', 'Versión', 'IP truncada', 'Huella del texto'], $out);
    }

    /** Guarda los textos con LegalService (versión y huella) y, si no está disponible, directamente. */
    private function persist(array $new): array
    {
        $changed = [];
        foreach ($new as $k => $v) {
            if ((string) Settings::get($k, '') !== $v) {
                $changed[] = $k;
            }
        }
        if (!$changed) {
            return [];
        }
        $versionBefore = (int) Settings::get('legal_version', 1);
        $done = false;
        if (class_exists('App\\Services\\LegalService')) {
            try {
                $payload = $new + ['privacy' => $new['privacy_text'] ?? '', 'terms' => $new['terms_text'] ?? '', 'cookies' => $new['cookies_notice'] ?? ''];
                \App\Services\LegalService::save($payload);
                Settings::flush();
                $done = true;
                foreach ($new as $k => $v) {
                    if ((string) Settings::get($k, '') !== $v) {
                        $done = false;
                    }
                }
            } catch (\Throwable $e) {
                \App\Core\Logger::error('LegalService::save falló', $e);
                $done = false;
            }
        }
        if (!$done) {
            Settings::setMany($new);
            if ((int) Settings::get('legal_version', 1) === $versionBefore) {
                Settings::set('legal_version', (string) ($versionBefore + 1));
            }
        }
        return $changed;
    }

    private function defaults(): array
    {
        if (class_exists('App\\Services\\LegalService')) {
            try {
                return (array) \App\Services\LegalService::defaults();
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }

    /** Citas ya terminadas hace más de N meses (las que borraría la retención hoy). */
    private function affected(int $months): int
    {
        if ($months <= 0) {
            return 0;
        }
        $cut = Tz::fromTs(Clock::now() - $months * 30 * 86400);
        try {
            return (int) Db::val("SELECT COUNT(*) FROM bookings WHERE ends_at < ? AND status NOT IN ('pending','confirmed')", [$cut]);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
