<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Controller;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Services\RoutingService;

/** Formulario de enrutamiento: unas preguntas y la persona llega a la cita correcta. */
final class RoutingController extends Controller
{
    public function show(Request $req, array $p): Response
    {
        return $this->page($this->form($p), [], [], null);
    }

    public function submit(Request $req, array $p): Response
    {
        $form = $this->form($p);
        if (trim((string) $req->input('company_site', '')) !== '') {
            throw new HttpException(404);
        }
        if (!RateLimiter::hit(PubSupport::bucket($req, 'route'), 30, 600)) {
            return $this->page($form, [], [], 'Has enviado muchas respuestas seguidas. Espera unos minutos e inténtalo de nuevo.', 429);
        }
        $answers = [];
        $raw = $req->input('a', []);
        foreach ($this->questions($form) as $q) {
            $v = is_array($raw) ? ($raw[$q['id']] ?? null) : null;
            $answers[$q['id']] = is_array($v) ? array_map(static fn($x) => Str::clean((string) $x, 300), $v) : Str::clean(is_scalar($v) ? (string) $v : '', 1000);
        }
        try {
            $res = RoutingService::run($form, $answers, $req->ip());
        } catch (\InvalidArgumentException $e) {
            return $this->page($form, $answers, [], $e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Falló el enrutamiento público', $e);
            return $this->page($form, $answers, [], 'No pudimos procesar tus respuestas ahora. Inténtalo de nuevo en un momento.', 500);
        }
        $redirect = $this->safeRedirect((string) ($res['redirect'] ?? ''));
        if ($redirect !== '') {
            return Response::redirect($redirect);
        }
        return $this->view('public/route_result', [
            'title' => (string) $form['name'],
            'noindex' => true,
            'biz' => PubSupport::biz(),
            'form' => $form,
            'message' => (string) ($res['message'] ?? ''),
            'events' => (array) ($res['events'] ?? []),
            'nav' => '',
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-route',
        ], 'layouts/public');
    }

    private function form(array $p): array
    {
        $f = Db::one('SELECT * FROM routing_forms WHERE slug = ? AND active = 1', [(string) $p['slug']]);
        if (!$f) {
            throw new HttpException(404);
        }
        return $f;
    }

    /** Preguntas normalizadas para la vista. */
    private function questions(array $form): array
    {
        $j = json_decode((string) ($form['questions'] ?? '[]'), true);
        $out = [];
        foreach (is_array($j) ? $j : [] as $q) {
            $id = (string) ($q['id'] ?? $q['name'] ?? '');
            if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,60}$/', $id)) {
                continue;
            }
            $opts = $q['options'] ?? [];
            if (is_string($opts)) {
                $opts = preg_split('/\R|,/', $opts) ?: [];
            }
            $out[] = [
                'id' => $id,
                'label' => (string) ($q['label'] ?? $id),
                'type' => in_array(($q['type'] ?? 'text'), ['select', 'radio', 'checkbox', 'text', 'textarea', 'number', 'email', 'phone'], true) ? (string) $q['type'] : 'text',
                'options' => array_values(array_filter(array_map(static fn($o) => Str::clean((string) $o, 160), (array) $opts), static fn($o) => $o !== '')),
                'required' => !empty($q['required']),
                'help' => (string) ($q['help'] ?? ''),
            ];
        }
        return $out;
    }

    private function page(array $form, array $values, array $errors, ?string $error, int $status = 200): Response
    {
        $r = $this->view('public/route', [
            'title' => (string) $form['name'],
            'description' => Str::truncate(trim(strip_tags((string) ($form['description'] ?? ''))), 160),
            'biz' => PubSupport::biz(),
            'form' => $form,
            'questions' => $this->questions($form),
            'values' => $values,
            'error' => $error,
            'nav' => '',
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-route',
        ], 'layouts/public');
        $r->status = $status;
        return $r;
    }

    /** Solo http(s) o rutas internas; nunca esquemas raros ni "//otro-sitio". */
    private function safeRedirect(string $u): string
    {
        $u = trim($u);
        if ($u === '') {
            return '';
        }
        if ($u[0] === '/' && !str_starts_with($u, '//') && !str_contains($u, '\\')) {
            return $u;
        }
        return PubSupport::safeUrl($u);
    }
}
