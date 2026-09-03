<?php
declare(strict_types=1);

/** Motor SEO: metadatos por pagina, Open Graph, Twitter Cards y Schema.org. */
final class Seo
{
    private static array $data = [
        'title' => '', 'description' => '', 'keywords' => '', 'image' => '',
        'canonical' => '', 'robots' => '', 'type' => 'website', 'schema' => [],
        'breadcrumbs' => [],
    ];

    public static function set(array $values): void
    {
        foreach ($values as $k => $v) {
            if (array_key_exists($k, self::$data) && $v !== null && $v !== '') {
                self::$data[$k] = $v;
            }
        }
    }

    public static function addSchema(array $schema): void
    {
        self::$data['schema'][] = $schema;
    }

    /** @param list<array{name:string,url:string}> $items */
    public static function breadcrumbs(array $items): void
    {
        self::$data['breadcrumbs'] = $items;
    }

    public static function title(): string
    {
        $t    = trim((string) self::$data['title']);
        $name = Settings::get('site_name', 'Servicom');
        $sep  = Settings::get('seo_separator', '|');
        if ($t === '') {
            return Settings::get('seo_default_title', $name);
        }
        if (stripos($t, $name) !== false) {
            return $t;
        }
        return $t . ' ' . $sep . ' ' . $name;
    }

    public static function description(): string
    {
        $d = trim((string) self::$data['description']);
        return $d !== '' ? excerpt($d, 300) : Settings::get('seo_default_description', '');
    }

    public static function canonical(): string
    {
        $c = trim((string) self::$data['canonical']);
        if ($c !== '') {
            return preg_match('#^https?://#i', $c) ? $c : url($c);
        }
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
        if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH));
        }
        return url(ltrim($path, '/'));
    }

    public static function image(): string
    {
        $img = trim((string) self::$data['image']);
        if ($img === '') {
            $img = Settings::get('seo_og_image', Settings::get('logo', ''));
        }
        if ($img === '') {
            return '';
        }
        return preg_match('#^https?://#i', $img) ? $img : url(ltrim($img, '/'));
    }

    /** Imprime todas las etiquetas <meta>, <link> y JSON-LD del <head>. */
    public static function render(): string
    {
        $out  = '';
        $desc = self::description();
        $kw   = trim((string) self::$data['keywords']);
        $kw   = $kw !== '' ? $kw : Settings::get('seo_default_keywords', '');
        $img  = self::image();
        $can  = self::canonical();
        $rob  = trim((string) self::$data['robots']);
        $rob  = $rob !== '' ? $rob : Settings::get('seo_robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
        $name = Settings::get('site_name', 'Servicom');

        $out .= '<title>' . e(self::title()) . "</title>\n";
        if ($desc !== '') {
            $out .= '<meta name="description" content="' . e($desc) . "\">\n";
        }
        if ($kw !== '') {
            $out .= '<meta name="keywords" content="' . e($kw) . "\">\n";
        }
        $out .= '<meta name="robots" content="' . e($rob) . "\">\n";
        $out .= '<link rel="canonical" href="' . e($can) . "\">\n";
        $out .= '<meta name="author" content="' . e($name) . "\">\n";
        $geo = Settings::get('seo_geo_region', 'GT');
        $out .= '<meta name="geo.region" content="' . e($geo) . "\">\n";
        $out .= '<meta name="geo.placename" content="' . e(Settings::get('address_city', 'Ciudad de Guatemala')) . "\">\n";

        // Open Graph
        $out .= '<meta property="og:site_name" content="' . e($name) . "\">\n";
        $out .= '<meta property="og:type" content="' . e((string) self::$data['type']) . "\">\n";
        $out .= '<meta property="og:locale" content="es_GT">' . "\n";
        $out .= '<meta property="og:title" content="' . e(self::title()) . "\">\n";
        if ($desc !== '') {
            $out .= '<meta property="og:description" content="' . e($desc) . "\">\n";
        }
        $out .= '<meta property="og:url" content="' . e($can) . "\">\n";
        if ($img !== '') {
            $out .= '<meta property="og:image" content="' . e($img) . "\">\n";
            $out .= '<meta property="og:image:alt" content="' . e(self::title()) . "\">\n";
        }

        // Twitter
        $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $out .= '<meta name="twitter:title" content="' . e(self::title()) . "\">\n";
        if ($desc !== '') {
            $out .= '<meta name="twitter:description" content="' . e($desc) . "\">\n";
        }
        if ($img !== '') {
            $out .= '<meta name="twitter:image" content="' . e($img) . "\">\n";
        }

        // JSON-LD
        foreach (self::schemaGraph() as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json !== false) {
                $out .= '<script type="application/ld+json">' . str_replace('</', '<\/', $json) . "</script>\n";
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function schemaGraph(): array
    {
        $graph = [];
        $name  = Settings::get('site_name', 'Servicom');
        $phone = Settings::get('phone', '');
        $sameAs = array_values(array_filter([
            Settings::get('social_facebook', ''),
            Settings::get('social_instagram', ''),
            Settings::get('social_linkedin', ''),
            Settings::get('social_youtube', ''),
            Settings::get('social_tiktok', ''),
            Settings::get('social_x', ''),
        ]));

        $business = [
            '@context' => 'https://schema.org',
            '@type'    => Settings::get('schema_type', 'ProfessionalService'),
            '@id'      => url('#business'),
            'name'     => $name,
            'url'      => url(),
            'description' => Settings::get('seo_default_description', ''),
            'telephone'   => $phone,
            'priceRange'  => Settings::get('schema_price_range', '$$'),
            'address'  => [
                '@type' => 'PostalAddress',
                'addressLocality' => Settings::get('address_city', 'Ciudad de Guatemala'),
                'addressRegion'   => Settings::get('address_region', 'Guatemala'),
                'addressCountry'  => 'GT',
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Guatemala'],
            'inLanguage' => 'es-GT',
        ];
        $email = Settings::get('email', '');
        if ($email !== '') {
            $business['email'] = $email;
        }
        $logo = Settings::get('logo', '');
        if ($logo !== '') {
            $business['logo']  = url(ltrim($logo, '/'));
            $business['image'] = url(ltrim($logo, '/'));
        }
        if ($sameAs !== []) {
            $business['sameAs'] = $sameAs;
        }
        $lat = Settings::get('schema_lat', '');
        $lng = Settings::get('schema_lng', '');
        if ($lat !== '' && $lng !== '') {
            $business['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }
        $hours = Settings::get('schema_hours', '');
        if ($hours !== '') {
            $business['openingHours'] = $hours;
        }
        $graph[] = $business;

        $graph[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $name,
            'url'      => url(),
            'inLanguage' => 'es-GT',
            'publisher'  => ['@id' => url('#business')],
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('buscar?q={search_term_string}')],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        if (self::$data['breadcrumbs'] !== []) {
            $items = [];
            $i = 1;
            foreach (self::$data['breadcrumbs'] as $b) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $i++,
                    'name'     => (string) ($b['name'] ?? ''),
                    'item'     => preg_match('#^https?://#i', (string) ($b['url'] ?? '')) ? $b['url'] : url(ltrim((string) ($b['url'] ?? ''), '/')),
                ];
            }
            $graph[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }

        foreach (self::$data['schema'] as $extra) {
            if (is_array($extra) && $extra !== []) {
                $graph[] = $extra + ['@context' => 'https://schema.org'];
            }
        }

        return $graph;
    }
}
