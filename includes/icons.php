<?php
/**
 * Inline SVG icon library (self-contained, no external requests).
 * Usage: echo platform_icon('portal');
 */

declare(strict_types=1);

function platform_icon(string $name): string
{
    $p = '<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $paths = [
        'portal'     => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18"/><path d="M8 21h8"/>',
        'card'       => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        'admissions' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.7 3 6 3s6-2 6-3v-5"/>',
        'briefcase'  => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M2 13h20"/>',
        'bus'        => '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16"/><circle cx="8" cy="19" r="1.6"/><circle cx="16" cy="19" r="1.6"/><path d="M7 8h4"/>',
        'image'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5L5 20"/>',
        'radio'      => '<circle cx="12" cy="14" r="7"/><circle cx="12" cy="14" r="2.4"/><path d="M17 3 8 6"/>',
        'child'      => '<circle cx="12" cy="6" r="2.5"/><path d="M12 8.5v6"/><path d="m8 11 4 1 4-1"/><path d="m9 21 3-4 3 4"/>',
        'book'       => '<path d="M4 5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16l-6-3-6 3V5Z"/>',
        'graduation' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.7 3 6 3s6-2 6-3v-5"/><path d="M22 10v5"/>',
        'phone'      => '<path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L16 12l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2Z"/>',
        'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pin'        => '<path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
        'target'     => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
        'eye'        => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'heart'      => '<path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6C19 16.5 12 21 12 21Z"/>',
        'chat'       => '<path d="M21 12a8 8 0 0 1-11.5 7.2L3 21l1.8-6.5A8 8 0 1 1 21 12Z"/>',
        'clock'      => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'star'       => '<path d="m12 3 2.9 6 6.1.9-4.5 4.3 1.1 6.1L12 17.8 6.4 20.3 7.5 14 3 9.9 9.1 9Z"/>',
    ];
    return $p . ($paths[$name] ?? $paths['star']) . '</svg>';
}
