<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Notification extends Model
{
    protected static function table(): string { return 'notifications'; }
    public const TYPES = ['appointment_reminder', 'low_stock', 'expiry_alert', 'lab_pending', 'admission_alert', 'announcement', 'system'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO notifications (user_id, type, title, message, priority, action_url)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['user_id'] ?? null, $data['type'] ?? 'system', $data['title'] ?? '', $data['message'] ?? '',
             $data['priority'] ?? 'medium', $data['action_url'] ?? null]
        );
        return (int) Database::lastInsertId();
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        return Database::query(
            'SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)),
            [$userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0', [$userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND (user_id = ? OR user_id IS NULL)', [$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0', [$userId]);
    }

    public static function broadcast(string $type, string $title, string $message, string $priority = 'medium', ?string $actionUrl = null): int
    {
        return self::create(['user_id' => null, 'type' => $type, 'title' => $title, 'message' => $message, 'priority' => $priority, 'action_url' => $actionUrl]);
    }

    public static function forUserScoped(int $userId, string $type, string $title, string $message, string $priority = 'medium', ?string $actionUrl = null): int
    {
        return self::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'priority' => $priority, 'action_url' => $actionUrl]);
    }
}
