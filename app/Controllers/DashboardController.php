<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DashboardService;

/**
 * Dashboard — every number on this page comes from a real query.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        return Response::html(view('admin/dashboard', DashboardService::build()));
    }
}
