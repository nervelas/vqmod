<?php
declare(strict_types=1);

/** Petición de generación ya validada. */
final class GenRequest
{
    public string $prompt;       // exactamente lo que escribió la persona (+ sufijo si lo activó)
    public string $negative;
    public int $width;
    public int $height;
    public int $seed;
    public string $format;

    public function __construct(string $prompt, string $negative, int $width, int $height, int $seed, string $format)
    {
        $this->prompt = $prompt;
        $this->negative = $negative;
        $this->width = $width;
        $this->height = $height;
        $this->seed = $seed;
        $this->format = $format;
    }
}

/** Imagen devuelta por un proveedor, todavía sin ajustar al tamaño exacto. */
final class ProviderResult
{
    public string $bytes;
    public string $model;
    public int $sourceWidth = 0;
    public int $sourceHeight = 0;
    public array $notes = [];

    public function __construct(string $bytes, string $model, array $notes = [])
    {
        $this->bytes = $bytes;
        $this->model = $model;
        $this->notes = $notes;
        [$this->sourceWidth, $this->sourceHeight] = Imaging::dimensions($bytes);
    }
}

/** Error con mensaje ya redactado en español para la persona usuaria. */
final class ProviderError extends RuntimeException
{
    public string $reason;

    public function __construct(string $message, string $reason = 'generico')
    {
        parent::__construct($message);
        $this->reason = $reason;
    }
}

abstract class ImageProvider
{
    protected Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    abstract public function id(): string;
    abstract public function label(): string;
    abstract public function requiresKey(): bool;
    abstract public function supportsNegativePrompt(): bool;
    abstract public function model(): string;
    abstract public function generate(GenRequest $req): ProviderResult;

    /** Tamaño que se le pedirá al proveedor (el ajuste exacto es posterior). */
    abstract public function requestSize(int $width, int $height): array;

    public function apiKey(): string
    {
        return $this->settings->apiKey($this->id());
    }

    public function isConfigured(): bool
    {
        return !$this->requiresKey() || $this->apiKey() !== '';
    }

    public function isEnabled(): bool
    {
        return $this->settings->providerEnabled($this->id()) && $this->isConfigured();
    }

    protected function timeout(): int
    {
        return max(20, min(180, $this->settings->int('http_timeout')));
    }

    protected function retries(): int
    {
        return max(1, min(5, $this->settings->int('http_retries')));
    }

    /** Redondea a múltiplo de $step dentro de los límites del proveedor. */
    protected static function fit(int $width, int $height, int $min, int $max, int $step): array
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $long = max($width, $height);
        if ($long > $max) {
            $scale = $max / $long;
            $width = (int) round($width * $scale);
            $height = (int) round($height * $scale);
        }
        $round = static function (int $value) use ($min, $max, $step): int {
            $value = (int) (round($value / $step) * $step);
            return max($min, min($max, $value));
        };
        return [$round($width), $round($height)];
    }
}

/**
 * Pollinations.ai — gratuito, sin API key.
 * GET https://image.pollinations.ai/prompt/{prompt}?width=&height=&seed=&model=&nologo=
 * Límite anónimo documentado: 1 petición cada 15 s.
 */
final class PollinationsProvider extends ImageProvider
{
    private const ENDPOINT = 'https://image.pollinations.ai/prompt/';
    private const MIN_INTERVAL_ANON = 15;

    public function id(): string
    {
        return 'pollinations';
    }

    public function label(): string
    {
        return 'Pollinations.ai';
    }

    public function requiresKey(): bool
    {
        return false;
    }

    public function supportsNegativePrompt(): bool
    {
        return false;
    }

    public function model(): string
    {
        $model = trim($this->settings->get('model_pollinations'));
        return $model !== '' ? $model : 'flux';
    }

    public function requestSize(int $width, int $height): array
    {
        return self::fit($width, $height, 64, 1536, 8);
    }

