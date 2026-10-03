<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

/**
 * Notification feed derived from the audit trail — no fabricated data.
 * "Unread" means newer than the browser's last seen timestamp.
 */
final class NotificationService
{
    /**
     * @return array{items: array<int, array{event: string, description: string, actor: string, when: string, icon: string, tone: string}>, unread: int}
     */
    public static function feed(int $limit = 6): array
    {
        if (!Database::tableExists('audit_logs')) {
            return ['items' => [], 'unread' => 0];
        }

        $userId = Auth::id();
        $rows = Database::query(
            "SELECT a.event, a.description, a.created_at, u.name AS actor_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.user_id IS NULL OR a.user_id = ? OR a.event IN ('system.seeded', 'settings.updated')
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT " . max(1, min(20, $limit)),
            [$userId]
        );

        $seenAt = Session::get('_notifications_seen_at');
        $unread = 0;
        $items = [];

        foreach ($rows as $row) {
            $ts = strtotime((string) $row['created_at']);
            if ($seenAt === null || $ts > (int) $seenAt) {
                $unread++;
            }
            [$icon, $tone] = self::iconFor((string) $row['event']);
            $items[] = [
                'event'       => (string) $row['event'],
                'description' => (string) ($row['description'] ?? $row['event']),
                'actor'       => (string) ($row['actor_name'] ?? 'System'),
                'when'        => time_ago((string) $row['created_at']),
                'icon'        => $icon,
                'tone'        => $tone,
            ];
        }

        return ['items' => $items, 'unread' => $unread];
    }

    public static function markAllRead(): void
    {
        Session::put('_notifications_seen_at', time());
    }

    /** @return array{0: string, 1: string} lucide icon name + color tone */
    private static function iconFor(string $event): array
    {
        return match (true) {
            str_starts_with($event, 'login.success')  => ['log-in', 'teal'],
            str_starts_with($event, 'login.failed')   => ['shield-alert', 'red'],
            str_starts_with($event, 'settings')       => ['settings', 'amber'],
            str_starts_with($event, 'user'), str_starts_with($event, 'role') => ['user-cog', 'navy'],
            str_starts_with($event, 'system')         => ['server', 'slate'],
            default                                    => ['activity', 'teal'],
        };
    }
}
