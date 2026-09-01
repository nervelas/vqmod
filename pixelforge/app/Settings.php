<?php
declare(strict_types=1);

/** Ajustes de la aplicación con valores por defecto sensatos. */
final class Settings
{
    public const DEFAULTS = [
        'password_hash' => '',
        'installed_at' => '',
        'provider_order' => 'pollinations,huggingface,gemini',
        'provider_enabled_pollinations' => '1',
        'provider_enabled_huggingface' => '0',
        'provider_enabled_gemini' => '0',
        'key_pollinations' => '',
        'key_huggingface' => '',
        'key_gemini' => '',
        'model_pollinations' => 'flux',
        'model_huggingface' => 'black-forest-labs/FLUX.1-schnell',
        'model_gemini' => 'gemini-2.5-flash-image',
        'realism_suffix' => 'fotografía hiperrealista, captada con cámara full frame, lente 85mm f/1.4, iluminación natural suave, enfoque nítido, textura de piel real, profundidad de campo, alto rango dinámico, sin ilustración',
        'default_format' => 'png',
        'default_width' => '1024',
        'default_height' => '1024',
        'http_timeout' => '90',
        'http_retries' => '3',
        'rate_limit_hour' => '60',
        'pollinations_nologo' => '1',
        'pollinations_private' => '1',
        'keep_history' => '500',
    ];

    private Store $store;
    private array $values = [];

    public function __construct(Store $store)
    {
        $this->store = $store;
        $this->reload();
    }

    public function reload(): void
    {
        $this->values = array_merge(self::DEFAULTS, $this->store->settingsAll());
    }

    public function get(string $key, ?string $default = null): string
    {
        if (array_key_exists($key, $this->values)) {
            return (string) $this->values[$key];
        }
        return $default ?? (string) (self::DEFAULTS[$key] ?? '');
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);
        return $value === '1' || $value === 'true' || $value === 'on';
    }

    public function set(string $key, string $value): void
    {
        $this->store->settingSet($key, $value);
        $this->values[$key] = $value;
    }

    // --- Instalación / contraseña -----------------------------------------
    public function isInstalled(): bool
    {
        return $this->get('password_hash') !== '';
    }

    public function setPassword(string $plain): void
    {
        $this->set('password_hash', password_hash($plain, PASSWORD_DEFAULT));
        if ($this->get('installed_at') === '') {
            $this->set('installed_at', date('Y-m-d H:i:s'));
        }
    }

    public function verifyPassword(string $plain): bool
    {
        $hash = $this->get('password_hash');
        return $hash !== '' && password_verify($plain, $hash);
    }

    // --- API keys ----------------------------------------------------------
    public function apiKey(string $provider): string
    {
        return Crypto::decrypt($this->get('key_' . $provider));
    }

    public function setApiKey(string $provider, string $plain): void
    {
        $this->set('key_' . $provider, $plain === '' ? '' : Crypto::encrypt($plain));
    }

    public function apiKeyMask(string $provider): string
    {
        return Crypto::mask($this->apiKey($provider));
    }

    // --- Proveedores -------------------------------------------------------
    /** Orden configurado, filtrado a proveedores existentes. */
    public function providerOrder(): array
    {
        $known = array_keys(ProviderRegistry::catalog());
        $order = array_values(array_filter(array_map('trim', explode(',', $this->get('provider_order')))));
        $order = array_values(array_intersect($order, $known));
        foreach ($known as $id) {
            if (!in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        return $order;
    }

    public function providerEnabled(string $id): bool
    {
        return $this->bool('provider_enabled_' . $id);
    }
}
