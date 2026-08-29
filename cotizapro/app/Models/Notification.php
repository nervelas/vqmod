<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Notification
{
    public static function push(int $userId, string $title, string $body = '', string $link = '', string $type = 'info', ?int $companyId = null): void
    {
        DB::insert('notifications', [
            'company_id' => $companyId,
            'user_id'    => $userId,
            'type'       => mb_substr($type, 0, 40),
            'title'      => mb_substr($title, 0, 190),
            'body'       => mb_substr($body, 0, 400) ?: null,
            'link'       => mb_substr($link, 0, 255) ?: null,
            'created_at' => nowSql(),
        ]);
    }

    public static function unread(int $userId, int $limit = 12): array
    {
        return DB::all('SELECT * FROM notifications WHERE user_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT ' . (int) $limit, [$userId]);
    }

    public static function countUnread(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId], 0);
    }

    public static function markAllRead(int $userId): void
    {
        DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
