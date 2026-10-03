<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;
use App\Services\SearchService;

/**
 * JSON endpoints powering the admin chrome (topbar search + notifications)
 * and lightweight workflow helpers (duplicate detection).
 */
final class ApiController extends Controller
{
    public function search(Request $request): string
    {
        $query = (string) $request->query('q', '');
        return $this->json(['ok' => true, 'data' => SearchService::search($query)]);
    }

    public function notifications(Request $request): string
    {
        $feed = NotificationService::feed();
        NotificationService::markAllRead(); // viewing the dropdown marks as seen
        return $this->json(['ok' => true, 'data' => $feed]);
    }

    /** Live duplicate detection while filling the patient form. */
    public function patientDuplicates(Request $request): string
    {
        // Edit-capable OR create-capable staff may run the check.
        if (!\App\Core\Auth::can('patients.create') && !\App\Core\Auth::can('patients.update')) {
            return $this->json(['ok' => false, 'message' => 'Not permitted.'], 403);
        }

        $payload = $request->json() ?? [];

        $data = [
            'first_name' => mb_substr(trim((string) ($payload['first_name'] ?? '')), 0, 80),
            'last_name'  => mb_substr(trim((string) ($payload['last_name'] ?? '')), 0, 80),
            'date_of_birth' => (string) ($payload['date_of_birth'] ?? ''),
            'phone'      => mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 30),
            'national_id' => mb_substr(trim((string) ($payload['national_id'] ?? '')), 0, 40),
            'ignore_id'  => (int) ($payload['ignore_id'] ?? 0),
        ];

        $duplicates = \App\Models\Patient::findPossibleDuplicates($data, $data['ignore_id'] > 0 ? $data['ignore_id'] : null);

        return $this->json(['ok' => true, 'data' => ['duplicates' => $duplicates]]);
    }
}
