<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Services\SettingService;

/**
 * Audit log browser with search + event filtering.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): string
    {
        $search = trim((string) $request->query('q', ''));
        $event = trim((string) $request->query('event', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = AuditLog::paginateWithActor($search, $event, $page, $perPage);

        return Response::html(view('admin/audit-logs', [
            'logs'    => $data['rows'],
            'total'   => $data['total'],
            'page'    => $data['page'],
            'pages'   => $data['pages'],
            'perPage' => $data['perPage'],
            'search'  => $search,
            'event'   => $event,
            'events'  => AuditLog::distinctEvents(),
        ]));
    }
}
