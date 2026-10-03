<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): string
    {
        $userId = Auth::id() ?? 0;
        $notifications = Notification::forUser($userId, 50);
        $unread = Notification::unreadCount($userId);
        return Response::html(view('admin/notifications/index', ['notifications' => $notifications, 'unread' => $unread]));
    }

    public function markRead(Request $request, string $id): string
    {
        Notification::markRead((int) $id, Auth::id() ?? 0);
        return Response::redirect(url('/admin/notifications'));
    }

    public function markAllRead(Request $request): string
    {
        Notification::markAllRead(Auth::id() ?? 0);
        return Response::redirect(url('/admin/notifications'));
    }
}