    public function generate(GenRequest $req): ProviderResult
    {
        [$w, $h] = $this->requestSize($req->width, $req->height);
        $token = $this->apiKey();
        $this->respectRateLimit($token !== '');

        $query = [
            'width' => (string) $w,
            'height' => (string) $h,
            'seed' => (string) $req->seed,
            'model' => $this->model(),
        ];
        if ($this->settings->bool('pollinations_nologo')) {
            $query['nologo'] = 'true';
        }
        if ($this->settings->bool('pollinations_private')) {
            $query['private'] = 'true';
        }
        // El prompt viaja tal cual, solo codificado para la URL.
        $url = self::ENDPOINT . rawurlencode($req->prompt) . '?' . http_build_query($query);

        $headers = ['Accept: image/*'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $response = Http::request($url, [
            'headers' => $headers,
            'timeout' => $this->timeout(),
            'retries' => $this->retries(),
        ]);
        pf_store()->kvSet('last_call_pollinations', (string) time());

        if (!$response->ok()) {
            throw new ProviderError($this->explain($response), $this->reasonFor($response));
        }
        if (!Imaging::looksLikeImage($response->body)) {
            $snippet = trim(substr(strip_tags($response->body), 0, 160));
            Logger::write('provider', 'Pollinations devolvió contenido no válido: ' . $snippet);
            throw new ProviderError('Pollinations respondió sin una imagen válida. Inténtalo de nuevo en unos segundos.', 'respuesta');
        }
        $notes = [];
        if ($req->negative !== '') {
            $notes[] = 'Pollinations no admite prompt negativo: se ignoró en este proveedor.';
        }
        return new ProviderResult($response->body, $this->model(), $notes);
    }

    /** Espera lo justo para no chocar con el límite anónimo de 1 petición/15 s. */
    private function respectRateLimit(bool $hasToken): void
    {
        $interval = $hasToken ? 5 : self::MIN_INTERVAL_ANON;
        $last = (int) pf_store()->kvGet('last_call_pollinations', '0');
        $elapsed = time() - $last;
        if ($last > 0 && $elapsed < $interval) {
            $wait = min($interval, $interval - $elapsed);
            if ($wait > 0) {
                sleep($wait);
            }
        }
    }

    private function reasonFor(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'red';
        }
        return match ($response->status) {
            402, 429 => 'cuota',
            400, 422 => 'peticion',
            403 => 'rechazado',
            default => 'caido',
        };
    }

    private function explain(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'Pollinations: ' . $response->error;
        }
        return match ($response->status) {
            429 => 'Pollinations está limitando las peticiones (1 imagen cada 15 s sin cuenta). Espera unos segundos e inténtalo otra vez.',
            402 => 'Pollinations agotó la cuota gratuita disponible en este momento.',
            400, 422 => 'Pollinations rechazó los parámetros de la petición (revisa el tamaño solicitado).',
            403 => 'Pollinations rechazó el contenido del prompt.',
            500, 502, 503, 504 => 'Pollinations no está disponible ahora mismo. Se intentará con el siguiente proveedor.',
            default => 'Pollinations respondió con un error (HTTP ' . $response->status . ').',
        };
    }
}

/**
 * Hugging Face Inference Providers — requiere token gratuito (opcional aquí).
 * POST https://router.huggingface.co/hf-inference/models/{modelo}
 * Devuelve los bytes de la imagen directamente.
 */
final class HuggingFaceProvider extends ImageProvider
{
    private const ENDPOINT = 'https://router.huggingface.co/hf-inference/models/';

    public function id(): string
    {
        return 'huggingface';
    }

    public function label(): string
    {
        return 'Hugging Face (FLUX.1-schnell)';
    }

    public function requiresKey(): bool
    {
        return true;
    }

    public function supportsNegativePrompt(): bool
    {
        return true;
    }

    public function model(): string
    {
        $model = trim($this->settings->get('model_huggingface'));
        return $model !== '' ? $model : 'black-forest-labs/FLUX.1-schnell';
    }

    public function requestSize(int $width, int $height): array
    {
        return self::fit($width, $height, 256, 1024, 16);
    }

