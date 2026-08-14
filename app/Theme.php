<?php
/**
 * Theme resolution and CSS-variable generation.
 *
 * A theme is defined by exactly 4 colors (bg, primary, secondary, accent).
 * Everything else — text, borders, surfaces, hover/focus/state colors,
 * shadows — is derived here with guaranteed contrast/legibility.
 *
 * Themes can only be changed from the admin panel. The public never writes.
 */
class Theme
{
    public static function all(): array
    {
        return Database::all("SELECT * FROM themes ORDER BY sort_order ASC, id ASC");
    }

    public static function find(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        return Database::one("SELECT * FROM themes WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one("SELECT * FROM themes WHERE slug = ?", [$slug]);
    }

    /** Resolve the active theme: league override → default setting → first. */
    public static function resolve(?int $leagueThemeId = null): array
    {
        $theme = self::find($leagueThemeId);
        if (!$theme) {
            $theme = self::find((int)Settings::get('default_theme_id', 1));
        }
        if (!$theme) {
            $all = self::all();
            $theme = $all[0] ?? self::fallback();
        }
        return $theme;
    }

    private static function fallback(): array
    {
        return [
            'id' => 0, 'slug' => 'default', 'name' => 'Default',
            'color_bg' => '#0B0F14', 'color_primary' => '#B6FF2E',
            'color_secondary' => '#FFFFFF', 'color_accent' => '#6CFFB8',
            'is_light' => 0, 'style' => '',
        ];
    }

    /**
     * Build the derived CSS-variable set for a theme as an associative array.
     */
    public static function variables(array $theme): array
    {
        $bg        = $theme['color_bg'];
        $primary   = $theme['color_primary'];
        $secondary = $theme['color_secondary'];
        $accent    = $theme['color_accent'];
        $light     = Color::isLight($bg);

        // Surfaces: lift away from the background so cards separate cleanly.
        $surface  = $light ? Color::darken($bg, 0.035) : Color::lighten($bg, 0.06);
        $surface2 = $light ? Color::darken($bg, 0.07)  : Color::lighten($bg, 0.11);
        $elevated = $light ? '#FFFFFF' : Color::lighten($bg, 0.14);

        // Text derived for legibility on the base background.
        $text      = Color::readableText($bg, $secondary);
        $textMuted = $light ? Color::mix($text, $bg, 0.45) : Color::mix($text, $bg, 0.40);
        $textSubtle= $light ? Color::mix($text, $bg, 0.60) : Color::mix($text, $bg, 0.58);

        // Text that sits on top of surfaces (may differ from base bg text).
        $textOnSurface = Color::readableText($surface, $text);

        // Borders and dividers.
        $border  = $light ? Color::darken($bg, 0.12) : Color::lighten($bg, 0.16);
        $borderStrong = $light ? Color::darken($bg, 0.22) : Color::lighten($bg, 0.28);

        // Button foregrounds — always contrast against their fill.
        $onPrimary = Color::readableText($primary);
        $onAccent  = Color::readableText($accent);
        $onSecondary = Color::readableText($secondary);

        // Hover/active variants.
        $primaryHover = $light ? Color::darken($primary, 0.10) : Color::lighten($primary, 0.10);
        $accentHover  = $light ? Color::darken($accent, 0.10)  : Color::lighten($accent, 0.10);

        // Link color: primary, but forced readable on the background.
        $link      = Color::ensureContrast($primary, $bg, 4.5);
        $linkHover = Color::ensureContrast($accent, $bg, 4.5);

        // Focus ring.
        $focus = $accent;

        // Fixed, accessible state colors, tinted toward the theme.
        $success = Color::ensureContrast('#16A34A', $bg, 3.0);
        $warning = Color::ensureContrast('#D97706', $bg, 3.0);
        $danger  = Color::ensureContrast('#DC2626', $bg, 3.0);
        $info    = Color::ensureContrast('#2563EB', $bg, 3.0);

        // Input fields.
        $inputBg     = $light ? '#FFFFFF' : Color::lighten($bg, 0.08);
        $inputText   = Color::readableText($inputBg, $text);
        $inputBorder = $light ? Color::darken($bg, 0.16) : Color::lighten($bg, 0.24);

        $shadow = $light ? '15, 23, 42' : '0, 0, 0';

        return [
            '--c-bg'            => $bg,
            '--c-bg-rgb'        => Color::rgbString($bg),
            '--c-primary'       => $primary,
            '--c-primary-rgb'   => Color::rgbString($primary),
            '--c-primary-hover' => $primaryHover,
            '--c-secondary'     => $secondary,
            '--c-accent'        => $accent,
            '--c-accent-rgb'    => Color::rgbString($accent),
            '--c-accent-hover'  => $accentHover,
            '--c-surface'       => $surface,
            '--c-surface-2'     => $surface2,
            '--c-elevated'      => $elevated,
            '--c-text'          => $text,
            '--c-text-on-surface'=> $textOnSurface,
            '--c-text-muted'    => $textMuted,
            '--c-text-subtle'   => $textSubtle,
            '--c-border'        => $border,
            '--c-border-strong' => $borderStrong,
            '--c-on-primary'    => $onPrimary,
            '--c-on-accent'     => $onAccent,
            '--c-on-secondary'  => $onSecondary,
            '--c-link'          => $link,
            '--c-link-hover'    => $linkHover,
            '--c-focus'         => $focus,
            '--c-success'       => $success,
            '--c-warning'       => $warning,
            '--c-danger'        => $danger,
            '--c-info'          => $info,
            '--c-input-bg'      => $inputBg,
            '--c-input-text'    => $inputText,
            '--c-input-border'  => $inputBorder,
            '--c-shadow-rgb'    => $shadow,
        ];
    }

    /** Render the variables as an inline style block for :root or a scope. */
    public static function styleBlock(array $theme, string $selector = ':root'): string
    {
        $vars = self::variables($theme);
        $lines = [];
        foreach ($vars as $k => $v) {
            $lines[] = "  {$k}: {$v};";
        }
        return $selector . " {\n" . implode("\n", $lines) . "\n}\n";
    }

    /** Inline style attribute value for a scoped preview element. */
    public static function styleAttr(array $theme): string
    {
        $vars = self::variables($theme);
        $out = [];
        foreach ($vars as $k => $v) {
            $out[] = "{$k}:{$v}";
        }
        return implode(';', $out);
    }
}
