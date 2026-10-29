<?php

namespace App\Http\Controllers;

use App\Services\PortalDashboardService;
use Illuminate\Http\Request;

/**
 * Authorizes patient portal access and renders the home page with patient-specific dashboard data.
 */
class PortalController extends Controller
{
    /**
     * Requires a linked patient account and renders PortalDashboardService data with enabled portal modules.
     */
    public function home(Request $request, PortalDashboardService $dashboard)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return view('portal.home', $dashboard->data($patient) + [
            'appointmentsEnabled' => $request->user()->hospital?->hasModule('appointments') ?? false,
            'onlinePaymentsEnabled' => $request->user()->hospital?->hasModule('online_payments') ?? false,
        ]);
    }
}
