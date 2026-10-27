<?php

namespace App\Http\Controllers;

use App\Services\PortalDashboardService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
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
