<?php
namespace MenuGold\Core;

final class Logger
{
    private static function write($level, $message)
    {
        $dir = defined('MG_STORAGE') ? MG_STORAGE . '/logs' : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $level . ': '
              . str_replace(array("\r", "\n"), ' ', (string)$message) . PHP_EOL;
        @file_put_contents($dir . '/app-' . gmdate('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error($m) { self::write('ERROR', $m); }
    public static function warn($m)  { self::write('WARN', $m); }
    public static function info($m)  { self::write('INFO', $m); }
}
