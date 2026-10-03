<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;

/**
 * Central audit trail writer. Every security-relevant action flows
 * through here so the trail stays consistent.
 */
final class AuditService
{
    /**
     * Record an audit event. Never throws — an audit failure must not
     * break the user action it is describing; it lands in the file log.
     */
    public static function log(
        string $event,
        string $module,
        string $action,
        ?string $description = null,
        array $context = [],
        ?Request $request = null,
    ): void {
        try {
            $user = Auth::user();
            Database::execute(
                'INSERT INTO audit_logs (user_id, event, module, action, description, context, ip_address, user_agent, url, method)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $user['id'] ?? null,
                    $event,
                    $module,
                    $action,
                    $description !== null ? mb_substr($description, 0, 500) : null,
                    $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    $request?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                    $request?->userAgent() ?? mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    $request !== null ? $request->fullUrl() : null,
                    $request !== null ? $request->method() : null,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('Audit write failed: ' . $e->getMessage(), ['event' => $event]);
        }
    }
}
