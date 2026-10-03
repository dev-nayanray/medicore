<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\HospitalSetting;
use App\Services\AuditService;
use App\Services\SettingService;

/**
 * Hospital settings — renders the self-describing settings catalogue
 * and persists changes with audit coverage.
 */
final class SettingController extends Controller
{
    public function index(Request $request): string
    {
        return Response::html(view('admin/settings', [
            'groups' => HospitalSetting::groupedOrdered(),
        ]));
    }

    public function update(Request $request): string
    {
        $submitted = (array) $request->input('settings', []);
        $catalog = HospitalSetting::allAsMap();

        $updatedKeys = [];
        foreach ($submitted as $key => $value) {
            $key = (string) $key;
            if (!isset($catalog[$key])) {
                continue; // never trust keys outside the catalogue
            }
            $value = trim((string) $value);
            $old = (string) ($catalog[$key]['value'] ?? '');

            // Basic type validation against the catalogue definition.
            $type = (string) $catalog[$key]['type'];
            if ($type === 'number' && $value !== '' && !is_numeric($value)) {
                Session::flash('error', 'Value for "' . $catalog[$key]['label'] . '" must be a number.');
                return Response::back();
            }
            if ($type === 'boolean') {
                $value = in_array($value, ['1', 'true', 'on'], true) ? 'true' : 'false';
            }

            if ($value !== $old) {
                SettingService::set($key, $value, Auth::id());
                $updatedKeys[] = $key;
            }
        }

        if ($updatedKeys === []) {
            Session::flash('info', 'No changes to save.');
        } else {
            AuditService::log('settings.updated', 'settings', 'update', 'Hospital settings updated: ' . implode(', ', $updatedKeys) . '.', [
                'keys' => $updatedKeys,
            ], $request);
            Session::flash('success', 'Settings saved (' . count($updatedKeys) . ' updated).');
        }

        return Response::redirect(url('/admin/settings'));
    }
}
