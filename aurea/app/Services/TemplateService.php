<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

final class TemplateService
{
    /** Carga (o reinicia) las plantillas predeterminadas. $overwrite=false conserva las editadas. */
    public static function seed(string $citaLower, bool $overwrite = false): void
    {
        $fn = require AUREA_ROOT . '/app/Data/templates.php';
        foreach ($fn($citaLower) as $code => $t) {
            $sql = 'INSERT INTO message_templates (code,name,subject,email_body,wa_body,active) VALUES (?,?,?,?,?,1)';
            if ($overwrite) {
                $sql .= ' ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),email_body=VALUES(email_body),wa_body=VALUES(wa_body)';
            } else {
                $sql .= ' ON DUPLICATE KEY UPDATE code=code';
            }
            Db::exec($sql, [$code, $t['name'], $t['subject'], $t['email'], $t['wa']]);
        }
    }

    public static function find(string $code): ?array
    {
        return Db::one('SELECT * FROM message_templates WHERE code=?', [$code]);
    }

    /** Reemplaza {variables}. Las desconocidas se dejan intactas. */
    public static function fill(string $text, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) { $map['{' . $k . '}'] = (string)$v; }
        return strtr($text, $map);
    }
}
