<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

/**
 * Renders the dashboard using summary data scoped by DashboardService to the signed-in user.
 */
class DashboardController extends Controller
{
    /**
     * Retrieves dashboard data for the current user and renders the home page.
     */
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        return view('dashboard', $dashboard->data($request->user()));
    }
}
