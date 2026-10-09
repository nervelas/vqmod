<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \S5\Core\Csrf::token();
    }
}
if (!function_exists('money_q')) {
    function money_q($n): string
    {
        $n = (float) $n;
        return 'Q' . number_format($n, floor($n) == $n ? 0 : 2, '.', ',');
    }
}
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $f = S5_ROOT . '/assets/' . ltrim($path, '/');
        $v = is_file($f) ? substr((string) md5_file($f), 0, 8) : '0';
        return '/assets/' . ltrim($path, '/') . '?v=' . $v;
    }
}
