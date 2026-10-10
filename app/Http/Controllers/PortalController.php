<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function home(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $unpaid = Invoice::where('patient_id', $patient->id)->whereIn('status', ['unpaid', 'partial']);

        return view('portal.home', [
            'patient' => $patient,
            'outstanding' => (float) (clone $unpaid)->sum(DB::raw('total - amount_paid')),
            'unpaidInvoices' => (clone $unpaid)->count(),
            'nextAppointment' => $patient->appointments()
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                    ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
                ->with('doctor')
                ->orderBy('scheduled_at')
                ->first(),
            'lastVisit' => $patient->visits()
                ->where('status', VisitStatus::Completed->value)
                ->with('doctor')
                ->latest('closed_at')
                ->first(),
        ]);
    }
}
