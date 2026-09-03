<?php
declare(strict_types=1);

/** Sistema de 8 temas (4 oscuros / 4 claros) totalmente distintos entre si. */
final class Theme
{
    private static ?array $active = null;

    /** Definiciones por defecto, usadas tambien por el instalador para poblar la tabla. */
    public static function defaults(): array
    {
        return [
            [
                'theme_key' => 'obsidiana', 'name' => 'Obsidiana', 'mode' => 'dark',
                'description' => 'Negro profundo con turquesa electrico. Tecnologico, nitido y de alto contraste.',
                'fonts' => ['display' => "'Space Grotesk'", 'body' => "'Inter'", 'display_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700'],
                'palette' => [
                    'bg' => '#05070a', 'bg-alt' => '#090d13', 'surface' => '#0e131b', 'surface-2' => '#141b25',
                    'text' => '#eef3f8', 'muted' => '#8d9bad', 'border' => 'rgba(255,255,255,.09)',
                    'accent' => '#22e5c7', 'accent-2' => '#7c5cff', 'accent-ink' => '#03110e',
                    'glow' => 'rgba(34,229,199,.32)', 'grain' => '.05', 'radius' => '20px', 'display-tracking' => '-.035em',
                ],
            ],
            [
                'theme_key' => 'medianoche', 'name' => 'Medianoche', 'mode' => 'dark',
                'description' => 'Azul noche con oro suave. Elegancia editorial de alta gama.',
                'fonts' => ['display' => "'Playfair Display'", 'body' => "'Jost'", 'display_fallback' => "Georgia,'Times New Roman',serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600'],
                'palette' => [
                    'bg' => '#070c1b', 'bg-alt' => '#0a1124', 'surface' => '#0e1730', 'surface-2' => '#131f3d',
                    'text' => '#f2f0e9', 'muted' => '#97a3c0', 'border' => 'rgba(232,200,122,.16)',
                    'accent' => '#e8c87a', 'accent-2' => '#6f8fd6', 'accent-ink' => '#1a1305',
                    'glow' => 'rgba(232,200,122,.28)', 'grain' => '.045', 'radius' => '6px', 'display-tracking' => '-.015em',
                ],
            ],
            [
                'theme_key' => 'carbon', 'name' => 'Carbon', 'mode' => 'dark',
                'description' => 'Grafito con lima neon. Brutalismo premium, tipografia de gran peso.',
                'fonts' => ['display' => "'Archivo'", 'body' => "'Inter Tight'", 'display_fallback' => "'Arial Black',system-ui,sans-serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Archivo:wght@500;600;700;800;900&family=Inter+Tight:wght@300;400;500;600'],
                'palette' => [
                    'bg' => '#0c0c0d', 'bg-alt' => '#111113', 'surface' => '#161618', 'surface-2' => '#1e1e21',
                    'text' => '#f5f5f2', 'muted' => '#9b9b9b', 'border' => 'rgba(255,255,255,.12)',
                    'accent' => '#c8f31d', 'accent-2' => '#ff5c35', 'accent-ink' => '#11140a',
                    'glow' => 'rgba(200,243,29,.3)', 'grain' => '.07', 'radius' => '2px', 'display-tracking' => '-.045em',
                ],
            ],
            [
                'theme_key' => 'nebulosa', 'name' => 'Nebulosa', 'mode' => 'dark',
                'description' => 'Violeta profundo con magenta y cian. Futurista, luminoso y envolvente.',
                'fonts' => ['display' => "'Outfit'", 'body' => "'Manrope'", 'display_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Outfit:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700'],
                'palette' => [
                    'bg' => '#0b0616', 'bg-alt' => '#100a20', 'surface' => '#16102b', 'surface-2' => '#1e1738',
                    'text' => '#f4eefc', 'muted' => '#a294c2', 'border' => 'rgba(255,255,255,.1)',
                    'accent' => '#ff5fa2', 'accent-2' => '#46e0ff', 'accent-ink' => '#22030f',
                    'glow' => 'rgba(255,95,162,.32)', 'grain' => '.05', 'radius' => '28px', 'display-tracking' => '-.03em',
                ],
            ],
            [
                'theme_key' => 'alabastro', 'name' => 'Alabastro', 'mode' => 'light',
                'description' => 'Blanco calido con indigo intenso. Precision suiza y aire limpio.',
                'fonts' => ['display' => "'Sora'", 'body' => "'Inter'", 'display_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Sora:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700'],
                'palette' => [
                    'bg' => '#f7f7f5', 'bg-alt' => '#efefec', 'surface' => '#ffffff', 'surface-2' => '#f2f2ef',
                    'text' => '#111214', 'muted' => '#5e6470', 'border' => 'rgba(17,18,20,.1)',
                    'accent' => '#3b2fe0', 'accent-2' => '#ff7a2f', 'accent-ink' => '#ffffff',
                    'glow' => 'rgba(59,47,224,.22)', 'grain' => '.035', 'radius' => '16px', 'display-tracking' => '-.03em',
                ],
            ],
            [
                'theme_key' => 'arena', 'name' => 'Arena', 'mode' => 'light',
                'description' => 'Crema arena con terracota. Calido, artesanal y editorial.',
                'fonts' => ['display' => "'Fraunces'", 'body' => "'Karla'", 'display_fallback' => "Georgia,'Times New Roman',serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=Karla:wght@300;400;500;600;700'],
                'palette' => [
                    'bg' => '#faf4ec', 'bg-alt' => '#f2e9dc', 'surface' => '#fffaf3', 'surface-2' => '#f6ede0',
                    'text' => '#2b1d14', 'muted' => '#7a6555', 'border' => 'rgba(43,29,20,.14)',
                    'accent' => '#a84522', 'accent-2' => '#1a6350', 'accent-ink' => '#fff6ef',
                    'glow' => 'rgba(168,69,34,.2)', 'grain' => '.06', 'radius' => '14px', 'display-tracking' => '-.02em',
                ],
            ],
            [
                'theme_key' => 'menta', 'name' => 'Menta', 'mode' => 'light',
                'description' => 'Verde menta con esmeralda. Fresco, confiable y corporativo.',
                'fonts' => ['display' => "'DM Serif Display'", 'body' => "'DM Sans'", 'display_fallback' => "Georgia,'Times New Roman',serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700'],
                'palette' => [
                    'bg' => '#f2f7f4', 'bg-alt' => '#e7f0eb', 'surface' => '#ffffff', 'surface-2' => '#edf5f0',
                    'text' => '#0f2019', 'muted' => '#5b7268', 'border' => 'rgba(15,32,25,.11)',
                    'accent' => '#0e7c5a', 'accent-2' => '#d8a02a', 'accent-ink' => '#ffffff',
                    'glow' => 'rgba(14,124,90,.22)', 'grain' => '.03', 'radius' => '22px', 'display-tracking' => '-.015em',
                ],
            ],
            [
                'theme_key' => 'perla', 'name' => 'Perla', 'mode' => 'light',
                'description' => 'Perla fria con azul real y coral. Moderno, financiero y luminoso.',
                'fonts' => ['display' => "'Bricolage Grotesque'", 'body' => "'Public Sans'", 'display_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'body_fallback' => "system-ui,-apple-system,'Segoe UI',sans-serif", 'google' => 'Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Public+Sans:wght@300;400;500;600;700'],
                'palette' => [
                    'bg' => '#f4f6fb', 'bg-alt' => '#e9edf7', 'surface' => '#ffffff', 'surface-2' => '#eef1f9',
                    'text' => '#0d1220', 'muted' => '#5a6480', 'border' => 'rgba(13,18,32,.1)',
                    'accent' => '#1f4fe0', 'accent-2' => '#ff6b4a', 'accent-ink' => '#ffffff',
                    'glow' => 'rgba(31,79,224,.22)', 'grain' => '.03', 'radius' => '10px', 'display-tracking' => '-.04em',
                ],
            ],
        ];
    }

    public static function active(): array
    {
        if (self::$active !== null) {
            return self::$active;
        }

        $key = Settings::get('theme_active', 'obsidiana');

        // Vista previa temporal desde el panel: ?preview_theme=clave
        $preview = isset($_GET['preview_theme']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['preview_theme']) : '';
        if ($preview !== '') {
            $key = $preview;
        }

        $row = null;
        try {
            $row = Database::first('SELECT * FROM themes WHERE theme_key = :k LIMIT 1', ['k' => $key]);
        } catch (Throwable) {
            $row = null;
        }

        if ($row === null) {
            $row = self::defaults()[0];
            $row['palette'] = json_encode($row['palette']);
            $row['fonts']   = json_encode($row['fonts']);
        }

        $row['palette'] = json_field($row['palette'] ?? '');
        $row['fonts']   = json_field($row['fonts'] ?? '');
        self::$active   = $row;
        return self::$active;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        try {
            $rows = Database::all('SELECT * FROM themes ORDER BY sort_order ASC, id ASC');
        } catch (Throwable) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['palette'] = json_field($r['palette']);
            $r['fonts']   = json_field($r['fonts']);
        }
        return $rows;
    }

    public static function mode(): string
    {
        return (string) (self::active()['mode'] ?? 'dark');
    }

    public static function googleFontsUrl(): string
    {
        $fonts = self::active()['fonts'] ?? [];
        $query = (string) ($fonts['google'] ?? '');
        if ($query === '') {
            return '';
        }
        return 'https://fonts.googleapis.com/css2?family=' . $query . '&display=swap';
    }

    /** Variables CSS del tema activo, listas para incrustar en <style>. */
    public static function cssVariables(): string
    {
        $t       = self::active();
        $palette = is_array($t['palette']) ? $t['palette'] : [];
        $fonts   = is_array($t['fonts']) ? $t['fonts'] : [];

        $out = ":root{\n";
        foreach ($palette as $k => $v) {
            $key = preg_replace('/[^a-z0-9-]/i', '', (string) $k) ?? '';
            $val = str_replace(['<', '>', '"', ';', '{', '}'], '', (string) $v);
            if ($key !== '') {
                $out .= '  --' . $key . ':' . $val . ";\n";
            }
        }
        $clean   = static fn(string $v): string => str_replace(['<', '>', ';', '{', '}'], '', $v);
        $display = $clean((string) ($fonts['display'] ?? "'Space Grotesk'"));
        $body    = $clean((string) ($fonts['body'] ?? "'Inter'"));
        $dfb     = $clean((string) ($fonts['display_fallback'] ?? "system-ui,-apple-system,'Segoe UI',sans-serif"));
        $bfb     = $clean((string) ($fonts['body_fallback'] ?? "system-ui,-apple-system,'Segoe UI',sans-serif"));
        $out .= '  --font-display:' . $display . ',' . $dfb . ";\n";
        $out .= '  --font-body:' . $body . ',' . $bfb . ";\n";
        $out .= "}\n";
        return $out;
    }
}
