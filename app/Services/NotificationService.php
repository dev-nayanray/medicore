<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

/**
 * Notification feed — merges DB-backed targeted alerts (notifications table)
 * with the audit-trail activity feed. "Unread" counts both sources.
 * The audit trail provides general activity (logins, settings changes,
 * CRUD events); the notifications table provides actionable alerts
 * (low-stock, expiry, lab pending, appointment reminders, announcements).
 */
final class NotificationService
{
    /**
     * @return array{items: array<int, array{event: string, description: string, actor: string, when: string, icon: string, tone: string, action_url?: string}>, unread: int}
     */
    public static function feed(int $limit = 8): array
    {
        $userId = Auth::id();
        $items = [];
        $unread = 0;

        // --- DB-backed targeted notifications (highest priority first) ---
        if (Database::tableExists('notifications')) {
            $dbNotifs = Database::query(
                'SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT ' . max(1, min(10, $limit)),
                [$userId]
            );
            foreach ($dbNotifs as $n) {
                $icon = self::iconForType((string) $n['type']);
                $tone = self::toneForPriority((string) $n['priority']);
                if ((int) $n['is_read'] === 0) {
                    $unread++;
                }
                $items[] = [
                    'event'       => (string) $n['type'],
                    'description' => (string) $n['title'] . ' — ' . $n['message'],
                    'actor'       => 'System',
                    'when'        => time_ago((string) $n['created_at']),
                    'icon'        => $icon,
                    'tone'        => $tone,
                    'action_url'  => $n['action_url'] ?? null,
                ];
            }
        }

        // --- Audit-trail activity feed (fill remaining slots) ---
        if (Database::tableExists('audit_logs') && count($items) < $limit) {
            $remaining = $limit - count($items);
            $rows = Database::query(
                "SELECT a.event, a.description, a.created_at, u.name AS actor_name
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.user_id IS NULL OR a.user_id = ? OR a.event IN ('system.seeded', 'settings.updated')
                 ORDER BY a.created_at DESC, a.id DESC
                 LIMIT " . max(1, min(20, $remaining)),
                [$userId]
            );

            $seenAt = Session::get('_notifications_seen_at');
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
        }

        return ['items' => $items, 'unread' => $unread];
    }

    public static function markAllRead(): void
    {
        Session::put('_notifications_seen_at', time());
        // Also mark DB notifications as read for the current user.
        if (Database::tableExists('notifications') && Auth::id() !== null) {
            Database::execute(
                'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0',
                [Auth::id()]
            );
        }
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
            str_starts_with($event, 'patient')       => ['users', 'teal'],
            str_starts_with($event, 'appointment')    => ['calendar-days', 'navy'],
            str_starts_with($event, 'consultation')   => ['clipboard-list', 'teal'],
            str_starts_with($event, 'prescription')   => ['prescription', 'violet'],
            str_starts_with($event, 'invoice')        => ['receipt-text', 'teal'],
            str_starts_with($event, 'payment')       => ['banknote', 'emerald'],
            str_starts_with($event, 'admission')      => ['door-open', 'navy'],
            str_starts_with($event, 'lab')           => ['flask-conical', 'violet'],
            str_starts_with($event, 'pharmacy')      => ['pill', 'teal'],
            default                                    => ['activity', 'teal'],
        };
    }

    /** @return array{0: string, 1: string} */
    private static function iconForType(string $type): array
    {
        return match ($type) {
            'appointment_reminder' => ['calendar-clock', 'teal'],
            'low_stock'            => ['alert-triangle', 'amber'],
            'expiry_alert'         => ['calendar-x', 'red'],
            'lab_pending'          => ['flask-conical', 'violet'],
            'admission_alert'      => ['door-open', 'navy'],
            'announcement'         => ['megaphone', 'teal'],
            default                => ['bell', 'slate'],
        };
    }

    /** @return string */
    private static function toneForPriority(string $priority): string
    {
        return match ($priority) {
            'critical' => 'red',
            'high'     => 'amber',
            'medium'   => 'teal',
            default    => 'slate',
        };
    }
}
