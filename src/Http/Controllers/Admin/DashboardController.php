<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Dashboard\DashboardData;
use Webtack\Ecms\Http\Controllers\Controller;

/**
 * The admin start page: every widget's data comes from {@see DashboardData}, limited by the user's rights.
 * Plain props (not deferred): every query is bounded, see docs/admin-dashboard.md.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $dashboard): Response
    {
        return Inertia::render('Admin/Dashboard', $dashboard->forUser($request->user()));
    }
}