    public function generate(GenRequest $req): ProviderResult
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new ProviderError('Falta la API key de Hugging Face. Añádela en el panel de administración.', 'key');
        }
        [$w, $h] = $this->requestSize($req->width, $req->height);
        $parameters = [
            'width' => $w,
            'height' => $h,
            'seed' => $req->seed,
        ];
        if ($req->negative !== '') {
            $parameters['negative_prompt'] = $req->negative;
        }
        $payload = json_encode([
            'inputs' => $req->prompt, // prompt exacto
            'parameters' => $parameters,
            'options' => ['wait_for_model' => true],
        ], JSON_UNESCAPED_UNICODE);

        $response = Http::request(self::ENDPOINT . $this->model(), [
            'method' => 'POST',
            'headers' => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Accept: image/png',
            ],
            'body' => (string) $payload,
            'timeout' => $this->timeout(),
            'retries' => $this->retries(),
        ]);

        if (!$response->ok()) {
            throw new ProviderError($this->explain($response), $this->reasonFor($response));
        }
        if (!Imaging::looksLikeImage($response->body)) {
            $json = json_decode($response->body, true);
            $detail = is_array($json) ? (string) ($json['error'] ?? '') : '';
            Logger::write('provider', 'Hugging Face sin imagen: ' . substr($detail !== '' ? $detail : $response->body, 0, 200));
            throw new ProviderError(
                $detail !== ''
                    ? 'Hugging Face respondió: ' . $detail
                    : 'Hugging Face no devolvió una imagen válida.',
                'respuesta'
            );
        }
        return new ProviderResult($response->body, $this->model());
    }

    private function reasonFor(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'red';
        }
        return match ($response->status) {
            401, 403 => 'key',
            402, 429 => 'cuota',
            400, 422 => 'peticion',
            503 => 'caido',
            default => 'caido',
        };
    }

    private function explain(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'Hugging Face: ' . $response->error;
        }
        $json = json_decode($response->body, true);
        $detail = is_array($json) ? trim((string) ($json['error'] ?? '')) : '';
        return match ($response->status) {
            401 => 'La API key de Hugging Face no es válida o fue revocada.',
            403 => 'Tu cuenta de Hugging Face no tiene permiso para este modelo (acepta sus condiciones en la web del modelo).',
            402 => 'Se agotó el crédito mensual gratuito de Hugging Face. Se intentará con otro proveedor.',
            429 => 'Hugging Face está limitando las peticiones. Espera un momento antes de reintentar.',
            400, 422 => 'Hugging Face rechazó los parámetros' . ($detail !== '' ? ': ' . $detail : ' (tamaño no soportado).'),
            503 => 'El modelo de Hugging Face se está cargando. Reintenta en un minuto.',
            default => 'Hugging Face respondió con un error (HTTP ' . $response->status . ')' . ($detail !== '' ? ': ' . $detail : '.'),
        };
    }
}

/**
 * Google Gemini (AI Studio) — key gratuita, opcional.
 * POST https://generativelanguage.googleapis.com/v1beta/models/{modelo}:generateContent
 * La imagen llega en base64 dentro de inlineData.
 */
final class GeminiProvider extends ImageProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const RATIOS = [
        '1:1' => 1.0,
        '2:3' => 0.6667,
        '3:2' => 1.5,
        '3:4' => 0.75,
        '4:3' => 1.3333,
        '4:5' => 0.8,
        '5:4' => 1.25,
        '9:16' => 0.5625,
        '16:9' => 1.7778,
        '21:9' => 2.3333,
    ];

    public function id(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Google Gemini (imagen)';
    }

    public function requiresKey(): bool
    {
        return true;
    }

    public function supportsNegativePrompt(): bool
    {
        return false;
    }

    public function model(): string
    {
        $model = trim($this->settings->get('model_gemini'));
        return $model !== '' ? $model : 'gemini-2.5-flash-image';
    }

    /** Gemini no acepta píxeles exactos: se elige la proporción más cercana. */
    public function requestSize(int $width, int $height): array
    {
        return [$width, $height];
    }

    public function nearestRatio(int $width, int $height): string
    {
        $target = $height > 0 ? $width / $height : 1.0;
        $best = '1:1';
        $bestDiff = PHP_FLOAT_MAX;
        foreach (self::RATIOS as $name => $value) {
            $diff = abs($value - $target);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $name;
            }
        }
        return $best;
    }

    public function generate(GenRequest $req): ProviderResult
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new ProviderError('Falta la API key de Google Gemini. Añádela en el panel de administración.', 'key');
        }
        $ratio = $this->nearestRatio($req->width, $req->height);
        $payload = json_encode([
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $req->prompt]], // prompt exacto
            ]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => ['aspectRatio' => $ratio],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $url = self::ENDPOINT . rawurlencode($this->model()) . ':generateContent';
        $response = Http::request($url, [
            'method' => 'POST',
            'headers' => [
                'x-goog-api-key: ' . $key, // la key nunca llega al navegador
                'Content-Type: application/json',
            ],
            'body' => (string) $payload,
            'timeout' => $this->timeout(),
            'retries' => $this->retries(),
        ]);

        if (!$response->ok()) {
            throw new ProviderError($this->explain($response), $this->reasonFor($response));
        }
        $json = json_decode($response->body, true);
        if (!is_array($json)) {
            throw new ProviderError('Gemini devolvió una respuesta que no se pudo interpretar.', 'respuesta');
        }
        $block = $json['promptFeedback']['blockReason'] ?? ($json['candidates'][0]['finishReason'] ?? '');
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        foreach ((array) $parts as $part) {
            $data = $part['inlineData']['data'] ?? ($part['inline_data']['data'] ?? null);
            if (is_string($data) && $data !== '') {
                $bytes = base64_decode($data, true);
                if ($bytes !== false && Imaging::looksLikeImage($bytes)) {
                    $notes = [];
                    if ($req->negative !== '') {
                        $notes[] = 'Gemini no admite prompt negativo: se ignoró en este proveedor.';
                    }
                    $notes[] = 'Gemini genera por proporción (' . $ratio . '); el tamaño exacto se ajustó en el servidor.';
                    return new ProviderResult($bytes, $this->model(), $notes);
                }
            }
        }
        if (in_array((string) $block, ['SAFETY', 'PROHIBITED_CONTENT', 'IMAGE_SAFETY', 'BLOCKLIST'], true)) {
            throw new ProviderError('Gemini rechazó el contenido del prompt por sus filtros de seguridad.', 'rechazado');
        }
        Logger::write('provider', 'Gemini sin imagen en la respuesta: ' . substr($response->body, 0, 300));
        throw new ProviderError('Gemini no incluyó ninguna imagen en su respuesta.', 'respuesta');
    }

    private function reasonFor(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'red';
        }
        return match ($response->status) {
            400 => 'peticion',
            401, 403 => 'key',
            429 => 'cuota',
            default => 'caido',
        };
    }

    private function explain(HttpResponse $response): string
    {
        if ($response->error !== '') {
            return 'Gemini: ' . $response->error;
        }
        $json = json_decode($response->body, true);
        $detail = is_array($json) ? trim((string) ($json['error']['message'] ?? '')) : '';
        return match ($response->status) {
            400 => 'Gemini rechazó la petición' . ($detail !== '' ? ': ' . $detail : ' (revisa el modelo configurado).'),
            401, 403 => 'La API key de Gemini no es válida o no tiene acceso a este modelo.',
            429 => 'Se agotó la cuota gratuita de Gemini por ahora. Se intentará con otro proveedor.',
            404 => 'El modelo de Gemini configurado no existe. Revisa su nombre en el panel.',
            500, 502, 503 => 'Gemini no está disponible en este momento.',
            default => 'Gemini respondió con un error (HTTP ' . $response->status . ')' . ($detail !== '' ? ': ' . $detail : '.'),
        };
    }
}

/** Catálogo y construcción de proveedores. */
final class ProviderRegistry
{
    /** @return array<string,class-string<ImageProvider>> */
    public static function catalog(): array
    {
        return [
            'pollinations' => PollinationsProvider::class,
            'huggingface' => HuggingFaceProvider::class,
            'gemini' => GeminiProvider::class,
        ];
    }

    public static function get(string $id, Settings $settings): ?ImageProvider
    {
        $class = self::catalog()[$id] ?? null;
        if ($class === null) {
            return null;
        }
        /** @var ImageProvider $provider */
        $provider = new $class($settings);
        return $provider;
    }

    /** Todos los proveedores en el orden configurado. @return ImageProvider[] */
    public static function ordered(Settings $settings): array
    {
        $out = [];
        foreach ($settings->providerOrder() as $id) {
            $provider = self::get($id, $settings);
            if ($provider !== null) {
                $out[] = $provider;
            }
        }
        return $out;
    }

    /** Solo los activos y configurados. @return ImageProvider[] */
    public static function active(Settings $settings): array
    {
        return array_values(array_filter(self::ordered($settings), static fn (ImageProvider $p): bool => $p->isEnabled()));
    }
}
